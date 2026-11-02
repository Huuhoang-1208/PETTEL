# M05 — Booking & Availability

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Chủ nuôi yêu cầu dịch vụ cho một pet, listing và khoảng thời gian; người chăm chấp nhận/từ chối; hệ thống giữ lịch, theo dõi thực hiện và xác nhận hoàn tất. Booking lưu giá, chính sách và hướng dẫn chăm sóc dạng snapshot.

# Quy tắc

REQUESTED không khóa lịch. Khi accept, transaction khóa listing/khoảng thời gian và kiểm tra giao nhau với booking đang giữ chỗ; xung đột trả AVAILABILITY\_CONFLICT. AWAITING\_PAYMENT giữ chỗ 15 phút từ accept, retry không gia hạn; quá hạn hủy. CONFIRMED chỉ khi payment AUTHORIZED. IN\_PROGRESS → AWAITING\_CONFIRMATION do caregiver; COMPLETED do owner xác nhận và payment release sandbox.

**Create contract chốt 02/11/2026:** owner ACTIVE/có role OWNER dùng pet ACTIVE/chưa xóa mềm thuộc mình; listing ACTIVE/chưa xóa mềm có slot bao trọn, không giao booking đang khóa lịch và điều kiện `acceptance_rules_json` v1 hợp lệ. Version 1 chỉ đối chiếu `species`/`sizes`, không tự lọc tuổi/nhu cầu đặc biệt. Profile chưa xóa mềm; user caregiver ACTIVE/còn CAREGIVER và khác owner. Server xác định owner/caregiver/giá. Request mới cần start trong tương lai, timestamp có Z/offset, tính thời lượng thực giữ giây lẻ theo P0; không giới hạn thêm số ngày đặt trước.

## Accept/reject — chốt BE-HA03 ngày 02/11/2026

Người dùng đã đồng ý contract tại [P0 §12](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) và [API M05 accept/reject](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>); đây chưa phải xác nhận triển khai hoặc reviewer sign-off.

- Chỉ caregiver của booking được xử lý; booking không tồn tại/thuộc caregiver khác trả `404 NOT_FOUND`, kiểm tra quyền trước chi tiết trạng thái/lịch. Auth/role dùng contract hiện có.
- Accept chỉ từ REQUESTED trước `min(created_at + 24 giờ, start_at)` → AWAITING_PAYMENT, hạn thanh toán 15 phút. Accept lặp hoặc từ trạng thái khác trả `409 INVALID_STATE`, không gia hạn/event trùng; REQUESTED đã hết hiệu lực trả `409 REQUEST_EXPIRED` dù job chưa chạy.
- Reject REQUESTED → CANCELLED, event USER của caregiver có `reason_code=CAREGIVER_REJECTED`. Replay cùng caregiver sau chính lần từ chối đó trả 200, không thêm event; hủy vì lý do khác/trạng thái khác trả `409 INVALID_STATE`. Listing ngừng ACTIVE không chặn reject hợp lệ. Không thêm status ACCEPTED/REJECTED.
- Lịch xét cùng listing, capacity=1; không chia sẻ công suất giữa listing cùng caregiver. Một slot mở chưa xóa mềm phải bao trọn booking. Khoảng UTC/microsecond `[start_at,end_at)`, hai khoảng chỉ chạm đầu/cuối không xung đột.
- Chỉ AWAITING_PAYMENT, CONFIRMED, IN_PROGRESS, AWAITING_CONFIRMATION giữ lịch. Không tự bỏ qua hold quá hạn nếu trạng thái chưa chuyển theo BE-T04; create/search/accept phải nhất quán.
- Accept phải kiểm tra lại listing ACTIVE và caregiver đủ điều kiện. Giữ giá/snapshot khi chỉ đổi giá; thay dịch vụ/điều kiện nhận pet trọng yếu trả `409 INVALID_STATE`, yêu cầu booking mới. Không có slot/trùng lịch trả `409 AVAILABILITY_CONFLICT`.
- Transaction và khóa chung listing bảo vệ overlap; dữ liệu quyết định lấy từ DB. Booking/hạn thanh toán/event ghi cùng transaction; reject cũng ghi booking/event cùng transaction. Hai accept xung đột chỉ một thành công, thao tác còn lại lỗi rõ và không có dữ liệu ghi dở.

# Dữ liệu và phụ thuộc

`bookings`, `booking_status_events`, `price_snapshot_vnd`, `price_unit`, `price_quantity`, `pricing_version`, `cancellation_policy_version`, `pet_snapshot_json`, `listing_snapshot_json`, `policy_snapshot_json`, `instructions_snapshot`, `start_at`/`end_at` UTC, `payment_due_at`; REQUESTED hết hạn sau 24 giờ hoặc tại start_at, tùy mốc nào trước. Đọc Identity/Pet/Listing; gọi Payment Sandbox để authorize/release; mở Messaging/Update/Review theo trạng thái. Không đọc trạng thái từ cache để quyết định chấp nhận.

Allowlist snapshot tại [P0 Contract §9](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) và [Database Design §4 M05](<../08 — Architecture & Technical Design/database design.md>): pet lấy nguyên `Pet::snapshotForBooking()`; listing giữ version/đơn giá/điều kiện; policy giữ phí hủy 0/SANDBOX. Instructions từ request lưu riêng, không ghi đè pet notes. Giá/phiên bản/snapshots và event REQUESTED ghi cùng transaction; thay nguồn không cập nhật lịch sử, trừ redaction theo retention.

# Tiêu chí chấp nhận

Hai yêu cầu trùng có thể tồn tại nhưng chỉ một được accept. Thao tác chuyển sai trạng thái trả INVALID\_STATE; owner/caregiver không thể sửa booking của người khác. Hết hạn thanh toán thả lịch đúng một lần; refresh trang không tạo booking mới nếu cùng idempotency key.

Create trả 201; replay cùng owner/key/payload trả 200 với cùng booking/status hiện tại, không tính lại giá/snapshot hoặc tạo event trùng; khác payload trả 409 IDEMPOTENCY_CONFLICT. Chuẩn key ASCII chữ thường 1–128 ký tự và canonical SHA-256 payload/bảng lỗi tại [API Contract M05](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>). Test phải gồm pet trái quyền, listing/caregiver không hợp lệ, self-booking, thời gian giây lẻ, hai REQUESTED giao nhau, request cùng key đồng thời và giữ snapshot sau sửa nguồn. Contract này cần BE-H02 triển khai và nghiệm thu, không tự đánh dấu Done.

# Quyết định P0

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) quy định công thức giá DAYCARE/HOUR, BOARDING/NIGHT, hết hạn REQUESTED, hủy theo state và admin xử lý owner im lặng. Booking chỉ nằm trong slot mở liên tục; thiếu slot thì không accept. Hủy CONFIRMED đợi REFUNDED mô phỏng trước khi chuyển CANCELLED. Không auto complete sau 72 giờ.
