# Deployment & Environment Requirements

PETTEL — Non-functional Requirements \| Bản thảo 26/10/2026

# Môi trường

Tối thiểu local/dev và demo/staging tách DB, Redis, storage, secret và dữ liệu seed. \`APP\_ENV\`/\`APP\_DEBUG\` đúng môi trường; demo/staging tắt debug. HTTPS khi truy cập ngoài máy. Không commit \`.env\`, key, mật khẩu hoặc seed PII thật.

# Thành phần

Docker Compose điều phối web server phục vụ Vue build, Laravel PHP app, queue worker, scheduler, MySQL và Redis; storage private dùng volume hoặc dịch vụ object storage do nhóm chọn. Chỉ web server mở cổng ra ngoài. Healthcheck DB/Redis/app, volume bền vững cho DB và media; migration chạy có kiểm soát một lần khi deploy.

# Triển khai và quay lui

Build image cố định tag/commit, chạy test và migration trên staging, seed demo có thể chạy lại an toàn, smoke test bốn workflow. Backup trước migration có rủi ro; rollback bằng image trước và kế hoạch migration tương thích, không mặc định xóa dữ liệu. Document biến môi trường, build Vue, cấu hình Sanctum stateful domain/cookie và lệnh khởi động trong repo.

# Tiêu chí bàn giao

Người chấm có thể chạy lại bằng README, tài khoản demo không chứa dữ liệu thật và kịch bản sandbox thành công/thất bại. Sau khi \`compose up\`, app, DB và Redis đạt health; không cần chỉnh tay trạng thái booking/payment để demo.  
