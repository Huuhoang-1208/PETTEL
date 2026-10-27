# State Lifecycles — Listing, Order, Payment

PETTEL — Domain Documentation \| Bản thảo 26/10/2026

# Listing

DRAFT → PENDING\_REVIEW → ACTIVE → SUSPENDED → ACTIVE, hoặc ACTIVE/SUSPENDED → ARCHIVED. Tác giả gửi duyệt; admin kích hoạt, từ chối về DRAFT kèm lý do, tạm ẩn hoặc khôi phục. ARCHIVED là kết thúc và không nhận booking mới; booking cũ vẫn giữ snapshot. Sửa trường trọng yếu của ACTIVE tạo change request PENDING\_REVIEW; bản ACTIVE đã duyệt vẫn công khai cho tới khi admin chấp thuận.

# Order / Booking

REQUESTED → AWAITING\_PAYMENT khi người chăm chấp nhận và giữ lịch → CONFIRMED khi sandbox AUTHORIZED → IN\_PROGRESS khi bắt đầu chăm → AWAITING\_CONFIRMATION khi người chăm đánh dấu xong → COMPLETED khi chủ nuôi xác nhận. REQUESTED có thể bị từ chối/hủy; AWAITING\_PAYMENT có thể hết hạn; CONFIRMED có thể hủy theo chính sách trước khi bắt đầu; các nhánh này tới CANCELLED.  
Chỉ AWAITING\_PAYMENT, CONFIRMED, IN\_PROGRESS và AWAITING\_CONFIRMATION khóa lịch. REQUESTED có thể trùng; lúc chấp nhận phải kiểm tra lại trong transaction. Sau IN\_PROGRESS không hủy bằng luồng thường; báo cáo cho admin. Không tự hoàn thành khi chủ nuôi im lặng; nhắc sau 24 giờ và đưa admin queue sau 72 giờ. REQUESTED hết hạn sau 24 giờ hoặc tại start_at, tùy mốc nào trước.

# Payment sandbox

NOT\_STARTED → PENDING → AUTHORIZED → RELEASED. PENDING → FAILED nếu thất bại; retry tạo attempt mới. Hủy trước AUTHORIZED → VOIDED; hủy sau AUTHORIZED nhưng trước RELEASED → REFUNDED (mô phỏng). RELEASED không đảo ngược bằng thao tác thường.  
Booking CONFIRMED chỉ khi payment AUTHORIZED; COMPLETED chỉ sau khi owner xác nhận và release sandbox thành công, hoặc có quy trình ngoại lệ admin được ghi nhận. Callback có thể đến lặp/khác thứ tự: kiểm tra event ID, current state, booking ID và amount trước khi chuyển. Tất cả trạng thái payment là sandbox, không phải giao dịch tiền thật.

# Quyết định P0

[P0 Decision Contract](<05 — P0 Decision Contract.md>) là nguồn chuẩn. Không phí hủy sandbox. Owner/caregiver hủy REQUESTED hoặc AWAITING_PAYMENT; CONFIRMED chỉ hủy trước start_at và sau REFUNDED mô phỏng. Sau IN_PROGRESS chỉ admin hủy ngoại lệ có audit. Release lỗi giữ AWAITING_CONFIRMATION, retry cùng key 1/5/15 phút rồi chuyển admin; report đang xử lý tạm giữ release. Admin có thể xác nhận thay owner hoặc refund/cancel ngoại lệ với lý do. Không đảo RELEASED bằng thao tác thường.
