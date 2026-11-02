# API Contracts & Error Conventions

PETTEL — Architecture & Technical Design \| Hợp đồng JSON cho Vue SPA; Filament dùng cùng service/quy tắc nghiệp vụ.

# Endpoint P0

Breeze API scaffold cung cấp route đăng ký/đăng nhập/đăng xuất/khôi phục mật khẩu; Vue lấy `/sanctum/csrf-cookie` trước thao tác auth. Route riêng tư dùng `auth:sanctum`; không phát hành bearer token cho SPA.

GET /api/listings?service\_type=\&area_code=\&start\_at=\&end\_at=\&pet\_id= — public filters; pet\_id chỉ khi owner đăng nhập. GET /api/listings/{id} — chỉ ACTIVE hoặc owner/admin có quyền. POST /api/bookings — pet\_id, listing\_id, start\_at, end\_at, instructions, Idempotency-Key. POST /api/bookings/{id}/accept — caregiver; POST /api/bookings/{id}/cancel — actor được phép; POST /api/bookings/{id}/sandbox-payments — owner. POST /api/sandbox/callback — chỉ adapter sandbox. POST /api/bookings/{id}/updates và /confirm-completion; POST /api/reports.

## M02 — Pet Profiles

Các route pet yêu cầu `auth:sanctum`, account `ACTIVE` và role `OWNER`; mọi truy vấn chỉ trả pet chưa xóa mềm của owner hiện tại. ID không thuộc owner trả 404. Caregiver không có route đọc pet live; dữ liệu chăm sóc chỉ được cung cấp trong snapshot của booking liên quan.

| Method / route | Dùng để | Request / response |
| --- | --- | --- |
| `GET /api/pets` | Liệt kê pet của owner, gồm `ACTIVE` và `ARCHIVED` | Pagination chuẩn `data`, `links`, `meta`. |
| `POST /api/pets` | Tạo pet | `name`, `species` bắt buộc; các trường hồ sơ khác tùy chọn; trả resource 201. |
| `GET /api/pets/{pet}` | Xem hồ sơ | `PetResource`; chỉ owner của pet. |
| `PATCH /api/pets/{pet}` | Cập nhật hồ sơ | Chỉ field hồ sơ được gửi; `user_id` và `status` do server quản lý. |
| `PATCH /api/pets/{pet}/archive` / `activate` | Lưu trữ hoặc kích hoạt lại hồ sơ | Cập nhật `status`; chỉ pet ACTIVE được dùng tạo booking. |
| `DELETE /api/pets/{pet}` | Xóa mềm | Đặt `deleted_at`; luôn giữ row khi booking tham chiếu. Trả 204. |

Mã `species`: `DOG`, `CAT`, `OTHER`; `size`: `SMALL`, `MEDIUM`, `LARGE`; `sex`: `MALE`, `FEMALE`, `UNKNOWN`. Tên tối đa 100, `age_note` tối đa 100 và mỗi trường ghi chú tối đa 2.000 ký tự Unicode sau trim/NFC. Ngày sinh không được ở tương lai. Ảnh chỉ nhận `photo_media_id` thuộc owner hiện tại và đang `READY`; API này không upload ảnh hay trả URL ảnh.

`Pet::snapshotForBooking()` tạo allowlist version 1 gồm ID, tên, loài, ngày sinh/ghi chú tuổi, giới tính, kích cỡ và ghi chú chăm sóc; không gồm owner ID hoặc media ID. BE-H02 chịu trách nhiệm kiểm tra owner, chỉ nhận pet ACTIVE chưa xóa mềm và lưu kết quả này vào `pet_snapshot_json` lúc tạo booking. Thay đổi hồ sơ về sau không cập nhật snapshot đã lưu.

# Quy ước request/response

