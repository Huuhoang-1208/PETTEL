# P0 Decision Contract — giá, hủy, hoàn tất, quyền và dữ liệu

**Trạng thái:** quyết định thiết kế P0 ngày 27/10/2026 theo yêu cầu chốt các mục còn mở. Đây là hợp đồng cho bản mẫu PETTEL tại Đà Nẵng, chỉ dùng thanh toán sandbox. Không mô tả chính sách tiền thật hoặc tuyên bố tuân thủ pháp lý. Nếu mở sản phẩm thật, chủ sản phẩm và người phụ trách pháp lý phải duyệt lại giá, hủy và thời hạn lưu dữ liệu.

**Cập nhật 02/11/2026:** người dùng đã đồng ý sáu quyết định bổ sung cho BE-H02: điều kiện nhận pet, snapshot, thời gian, mã lỗi, idempotency và điều kiện caregiver/tự đặt listing. Các mục dưới đây là contract đã chốt để triển khai; cập nhật tài liệu không đồng nghĩa các hành vi này đã có trong code.

## 1. Giá và mã dịch vụ

P0 có hai `service_type` cố định: `DAYCARE` (chăm ban ngày) và `BOARDING` (lưu trú). `DAYCARE` chỉ dùng `price_unit=HOUR`; `BOARDING` chỉ dùng `price_unit=NIGHT`. `price_vnd` là số nguyên VND dương. Không có phụ phí, mã giảm giá, thuế hoặc giá theo ngày đặc biệt trong P0.

| Dịch vụ | Khoảng hợp lệ | Số đơn vị tính tiền | Ví dụ |
| --- | --- | --- | --- |
| `DAYCARE` | Cùng một ngày theo `Asia/Ho_Chi_Minh`, từ 1 đến 12 giờ | `ceil(thời lượng thực tính bằng giây / 3600)` | 2 giờ 10 phút tính 3 giờ; 1 giờ 1 giây tính 2 giờ. |
| `BOARDING` | Tối thiểu 12 giờ, tối đa 30 ngày | `ceil(thời lượng thực tính bằng giây / 86400)` | Nhận 15:00, trả 10:00 hôm sau tính 1 đêm; 24 giờ 1 giây tính 2 đêm. |

Trong UI, `NIGHT` được giải thích là một block tối đa 24 giờ lưu trú, không phụ thuộc giờ qua nửa đêm. Giá cuối = `price_vnd × số đơn vị`; dùng thời điểm UTC để tính thời lượng, chỉ dùng múi giờ Việt Nam để kiểm tra cùng ngày và hiển thị. Server tính và trả tổng trước khi owner gửi booking; client không gửi amount. Booking lưu `price_snapshot_vnd`, đơn giá, đơn vị, số đơn vị và `pricing_version=1` trong snapshot. Cùng công thức phải dùng ở preview, create booking và lịch sử. Chặn tràn số, giá bằng 0 và khoảng thời gian không hợp lệ.

Khi tạo booking mới, `start_at` phải lớn hơn thời điểm server kiểm tra request; `start_at < end_at`. Timestamp bắt buộc có `Z` hoặc offset như `+07:00`; không nhận thời gian thiếu múi giờ. Nhận giây lẻ và dùng thời lượng thực, không cắt phần giây trước khi tính giá hoặc kiểm tra giới hạn thời lượng. Công thức giây chi tiết hóa công thức phút trước đây, giữ `pricing_version=1`. Không đặt thêm giới hạn số ngày được đặt trước trong P0. Gửi lại cùng idempotency key/payload của booking đã tạo tuân theo §10, không coi đó là yêu cầu đặt mới.

`REQUESTED` hết hiệu lực sau 24 giờ kể từ khi tạo hoặc lúc `start_at` đến, tùy mốc nào trước; job chuyển `CANCELLED` với lý do `REQUEST_EXPIRED`. Caregiver chỉ accept trước mốc này. Nếu giá listing đổi trong lúc chờ, giữ giá đã báo; nếu loại dịch vụ hoặc điều kiện nhận pet trọng yếu đổi, yêu cầu owner tạo booking mới. Không tự sửa snapshot hoặc bắt owner trả giá mới.

## 2. Lịch và thay đổi listing

`availability_slots` là các khoảng **mở**. Chưa có slot thì không nhận booking; khoảng booking phải nằm trọn trong một slot liên tục và không giao booking đang khóa lịch của cùng listing. P0 `capacity=1` trên từng listing; nhiều listing của cùng caregiver chưa chia sẻ công suất. Khi sửa giá, loại dịch vụ hoặc điều kiện nhận pet của listing `ACTIVE`, tạo `listing_change_requests`; bản đã duyệt vẫn công khai cho tới khi admin duyệt. Caregiver phải tạm ngưng listing nếu không còn đáp ứng được bản đang công khai.

