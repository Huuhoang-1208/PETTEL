# Đăng bán & quản lý listing

PETTEL — Workflows \| “Đăng bán” \= đăng dịch vụ chăm sóc/lưu trú.

# Điều kiện đầu vào

Người dùng đăng nhập với quyền caregiver, có CaregiverProfile. Nếu là cơ sở, P0 vẫn dùng một tài khoản quản lý; nhiều nhân viên là ngoài phạm vi.

# Luồng chính

1\. Caregiver tạo listing DRAFT, thêm ảnh và slot khả dụng. Nội dung DRAFT có thể sửa/xóa qua API hiện có; ảnh được upload private, còn lịch được quản lý riêng.
2\. Caregiver submit → `PENDING_REVIEW`. Admin khác chủ approve → `ACTIVE` hoặc reject có lý do → `DRAFT`. Approve lần đầu kiểm tra lại caregiver, nội dung và media.
3\. `ACTIVE` xuất hiện trong search và nhận booking. Caregiver thay lịch qua slot API; slot overlap bị từ chối, slot tiếp giáp giữ riêng, một slot phải phủ toàn bộ booking giữ lịch. REQUESTED không khóa slot nhưng accept kiểm tra lại. Không sửa các slot khiến booking đang giữ lịch mất coverage.
4\. Thay nội dung listing `ACTIVE`/`SUSPENDED` bằng change request. Bản đã duyệt tiếp tục công khai trong lúc chờ; admin approve áp dụng nội dung và media trong transaction. Booking cũ giữ snapshot giá/điều kiện, không bị cập nhật.
5\. Caregiver hoặc admin có thể suspend; nguồn đình chỉ quyết định ai được restore. Suspension ẩn listing/ảnh khỏi public và chặn request mới; booking lịch sử không bị đổi. Suspend/archive rút request đang chờ. Archive yêu cầu không có booking giữ lịch và không thể restore/sửa sau đó.

# Nhánh lỗi và quyền

Listing/child không tồn tại, đã xóa mềm hoặc thuộc caregiver khác trả 404. Admin tự review hoặc caregiver restore suspension nguồn ADMIN trả 403. Payload/query lạ hoặc sai định dạng trả 422; sai trạng thái trả 409. Ảnh sai MIME/kích thước bị từ chối và không được gắn vào listing. Archive bị chặn khi có một booking ở `AWAITING_PAYMENT`, `CONFIRMED`, `IN_PROGRESS` hoặc `AWAITING_CONFIRMATION`, kể cả hold thanh toán đã quá hạn.

# Điểm kiểm thử

DRAFT không công khai; approve/reject và change request không áp dụng lặp; API lịch giữ UTC/microsecond và chặn overlap/coverage sai; suspension ẩn listing và ảnh mới; reject REQUESTED vẫn hoạt động; snapshot booking cũ không đổi. Cache ảnh public dùng `no-store` để request tiếp theo kiểm tra lại trạng thái listing/pivot.
