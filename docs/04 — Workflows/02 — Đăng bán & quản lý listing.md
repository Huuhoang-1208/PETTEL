# Đăng bán & quản lý listing

PETTEL — Workflows \| “Đăng bán” \= đăng dịch vụ chăm sóc/lưu trú.

# Điều kiện đầu vào

Người dùng đăng nhập với quyền caregiver, có CaregiverProfile. Nếu là cơ sở, P0 vẫn dùng một tài khoản quản lý; nhiều nhân viên là ngoài phạm vi.

# Luồng chính

1\. Caregiver tạo listing DRAFT: loại dịch vụ, mô tả, địa bàn, điều kiện nhận pet, giá VND, ảnh và lịch khả dụng. Hệ thống kiểm tra trường bắt buộc, giá lớn hơn 0 và thời gian hợp lệ.
2\. Caregiver xem trước, gửi duyệt → PENDING\_REVIEW. Admin kiểm tra nội dung và nhãn xác minh; chấp nhận → ACTIVE hoặc từ chối → DRAFT kèm lý do.  
3\. ACTIVE xuất hiện trong search và nhận booking. Caregiver có thể thay lịch trống; không được xóa/đổi các khoảng đã khóa bởi booking. Sửa trường trọng yếu như giá hoặc điều kiện tạo change request; bản đã duyệt vẫn công khai cho đến khi admin duyệt. Nếu không thể đáp ứng bản cũ, caregiver tạm ngưng listing.
4\. Caregiver có thể lưu trữ listing không còn cung cấp dịch vụ. Admin có thể SUSPEND khi vi phạm; listing biến khỏi search và không nhận booking mới, nhưng booking lịch sử và giá snapshot được giữ.

# Nhánh lỗi và quyền

Người không sở hữu listing bị 403\. Ảnh sai MIME/kích thước bị 422 và không gắn vào listing. Listing đang có booking tương lai không được ARCHIVE nếu việc đó làm mất nghĩa vụ; cần ngừng nhận mới hoặc admin xử lý riêng.

# Điểm kiểm thử

DRAFT không công khai; admin từ chối có lý do; ACTIVE sau sửa giá không thay giá booking cũ; SUSPENDED không xuất hiện ở tìm kiếm và cache được xóa.  
