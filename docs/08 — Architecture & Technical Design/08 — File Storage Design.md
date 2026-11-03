# File Storage Design

PETTEL — Architecture & Technical Design \| Bản thảo 26/10/2026

# Phân loại tệp

Ảnh listing chỉ công khai khi listing ACTIVE, media `READY`/`PUBLIC_ELIGIBLE` và nằm trong pivot listing đã duyệt. Ảnh pet và care update vẫn private. DB lưu media ID, owner/booking/listing relation, object key ngẫu nhiên, MIME đã xác định, kích thước, hash, thời điểm; API không trả object key/hash hoặc URL lưu trữ vĩnh viễn.

# Upload an toàn

BE-T03 chốt JPEG/PNG/WebP, đầu vào tối đa 10.485.760 byte (10 MiB), 20 megapixel và 10 ảnh/listing. MIME được xác định từ nội dung, ảnh decode/chuẩn hóa để bỏ metadata không cần thiết, tên object là ngẫu nhiên; SVG/executable bị từ chối. Thumbnail WebP được tạo đồng bộ với cạnh dài tối đa 640 px. Record chỉ ở `READY` sau khi ảnh và thumbnail đã lưu trên private local disk.

File I/O diễn ra ngoài DB transaction. Khi lưu file hoặc ghi row DB lỗi, ứng dụng xóa các object vừa tạo; lỗi cleanup được log nội bộ kèm key để retry thủ công. Không có READY giả, queue media hoặc cleanup scheduler trong phạm vi BE-T03. Upload giới hạn theo tầng web server/PHP có thể trả HTTP 413 trước khi chạy validation ứng dụng.

# Truy cập và xóa

Ảnh riêng được stream qua route có xác thực lại mỗi request; caregiver/admin phải đúng phạm vi listing, admin không tự duyệt ảnh listing của mình. Public stream kiểm tra listing, pivot, `READY` và `PUBLIC_ELIGIBLE`; suspend/detach làm request tiếp theo trả 404. Response đặt `Cache-Control: no-store` và `X-Content-Type-Options: nosniff`. Việc này không thu hồi byte người dùng đã tải.

Detach, reject hoặc withdraw không xóa object. Pending proposal là một tham chiếu cần giữ; chỉ cleanup khi không còn tham chiếu hay nghĩa vụ lưu hợp lệ theo P0. Seed/demo dùng ảnh có quyền sử dụng.

# Môi trường

Local/dev có thể dùng Laravel private local disk với volume; demo/staging có thể dùng S3-compatible storage nếu sẵn có. Cùng API Storage để đổi backend. Backup media tách khỏi DB và thử restore. Laravel hỗ trợ temporary URL cho local/S3: https\://laravel.com/docs/13.x/filesystem .

# Xóa dữ liệu P0

Job chỉ xóa object khi không còn tham chiếu hợp lệ và không có report đang giữ. Restore backup phải áp dụng lại danh sách yêu cầu xóa. Xem [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