## 3. Hủy và payment sandbox

Chính sách P0: **không thu phí hủy**. Toàn bộ UI gọi đây là “hủy/hoàn tiền mô phỏng”, không hứa hoàn tiền thật. Chủ nuôi thấy chính sách này trước khi gửi yêu cầu và booking lưu `cancellation_policy_version=1` trong snapshot.

| Trạng thái booking | Ai được hủy theo luồng thường | Kết quả payment | Lịch |
| --- | --- | --- | --- |
| `REQUESTED` | Owner hoặc caregiver từ chối | Chưa có attempt | Chưa khóa |
| `AWAITING_PAYMENT` | Owner hoặc caregiver, hoặc job hết hạn | `PENDING` hiện hành → `VOIDED`; attempt `FAILED` giữ lịch sử | Thả khi booking vào `CANCELLED` |
| `CONFIRMED`, trước `start_at` | Owner hoặc caregiver | `AUTHORIZED` → `REFUNDED` mô phỏng | Thả khi refund/void thành công |
| `IN_PROGRESS` hoặc `AWAITING_CONFIRMATION` | Không có nút hủy thường | Admin xử lý ngoại lệ có lý do; có thể refund và chuyển `CANCELLED` sau khi xác minh, hoặc release và `COMPLETED` | Chỉ thả khi quyết định được ghi nhận |
| `COMPLETED` hoặc `CANCELLED` | Không hủy thêm | `RELEASED` không đảo bằng thao tác thường | Không đổi |

Hủy phải idempotent. Với `AWAITING_PAYMENT`, nếu attempt hiện hành còn `PENDING`, phải `VOIDED` thành công trước khi booking `CANCELLED`; nếu đã `FAILED` thì có thể hủy ngay. Với `CONFIRMED`, phải có `REFUNDED` thành công trước khi booking `CANCELLED`. Nếu adapter lỗi, giữ booking ở trạng thái cũ, ghi lỗi và đưa vào hàng retry/admin; booking quá hạn không thể confirm dù status chưa đổi. Khi callback authorize cạnh tranh với hủy hoặc hết hạn, khóa booking rồi attempt; chỉ một kết quả thắng. Callback sau `payment_due_at` không xác nhận booking; nếu sandbox đã ghi nhận authorize muộn, tạo tác vụ refund/void bù có audit, không cấp lịch lại. Hạn giữ chỗ là 15 phút từ accept, retry không gia hạn.

## 4. Hoàn tất, owner im lặng và tranh chấp

Caregiver chuyển `IN_PROGRESS → AWAITING_CONFIRMATION` khi báo đã xong. Owner xác nhận thì release sandbox; chỉ khi release thành công mới chuyển `COMPLETED`. Sau 24 giờ chưa xác nhận, gửi một nhắc trong ứng dụng; sau 72 giờ đưa booking vào hàng admin. **Không tự complete hoặc release chỉ vì hết thời gian.**

Admin xem timeline, care updates và report liên quan theo quyền có audit. Nếu không có report đang xử lý và thông tin đủ rõ, admin có thể xác nhận thay owner: ghi actor, lý do `ADMIN_COMPLETION`, release một lần rồi chuyển `COMPLETED`. Nếu có tranh chấp, giữ `AWAITING_CONFIRMATION` trong lúc report `IN_REVIEW`; admin quyết định một trong hai hướng: hoàn tất/release, hoặc hủy ngoại lệ/refund mô phỏng. Phải ghi moderation action, booking event và payment event tương ứng. Quy trình này giải quyết trạng thái sandbox của bản mẫu, không phải cơ chế phân xử hay đền bù tài chính thật.

Nếu release lỗi, booking tiếp tục `AWAITING_CONFIRMATION`; retry cùng idempotency key tối đa 3 lần với khoảng chờ tăng dần (1, 5, 15 phút). Sau 3 lần vẫn lỗi, đưa hàng admin và không tự đổi booking sang `COMPLETED`. Admin có thể retry cùng key; không tạo release thứ hai.

## 5. Chat, care update và quyền caregiver

Mỗi booking có một đoạn chat riêng. Khi đặt lại cùng caregiver, booking mới có chat từ `REQUESTED`. Tin nhắn và ảnh cũ vẫn thuộc booking ban đầu.

| Trạng thái booking | Quyền chat của owner và caregiver thuộc booking |
| --- | --- |
| `REQUESTED`, `AWAITING_PAYMENT`, `CONFIRMED`, `IN_PROGRESS`, `AWAITING_CONFIRMATION` | Đọc tin, xem ảnh và gửi tin mới. |
| `COMPLETED` | Đọc tin và xem ảnh theo thời hạn lưu dữ liệu. Được gửi tin mới trước khi đủ 90 ngày kể từ thời điểm booking chuyển sang `COMPLETED`. Mốc gửi cố định và tin nhắn mới không gia hạn mốc này. |
| `CANCELLED` | Chỉ đọc tin và xem ảnh cũ theo thời hạn lưu dữ liệu. |

