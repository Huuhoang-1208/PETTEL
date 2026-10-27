# Security & Privacy

PETTEL — Non-functional Requirements \| Các yêu cầu dưới đây là thiết kế đề xuất, không phải tuyên bố đã kiểm thử.

# Kiểm soát truy cập

Mọi request ghi phải xác thực và dùng policy theo role, owner ID, booking relationship và state. Admin có tài khoản riêng; role admin không tự đăng ký. Session cookie Secure/HttpOnly/SameSite phù hợp, Sanctum stateful + CSRF cho Vue SPA, throttle login và các API nhạy cảm. SPA không giữ personal access token. Không tin giá, trạng thái, owner ID từ client.

# Dữ liệu và quyền riêng tư

Pet profile có chế độ ăn, tiêm phòng tự khai và lưu ý đặc biệt; chỉ chủ nuôi và người chăm của booking liên quan xem phần cần thiết. Địa chỉ liên hệ, tin nhắn và ảnh không công khai. Thu thập tối thiểu, có thông báo mục đích sử dụng, quy trình khóa tài khoản, ẩn danh và purge theo mốc P0 Decision Contract. Không đưa PII hoặc nội dung chat vào log/analytics.

# Tệp và payment

Upload giới hạn loại/kích thước, kiểm tra MIME thực, tên file an toàn, lưu private; cấp URL ngắn hạn sau khi policy cho phép. Sandbox không hỏi số thẻ, CVV, OTP hoặc tài khoản thật; secret callback nằm trong cấu hình bảo mật, không commit. Callback kiểm tra chữ ký/secret, amount và idempotency.

# Xác minh

Test 401/403 trên mọi actor và tài nguyên không thuộc quyền; thử IDOR, CSRF, XSS trong mô tả/review/chat, upload độc hại và callback giả/lặp. Rà theo OWASP ASVS 5.0 phù hợp phạm vi bài tập: https\://asvs.owasp.org/ . Không tuyên bố tuân thủ pháp lý khi chưa rà soát riêng.

# Thời hạn P0

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) quy định chat/care update/pet notes riêng 90 ngày, booking/payment/audit cốt lõi 12 tháng, backup 30 ngày; report đang mở tạm giữ dữ liệu cần thiết đến 90 ngày sau khi đóng. Yêu cầu xóa đặt DELETION_PENDING, chỉ cho user xử lý booking/report còn mở; sau khi đóng thì vô hiệu hóa và ẩn danh trong tối đa 30 ngày, có audit. Không log nội dung riêng. Các mốc này áp dụng cho sandbox P0, chưa phải kết luận tuân thủ pháp lý.
