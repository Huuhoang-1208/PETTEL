# Technology & Tools — Responsibilities & Constraints

PETTEL — Architecture & Technical Design | Quyết định nền tảng ngày 27/10/2026; tài liệu mô tả đích triển khai, không khẳng định source code đã chuyển đổi.

# Nền tảng chính

Laravel 13 + PHP 8.3 trở lên cho backend, Vue 3 cho frontend SPA, MySQL cho dữ liệu nghiệp vụ và Docker Compose cho môi trường chạy. Không ràng buộc Laravel 10 vì giảng viên không bắt buộc phiên bản đó. Giữ Laravel 13 đang khai báo trong `backend/composer.json`; kiểm tra tương thích package bằng Composer trước khi cài. Dùng **Laravel Breeze API stack** để tạo luồng đăng ký, đăng nhập, đăng xuất và đặt lại mật khẩu ở backend; Vue nằm ở `frontend/` và gọi JSON API. Breeze chỉ cung cấp scaffold xác thực, không thay Policy hay quy tắc nghiệp vụ. Nguồn: https://github.com/laravel/breeze/tree/2.x ; https://github.com/laravel/breeze/blob/2.x/src/Console/InstallsApiStack.php .

# Công cụ dùng trong P0

- Redis: cache và queue; MySQL vẫn là nguồn chuẩn cho booking, payment và quyền — https://laravel.com/docs/13.x/redis .
- Laravel Policy/Gate: kiểm tra quyền ở mọi endpoint, kể cả admin và file riêng tư — https://laravel.com/docs/13.x/authorization .
- Docker Compose: chạy frontend/reverse proxy, backend Laravel, worker, scheduler, MySQL, Redis; build Vue để frontend phục vụ cùng origin ở demo — https://docs.docker.com/compose/ .
- Laravel Telescope: debug local/dev, không mở public và không ghi bí mật/PII vào log — https://laravel.com/docs/13.x/telescope .

# Công cụ tùy điều kiện — quyết định sử dụng

- **Laravel Sanctum — dùng cho P0:** xác thực Vue SPA bằng cookie/session stateful và CSRF; API riêng tư dùng `auth:sanctum`. SPA không lưu personal access token trong trình duyệt. Cấu hình origin/domain, credentials và CSRF cookie; chỉ phát hành API token nếu sau này có client bên ngoài thật — https://laravel.com/docs/13.x/sanctum .
- **Scramble — dùng cho P0:** sinh OpenAPI từ JSON API để frontend và backend đối chiếu contract. Review thủ công quyền, trạng thái, idempotency và ví dụ lỗi; hạn chế truy cập tài liệu API ở môi trường demo nếu chứa endpoint nội bộ — https://scramble.dedoc.co/ .
- **Filament — dùng cho admin P0:** dựng màn hình duyệt listing và xử lý report. Admin panel có đăng nhập, Gate/Policy và audit riêng; Vue vẫn là frontend cho owner/caregiver, không phải viết lại admin bằng Vue — https://filamentphp.com/docs/5.x/introduction/installation .
- **Laravel Pulse — dùng cho quan sát P0:** theo dõi request, query và queue để tìm điểm chậm; bảo vệ dashboard bằng quyền admin và giới hạn dữ liệu nhạy cảm. Pulse bổ sung, không thay log/audit và cảnh báo vận hành — https://laravel.com/docs/13.x/pulse .

# Chưa cần trong P0

Laravel Scout chỉ thêm khi truy vấn tìm kiếm cơ bản cần full-text; không cần Meilisearch ngay — https://laravel.com/docs/13.x/scout . Laravel Octane chỉ xem xét sau benchmark — https://laravel.com/docs/13.x/octane . Không thêm microservice, search server hoặc streaming stack chỉ vì có tên trong danh sách gợi ý.

# Ràng buộc triển khai

Frontend và API ưu tiên cùng origin ở demo để giảm cấu hình cookie/CORS; nếu tách subdomain, chúng phải cùng top-level domain và cấu hình Sanctum stateful domains, session domain, CORS credentials và CSRF phù hợp. Chạy Composer dependency resolution cho Breeze, Filament, Scramble, Pulse, Sanctum với Laravel 13 rồi pin phiên bản tương thích và lưu lockfile. Ghi Docker/PHP extension, build Vue và biến môi trường trong README khi triển khai. Hiện repository mới là Laravel API shell; Breeze/Sanctum auth, Vue SPA và các công cụ P0 vẫn là việc cần scaffold/triển khai, không được coi là đã hoàn thành chỉ nhờ cập nhật tài liệu.