JSON UTF-8, timestamp ISO 8601 có offset/UTC; tiền số nguyên VND. ID dạng chuỗi trong response. Pagination \`data\`, \`links\`, \`meta\`. Thành công tạo tài nguyên trả 201 \+ resource/status hiện tại. Idempotency-Key bắt buộc cho booking và payment create; cùng key+payload trả cùng kết quả, khác payload trả 409\.

Riêng create booking theo quyết định 02/11/2026: lần tạo mới trả **201**; replay cùng owner/key/payload trả **200** với cùng booking và trạng thái hiện tại, giữ nguyên giá/snapshot đã lưu. Đây là contract BE-H02, không tự thay response replay của payment.

# Lỗi thống nhất

Lỗi JSON: `error: { code, message, details?, request_id }`. 400 request lỗi cấu trúc; 401 chưa đăng nhập; 403 không có quyền; 404 không tồn tại/không được lộ; 409 `AVAILABILITY_CONFLICT`, `INVALID_STATE`, `IDEMPOTENCY_CONFLICT`, `REQUEST_EXPIRED` hoặc `PAYMENT_EXPIRED`; 422 `VALIDATION_ERROR` theo field; 429 quá nhiều request; 500 lỗi server. CSRF sai/hết hạn trả 419 theo auth contract. Message dành cho người dùng không chứa stack trace hay dữ liệu riêng.

# Ví dụ hợp đồng booking

POST body: `{pet_id, listing_id, start_at, end_at, instructions}` + header `Idempotency-Key`. Response: `{id, status:"REQUESTED", pet_id, listing_id, start_at, end_at, price_snapshot_vnd, payment_due_at:null}`. Server tự lấy owner từ session Sanctum stateful, giá từ listing; bỏ qua mọi owner_id, status hoặc amount do client gửi. Key unique theo owner, hash payload chuẩn hóa; cùng key/payload trả kết quả cũ, khác payload trả 409 `IDEMPOTENCY_CONFLICT`.

## M05 — Chi tiết create booking đã chốt 02/11/2026

Các quyết định được người dùng đồng ý và ghi trong [P0 Contract §8–11](<../02 — Domain Documentation/05 — P0 Decision Contract.md>). Contract này là đầu vào triển khai/test; không đánh dấu endpoint đã hoạt động chỉ vì tài liệu đã cập nhật.

### Quyền, nguồn dữ liệu và thời gian

- `POST /api/bookings` dùng `auth:sanctum`, `account.active`, `role:OWNER`. Pet phải thuộc owner, `ACTIVE` và chưa xóa mềm. Listing phải `ACTIVE`, chưa xóa mềm và có điều kiện nhận pet hợp lệ theo P0 §8.
- Caregiver profile chưa xóa mềm; user của profile `ACTIVE`, còn role `CAREGIVER`. Server lấy `caregiver_user_id` qua listing/profile. Owner tự đặt listing của mình bị từ chối tại `listing_id`.
- Timestamp bắt buộc có `Z` hoặc offset; lưu UTC, nhận giây lẻ và không cắt giây khi tính thời lượng. Khi tạo mới, `start_at > thời điểm server kiểm tra request` và `start_at < end_at`. DAYCARE 1–12 giờ, cùng ngày Việt Nam; BOARDING 12 giờ–30 ngày. Không thêm giới hạn số ngày được đặt trước.
- DAYCARE tính `ceil(duration_seconds / 3600)`; BOARDING tính `ceil(duration_seconds / 86400)` theo thời lượng thực. Ví dụ 1 giờ 1 giây DAYCARE tính 2 giờ. Quote và create dùng chung công thức, create tính lại trong transaction.
- Khoảng đặt phải nằm trọn trong một slot mở và không giao booking đang khóa lịch. Các `REQUESTED` khác không khóa lịch. Hướng dẫn riêng tối đa 2.000 ký tự sau NFC + trim, lưu riêng với pet notes; allowlist snapshot tại P0 §9 và Database Design §4 M05.

### Chuẩn hóa idempotency payload

1. Kiểm tra header `Idempotency-Key` khớp `\A[a-z0-9._:-]{1,128}\z`: chỉ ASCII chữ thường/số và bốn dấu đã nêu. Không trim, lowercase hoặc sửa key giúp client; sai định dạng trả 422, `details` chỉ ra header `Idempotency-Key`. Key có phạm vi theo owner; client có thể dùng UUID chữ thường.
2. Chỉ lấy năm trường theo thứ tự cố định: `pet_id`, `listing_id`, `start_at`, `end_at`, `instructions`. ID là chuỗi số nguyên dương dạng thập phân chuẩn, không có số 0 dư ở đầu; không chuyển qua số thực làm mất độ chính xác.
3. Timestamp chuẩn hóa về UTC, định dạng cố định `YYYY-MM-DDTHH:mm:ss.ffffffZ` (sáu chữ số microsecond, bổ sung số 0 nếu input không có phần lẻ). Hai offset biểu diễn cùng thời điểm phải có cùng giá trị chuẩn hóa. Không cắt giây/phần lẻ trong phạm vi `DATETIME(6)` của schema.
4. Instructions chuẩn hóa Unicode NFC rồi trim. Trường thiếu, `null` hoặc chuỗi rỗng sau trim cùng biểu diễn bằng JSON `null`. Dữ liệu sai kiểu/định dạng trả 422 trước khi tính hash.
5. Encode object năm trường theo đúng thứ tự trên thành JSON UTF-8 gọn, không escape Unicode hoặc dấu `/`, không chèn khoảng trắng định dạng; SHA-256 của chuỗi JSON là `request_payload_hash`. Giá listing, snapshots và các trường client không có quyền quyết định như `owner_id`, `status`, `amount` không tham gia hash.

Sau auth/quyền của actor và chuẩn hóa payload, tra booking theo owner/key. Nếu đã có booking: khác hash trả 409; cùng hash trả 200 với cùng booking/status hiện tại và dữ liệu đã lưu, không kiểm tra lại giá/pet/listing/thời điểm bắt đầu như một lần tạo mới. Thay đổi dữ liệu nguồn không làm replay thất bại hoặc sửa snapshot. Quyền truy cập và retention/redaction hiện hành vẫn áp dụng; không khôi phục nội dung đã redact.

Chỉ khi chưa có booking mới kiểm tra nguồn dữ liệu/thời gian/slot để tạo `REQUESTED`. Unique `(owner_id,request_idempotency_key)` bảo vệ request đồng thời; nếu request khác vừa tạo cùng key, xử lý lại như replay/conflict. Booking và event tạo trạng thái ghi trong cùng transaction, không tạo event thứ hai khi replay.

### Bảng lỗi tạo booking

| Tình huống | HTTP | Mã lỗi / chi tiết |
| --- | --- | --- |
| Chưa đăng nhập | 401 | Theo auth contract. |
| Thiếu role OWNER hoặc account actor không ACTIVE | 403 | Theo auth/role contract. |
| CSRF thiếu, sai hoặc hết hạn | 419 | Theo auth contract. |
| Pet không tồn tại, xóa mềm hoặc thuộc owner khác | 404 | Không tiết lộ tài nguyên riêng. |
| Pet thuộc owner nhưng ARCHIVED | 409 | `INVALID_STATE`. |
| Listing không tồn tại, xóa mềm hoặc actor không được phép xem | 404 | Không tiết lộ listing ngoài quyền xem. |
| Listing actor được phép xem nhưng không ACTIVE | 409 | `INVALID_STATE`. |
| Listing thấy được nhưng điều kiện JSON sai/không hỗ trợ, hoặc caregiver không còn đủ điều kiện | 409 | `INVALID_STATE`; thông báo chung về listing, không lộ trạng thái tài khoản caregiver. |
| Pet không khớp điều kiện loài/kích cỡ | 422 | `VALIDATION_ERROR`, tại `pet_id`. |
| Owner tự đặt listing của mình | 422 | `VALIDATION_ERROR`, tại `listing_id`. |
| Sai định dạng/thời lượng hoặc bắt đầu trong quá khứ/bằng hiện tại | 422 | `VALIDATION_ERROR`, tại trường thời gian tương ứng. |
| Không có slot bao trọn hoặc giao booking đang khóa lịch | 409 | `AVAILABILITY_CONFLICT`; không tính các REQUESTED khác là khóa lịch. |
| Thiếu/sai định dạng idempotency key | 422 | `VALIDATION_ERROR`, chi tiết tại header `Idempotency-Key`. |
| Cùng owner/key nhưng khác payload chuẩn hóa | 409 | `IDEMPOTENCY_CONFLICT`. |

404 về quyền nhìn thấy tài nguyên được xét trước khi trả chi tiết trạng thái/điều kiện của tài nguyên đó. Bảng này bổ sung tình huống cho envelope `error: { code, message, details?, request_id }` chung; không thay các mã lỗi auth đã bàn giao.

# Tài liệu API

Dùng Scramble sinh OpenAPI từ routes/FormRequest/Resource phục vụ Vue; review thủ công ví dụ lỗi, quyền, state và idempotency. Giới hạn truy cập tài liệu API ở demo nếu có endpoint nội bộ.

# Contract P0 bổ sung

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) định nghĩa mã trạng thái và giá. `POST /api/bookings/quote` nhận pet_id, listing_id, start_at, end_at; trả price_total_vnd, price_unit, quantity, pricing_version và cancellation_policy_version, không tạo booking. POST booking tính lại cùng công thức trong transaction; không tin amount client. `POST /api/bookings/{id}/cancel` kiểm tra actor/state, chỉ trả CANCELLED sau void/refund sandbox tương ứng. `POST /api/bookings/{id}/messages` chỉ cho hai bên gửi khi booking từ REQUESTED đến AWAITING_CONFIRMATION. Care update chỉ caregiver trong IN_PROGRESS. Endpoint bật role CAREGIVER yêu cầu user đăng nhập, ghi audit; admin role không có route tự cấp. Các lỗi hết hạn dùng HTTP 409 với mã `REQUEST_EXPIRED` hoặc `PAYMENT_EXPIRED`; conflict, invalid state và idempotency conflict cũng trả 409.
