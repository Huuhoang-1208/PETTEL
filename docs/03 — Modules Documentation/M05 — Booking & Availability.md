# M05 — Booking & Availability

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Chủ nuôi yêu cầu dịch vụ cho một pet, listing và khoảng thời gian; người chăm chấp nhận/từ chối; hệ thống giữ lịch, theo dõi thực hiện và xác nhận hoàn tất. Booking lưu giá, chính sách và hướng dẫn chăm sóc dạng snapshot.

# Quy tắc

REQUESTED không khóa lịch. Khi accept, transaction khóa listing/khoảng thời gian và kiểm tra giao nhau với booking đang giữ chỗ; xung đột trả AVAILABILITY\_CONFLICT. AWAITING\_PAYMENT giữ chỗ 15 phút từ accept, retry không gia hạn; quá hạn hủy. CONFIRMED chỉ khi payment AUTHORIZED. IN\_PROGRESS → AWAITING\_CONFIRMATION do caregiver; COMPLETED do owner xác nhận và payment release sandbox.

# Dữ liệu và phụ thuộc

bookings, booking\_status\_events, price\_snapshot\_vnd, snapshot\_pet\_instructions, start\_at/end\_at UTC, payment_due_at; REQUESTED hết hạn sau 24 giờ hoặc tại start_at, tùy mốc nào trước. Đọc Identity/Pet/Listing; gọi Payment Sandbox để authorize/release; mở Messaging/Update/Review theo trạng thái. Không đọc trạng thái từ cache để quyết định chấp nhận.

# Tiêu chí chấp nhận

Hai yêu cầu trùng có thể tồn tại nhưng chỉ một được accept. Thao tác chuyển sai trạng thái trả INVALID\_STATE; owner/caregiver không thể sửa booking của người khác. Hết hạn thanh toán thả lịch đúng một lần; refresh trang không tạo booking mới nếu cùng idempotency key.

# Quyết định P0

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) quy định công thức giá DAYCARE/HOUR, BOARDING/NIGHT, hết hạn REQUESTED, hủy theo state và admin xử lý owner im lặng. Booking chỉ nằm trong slot mở liên tục; thiếu slot thì không accept. Hủy CONFIRMED đợi REFUNDED mô phỏng trước khi chuyển CANCELLED. Không auto complete sau 72 giờ.
