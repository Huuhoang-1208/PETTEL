# Tạo đơn & thanh toán sandbox

PETTEL — Workflows \| “Đơn” \= booking một thú cưng trên một listing.

# Điều kiện đầu vào

Chủ nuôi đăng nhập, account ACTIVE/có OWNER, sở hữu pet ACTIVE/chưa xóa mềm; listing ACTIVE/chưa xóa mềm nhận đúng loài/kích cỡ theo JSON v1 và có slot bao trọn. Profile chưa xóa mềm; user caregiver ACTIVE/còn CAREGIVER, khác owner. Chọn start/end có Z/offset, start trong tương lai và thời lượng hợp lệ; thấy giá cuối, chính sách hủy, nhãn payment sandbox trước khi gửi. Quyết định bổ sung 02/11/2026 tại P0 Contract §8–11.

# Luồng chính

1\. Owner gửi yêu cầu → REQUESTED; booking lưu snapshot pet/hướng dẫn/giá/chính sách. Không khóa lịch ở bước này.  
Create trả 201; pet snapshot lấy nguyên allowlist M02, hướng dẫn riêng lưu `instructions_snapshot`; snapshot listing/policy và giá/phiên bản lấy từ cùng lần tính trong transaction. Idempotency key bắt buộc; replay cùng owner/key/payload trả 200 với cùng booking/status hiện tại, không thay giá/snapshot hoặc kiểm tra nguồn như lần tạo mới; khác payload trả 409 IDEMPOTENCY_CONFLICT.
2\. Caregiver chấp nhận. Trong transaction, hệ thống khóa và kiểm tra mọi booking giữ chỗ cùng listing có giao khoảng \[start,end); nếu không trùng → AWAITING\_PAYMENT, giữ chỗ 15 phút từ accept, retry không gia hạn.
3\. Owner bắt đầu sandbox payment. Adapter tạo PaymentAttempt PENDING; callback hợp lệ chuyển AUTHORIZED và booking CONFIRMED. Thông báo “mô phỏng — không trừ tiền thật” luôn rõ ràng.  
4\. Caregiver bắt đầu chăm → IN\_PROGRESS; gửi cập nhật; đánh dấu xong → AWAITING\_CONFIRMATION. Owner xác nhận → sandbox RELEASED một lần và booking COMPLETED; lúc đó mới được review.

# Nhánh thay thế

Caregiver từ chối hoặc owner hủy khi REQUESTED → CANCELLED. Trùng lịch khi accept → 409 AVAILABILITY\_CONFLICT. Payment FAILED cho retry trong hạn; hết hạn → CANCELLED và thả chỗ. Hủy trước bắt đầu dùng VOIDED/REFUNDED mô phỏng theo trạng thái payment; P0 không thu phí hủy; CONFIRMED trước start_at chỉ CANCELLED sau REFUNDED mô phỏng thành công.
Callback lặp/đến muộn không đổi trạng thái sai; kiểm tra event ID, booking, amount và current state. Release lỗi không được âm thầm đánh dấu COMPLETED; đưa vào hàng cần xử lý và log.

# Điểm kiểm thử

Hai request trùng chỉ một accept; payment duplicate không release hai lần; snapshot giá không đổi; owner khác không xem booking; không nhập dữ liệu thẻ thật.

BE-H02 kiểm tra thêm: pet trái quyền 404; pet ARCHIVED/listing thấy được nhưng không ACTIVE 409 INVALID_STATE; pet không phù hợp và self-booking 422; thiếu slot/xung đột booking đang khóa lịch 409 AVAILABILITY_CONFLICT; cùng key đồng thời chỉ tạo một booking/event. DAYCARE 1 giờ 1 giây tính 2 giờ; BOARDING 24 giờ 1 giây tính 2 block. Sửa pet/listing sau đặt không thay snapshot; tuổi/nhu cầu đặc biệt chưa là bộ lọc tự động. Bảng lỗi đầy đủ tại API Contract M05.

# Quy tắc P0 đã chốt

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) là nguồn chuẩn. DAYCARE tính tròn giờ, BOARDING tính theo block 24 giờ; server hiển thị tổng trước khi gửi. REQUESTED hết hạn sau 24 giờ hoặc tại start_at. Owner/caregiver hủy trước IN_PROGRESS; sau đó chỉ admin xử lý ngoại lệ. Owner im lặng được nhắc sau 24 giờ, đưa admin sau 72 giờ; không auto release. Chat hai bên mở từ REQUESTED và chỉ đọc sau trạng thái kết thúc.