Trong chat, tài khoản `DELETION_PENDING` chỉ được đọc tin và xem ảnh cũ của booking mình tham gia, kể cả booking đã đóng, trong thời hạn lưu dữ liệu và trước khi bị vô hiệu hóa. Yêu cầu gửi tin mới của tài khoản này trả `403 FORBIDDEN`. Với người ngoài booking, đọc, gửi và tải ảnh đều trả cùng `404 NOT_FOUND` như khi booking không tồn tại.

Chỉ caregiver tạo care update ở `IN_PROGRESS`, còn owner có quyền đọc. Khi booking sang `AWAITING_CONFIRMATION`, quyền tạo care update kết thúc. Admin chỉ đọc nội dung riêng khi xử lý report/booking có lý do và audit.

User đăng ký thường được role `OWNER`. User đã đăng nhập có thể chủ động bật role `CAREGIVER` và tạo profile/listing nháp; không cần admin duyệt role riêng. Chỉ admin duyệt listing trước khi `ACTIVE`, và admin role chỉ được cấp ngoài luồng tự đăng ký. Role caregiver hoặc listing ACTIVE không có nghĩa là danh tính/lý lịch đã xác minh. P0 không có badge Verified/Pro thật và không tạo `verification_claims`.

## 6. Dữ liệu và quyền riêng tư P0

P0 dùng khóa tài khoản/xóa mềm và yêu cầu xóa được xử lý có audit; không có nút xóa cứng tức thì. Khi user yêu cầu xóa, đặt `users.status=DELETION_PENDING` và `deletion_requested_at`; ngừng booking/listing mới, ẩn profile/listing công khai. User chỉ được đăng nhập để thực hiện thao tác tối thiểu nhằm hoàn tất booking/report đang mở và xem lại chat theo quyền chỉ đọc tại §5. Khi các việc đang mở kết thúc, khóa đăng nhập và ẩn danh tài khoản trong tối đa 30 ngày; nếu user chưa có việc mở thì mốc 30 ngày tính từ lúc yêu cầu. Giao diện báo rõ thời điểm dự kiến và dữ liệu lịch sử còn giữ theo bảng dưới. `DEACTIVATED` không đăng nhập được.

| Dữ liệu | Thời hạn P0 tính từ booking kết thúc hoặc sự kiện cuối | Xử lý |
| --- | --- | --- |
| Chat và care update/ảnh riêng | 90 ngày; report mở giữ tới 90 ngày sau khi đóng | Xóa nội dung và object khi hết hạn; giữ metadata tối thiểu cần audit. |
| Pet notes trong booking snapshot | 90 ngày; report mở giữ tới 90 ngày sau khi đóng | Xóa trường ghi chú riêng; giữ ID, thời gian, giá và trạng thái booking. |
| Booking/payment/event cốt lõi | 12 tháng; report mở giữ tới 90 ngày sau khi đóng nếu dài hơn | Giữ để đối chiếu demo, sau đó ẩn danh/xóa theo FK và chính sách purge. |
| Audit/moderation | 12 tháng từ sự kiện cuối | Chỉ giữ metadata allowlist, không lưu chat/pet notes. |
| Backup DB và media | 30 ngày kể từ bản sao | Bản sao hết hạn tự xóa; restore phải áp dụng lại danh sách yêu cầu xóa đã xử lý. |

Ảnh pet/profile/listing không còn tham chiếu và không bị giữ bởi booking/report được dọn trong 30 ngày sau khi xóa mềm. Không giữ dữ liệu chỉ vì có một booking lịch sử mãi mãi. Trước khi bật purge tự động, cần job kiểm tra FK, các bản sao media, report đang mở và khả năng khôi phục; thông số trên là mặc định sản phẩm cho **sandbox P0**, không phải kết luận pháp lý.

## 7. Danh mục, giới hạn nhập và địa chỉ công khai

P0 dùng mã cố định trong API và validation; nhãn giao diện được dịch riêng. `service_type`: `DAYCARE`, `BOARDING`. `species`: `DOG`, `CAT`, `OTHER`; `OTHER` chỉ match listing khai báo rõ nhận `OTHER`. `size`: `SMALL` (dưới 10 kg), `MEDIUM` (10 đến dưới 25 kg), `LARGE` (từ 25 kg); owner có thể để trống nếu chưa biết, nhưng listing giới hạn size thì pet thiếu size không được tự coi là phù hợp. `area_code` là vùng nội bộ không mang nghĩa đơn vị hành chính: `DN_CENTER`, `DN_NORTH`, `DN_SOUTH`, `DN_WEST`, `DN_EAST`; nhãn lần lượt là “Trung tâm”, “Phía Bắc”, “Phía Nam”, “Phía Tây”, “Phía Đông”; đây là vùng do caregiver tự chọn trong form, không phải địa giới hành chính hoặc tọa độ được hệ thống xác minh. Không nhận free text làm mã lọc.

