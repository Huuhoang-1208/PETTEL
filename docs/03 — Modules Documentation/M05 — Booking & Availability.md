# M05 — Booking & Availability

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Chủ nuôi yêu cầu dịch vụ cho một pet, listing và khoảng thời gian; người chăm chấp nhận/từ chối; hệ thống giữ lịch, theo dõi thực hiện và xác nhận hoàn tất. Booking lưu giá, chính sách và hướng dẫn chăm sóc dạng snapshot.

# Quy tắc

REQUESTED không khóa lịch. Khi accept, transaction khóa listing/khoảng thời gian và kiểm tra giao nhau với booking đang giữ chỗ; xung đột trả AVAILABILITY\_CONFLICT. AWAITING\_PAYMENT giữ chỗ 15 phút từ accept, retry không gia hạn; quá hạn hủy. CONFIRMED chỉ khi payment AUTHORIZED. IN\_PROGRESS → AWAITING\_CONFIRMATION do caregiver; COMPLETED do owner xác nhận và payment release sandbox.

**Create contract chốt 02/11/2026:** owner ACTIVE/có role OWNER dùng pet ACTIVE/chưa xóa mềm thuộc mình; listing ACTIVE/chưa xóa mềm có slot bao trọn, không giao booking đang khóa lịch và điều kiện `acceptance_rules_json` v1 hợp lệ. Version 1 chỉ đối chiếu `species`/`sizes`, không tự lọc tuổi/nhu cầu đặc biệt. Profile chưa xóa mềm; user caregiver ACTIVE/còn CAREGIVER và khác owner. Server xác định owner/caregiver/giá. Request mới cần start trong tương lai, timestamp có Z/offset, tính thời lượng thực giữ giây lẻ theo P0; không giới hạn thêm số ngày đặt trước.

# Dữ liệu và phụ thuộc

`bookings`, `booking_status_events`, `price_snapshot_vnd`, `price_unit`, `price_quantity`, `pricing_version`, `cancellation_policy_version`, `pet_snapshot_json`, `listing_snapshot_json`, `policy_snapshot_json`, `instructions_snapshot`, `start_at`/`end_at` UTC, `payment_due_at`; REQUESTED hết hạn sau 24 giờ hoặc tại start_at, tùy mốc nào trước. Đọc Identity/Pet/Listing; gọi Payment Sandbox để authorize/release; mở Messaging/Update/Review theo trạng thái. Không đọc trạng thái từ cache để quyết định chấp nhận.

Allowlist snapshot tại [P0 Contract §9](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) và [Database Design §4 M05](<../08 — Architecture & Technical Design/database design.md>): pet lấy nguyên `Pet::snapshotForBooking()`; listing giữ version/đơn giá/điều kiện; policy giữ phí hủy 0/SANDBOX. Instructions từ request lưu riêng, không ghi đè pet notes. Giá/phiên bản/snapshots và event REQUESTED ghi cùng transaction; thay nguồn không cập nhật lịch sử, trừ redaction theo retention.

# Tiêu chí chấp nhận

Hai yêu cầu trùng có thể tồn tại nhưng chỉ một được accept. Thao tác chuyển sai trạng thái trả INVALID\_STATE; owner/caregiver không thể sửa booking của người khác. Hết hạn thanh toán thả lịch đúng một lần; refresh trang không tạo booking mới nếu cùng idempotency key.

Create trả 201; replay cùng owner/key/payload trả 200 với cùng booking/status hiện tại, không tính lại giá/snapshot hoặc tạo event trùng; khác payload trả 409 IDEMPOTENCY_CONFLICT. Chuẩn key ASCII chữ thường 1–128 ký tự và canonical SHA-256 payload/bảng lỗi tại [API Contract M05](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>). Test phải gồm pet trái quyền, listing/caregiver không hợp lệ, self-booking, thời gian giây lẻ, hai REQUESTED giao nhau, request cùng key đồng thời và giữ snapshot sau sửa nguồn. Contract này cần BE-H02 triển khai và nghiệm thu, không tự đánh dấu Done.

# Quyết định P0

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) quy định công thức giá DAYCARE/HOUR, BOARDING/NIGHT, hết hạn REQUESTED, hủy theo state và admin xử lý owner im lặng. Booking chỉ nằm trong slot mở liên tục; thiếu slot thì không accept. Hủy CONFIRMED đợi REFUNDED mô phỏng trước khi chuyển CANCELLED. Không auto complete sau 72 giờ.
