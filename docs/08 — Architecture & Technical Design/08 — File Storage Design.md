# File Storage Design

PETTEL — Architecture & Technical Design \| Bản thảo 26/10/2026

# Phân loại tệp

Ảnh listing có thể công khai khi listing ACTIVE và được duyệt; ảnh pet và care update là private. DB lưu media ID, owner/booking/listing relation, object key ngẫu nhiên, MIME đã xác định, kích thước, hash, thời điểm; không lưu đường dẫn máy hoặc public URL vĩnh viễn.

# Upload an toàn

Giới hạn JPEG/PNG/WebP và 10 MB/ảnh đề xuất; kiểm tra MIME thực, kích thước pixel, đổi tên ngẫu nhiên, loại metadata EXIF nếu không cần. Chặn executable/SVG nếu chưa có sanitizer. Upload tạm → validate → lưu private → DB commit; nếu DB lỗi, dọn object mồ côi bằng job. Tạo thumbnail trong queue; không dùng ảnh gốc làm thumbnail danh sách.

# Truy cập và xóa

Ảnh riêng cấp URL ký ngắn hạn hoặc stream qua controller sau policy; chỉ owner/caregiver booking và admin có lý do xem. Không để bucket/disk private ở chế độ public. Xóa mềm entity không xóa tệp lập tức nếu còn booking/audit; thời hạn lưu và purge theo P0 Decision Contract: nội dung booking riêng 90 ngày, media mồ côi dọn trong 30 ngày, backup 30 ngày; report mở tạm giữ đến 90 ngày sau khi đóng. Seed/demo dùng ảnh có quyền sử dụng.

# Môi trường

Local/dev có thể dùng Laravel private local disk với volume; demo/staging có thể dùng S3-compatible storage nếu sẵn có. Cùng API Storage để đổi backend. Backup media tách khỏi DB và thử restore. Laravel hỗ trợ temporary URL cho local/S3: https\://laravel.com/docs/13.x/filesystem .

# Xóa dữ liệu P0

Job chỉ xóa object khi không còn tham chiếu hợp lệ và không có report đang giữ. Restore backup phải áp dụng lại danh sách yêu cầu xóa. Xem [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