Giới hạn Unicode theo số ký tự: tên user/pet/profile 100; title listing 120; bio/description 2.000; pet notes và instructions 2.000 mỗi trường; message 2.000; care update 2.000; review 1.000; report description 2.000. Server kiểm tra sau chuẩn hóa Unicode và trim. Địa chỉ công khai chỉ hiển thị `area_code` và nhãn vùng; địa chỉ giao nhận chi tiết, nếu cần, chỉ hiển thị cho hai bên sau `CONFIRMED` và không được đưa vào search/log công khai. P0 chưa cần bảng địa chỉ giao nhận riêng; thống nhất điểm giao qua chat, không đưa địa chỉ chi tiết vào listing hoặc kết quả tìm kiếm.

## 8. Điều kiện nhận pet và caregiver — chốt 02/11/2026

`listings.acceptance_rules_json` phiên bản 1 có cấu trúc:

```json
{
  "schema_version": 1,
  "species": ["DOG", "CAT"],
  "sizes": ["SMALL", "MEDIUM"]
}
```

- `schema_version` là số nguyên `1`; `species` và `sizes` là mảng không rỗng, chỉ chứa mã P0 tương ứng. Nhận mọi kích cỡ thì liệt kê cả `SMALL`, `MEDIUM`, `LARGE`; nhận `OTHER` phải liệt kê rõ.
- Pet phải khớp cả loài và kích cỡ; pet thiếu kích cỡ không tự được xem là phù hợp. Thiếu key bắt buộc, sai kiểu, mã không hợp lệ hoặc version chưa hỗ trợ thì listing chưa đủ điều kiện nhận booking.
- Phiên bản đầu chỉ tự động đối chiếu loài và kích cỡ. Tuổi và nhu cầu đặc biệt chưa là điều kiện lọc tự động; dữ liệu tuổi/ghi chú vẫn nằm trong snapshot pet để caregiver xem theo quyền. Không dùng key tuổi/nhu cầu đặc biệt để tuyên bố hệ thống đã kiểm tra hỗ trợ; bổ sung sau phải chốt schema và quy tắc trước.
- Để nhận booking mới, listing phải `ACTIVE`, chưa xóa mềm; caregiver profile chưa xóa mềm; user của profile phải `ACTIVE` và còn role `CAREGIVER`. Không chỉ dựa vào status listing khi caregiver đã bị khóa.
- Owner không được đặt listing do chính mình quản lý. Server lấy `caregiver_user_id` qua `listing → caregiver_profile → user`; nếu trùng owner thì trả `422 VALIDATION_ERROR` tại `listing_id`.

BE-HA02 ghi điều kiện listing; BE-H02 kiểm tra khi tạo booking; BE-H03/BE-H04 dùng cùng contract cho search/Smart Match. Fixture test được tạo trực tiếp listing ACTIVE và slot mở, không phụ thuộc luồng admin duyệt.

## 9. Snapshot booking — chốt 02/11/2026

| Nơi lưu | Nội dung đã chốt |
| --- | --- |
| `pet_snapshot_json` | Nguyên kết quả `Pet::snapshotForBooking()`, gồm `schema_version=1`; không dump toàn bộ pet/user. |
| `listing_snapshot_json` | `schema_version=1`, `listing_id`, `listing_version`, `title`, `service_type`, `area_code`, `unit_price_vnd`, `price_unit`, `acceptance_rules`. |
| `policy_snapshot_json` | `schema_version=1`, `cancellation_policy_version=1`, `cancellation_fee_vnd=0`, `payment_mode="SANDBOX"`. |
| `instructions_snapshot` | Hướng dẫn riêng từ request sau chuẩn hóa Unicode NFC và trim; thiếu/null/rỗng sau trim lưu `null`. Tối đa 2.000 ký tự sau chuẩn hóa. |
| Các cột booking | `start_at`, `end_at` UTC; `price_snapshot_vnd` là tổng tiền; `price_unit`, `price_quantity`, `pricing_version=1`, `cancellation_policy_version=1`. |

`listing_id` trong JSON là chuỗi ID; `listing_version` lấy từ `listings.version`; `unit_price_vnd` lấy từ `listings.price_vnd`; `acceptance_rules` là object đã kiểm tra từ `acceptance_rules_json`. Ghi chú pet giữ trong `pet_snapshot_json`, không gộp hoặc ghi đè bằng `instructions_snapshot`. Đơn giá, tổng giá, số lượng, các phiên bản và snapshots phải lấy từ cùng lần tính trong transaction tạo booking; ghi event tạo `REQUESTED` cùng transaction. Sửa pet/listing/policy về sau không cập nhật lịch sử booking. Redaction theo retention ở §6 vẫn áp dụng cho nội dung riêng tư.

