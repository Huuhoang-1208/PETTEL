# Business Rules

PETTEL — Domain Documentation \| Bản thảo 26/10/2026

# Listing và khả dụng

BR-01. Chỉ listing ACTIVE được tìm thấy công khai và nhận booking mới; DRAFT, PENDING\_REVIEW, SUSPENDED, ARCHIVED không xuất hiện.  
BR-02. Người chăm chỉ sửa listing của mình. Sửa giá, loại dịch vụ hoặc điều kiện trọng yếu của listing ACTIVE phải duyệt lại trước khi công khai.

# Booking

BR-03. Booking P0 gắn một pet profile thuộc chủ nuôi và một listing. Chụp snapshot giá, thời gian, hướng dẫn và chính sách tại lúc gửi yêu cầu.  
BR-04. Khi người chăm chấp nhận, hệ thống khóa/transaction DB để chặn booking trùng khoảng \[start,end) trên cùng listing; trả AVAILABILITY\_CONFLICT nếu có xung đột.  
BR-05. Sau khi chấp nhận, giữ chỗ 15 phút chờ sandbox payment (đề xuất). Hết hạn thì hủy và thả chỗ. Giá listing đổi sau đó không đổi booking hiện có.  
BR-06. Chỉ hai bên booking được xem hướng dẫn, tin nhắn và ảnh cập nhật; admin chỉ đọc khi có lý do xử lý được ghi log.

# Payment, review và moderation

BR-07. Payment sandbox chỉ chấp nhận sự kiện có tham chiếu, amount và secret/chữ ký hợp lệ. Xử lý idempotent; callback trùng không tạo release thứ hai.  
BR-08. Chỉ chủ của booking COMPLETED được viết một review cho booking đó. Đánh giá seed không được gắn nhãn “verified”.  
BR-09. Badge Verified/Pro không được tuyên bố là xác minh thật nếu chưa có quy trình và bằng chứng. Mọi dữ liệu demo phải có nhãn minh họa.  
BR-10. Admin có thể ẩn listing, xử lý report và can thiệp booking theo quy trình; mọi hành động có actor, thời gian, lý do và audit.

# Cần chốt

Biểu phí hủy/hoàn tiền theo thời điểm, thời hạn chủ nuôi xác nhận hoàn thành, cơ chế xử lý tranh chấp và công suất nhiều chỗ của cơ sở chưa được proposal quyết định. Không tự triển khai mặc định.  
