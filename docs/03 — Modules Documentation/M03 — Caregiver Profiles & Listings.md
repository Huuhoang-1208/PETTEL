# M03 — Caregiver Profiles & Listings

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Người chăm/cơ sở quản lý hồ sơ giới thiệu, loại hình chăm sóc, địa bàn, mô tả năng lực, điều kiện tiếp nhận, mức giá VND, ảnh và lịch khả dụng. Listing qua DRAFT, PENDING\_REVIEW, ACTIVE, SUSPENDED, ARCHIVED.

# Quy tắc

Chỉ ACTIVE hiện công khai. Sửa trường trọng yếu của ACTIVE cần duyệt lại. Giá không âm, thời lượng hợp lệ, ảnh đúng loại/kích thước. Một listing P0 có công suất 1 tại một thời điểm. Badge xác minh chỉ hiện nếu có trạng thái và bằng chứng được admin duyệt; dữ liệu seed phải ghi demo.

# Dữ liệu và phụ thuộc

caregiver\_profiles, listings, availability\_slots, listing\_media, verification\_claims (nếu có). Identity cấp quyền; Search lập chỉ mục từ listing ACTIVE; Booking đọc lịch và snapshot giá. Không xóa listing có booking lịch sử.

# Tiêu chí chấp nhận

Caregiver tạo/sửa/gửi duyệt listing của mình. Admin duyệt hoặc từ chối có lý do. Listing bị SUSPENDED biến khỏi search và không nhận booking mới; booking đã CONFIRMED không bị thay đổi giá.

# Ngoài P0

Kiểm tra lý lịch, PETTEL Pro thật, mô hình nhiều nhân viên/chuồng và giá linh hoạt theo ngày.  
