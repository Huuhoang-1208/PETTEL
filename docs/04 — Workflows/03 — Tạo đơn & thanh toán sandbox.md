# Tạo đơn & thanh toán sandbox

PETTEL — Workflows \| “Đơn” \= booking một thú cưng trên một listing.

# Điều kiện đầu vào

Chủ nuôi đăng nhập, sở hữu pet profile, listing ACTIVE. Chọn start/end hợp lệ và thấy giá cuối, chính sách hủy, nhãn payment sandbox trước khi gửi.

# Luồng chính

1\. Owner gửi yêu cầu → REQUESTED; booking lưu snapshot pet/hướng dẫn/giá/chính sách. Không khóa lịch ở bước này.  
2\. Caregiver chấp nhận. Trong transaction, hệ thống khóa và kiểm tra mọi booking giữ chỗ cùng listing có giao khoảng \[start,end); nếu không trùng → AWAITING\_PAYMENT, giữ chỗ 15 phút đề xuất.  
3\. Owner bắt đầu sandbox payment. Adapter tạo PaymentAttempt PENDING; callback hợp lệ chuyển AUTHORIZED và booking CONFIRMED. Thông báo “mô phỏng — không trừ tiền thật” luôn rõ ràng.  
4\. Caregiver bắt đầu chăm → IN\_PROGRESS; gửi cập nhật; đánh dấu xong → AWAITING\_CONFIRMATION. Owner xác nhận → sandbox RELEASED một lần và booking COMPLETED; lúc đó mới được review.

# Nhánh thay thế

Caregiver từ chối hoặc owner hủy khi REQUESTED → CANCELLED. Trùng lịch khi accept → 409 AVAILABILITY\_CONFLICT. Payment FAILED cho retry trong hạn; hết hạn → CANCELLED và thả chỗ. Hủy trước bắt đầu dùng VOIDED/REFUNDED mô phỏng theo trạng thái payment; biểu phí hủy cần chốt.  
Callback lặp/đến muộn không đổi trạng thái sai; kiểm tra event ID, booking, amount và current state. Release lỗi không được âm thầm đánh dấu COMPLETED; đưa vào hàng cần xử lý và log.

# Điểm kiểm thử

Hai request trùng chỉ một accept; payment duplicate không release hai lần; snapshot giá không đổi; owner khác không xem booking; không nhập dữ liệu thẻ thật.  
