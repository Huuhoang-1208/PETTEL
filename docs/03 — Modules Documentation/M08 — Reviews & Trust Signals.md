# M08 — Reviews & Trust Signals

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Thu đánh giá sau booking COMPLETED; hiển thị rating, số booking hoàn tất và các tín hiệu có thể giải thích để chủ nuôi tự đánh giá người chăm. Không tạo điểm tin cậy tổng hợp P0 khi chưa định nghĩa công thức.

# Quy tắc

Một review/booking, chỉ owner của booking, booking COMPLETED. Rating 1–5 và nội dung có giới hạn; có thể báo cáo review sai. Chỉ review gắn booking thật của hệ thống mới mang nhãn “từ lượt đặt lịch hoàn tất”; dữ liệu seed phải ghi minh họa. Badge xác minh cần quy trình/bằng chứng riêng, không suy từ rating.

# Dữ liệu và phụ thuộc

`reviews.status` dùng `VISIBLE`/`HIDDEN`; tham chiếu booking/listing/user và lịch sử moderation. Rating theo listing được tính trực tiếp khi đọc từ review `VISIBLE`, `is_demo = false`, thuộc booking `COMPLETED`; chưa dùng cache hoặc lưu sẵn aggregate. Nếu bổ sung cache sau này, phải xóa khi review thay đổi. Listing chỉ đọc aggregate, không sửa review.

# Contract BE-H05 — chốt 07/11/2026

Người dùng đã đồng ý bốn nhóm quy tắc task 1 tại [Contract review và rating công khai](<../05 — Implementation Plan/BE-H05 — Contract review và rating công khai.md>). Người tạo phải đăng nhập, account ACTIVE, role OWNER và sở hữu booking COMPLETED. Backend lấy owner/listing từ booking; rating nguyên 1–5, body tùy chọn tối đa 1.000 ký tự. Một review/booking kể cả khi đã HIDDEN; tạo trùng trả 409.

Review mới mặc định VISIBLE; report không tự ẩn. Module moderation cập nhật trạng thái và ghi lịch sử trong cùng transaction. Response aggregate dùng `rating_average` (number, làm tròn 2 chữ số thập phân) và `review_count` (integer); không có review đủ điều kiện thì trả null/0. Seed dùng `is_demo = true`, có thể hiển thị kèm cờ minh họa nhưng không tính điểm/count. Client không được tự đặt status, is_demo, owner_id hoặc listing_id. Đây là quyết định nghiệp vụ, chưa phải bằng chứng triển khai hoặc nghiệm thu.

# Tiêu chí chấp nhận

Owner không đánh giá trước COMPLETED; caregiver không tự đánh giá listing mình; tạo trùng trả 409\. Review bị admin ẩn không tính vào rating công khai nhưng vẫn còn audit. UI không hiển thị “PETTEL Verified/Pro” như thật trong demo.

# P1

Điểm tin cậy tổng hợp chỉ sau khi thống nhất biến đầu vào, trọng số, khả năng giải thích và quy trình chống gian lận.  
