# M03 — Caregiver Profiles & Listings

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Người chăm/cơ sở quản lý hồ sơ giới thiệu, loại hình chăm sóc, địa bàn, mô tả năng lực, điều kiện tiếp nhận, mức giá VND, ảnh và lịch khả dụng. Listing qua DRAFT, PENDING\_REVIEW, ACTIVE, SUSPENDED, ARCHIVED.

# Quy tắc

Chỉ ACTIVE hiện công khai. Sửa trường trọng yếu của ACTIVE tạo change request; bản đã duyệt tiếp tục công khai cho tới khi admin duyệt. Nếu không còn đáp ứng bản cũ, caregiver phải tạm ngưng listing. Giá lớn hơn 0, thời lượng hợp lệ, ảnh đúng loại/kích thước. Một listing P0 có công suất 1 tại một thời điểm. P0 không hiển thị badge Verified/Pro thật; dữ liệu seed phải ghi demo.

Caregiver profile không có status riêng; account status/role quyết định caregiver đủ điều kiện thao tác, listing status quyết định khả năng công khai. Listing change request có status `PENDING_REVIEW`, `APPROVED`, `REJECTED`, `WITHDRAWN`; mỗi listing tối đa một request chờ. Admin duyệt/từ chối, caregiver có thể rút khi còn chờ. Duyệt cập nhật payload và tăng version trong transaction khóa listing. Caregiver sở hữu được suspend/restore/archive listing; admin cũng có thể moderation với lý do/audit. ARCHIVED là trạng thái kết thúc.

**Contract chốt 02/11/2026:** `acceptance_rules_json` v1 gồm `schema_version=1`, `species` và `sizes`; hai mảng không rỗng chỉ chứa mã P0. Ví dụ `{"schema_version":1,"species":["DOG","CAT"],"sizes":["SMALL","MEDIUM"]}`. Nhận mọi kích cỡ thì liệt kê đủ ba mã; pet thiếu size không match. JSON thiếu key, sai kiểu/mã hoặc version không hỗ trợ chưa đủ điều kiện nhận booking. Phiên bản đầu chưa tự lọc theo tuổi/nhu cầu đặc biệt; caregiver đọc thông tin này từ pet snapshot của booking liên quan. Cấu trúc chung cho listing, booking và Smart Match tại P0 Contract §8.

Listing nhận booking mới phải ACTIVE/chưa xóa mềm, profile chưa xóa mềm, user của profile ACTIVE và còn CAREGIVER. Server đọc caregiver qua quan hệ listing/profile/user; chặn owner tự đặt listing của mình (`422 VALIDATION_ERROR` tại `listing_id`). Fixture ACTIVE + slot mở có thể tạo trực tiếp trong database test, không cần API admin duyệt. Đây là contract đã chốt, không xác nhận BE-HA02/BE-H02 đã triển khai các kiểm tra.

# Dữ liệu và phụ thuộc

caregiver\_profiles, listings, availability\_slots, listing\_media, listing\_change\_requests. Không có verification_claims trong P0. Identity cấp quyền; Search lập chỉ mục từ listing ACTIVE; Booking đọc lịch và snapshot giá. Không xóa listing có booking lịch sử.

# Tiêu chí chấp nhận

Caregiver tạo/sửa/gửi duyệt listing của mình. Admin duyệt hoặc từ chối có lý do. Listing bị SUSPENDED biến khỏi search và không nhận booking mới; booking đã CONFIRMED không bị thay đổi giá.

# Ngoài P0

Kiểm tra lý lịch, PETTEL Pro thật, mô hình nhiều nhân viên/chuồng và giá linh hoạt theo ngày.

# Quyết định P0

User tự bật role CAREGIVER nhưng listing phải qua admin duyệt. DAYCARE tính theo HOUR; BOARDING tính theo NIGHT; slot là khoảng mở và thiếu slot thì không nhận booking. Các mã/giới hạn ở [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
