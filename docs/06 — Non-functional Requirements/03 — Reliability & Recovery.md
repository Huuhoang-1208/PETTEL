# Reliability & Recovery

PETTEL — Non-functional Requirements \| Mục tiêu đề xuất cho môi trường demo.

# Tính nhất quán

Chuyển trạng thái booking/payment trong transaction; unique event ID và idempotency key để retry an toàn. Worker xử lý job sau commit; job lỗi được retry có giới hạn và đưa vào failed jobs để xem. Không đánh dấu booking COMPLETED khi release sandbox chưa thành công hoặc chưa có xử lý ngoại lệ được audit.

# Sự cố phụ thuộc

Nếu Redis ngừng: tìm kiếm có thể giảm tốc và hàng đợi dừng; dữ liệu booking/payment vẫn ở MySQL. Nếu sandbox không phản hồi: giữ booking AWAITING\_PAYMENT đến hạn, cho retry và báo lỗi rõ; không tự xác nhận. Nếu storage lỗi: không tạo care update giả và người dùng được thử lại.

# Sao lưu và phục hồi

Đề xuất sao lưu DB hằng ngày, lưu bản sao tách môi trường, kiểm thử restore trước demo quan trọng; mục tiêu RPO ≤24 giờ, RTO ≤4 giờ cho môi trường demo. Ảnh private cần backup hoặc chấp nhận mất theo chính sách rõ. Không dùng \`docker compose down \-v\` trên dữ liệu cần giữ.

# Kiểm chứng

Thử kill worker giữa callback/release, gửi callback lặp, tắt Redis, restore DB trên môi trường thử; kiểm tra không mất booking và không release hai lần.  
