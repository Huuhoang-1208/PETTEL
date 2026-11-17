# Technology & Tools — Responsibilities & Constraints

PETTEL — Architecture & Technical Design | Stack được đối chiếu với repository ngày 17/11/2026.

# Nền tảng chính

Giảng viên không bắt buộc một công nghệ frontend cụ thể; nhóm chọn **Next.js App Router và React** cho giao diện trong `frontend/`. Backend dùng Laravel 13 + PHP 8.3 trở lên để cung cấp JSON API; MySQL lưu dữ liệu nghiệp vụ; Docker Compose chạy các dịch vụ. Phiên bản Laravel được giữ theo `backend/composer.json`. **Laravel Breeze API stack** cung cấp scaffold xác thực backend; quyền và quy tắc nghiệp vụ vẫn do Policy/service thực thi. Nguồn: https://github.com/laravel/breeze/tree/2.x ; https://github.com/laravel/breeze/blob/2.x/src/Console/InstallsApiStack.php .

# Công cụ dùng trong P0

- Redis: cache và queue; MySQL vẫn là nguồn chuẩn cho booking, payment và quyền — https://laravel.com/docs/13.x/redis .
- Laravel Policy/Gate: kiểm tra quyền ở mọi endpoint, kể cả admin và file riêng tư — https://laravel.com/docs/13.x/authorization .
- Docker Compose: chạy Next.js/React (`web`), Nginx/API gateway (`frontend`), backend Laravel, worker, scheduler, MySQL và Redis — https://docs.docker.com/compose/ .
- Laravel Telescope: debug local/dev, không mở public và không ghi bí mật/PII vào log — https://laravel.com/docs/13.x/telescope .

# Công cụ tùy điều kiện — quyết định sử dụng

- **Laravel Sanctum — dùng cho P0:** xác thực frontend Next.js/React bằng cookie/session stateful và CSRF; client API được cấp bearer token theo allowlist ability, có hạn dùng và thu hồi được. Trình duyệt không lưu personal access token; route bearer token phải khai báo ability. Cấu hình origin/domain, credentials và CSRF cookie — https://laravel.com/docs/13.x/sanctum .
- **Scramble — dùng cho P0:** sinh OpenAPI từ JSON API để frontend và backend đối chiếu contract. Review thủ công quyền, trạng thái, idempotency và ví dụ lỗi; hạn chế truy cập tài liệu API ở môi trường demo nếu chứa endpoint nội bộ — https://scramble.dedoc.co/ .
- **Filament — dùng cho admin P0:** dựng màn hình duyệt listing và xử lý report. Admin panel có đăng nhập, Gate/Policy và audit riêng; đây là giao diện quản trị Laravel tách với frontend Next.js/React cho owner/caregiver — https://filamentphp.com/docs/5.x/introduction/installation .
- **Laravel Pulse — dùng cho quan sát P0:** theo dõi request, query và queue để tìm điểm chậm; bảo vệ dashboard bằng quyền admin và giới hạn dữ liệu nhạy cảm. Pulse bổ sung, không thay log/audit và cảnh báo vận hành — https://laravel.com/docs/13.x/pulse .

# Chưa cần trong P0

Laravel Scout chỉ thêm khi truy vấn tìm kiếm cơ bản cần full-text; không cần Meilisearch ngay — https://laravel.com/docs/13.x/scout . Laravel Octane chỉ xem xét sau benchmark — https://laravel.com/docs/13.x/octane . Không thêm microservice, search server hoặc streaming stack chỉ vì có tên trong danh sách gợi ý.

# Ràng buộc triển khai

Frontend Next.js/React và API được chạy qua Docker Compose; cấu hình origin, Sanctum stateful domains, session domain, CORS credentials và CSRF theo cách triển khai thực tế. Composer packages và npm packages được pin bằng lockfile tương ứng. README mô tả lệnh build, biến môi trường và dịch vụ chạy. Tài liệu này mô tả stack đã chọn; trạng thái triển khai cần đối chiếu mã nguồn và CI.
