# Domain Model

PETTEL — Domain Documentation \| Bản thảo 26/10/2026

# Quan hệ chính

User 1—n Pet; User 1—0..1 CaregiverProfile; CaregiverProfile 1—n Listing; Listing 1—n AvailabilitySlot và Booking; Pet 1—n Booking; Booking 1—n PaymentAttempt, Message, CareUpdate; Booking 1—0..1 Review; User 1—n Report; Report 1—n ModerationAction.

# Aggregate và bất biến

Listing gồm owner, loại dịch vụ, mô tả, giá, status và lịch. Chỉ owner sửa; chỉ ACTIVE nhận booking. Thay đổi trọng yếu phải qua duyệt lại.  
Booking gồm owner, pet, listing, khoảng thời gian, giá/hướng dẫn snapshot và status. Một thú cưng P0; không trùng lịch khi giữ chỗ; mọi chuyển trạng thái phải hợp lệ.  
Payment gồm booking, amount, attempt, provider reference và status. Amount khớp snapshot; event idempotent; tối đa một RELEASED cho booking. PaymentAttempt giữ lịch sử retry.  
Review gồm booking, rating, nội dung; chỉ booking COMPLETED và đúng owner; unique theo booking.  
Report gồm reporter, target, reason, status, resolution; chỉ admin kết luận và có audit. Message/CareUpdate thuộc booking để áp dụng quyền đọc theo hai bên.

# Thông tin riêng và dữ liệu công khai

Pet profile chứa chế độ ăn, tính cách, tiêm phòng và lưu ý chăm sóc: chỉ chủ nuôi và người chăm của booking liên quan xem được phần cần thiết. File ảnh lưu trong storage; DB chỉ lưu object key, MIME, kích thước và owner. Không lưu URL public vĩnh viễn.

# Quyết định P0

Mỗi listing chỉ phục vụ một booking tại cùng thời điểm. Nếu cơ sở có nhiều nhân viên/chuồng hoặc công suất \>1, cần thêm Resource/Capacity và thay quy tắc chống trùng; không dùng một cờ available đơn giản.  