## 10. Idempotency tạo booking — chốt 02/11/2026

`Idempotency-Key` bắt buộc, dài 1–128 ký tự ASCII gồm chữ thường, số và các dấu `-`, `_`, `.`, `:`; không tự trim hoặc đổi key. Key sai định dạng trả `422 VALIDATION_ERROR`. Giới hạn chữ thường giúp hành vi thống nhất với collation database không phân biệt hoa/thường; không cần thay unique constraint `(owner_id,request_idempotency_key)` hiện có.

Hash SHA-256 chỉ gồm `pet_id`, `listing_id`, `start_at`, `end_at`, `instructions` đã chuẩn hóa: ID là chuỗi số, timestamp về UTC với định dạng cố định, instructions NFC + trim và quy về `null` khi thiếu/null/rỗng. Giá nguồn, snapshot, owner/status/amount do client gửi không nằm trong hash. Quy trình chuẩn hóa cụ thể và bảng lỗi được ghi trong [API Contract — M05](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>).

Cùng owner/key/payload trả cùng booking, HTTP **200** với trạng thái hiện tại; tạo lần đầu trả **201**. Không tính lại giá hoặc thay snapshot theo dữ liệu nguồn mới. Vẫn kiểm tra đăng nhập và quyền truy cập booking. Khi booking đã tồn tại, xử lý replay trước các kiểm tra dữ liệu nguồn dành cho tạo mới: không từ chối replay chỉ vì pet/listing vừa đổi hoặc thời điểm bắt đầu đã đến. Khác payload trả `409 IDEMPOTENCY_CONFLICT`. Hai request đồng thời cùng key phải được bảo vệ bằng unique constraint và transaction, không tạo booking/event trùng.

## 11. Lỗi tạo booking — chốt 02/11/2026

Dùng bảng tình huống/HTTP/mã lỗi tại [API Contract — M05](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) làm nguồn chung cho BE-H02, frontend và test. Phân biệt 404 để không lộ tài nguyên, 409 khi trạng thái/khả dụng không còn hợp lệ và 422 cho dữ liệu hoặc điều kiện pet không phù hợp. Hai `REQUESTED` giao nhau không xung đột vì chưa khóa lịch.

## 12. Accept/reject booking — chốt BE-HA03 ngày 02/11/2026

Người dùng đã đồng ý các quyết định dưới đây. Đây là contract để triển khai và viết test; không xác nhận BE-HA03 đã có code, kiểm thử đạt hoặc Hoàng đã review.

- Caregiver chỉ xử lý booking có `caregiver_user_id` là user đang đăng nhập. Kiểm tra auth/quyền trước khi trả chi tiết trạng thái, listing hoặc lịch; booking không tồn tại hoặc thuộc caregiver khác trả `404 NOT_FOUND`. Account/role không được phép thao tác trả 403 theo auth contract hiện có.
- Accept chỉ chuyển `REQUESTED` còn hiệu lực sang `AWAITING_PAYMENT`, đặt `payment_due_at` bằng thời điểm accept + 15 phút. Mốc hết hiệu lực là `min(created_at + 24 giờ, start_at)`; tại hoặc sau mốc này phải từ chối accept với `409 REQUEST_EXPIRED`, kể cả job chưa chạy. Accept từ trạng thái khác, kể cả accept lặp, trả `409 INVALID_STATE`; không gia hạn, không thêm event.
- Reject chuyển `REQUESTED` sang `CANCELLED`, ghi `booking_status_events` với `reason_code=CAREGIVER_REJECTED`, `source=USER`, `actor_user_id` là caregiver. Không thêm booking status `ACCEPTED` hoặc `REJECTED`. Reject lặp trả 200 với booking đã lưu chỉ khi booking đang `CANCELLED` do chính caregiver đó từ chối trước đó; không thêm event. Hủy vì lý do khác hoặc reject từ trạng thái khác trả `409 INVALID_STATE`; hủy sau accept thuộc luồng cancel/payment ở §3.
- Listing không còn `ACTIVE` chặn accept với `409 INVALID_STATE`, nhưng không chặn reject yêu cầu đang chờ hợp lệ. Accept phải kiểm tra lại listing/caregiver đủ điều kiện theo §8. Giữ nguyên giá và snapshots nếu chỉ giá listing thay đổi; loại dịch vụ hoặc điều kiện nhận pet trọng yếu thay đổi so với snapshot thì trả `409 INVALID_STATE`, yêu cầu owner tạo booking mới. Không sửa snapshot theo nguồn mới.
- Kiểm tra xung đột theo **cùng `listing_id`**, `capacity=1`; chưa chia sẻ công suất giữa nhiều listing của cùng caregiver. Booking phải nằm trọn trong một slot mở chưa xóa mềm; không tự ghép các slot rời. Thời gian UTC, giữ microsecond; khoảng `[start_at,end_at)` giao nhau khi `existing.start_at < incoming.end_at AND existing.end_at > incoming.start_at`. `end = next start` không xung đột.
- Chỉ `AWAITING_PAYMENT`, `CONFIRMED`, `IN_PROGRESS`, `AWAITING_CONFIRMATION` giữ lịch; `REQUESTED`, `CANCELLED`, `COMPLETED` không giữ lịch. Không có slot bao trọn hoặc giao booking giữ lịch trả `409 AVAILABILITY_CONFLICT`. Không tự bỏ qua `AWAITING_PAYMENT` quá hạn khi trạng thái chưa được chuyển theo quy trình expiry/void/refund; BE-T04 phối hợp thả lịch nhất quán với create/search/accept.
- Accept dùng DB làm nguồn quyết định trong transaction và khóa chung listing để bảo vệ cả trường hợp chưa có booking giữ lịch. Chỉ khóa hai booking riêng lẻ không đủ. Kiểm tra lại quyền/trạng thái/expiry/listing/slot/overlap trong transaction; ghi trạng thái, hạn thanh toán và event cùng transaction. Reject cũng ghi trạng thái/event cùng transaction. Hai accept xung đột chỉ một thành công, thao tác còn lại nhận `409 AVAILABILITY_CONFLICT`, không tồn tại cập nhật một phần.

