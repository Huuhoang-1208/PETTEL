# M03 — Caregiver Profiles & Listings

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Người chăm/cơ sở quản lý hồ sơ giới thiệu, loại hình chăm sóc, địa bàn, mô tả năng lực, điều kiện tiếp nhận, mức giá VND, ảnh và lịch khả dụng. Listing qua DRAFT, PENDING\_REVIEW, ACTIVE, SUSPENDED, ARCHIVED.

# Quy tắc

Chỉ ACTIVE hiện công khai. Sửa trường trọng yếu của ACTIVE tạo change request; bản đã duyệt tiếp tục công khai cho tới khi admin duyệt. Nếu không còn đáp ứng bản cũ, caregiver phải tạm ngưng listing. Giá lớn hơn 0, thời lượng hợp lệ, ảnh đúng loại/kích thước. Một listing P0 có công suất 1 tại một thời điểm. P0 không hiển thị badge Verified/Pro thật; dữ liệu seed phải ghi demo.

Caregiver profile không có status riêng; account status/role quyết định caregiver đủ điều kiện thao tác, listing status quyết định khả năng công khai. Listing change request có status `PENDING_REVIEW`, `APPROVED`, `REJECTED`, `WITHDRAWN`; mỗi listing tối đa một request chờ. Admin duyệt/từ chối, caregiver có thể rút khi còn chờ. Duyệt cập nhật payload và tăng version trong transaction khóa listing. Caregiver sở hữu được suspend/restore/archive listing; admin cũng có thể moderation với lý do/audit. ARCHIVED là trạng thái kết thúc.

### BE-T03 — kết quả triển khai issue #16, 03/11/2026

- Luồng DRAFT của HA02 vẫn là nguồn sửa/xóa nội dung trực tiếp. Submit đưa listing sang `PENDING_REVIEW`; admin khác chủ approve thành `ACTIVE` hoặc reject về `DRAFT` có lý do.
- Listing `ACTIVE`/`SUSPENDED` đổi nội dung qua change request. Trong lúc chờ, nội dung và ảnh đã duyệt tiếp tục phục vụ; approve áp dụng cùng transaction và tăng version một lần. Withdraw để `reviewed_by_user_id`, `reviewed_at`, `decision_reason` null; audit lưu actor, thời điểm và request ID.
- Suspend lưu nguồn `CAREGIVER` hoặc `ADMIN`; caregiver chỉ restore nguồn của caregiver, admin restore có lý do và kiểm tra lại điều kiện caregiver. Suspend/restore/archive rút request đang chờ. Archive bị chặn khi có booking giữ lịch và là trạng thái kết thúc.
- Lịch có CRUD slot UTC `DATETIME(6)`, offset được chuẩn hóa và microsecond được giữ. Slot không overlap; một slot liên tục phải phủ trọn booking đang giữ lịch. REQUESTED không khóa chỉnh lịch; accept kiểm tra lại lịch. Slot CRUD không tăng listing version.
- Ảnh nhận JPEG/PNG/WebP, tối đa 10 MiB, 20 megapixel và 10 ảnh/listing. Ảnh và thumbnail cạnh dài tối đa 640 px được xử lý đồng bộ trên private disk; public stream kiểm tra listing, pivot và media mỗi request, dùng `no-store`.

Implementation và kiểm thử chi tiết nằm trong [báo cáo BE-T03 issue #16](<../05 — Implementation Plan/BE-T03 — Báo cáo thực hiện issue #16.md>). Trạng thái hiện tại là working tree; CI gắn commit cuối và nghiệm thu reviewer vẫn là mốc riêng.

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
