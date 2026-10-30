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

# Lỗi thống nhất

Lỗi JSON: `error: { code, message, details?, request_id }`. 400 request lỗi cấu trúc; 401 chưa đăng nhập; 403 không có quyền; 404 không tồn tại/không được lộ; 409 `AVAILABILITY_CONFLICT`, `INVALID_STATE`, `IDEMPOTENCY_CONFLICT`, `REQUEST_EXPIRED` hoặc `PAYMENT_EXPIRED`; 422 `VALIDATION_ERROR` theo field; 429 quá nhiều request; 500 lỗi server. CSRF sai/hết hạn trả 419 theo auth contract. Message dành cho người dùng không chứa stack trace hay dữ liệu riêng.

# Ví dụ hợp đồng booking

POST body: `{pet_id, listing_id, start_at, end_at, instructions}` + header `Idempotency-Key`. Response: `{id, status:"REQUESTED", pet_id, listing_id, start_at, end_at, price_snapshot_vnd, payment_due_at:null}`. Server tự lấy owner từ session Sanctum stateful, giá từ listing; bỏ qua mọi owner_id, status hoặc amount do client gửi. Key unique theo owner, hash payload chuẩn hóa; cùng key/payload trả kết quả cũ, khác payload trả 409 `IDEMPOTENCY_CONFLICT`.

# Tài liệu API

Dùng Scramble sinh OpenAPI từ routes/FormRequest/Resource phục vụ Vue; review thủ công ví dụ lỗi, quyền, state và idempotency. Giới hạn truy cập tài liệu API ở demo nếu có endpoint nội bộ.

# Contract P0 bổ sung

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) định nghĩa mã trạng thái và giá. `POST /api/bookings/quote` nhận pet_id, listing_id, start_at, end_at; trả price_total_vnd, price_unit, quantity, pricing_version và cancellation_policy_version, không tạo booking. POST booking tính lại cùng công thức trong transaction; không tin amount client. `POST /api/bookings/{id}/cancel` kiểm tra actor/state, chỉ trả CANCELLED sau void/refund sandbox tương ứng. `POST /api/bookings/{id}/messages` chỉ cho hai bên gửi khi booking từ REQUESTED đến AWAITING_CONFIRMATION. Care update chỉ caregiver trong IN_PROGRESS. Endpoint bật role CAREGIVER yêu cầu user đăng nhập, ghi audit; admin role không có route tự cấp. Các lỗi hết hạn dùng HTTP 409 với mã `REQUEST_EXPIRED` hoặc `PAYMENT_EXPIRED`; conflict, invalid state và idempotency conflict cũng trả 409.
