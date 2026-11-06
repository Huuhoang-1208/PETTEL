# M07 — Messaging & Care Updates

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Cung cấp kênh trao đổi giữa chủ nuôi và người chăm gắn với booking; gửi văn bản và ảnh cập nhật có thời điểm, người gửi. Chủ nuôi thấy tiến trình chăm sóc trong một nơi.

# Quy tắc

Mỗi booking có một đoạn chat riêng. Hai bên được đọc, gửi và xem ảnh từ `REQUESTED` đến `AWAITING_CONFIRMATION`. Với `COMPLETED`, hai bên được gửi tin mới trong 90 ngày cố định tính từ lúc hoàn tất. Từ đúng mốc hết hạn, chat chỉ đọc theo thời hạn lưu dữ liệu. Với `CANCELLED`, chat chỉ đọc kể từ lúc hủy. Tài khoản `DELETION_PENDING` chỉ xem tin và ảnh cũ của booking mình tham gia theo quyền tại P0 §5. Admin đọc nội dung riêng khi xử lý report/booking có lý do và audit.

Chỉ caregiver tạo care update ở `IN_PROGRESS`. Quyền tạo kết thúc khi booking chuyển sang `AWAITING_CONFIRMATION`. Thời hạn lưu dữ liệu và ngoại lệ khi có report theo P0 §6.

# Dữ liệu và phụ thuộc

`messages` và `care_updates` gắn với booking và người gửi. Dùng `GET /api/bookings/{id}/messages` để đọc tin và `POST /api/bookings/{id}/messages` để gửi tin. Khi gửi ảnh, dùng `multipart/form-data` với `attachments[]` và `body` nếu có. API trả đường dẫn riêng cho từng ảnh tại `GET /api/bookings/{id}/messages/{messageId}/attachment/{mediaId}`. Bỏ `mediaId` để lấy ảnh đầu tiên. API tải ảnh kiểm tra quyền đọc booking và đối chiếu ảnh với đúng tin nhắn hiển thị mỗi lần truy cập. Thông báo email là P1. P0 có thể dùng refresh hoặc poll nhẹ.

# Tiêu chí chấp nhận

- Người ngoài booking nhận `404 NOT_FOUND` khi đọc, gửi hoặc tải ảnh, cùng phản hồi với booking không tồn tại.
- Cho gửi chỉ chữ, chỉ ảnh hoặc cả hai. Mỗi tin có tối đa 5 ảnh JPEG, PNG hoặc WebP với tổng dung lượng tối đa 10 MB (10.000.000 byte). Kiểm tra MIME từ nội dung tệp. Nếu một ảnh không hợp lệ hoặc không lưu được, từ chối cả tin và dọn các ảnh đã ghi của lần gửi. Ảnh không có URL công khai vĩnh viễn.
- Phân trang với thứ tự ổn định theo thời gian và ID. Người dùng thấy nhãn thời gian theo Asia/Ho_Chi_Minh.

# Ngoài P0

Video call, camera trực tiếp, streaming và cam kết phản hồi thời gian thực.

# Quyết định P0

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) là nguồn chuẩn cho quyền theo trạng thái và thời hạn lưu. Admin chỉ đọc khi xử lý report/booking có lý do và audit.
