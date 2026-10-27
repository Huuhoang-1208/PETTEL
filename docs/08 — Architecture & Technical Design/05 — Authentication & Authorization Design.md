# Authentication & Authorization Design

PETTEL — Architecture & Technical Design \| Bản thảo 26/10/2026

# Xác thực

Laravel Breeze API stack cung cấp route auth; Vue SPA dùng Sanctum stateful session cookie, CSRF và reset password của Breeze. Chỉ bật email verification nếu luồng gửi mail demo đáng tin; không tự tuyên bố email đã xác minh nếu chưa xử lý. Admin không được tự chọn role ở form đăng ký. Throttle login và password reset; logout hủy session.

# Phân quyền

Policy/Gate ở server cho từng tài nguyên: PetPolicy owner; ListingPolicy caregiver owner/admin; BookingPolicy owner hoặc caregiver của listing và kiểm tra status; Message/UpdatePolicy chỉ hai bên booking; ReviewPolicy owner của booking COMPLETED; ReportPolicy reporter và admin; AdminGate riêng. Kiểm tra quyền ở cả trang lẫn endpoint, không dựa vào ẩn nút.

# Sanctum và token

P0 dùng Sanctum cho Vue SPA: gọi `/sanctum/csrf-cookie`, gửi cookie/CSRF trên login và request ghi, bảo vệ API riêng tư bằng `auth:sanctum`. Phục vụ frontend/API cùng origin khi demo; nếu tách subdomain, cấu hình stateful domains, session domain và CORS credentials. SPA không lưu personal access token; chỉ phát token có ability/thời hạn/thu hồi nếu có client bên ngoài thật. Xử lý 401/419 bằng điều hướng đăng nhập hoặc làm mới CSRF theo luồng phù hợp.

# Bảo vệ dữ liệu

Người chăm chỉ đọc pet notes từ booking có liên quan, không từ pet ID bất kỳ. Admin đọc nội dung riêng khi xử lý report có audit. Signed URL tệp chỉ cấp sau policy. Route model binding phải trả 404/403 thích hợp để không lộ tài nguyên.

# Kiểm thử bắt buộc

Matrix actor × resource × state cho tất cả write routes; thử cookie/CSRF, 401/419 và CORS; thử đổi ID trong URL/body, leo quyền admin, replay form, session hết hạn, callback giả. Không đưa token/secret/password vào log.

# Quy trình cấp role P0

User đăng ký nhận OWNER; user có thể tự bật CAREGIVER và tạo listing nháp. Chỉ admin duyệt listing mới cho ACTIVE; admin role được cấp ngoài đăng ký và mọi cấp/thu hồi có audit. Quyền chat/care update theo trạng thái booking và quyền xem dữ liệu sau yêu cầu xóa ở [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).

# Xóa tài khoản P0

Yêu cầu xóa đặt `DELETION_PENDING`: dừng booking/listing mới, chỉ cho user hoàn tất booking/report còn mở; sau đó vô hiệu hóa và ẩn danh trong tối đa 30 ngày. Policy phải kiểm tra status trên mọi route ghi. Xem [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
