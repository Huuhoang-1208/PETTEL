# API Contracts & Error Conventions

PETTEL — Architecture & Technical Design \| Hợp đồng JSON cho Vue SPA; Filament dùng cùng service/quy tắc nghiệp vụ.

# Endpoint P0

Breeze API scaffold cung cấp route đăng ký/đăng nhập/đăng xuất/khôi phục mật khẩu; Vue lấy `/sanctum/csrf-cookie` trước thao tác auth. Route riêng tư dùng `auth:sanctum`; không phát hành bearer token cho SPA.

GET /api/listings?service\_type=\&area=\&start\_at=\&end\_at=\&pet\_id= — public filters; pet\_id chỉ khi owner đăng nhập. GET /api/listings/{id} — chỉ ACTIVE hoặc owner/admin có quyền. POST /api/bookings — pet\_id, listing\_id, start\_at, end\_at, instructions, Idempotency-Key. POST /api/bookings/{id}/accept — caregiver; POST /api/bookings/{id}/cancel — actor được phép; POST /api/bookings/{id}/sandbox-payments — owner. POST /api/sandbox/callback — chỉ adapter sandbox. POST /api/bookings/{id}/updates và /confirm-completion; POST /api/reports.

# Quy ước request/response

JSON UTF-8, timestamp ISO 8601 có offset/UTC; tiền số nguyên VND. ID dạng chuỗi trong response. Pagination \`data\`, \`links\`, \`meta\`. Thành công tạo tài nguyên trả 201 \+ resource/status hiện tại. Idempotency-Key bắt buộc cho booking và payment create; cùng key+payload trả cùng kết quả, khác payload trả 409\.

# Lỗi thống nhất

Lỗi JSON: \`error: { code, message, details?, request\_id }\`. 400 request lỗi cấu trúc; 401 chưa đăng nhập; 403 không có quyền; 404 không tồn tại/không được lộ; 409 AVAILABILITY\_CONFLICT hoặc INVALID\_STATE/IDEMPOTENCY\_CONFLICT; 422 VALIDATION\_ERROR với lỗi theo field; 429 quá nhiều request; 500 lỗi server. Message dành cho người dùng không chứa stack trace hay dữ liệu riêng.

# Ví dụ hợp đồng booking

POST body: \`{pet\_id, listing\_id, start\_at, end\_at, instructions}\`. Response: \`{id, status:"REQUESTED", pet\_id, listing\_id, start\_at, end\_at, price\_snapshot\_vnd, payment\_due\_at:null}\`. Server tự lấy owner từ session Sanctum stateful, giá từ listing; bỏ qua mọi owner\_id, status hoặc amount do client gửi.

# Tài liệu API

Dùng Scramble sinh OpenAPI từ routes/FormRequest/Resource phục vụ Vue; review thủ công ví dụ lỗi, quyền, state và idempotency. Giới hạn truy cập tài liệu API ở demo nếu có endpoint nội bộ.
