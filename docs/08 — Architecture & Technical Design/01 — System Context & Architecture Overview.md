# System Context & Architecture Overview

PETTEL — Architecture & Technical Design \| Thiết kế đề xuất, chưa phải mô tả hệ thống đã triển khai.

# Tác nhân và hệ thống ngoài

Chủ nuôi, người chăm/cơ sở và admin dùng trình duyệt. PETTEL web app nhận request qua HTTPS. MySQL lưu nghiệp vụ; Redis hỗ trợ cache/queue; private file storage giữ ảnh; payment sandbox adapter tạo/nhận trạng thái giả. Không có cổng tiền thật, camera hoặc video provider.

# Kiến trúc

Một Laravel monolith có các module logic: Identity, Pet, Listing, Discovery, Booking, PaymentSandbox, Messaging/Update, Review, Report/Admin. Vue SPA gọi JSON API của Laravel; Filament phục vụ admin. Controller/FormRequest/Policy gọi application service; service dùng Eloquent trong transaction. Queue worker xử lý việc phụ sau commit; scheduler hết hạn booking chờ thanh toán. Không tách microservice để tránh giao dịch phân tán trong bản mẫu.

# Đường dữ liệu quan trọng

Tìm kiếm: Vue SPA → JSON API → cache/search query → MySQL listing ACTIVE → JSON kết quả. Booking: browser → authorization/validation → transaction khóa listing và kiểm tra overlap → MySQL booking → trả state. Payment: booking tạo attempt → sandbox callback được xác thực → transaction payment \+ booking → event/audit. Ảnh: upload qua app → private storage; người đọc có quyền mới nhận URL ngắn hạn.

# Nguồn chuẩn và lỗi

MySQL là nguồn chuẩn cho status, amount, availability và quyền sở hữu. Redis hỏng không được làm sai booking/payment; có thể chậm hoặc dừng tác vụ phụ. Mọi chuyển trạng thái có audit và idempotency key; lỗi ngoại vi không được âm thầm coi là thành công.

# Quyết định môi trường còn phụ thuộc triển khai

Hosting demo, tương thích package với Laravel 13/PHP 8.3 và loại storage còn phụ thuộc môi trường. Chính sách hủy/retention P0 đã chốt trong [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>); xác minh thật nằm ngoài P0. Phiên bản package ở tài liệu Technology & Tools.
