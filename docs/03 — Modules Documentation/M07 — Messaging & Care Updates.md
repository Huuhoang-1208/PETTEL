# M07 — Messaging & Care Updates

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Cung cấp kênh trao đổi giữa chủ nuôi và người chăm gắn với booking; gửi văn bản và ảnh cập nhật có thời điểm, người gửi. Chủ nuôi thấy tiến trình chăm sóc trong một nơi.

# Quy tắc

Chỉ hai bên booking đọc/ghi; admin đọc khi xử lý report có lý do. Hai bên được đọc/ghi chat từ REQUESTED đến AWAITING_CONFIRMATION; sau CANCELLED/COMPLETED chat chỉ đọc. Chỉ caregiver tạo care update trong IN_PROGRESS; từ AWAITING_CONFIRMATION không tạo mới. Tệp phải kiểm tra MIME, kích thước, quét hoặc chặn loại nguy hiểm; ảnh không có URL public vĩnh viễn. Nội dung riêng tư hết hạn theo mốc 90 ngày của P0 Decision Contract, trừ report đang mở.

# Dữ liệu và phụ thuộc

messages, care\_updates, media\_objects gắn booking\_id và sender\_id. Identity/Booking cung cấp quyền; Storage cấp URL truy cập ngắn hạn. Thông báo email là P1; P0 có thể dùng refresh/poll nhẹ thay WebSocket.

# Tiêu chí chấp nhận

Người ngoài booking nhận 403\. Ảnh sai định dạng/kích thước bị từ chối; lỗi tải lên không tạo message giả. Thứ tự hiển thị theo thời gian và ID; người dùng thấy nhãn thời gian theo Asia/Ho\_Chi\_Minh.

# Ngoài P0

Video call, camera trực tiếp, streaming và cam kết phản hồi thời gian thực.

# Quyết định P0

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) là nguồn chuẩn cho quyền theo trạng thái và thời hạn lưu. Admin chỉ đọc khi xử lý report/booking có lý do và audit.

# Triển khai BE-T05

`GET /api/bookings/{id}/care-updates` trả lịch sử phân trang, sắp xếp theo `created_at`, `id`; `POST` cùng đường dẫn tạo cập nhật với `body` và/hoặc `image`. Chỉ caregiver thuộc booking tạo được khi booking là `IN_PROGRESS`. Owner và caregiver của booking được đọc; user khác nhận `403`.

Ảnh lưu trên private local disk, chấp nhận JPEG/PNG/WebP, tối đa 10 MiB và 20 megapixel; API response cấp URL ký 5 phút. Endpoint ảnh vẫn yêu cầu Sanctum và kiểm tra participant, booking và quan hệ care update. Timestamp trả về UTC ISO-8601; client trình bày theo `Asia/Ho_Chi_Minh`.

`care-updates:redact-expired` chạy hàng ngày. Sau 90 ngày, nội dung bị redacted và ảnh riêng bị xóa nếu không còn tham chiếu. Report OPEN/IN_REVIEW hoặc report đóng chưa đủ 90 ngày giữ nội dung booking liên quan.
