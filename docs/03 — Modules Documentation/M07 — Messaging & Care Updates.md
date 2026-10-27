# M07 — Messaging & Care Updates

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Cung cấp kênh trao đổi giữa chủ nuôi và người chăm gắn với booking; gửi văn bản và ảnh cập nhật có thời điểm, người gửi. Chủ nuôi thấy tiến trình chăm sóc trong một nơi.

# Quy tắc

Chỉ hai bên booking đọc/ghi; admin đọc khi xử lý report có lý do. Tin nhắn trước khi CONFIRMED có thể dùng để hỏi đáp theo chính sách P0; ảnh cập nhật chăm sóc chỉ sau IN\_PROGRESS. Tệp phải kiểm tra MIME, kích thước, quét hoặc chặn loại nguy hiểm; ảnh không có URL public vĩnh viễn. Giữ nguyên lịch sử sau hoàn tất trừ yêu cầu xóa theo chính sách được chốt.

# Dữ liệu và phụ thuộc

messages, care\_updates, media\_objects gắn booking\_id và sender\_id. Identity/Booking cung cấp quyền; Storage cấp URL truy cập ngắn hạn. Thông báo email là P1; P0 có thể dùng refresh/poll nhẹ thay WebSocket.

# Tiêu chí chấp nhận

Người ngoài booking nhận 403\. Ảnh sai định dạng/kích thước bị từ chối; lỗi tải lên không tạo message giả. Thứ tự hiển thị theo thời gian và ID; người dùng thấy nhãn thời gian theo Asia/Ho\_Chi\_Minh.

# Ngoài P0

Video call, camera trực tiếp, streaming và cam kết phản hồi thời gian thực.  
