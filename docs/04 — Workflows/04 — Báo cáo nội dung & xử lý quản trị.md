# Báo cáo nội dung & xử lý quản trị

PETTEL — Workflows \| Bản thảo 26/10/2026

# Điều kiện đầu vào

Người dùng đăng nhập, thấy đối tượng có thể báo cáo (listing công khai hoặc nội dung trong booking của mình). Report có loại đối tượng, lý do, mô tả ngắn; không buộc cung cấp dữ liệu riêng không liên quan.

# Luồng chính

1\. Người dùng gửi report → OPEN; hệ thống lưu target ID/type, reporter, thời gian và nội dung. Chặn spam bằng rate limit và xử lý báo cáo trùng theo chính sách.  
2\. Admin nhận report → IN\_REVIEW; xem đối tượng và chứng cứ trong phạm vi cần thiết. Quyền đọc dữ liệu booking/tin nhắn riêng phải có lý do và audit.  
3\. Admin quyết định DISMISSED (không vi phạm) hoặc RESOLVED với hành động: ẩn review/message, SUSPEND listing, cảnh báo caregiver, hoặc chuyển booking sang xử lý thủ công. Mọi hành động ghi actor, lý do và thời gian.  
4\. Hệ thống cập nhật search/cache khi listing bị ẩn. Người báo cáo thấy trạng thái xử lý và thông báo phù hợp, không thấy dữ liệu riêng của bên bị báo cáo.

# Nhánh ngoại lệ

Target bị xóa mềm vẫn còn bản ghi để điều tra. Report sai loại hoặc target không truy cập được trả 404/403 phù hợp, không tiết lộ nội dung. Admin không tự sửa payment sandbox; nếu booking bị ảnh hưởng, đi qua service trạng thái và có audit.

# Điểm kiểm thử

Non-admin không mở admin panel; một report có lịch sử quyết định; listing bị SUSPENDED biến khỏi search nhưng booking cũ không mất; không rò rỉ ảnh/tin nhắn qua report.  
