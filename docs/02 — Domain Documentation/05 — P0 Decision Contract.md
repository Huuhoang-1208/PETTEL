# P0 Decision Contract — giá, hủy, hoàn tất, quyền và dữ liệu

**Trạng thái:** quyết định thiết kế P0 ngày 27/10/2026 theo yêu cầu chốt các mục còn mở. Đây là hợp đồng cho bản mẫu PETTEL tại Đà Nẵng, chỉ dùng thanh toán sandbox. Không mô tả chính sách tiền thật hoặc tuyên bố tuân thủ pháp lý. Nếu mở sản phẩm thật, chủ sản phẩm và người phụ trách pháp lý phải duyệt lại giá, hủy và thời hạn lưu dữ liệu.

## 1. Giá và mã dịch vụ

P0 có hai `service_type` cố định: `DAYCARE` (chăm ban ngày) và `BOARDING` (lưu trú). `DAYCARE` chỉ dùng `price_unit=HOUR`; `BOARDING` chỉ dùng `price_unit=NIGHT`. `price_vnd` là số nguyên VND dương. Không có phụ phí, mã giảm giá, thuế hoặc giá theo ngày đặc biệt trong P0.

| Dịch vụ | Khoảng hợp lệ | Số đơn vị tính tiền | Ví dụ |
| --- | --- | --- | --- |
| `DAYCARE` | Cùng một ngày theo `Asia/Ho_Chi_Minh`, từ 1 đến 12 giờ | `ceil(thời lượng thực tính bằng phút / 60)` | 2 giờ 10 phút tính 3 giờ. |
| `BOARDING` | Tối thiểu 12 giờ, tối đa 30 ngày | `ceil(thời lượng thực tính bằng phút / 1440)` | Nhận 15:00, trả 10:00 hôm sau tính 1 đêm; 25 giờ tính 2 đêm. |

Trong UI, `NIGHT` được giải thích là một block tối đa 24 giờ lưu trú, không phụ thuộc giờ qua nửa đêm. Giá cuối = `price_vnd × số đơn vị`; dùng thời điểm UTC để tính thời lượng, chỉ dùng múi giờ Việt Nam để kiểm tra cùng ngày và hiển thị. Server tính và trả tổng trước khi owner gửi booking; client không gửi amount. Booking lưu `price_snapshot_vnd`, đơn giá, đơn vị, số đơn vị và `pricing_version=1` trong snapshot. Cùng công thức phải dùng ở preview, create booking và lịch sử. Chặn tràn số, giá bằng 0 và khoảng thời gian không hợp lệ.

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

Owner và caregiver của booking được **đọc và gửi chat** khi booking ở `REQUESTED`, `AWAITING_PAYMENT`, `CONFIRMED`, `IN_PROGRESS` hoặc `AWAITING_CONFIRMATION`. Khi `CANCELLED` hoặc `COMPLETED`, chat chỉ đọc; lịch sử vẫn theo quy tắc retention. Chỉ caregiver tạo care update ở `IN_PROGRESS`; owner đọc. Không cho tạo care update ở `AWAITING_CONFIRMATION`. Admin chỉ đọc nội dung riêng khi xử lý report/booking có lý do và audit.

User đăng ký thường được role `OWNER`. User đã đăng nhập có thể chủ động bật role `CAREGIVER` và tạo profile/listing nháp; không cần admin duyệt role riêng. Chỉ admin duyệt listing trước khi `ACTIVE`, và admin role chỉ được cấp ngoài luồng tự đăng ký. Role caregiver hoặc listing ACTIVE không có nghĩa là danh tính/lý lịch đã xác minh. P0 không có badge Verified/Pro thật và không tạo `verification_claims`.

## 6. Dữ liệu và quyền riêng tư P0

P0 dùng khóa tài khoản/xóa mềm và yêu cầu xóa được xử lý có audit; không có nút xóa cứng tức thì. Khi user yêu cầu xóa, đặt `users.status=DELETION_PENDING` và `deletion_requested_at`; ngừng booking/listing mới, ẩn profile/listing công khai. User chỉ được đăng nhập để xem và hoàn tất booking/report đang mở; mọi tác vụ khác bị chặn. Khi các việc đang mở kết thúc, khóa đăng nhập và ẩn danh tài khoản trong tối đa 30 ngày; nếu user chưa có việc mở thì mốc 30 ngày tính từ lúc yêu cầu. Giao diện báo rõ thời điểm dự kiến và dữ liệu lịch sử còn giữ theo bảng dưới. `DELETION_PENDING` vẫn cho đọc/ghi tối thiểu để đóng booking/report đang mở; `DEACTIVATED` không đăng nhập được.

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

## Decision log

### Schema và lifecycle BE-T01 (28/10/2026)

Mã trạng thái/danh mục lưu trong schema dùng `VARCHAR` và CHECK trên MySQL 8.4, kèm validation tại service. Migration mới chuyển `users.status` và `user_roles.role` khỏi ENUM hiện có, không sửa migration cũ. Pet có status `ACTIVE`/`ARCHIVED`; chỉ ACTIVE được dùng cho booking mới. `caregiver_profiles` không lưu status riêng: account status/role và listing status quyết định khả năng hoạt động/công khai. `listing_change_requests.status` là `PENDING_REVIEW`, `APPROVED`, `REJECTED`, `WITHDRAWN`; tối đa một request chờ/listing, duyệt payload và tăng version trong một transaction khóa listing. Report dùng bốn FK nullable thật (listing/review/message/booking), CHECK đúng một đích.

| Quyết định | Phương án đã xét | Lý do chọn |
| --- | --- | --- |
| `DAYCARE` theo giờ, `BOARDING` theo block 24 giờ | Theo ngày lịch, theo giờ mở cửa | Một công thức có thể tính và giải thích bằng khoảng thời gian UTC; không cần lịch check-in riêng cho P0. |
| Không phí hủy trong sandbox | Biểu phí theo mốc giờ | Tránh mô phỏng quyền lợi tiền thật khi chưa có hợp đồng/chính sách. |
| Admin xử lý im lặng sau 72 giờ | Auto release | Giữ bước kiểm tra con người khi owner không xác nhận. |
| Chat đóng ghi ở terminal state | Cho nhắn không giới hạn | Giữ lịch sử giao dịch ổn định và giảm nội dung phải quản trị. |
| Caregiver tự bật role, listing vẫn duyệt | Admin duyệt cả role | Một cổng duyệt nội dung công khai đủ cho P0. |
| Retention theo loại dữ liệu | Giữ vô thời hạn | Giảm dữ liệu riêng tư tồn đọng; các mốc là mặc định P0 cần rà lại khi vận hành thật. |
| Mã vùng nội bộ | Mã hành chính/free text | Tránh phụ thuộc thay đổi địa giới và giữ filter nhất quán. |
