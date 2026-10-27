# M08 — Reviews & Trust Signals

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Thu đánh giá sau booking COMPLETED; hiển thị rating, số booking hoàn tất và các tín hiệu có thể giải thích để chủ nuôi tự đánh giá người chăm. Không tạo điểm tin cậy tổng hợp P0 khi chưa định nghĩa công thức.

# Quy tắc

Một review/booking, chỉ owner của booking, booking COMPLETED. Rating 1–5 và nội dung có giới hạn; có thể báo cáo review sai. Chỉ review gắn booking thật của hệ thống mới mang nhãn “từ lượt đặt lịch hoàn tất”; dữ liệu seed phải ghi minh họa. Badge xác minh cần quy trình/bằng chứng riêng, không suy từ rating.

# Dữ liệu và phụ thuộc

reviews, review\_status, moderation events; tham chiếu booking/listing/user. Aggregate rating được tính từ review công khai, loại các review bị ẩn; cache phải xóa khi review thay đổi. Listing chỉ đọc aggregate, không sửa review.

# Tiêu chí chấp nhận

Owner không đánh giá trước COMPLETED; caregiver không tự đánh giá listing mình; tạo trùng trả 409\. Review bị admin ẩn không tính vào rating công khai nhưng vẫn còn audit. UI không hiển thị “PETTEL Verified/Pro” như thật trong demo.

# P1

Điểm tin cậy tổng hợp chỉ sau khi thống nhất biến đầu vào, trọng số, khả năng giải thích và quy trình chống gian lận.  
