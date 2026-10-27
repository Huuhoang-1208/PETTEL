# State Lifecycles — Listing, Order, Payment

PETTEL — Domain Documentation \| Bản thảo 26/10/2026

# Listing

DRAFT → PENDING\_REVIEW → ACTIVE → SUSPENDED → ACTIVE, hoặc ACTIVE/SUSPENDED → ARCHIVED. Tác giả gửi duyệt; admin kích hoạt, từ chối về DRAFT kèm lý do, tạm ẩn hoặc khôi phục. ARCHIVED là kết thúc và không nhận booking mới; booking cũ vẫn giữ snapshot. Sửa trường trọng yếu của ACTIVE đưa bản sửa về PENDING\_REVIEW.

# Order / Booking

REQUESTED → AWAITING\_PAYMENT khi người chăm chấp nhận và giữ lịch → CONFIRMED khi sandbox AUTHORIZED → IN\_PROGRESS khi bắt đầu chăm → AWAITING\_CONFIRMATION khi người chăm đánh dấu xong → COMPLETED khi chủ nuôi xác nhận. REQUESTED có thể bị từ chối/hủy; AWAITING\_PAYMENT có thể hết hạn; CONFIRMED có thể hủy theo chính sách trước khi bắt đầu; các nhánh này tới CANCELLED.  
Chỉ AWAITING\_PAYMENT, CONFIRMED, IN\_PROGRESS và AWAITING\_CONFIRMATION khóa lịch. REQUESTED có thể trùng; lúc chấp nhận phải kiểm tra lại trong transaction. Sau IN\_PROGRESS không hủy bằng luồng thường; báo cáo cho admin. Không tự hoàn thành nếu chủ nuôi im lặng khi chưa chốt chính sách.

# Payment sandbox

NOT\_STARTED → PENDING → AUTHORIZED → RELEASED. PENDING → FAILED nếu thất bại; retry tạo attempt mới. Hủy trước AUTHORIZED → VOIDED; hủy sau AUTHORIZED nhưng trước RELEASED → REFUNDED (mô phỏng). RELEASED không đảo ngược bằng thao tác thường.  
Booking CONFIRMED chỉ khi payment AUTHORIZED; COMPLETED chỉ sau khi owner xác nhận và release sandbox thành công, hoặc có quy trình ngoại lệ admin được ghi nhận. Callback có thể đến lặp/khác thứ tự: kiểm tra event ID, current state, booking ID và amount trước khi chuyển. Tất cả trạng thái payment là sandbox, không phải giao dịch tiền thật.

# Cần chốt

Chính sách phí hủy, thời hạn owner xác nhận, xử lý trường hợp RELEASED lỗi và tranh chấp sau hoàn tất cần quyết định nghiệp vụ riêng.  
