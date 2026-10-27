# Docker Topology & Deployment Design

PETTEL — Architecture & Technical Design \| Bản thảo 26/10/2026

# Dịch vụ Compose

`web` (Nginx hoặc reverse proxy) phục vụ bản build Vue SPA và chuyển `/api`, `/sanctum`, auth route, `/admin` đến Laravel; là cổng duy nhất công khai; \`app\` chạy PHP-FPM/Laravel; \`worker\` chạy queue:work cùng image app; \`scheduler\` chạy lịch tác vụ; \`db\` MySQL; \`redis\` cache/queue. \`sandbox\` có thể là adapter nội bộ trong app, không cần container riêng. File media ở volume private hoặc object storage. Filament và Pulse dashboard nằm sau xác thực/phân quyền.

# Mạng, volume và cấu hình

App/worker/scheduler dùng cùng image nhưng command khác; kết nối db/redis qua network nội bộ. Volume bền vững cho MySQL và media local. Secret qua biến môi trường/secret manager, không viết trong Compose hoặc Git. Healthcheck db/redis/app; \`depends\_on\` giúp thứ tự khởi động nhưng app vẫn phải chịu được phụ thuộc đến muộn. \`APP\_DEBUG=false\` ở demo/staging; chỉ web mở port.

# Triển khai

Build image gắn commit/tag → chạy tests → backup DB → chạy migration có kiểm soát một lần → khởi động/healthcheck → seed demo idempotent nếu là môi trường demo → smoke test search, booking, sandbox, report. Worker cần restart khi triển khai code mới. Không chạy migration đồng thời ở mọi replica.

# Phục hồi và rollback

Giữ image trước để quay lui; thiết kế migration tương thích giữa hai phiên bản hoặc có kế hoạch khôi phục DB. \`docker compose down \-v\` xóa volume nên không dùng trên dữ liệu cần giữ. Tài liệu chạy phải nêu biến môi trường, account demo và cách reset sandbox. Compose reference: https\://docs.docker.com/compose/ .  