Bảng lỗi và thứ tự xử lý tại [API Contract — M05 accept/reject](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>). Checklist triển khai và test tại [BE-HA03 — Quy tắc accept/reject và chống trùng lịch](<../05 — Implementation Plan/BE-HA03 — Quy tắc accept-reject và chống trùng lịch.md>).

## 13. Cancel request, idempotency và lỗi payment — chốt nguyên tắc ngày 06/11/2026

- API cancel thường bắt buộc `Idempotency-Key`; key scope theo actor + booking. Cùng key/cùng payload trả kết quả đã lưu, khác payload trả `409 IDEMPOTENCY_CONFLICT`, thiếu/sai key trả `422 VALIDATION_ERROR`.
- Cancel replay với key mới chỉ trả `200` khi event xác nhận cùng actor đã hủy cùng booking với cùng action/reason. `CANCELLED` do expiry/reject, actor khác hoặc reason khác không phải replay của yêu cầu mới và trả `409 INVALID_STATE`.
- Reason code do server gán theo actor/source: `OWNER_CANCELLED`, `CAREGIVER_CANCELLED`, `REQUEST_EXPIRED`, `PAYMENT_EXPIRED`; reject giữ `CAREGIVER_REJECTED`. Mô tả tự do (`reason_note`) tùy chọn, tối đa 500 ký tự sau NFC + trim; chỉ ghi giải thích ngắn, không yêu cầu chi tiết sức khỏe và không nhập thông tin liên hệ/tài chính hoặc bí mật. Lưu trong audit có kiểm soát truy cập, không đưa vào log thường.
- Khi gateway timeout/lỗi có thể retry, trả `202 CANCELLATION_PENDING`; booking giữ nguyên status và tiếp tục khóa lịch. Từ chối cuối cùng sau retry tự động trả `502 PAYMENT_CANCELLATION_FAILED`, giữ booking/lịch và chuyển admin. Mâu thuẫn trạng thái attempt trả `409 PAYMENT_STATE_MISMATCH`, giữ nguyên để đối soát.
- Cancellation operation/outbox phải được lưu bền vững trong MySQL cùng stable gateway operation key, actor/source/reason, key/hash, attempt count, `next_retry_at` và lỗi gần nhất. Worker T04 tiếp tục xử lý độc lập HTTP request. Đề xuất 5 lần retry tự động với backoff 1, 2, 4, 8, 16 phút, rồi đưa admin; mọi lần dùng cùng gateway operation key. Booking chỉ sang `CANCELLED` sau void/refund thành công; không thêm status booking `CANCEL_PENDING`.
- Retry tối đa 5 lần sau lần gọi ban đầu; nếu hết retry vẫn lỗi thì operation vào hàng admin. Worker expiry chạy mỗi phút, batch tối đa 100 booking, mục tiêu xử lý trong 2 phút từ deadline khi worker/DB khả dụng. `REQUESTED` expiry cùng lịch xử lý, chuyển trực tiếp vì chưa có payment; nếu void/refund của `AWAITING_PAYMENT` chưa xong thì giữ status và lịch.
- Hủy thường chỉ dành cho owner/caregiver liên quan theo quan hệ booking và quyền account còn hợp lệ. Admin chỉ xử lý ngoại lệ theo workflow M09, với lý do và audit riêng; không dùng endpoint cancel thường.
- Lock order cancel/expiry/callback là `booking → payment_attempt`. Không khóa listing/user cho cancel booking cũ. Giữ `payment_due_at`, current attempt và snapshots; chỉ cập nhật trạng thái attempt theo kết quả payment, không xóa dấu vết.

