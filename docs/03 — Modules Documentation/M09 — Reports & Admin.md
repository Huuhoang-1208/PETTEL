# M09 — Reports & Admin

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Người dùng gửi report từ Vue SPA về listing, review, message hoặc booking; admin thao tác trong Filament panel và phân loại, xem chứng cứ có liên quan, quyết định giữ nguyên/ẩn/suspend và ghi lý do. Admin duyệt listing trước khi ACTIVE.

# Quy tắc

Report có target type/id, reason, mô tả và trạng thái OPEN, IN\_REVIEW, RESOLVED, DISMISSED. Không cho người báo cáo xem dữ liệu riêng của bên kia. Filament action phải gọi cùng service/Policy, ghi actor, thời điểm, lý do cho hành động; can thiệp booking phải đi qua service kiểm tra state, không sửa DB thủ công.

# Dữ liệu và phụ thuộc

reports, moderation\_actions, audit\_logs; đọc Listing, Review, Message/Update và Booking qua policy. Hành động ẩn listing làm Search cập nhật; không thay đổi booking đã xác nhận nếu chưa có quyết định riêng.

# Tiêu chí chấp nhận

Người không phải admin không mở panel quản trị. Một report được chuyển trạng thái có lịch sử. Ẩn listing loại khỏi search và ngăn booking mới. Báo cáo về nội dung riêng tư không làm lộ tệp cho người ngoài. Có nhánh dismiss với lý do.

# Ngoài P0

Phân xử/đền bù tài chính thật, tự động nhận diện nội dung bằng AI, công cụ xác minh lý lịch thực.

# Xử lý booking P0

Sau 72 giờ owner không xác nhận, booking vào hàng admin. Report IN_REVIEW giữ booking ở AWAITING_CONFIRMATION; admin có lý do/audit quyết định release+COMPLETED hoặc refund+CANCELLED ngoại lệ. Release lỗi sau ba retry cũng vào hàng này. Xem [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
