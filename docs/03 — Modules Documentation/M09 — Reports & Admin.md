# M09 — Reports & Admin

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Người dùng gửi report từ Vue SPA về listing, review, message hoặc booking; admin thao tác trong Filament panel và phân loại, xem chứng cứ có liên quan, quyết định giữ nguyên/ẩn/suspend và ghi lý do. Admin duyệt listing trước khi ACTIVE.

# Quy tắc

Report có đúng một target FK trong listing, review, message hoặc booking; gồm reason, mô tả và trạng thái OPEN, IN\_REVIEW, RESOLVED, DISMISSED. Không lưu target bằng cặp polymorphic type/id thiếu FK. Không cho người báo cáo xem dữ liệu riêng của bên kia. Filament/API action phải gọi cùng service/Policy, ghi actor, thời điểm, lý do và request ID.

Report nhắm tới booking khiếu nại cần giữ booking ở trạng thái hiện tại trong lúc report là IN\_REVIEW. DISMISSED nghĩa là khiếu nại không có căn cứ: chỉ đóng report và gỡ hold, không đổi booking/payment; các bên tiếp tục workflow hiện có. RESOLVED cho booking bắt buộc có quyết định COMPLETE hoặc CANCEL\_REFUND. COMPLETE tạo release operation; CANCEL\_REFUND tạo refund/cancellation operation. Cả hai phải qua booking/payment service và outbox; booking chỉ đổi trạng thái sau khi worker xác nhận thao tác sandbox thành công. Không cập nhật booking trực tiếp trong ReportController hoặc moderation action.

# Dữ liệu và phụ thuộc

reports, moderation\_actions, audit\_logs; đọc Listing, Review, Message/Update và Booking qua policy. Hành động ẩn listing làm Search cập nhật; không thay đổi booking đã xác nhận nếu chưa có quyết định riêng.

# Tiêu chí chấp nhận

Người không phải admin không mở panel quản trị. Một report được chuyển trạng thái có lịch sử. Ẩn listing loại khỏi search và ngăn booking mới. Báo cáo về nội dung riêng tư không làm lộ tệp cho người ngoài. Có nhánh dismiss với lý do.

# Ngoài P0

Phân xử/đền bù tài chính thật, tự động nhận diện nội dung bằng AI, công cụ xác minh lý lịch thực.

# Xử lý booking P0

Sau 72 giờ owner không xác nhận, booking vào hàng admin. Report IN\_REVIEW giữ booking ở AWAITING\_CONFIRMATION. Admin quyết định theo endpoint booking-decision với một trong hai lựa chọn: COMPLETE để tạo release operation, hoặc CANCEL\_REFUND để tạo cancellation/refund operation. Report chuyển RESOLVED cùng transaction với việc ghi quyết định và tạo operation; worker mới cập nhật booking sau khi release/refund thành công. Khi report bị DISMISSED, booking không đổi và owner có thể tiếp tục luồng xác nhận thông thường. Release/refund lỗi được retry hoặc đưa vào hàng MANUAL\_REVIEW, không đánh dấu booking hoàn tất/hủy trước khi gateway thành công. Xem [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