## Decision log

### Bổ sung contract BE-HA03 task 1 (02/11/2026)

Người dùng đã đồng ý quy tắc tại §12; Hoàng vẫn cần review triển khai và kết quả kiểm thử trước nghiệm thu.

| ID | Quyết định | Issue nhận ảnh hưởng |
| --- | --- | --- |
| HA03-D01 | Accept REQUESTED còn hiệu lực → AWAITING_PAYMENT trong 15 phút; accept lặp trả INVALID_STATE, không gia hạn. | BE-HA03, BE-T04, frontend booking. |
| HA03-D02 | Reject → CANCELLED với CAREGIVER_REJECTED; replay cùng caregiver trả 200, không thêm event; listing ngừng nhận không chặn reject hợp lệ. | BE-HA03, lịch sử booking, frontend booking. |
| HA03-D03 | Giữ công suất theo listing, bốn trạng thái giữ lịch, slot bao trọn và khoảng nửa mở; không bỏ qua hold chỉ vì quá hạn. | BE-H02, BE-H03, BE-T03, BE-HA03, BE-T04. |
| HA03-D04 | Sai caregiver trả 404; state/listing/điều kiện thay đổi trả 409 INVALID_STATE; lịch trả AVAILABILITY_CONFLICT; accept REQUESTED hết hạn trả REQUEST_EXPIRED. | BE-HA03, frontend, test API. |
| HA03-D05 | Giữ giá snapshot khi chỉ đổi giá; thay đổi dịch vụ/điều kiện trọng yếu yêu cầu booking mới; transaction DB bảo vệ lịch và event. | BE-HA03, BE-T03, BE-T04. |

### Bổ sung contract BE-H03 task 1 (02/11/2026)

Người dùng đã đồng ý các đề xuất chốt API và quy tắc truy vấn. Contract chi tiết và ma trận validation ở [API Contract — M04](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>); đây không phải bằng chứng triển khai hoặc reviewer sign-off.

| ID | Quyết định | Issue nhận ảnh hưởng |
| --- | --- | --- |
| H03-D01 | Từ khóa tối đa 100 ký tự sau trim/NFC, tìm title OR description; các bộ lọc kết hợp AND, mã P0 giữ theo §7. H03 có species/size thủ công; pet_id thuộc BE-H04. | BE-H03, BE-H04, frontend discovery. |
| H03-D02 | Hai mốc thời gian đi cùng, có múi giờ, start trong tương lai; slot bao trọn và giao nhau theo khoảng nửa mở. Thời lượng/giá dùng cùng P0 và booking. Không tự coi hold hết hạn là đã thả khi booking vẫn khóa lịch. | BE-H03, BE-H02, BE-T03, BE-HA03, BE-T04. |
| H03-D03 | Lọc giá theo tổng VND, yêu cầu ngày; cho phép một cận, hai cận thì min <= max. Có ngày sắp tổng giá/ID tăng dần, không ngày sắp ID tăng dần. Phân trang sau lọc/sắp xếp, mặc định 20, tối đa 50. | BE-H03, frontend discovery. |
| H03-D04 | Danh sách/chi tiết công khai dùng chung điều kiện ACTIVE + profile/user/role hợp lệ; DRAFT không có ngoại lệ owner/admin trên route công khai, dùng luồng quản lý riêng. Response allowlist không chứa dữ liệu pet/booking riêng. | BE-H03, BE-HA02, BE-T03. |
| H03-D05 | Query không hợp lệ trả 422 theo trường; chi tiết không công khai trả 404; không có kết quả/trang vượt trả 200 với data rỗng. Mỗi listing không phù hợp lịch/dịch vụ bị loại, không gây 409 toàn bộ search. | BE-H03, frontend discovery, kiểm thử. |

### Bổ sung contract BE-H02 (02/11/2026)

Người dùng đã đồng ý sáu đề xuất trong phiên làm việc ngày 02/11/2026. Đây là cập nhật contract, không phải bằng chứng triển khai hoặc reviewer sign-off.

| ID | Nội dung cũ → quyết định mới | Lý do | Issue nhận ảnh hưởng |
| --- | --- | --- | --- |
| H02-D01 | JSON điều kiện chưa có key/schema cụ thể → version 1 gồm `schema_version`, `species`, `sizes`; chưa tự lọc tuổi/nhu cầu đặc biệt. | Listing, booking và Smart Match dùng chung cấu trúc; thu gọn phiên bản đầu có chủ đích. | BE-HA02, BE-H02, BE-H03, BE-H04; Hào/Hoàng, Thịnh review booking. |
| H02-D02 | Chỉ có cột snapshot → allowlist pet/listing/policy và hướng dẫn riêng theo §9. | Giữ lịch sử đúng dữ liệu lúc đặt, không sao chép dư PII. | BE-T02, BE-HA02, BE-H02; Thịnh/Hào/Hoàng. |
| H02-D03 | Công thức phút chưa nói giây lẻ/thời điểm bắt đầu → nhận offset bắt buộc, bắt đầu trong tương lai, tính thời lượng thực bằng giây. | Không cắt giây gây thiếu tiền hoặc vượt giới hạn thời lượng. | BE-H02, BE-H03, frontend booking; Hoàng, Thịnh review. |
| H02-D04 | Lỗi chung chưa ánh xạ case create → bảng lỗi M05 trong API Contract. | Server, frontend và test thống nhất 404/409/422. | BE-H02 và frontend booking; Hoàng, Thịnh review. |
| H02-D05 | Hash chuẩn hóa/replay chưa đủ chi tiết → key ASCII chữ thường, chuẩn hóa payload cố định, 201 create/200 replay, 409 khi khác payload. | Chống trùng và giữ giá/snapshot khi dữ liệu nguồn thay đổi. | BE-H02 và frontend booking; Hoàng, Thịnh review. |
| H02-D06 | Chưa nói rõ caregiver bị khóa/tự đặt → kiểm tra profile/user/role và chặn self-booking. | Listing ACTIVE không đủ bảo đảm caregiver còn hoạt động; tách hai bên giao dịch. | BE-HA02, BE-H02, BE-H03; Hào/Hoàng, Thịnh review booking. |

### Schema và lifecycle BE-T01 (28/10/2026)

Mã trạng thái/danh mục lưu trong schema dùng `VARCHAR` và CHECK trên MySQL 8.4, kèm validation tại service. Migration mới chuyển `users.status` và `user_roles.role` khỏi ENUM hiện có, không sửa migration cũ. Pet có status `ACTIVE`/`ARCHIVED`; chỉ ACTIVE được dùng cho booking mới. `caregiver_profiles` không lưu status riêng: account status/role và listing status quyết định khả năng hoạt động/công khai. `listing_change_requests.status` là `PENDING_REVIEW`, `APPROVED`, `REJECTED`, `WITHDRAWN`; tối đa một request chờ/listing, duyệt payload và tăng version trong một transaction khóa listing. Report dùng bốn FK nullable thật (listing/review/message/booking), CHECK đúng một đích.

### Bổ sung BE-T03 issue #16 (03/11/2026)

Các quyết định cụ thể cho listing lifecycle, moderation source, change request, media và slot được chốt tại [BE-T03 contract v1.0 §17](<../05 — Implementation Plan/BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#17-decision-log-đã-chốt>). Bổ sung schema chỉ là `listings.suspension_source` với backfill SUSPENDED cũ thành `ADMIN`; các bảng, snapshot booking, quy tắc HA03 về giữ lịch/accept/reject và expiry BE-T04 tiếp tục được tái sử dụng. Kết quả code/test cùng các tiêu chí còn chờ CI trên SHA và reviewer được ghi tại [báo cáo thực hiện #16](<../05 — Implementation Plan/BE-T03 — Báo cáo thực hiện issue #16.md>).

| Quyết định | Phương án đã xét | Lý do chọn |
| --- | --- | --- |
| `DAYCARE` theo giờ, `BOARDING` theo block 24 giờ | Theo ngày lịch, theo giờ mở cửa | Một công thức có thể tính và giải thích bằng khoảng thời gian UTC; không cần lịch check-in riêng cho P0. |
| Không phí hủy trong sandbox | Biểu phí theo mốc giờ | Tránh mô phỏng quyền lợi tiền thật khi chưa có hợp đồng/chính sách. |
| Admin xử lý im lặng sau 72 giờ | Auto release | Giữ bước kiểm tra con người khi owner không xác nhận. |
| Chat chỉ đọc khi `CANCELLED` hoặc đã đủ 90 ngày sau `COMPLETED` | Đóng quyền gửi ngay khi hoàn tất hoặc cho nhắn không giới hạn | Hai bên có thời gian hỏi lịch để đặt lại, với hạn gửi cố định theo booking cũ. |
| Caregiver tự bật role, listing vẫn duyệt | Admin duyệt cả role | Một cổng duyệt nội dung công khai đủ cho P0. |
| Retention theo loại dữ liệu | Giữ vô thời hạn | Giảm dữ liệu riêng tư tồn đọng; các mốc là mặc định P0 cần rà lại khi vận hành thật. |
| Mã vùng nội bộ | Mã hành chính/free text | Tránh phụ thuộc thay đổi địa giới và giữ filter nhất quán. |
