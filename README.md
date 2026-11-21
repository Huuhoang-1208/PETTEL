# PETTEL

Nền tảng tìm và đặt dịch vụ chăm sóc thú cưng tại Đà Nẵng. Stack đích: Laravel 13/PHP 8.3 cho JSON API, Next.js App Router trong `frontend/`, Sanctum stateful cookie/CSRF, MySQL 8.4, Redis 7 và Docker Compose. Breeze API cung cấp scaffold xác thực; Filament dành cho admin, Scramble cho OpenAPI, Pulse cho quan sát. Chi tiết stack và các ràng buộc nằm trong [Technology & Tools](<docs/08 — Architecture & Technical Design/10 — Technology & Tools — Responsibilities & Constraints.md>).

## Trạng thái hiện tại

Backend có xác thực Breeze/Sanctum, vai trò OWNER/CAREGIVER/ADMIN, các workflow nghiệp vụ P0, Filament cho quản trị, Scramble cho OpenAPI và Pulse cho quan sát. Docker có MySQL, Redis, worker và scheduler; môi trường test dùng MySQL riêng. Có tài khoản, pet và caregiver profile minh họa. Frontend Next.js trong `frontend/` đã kết nối các luồng tài khoản, tìm dịch vụ, thú cưng, đặt lịch, thanh toán sandbox, chat, chăm sóc và duyệt dịch vụ. Xem [hướng dẫn frontend](frontend/README.md) và [danh mục tích hợp](docs/frontend-backend-integration.md).

Giao diện Next.js/React đã được giảng viên chấp thuận theo xác nhận của nhóm. Bản demo hỗ trợ lưu trú qua đêm và chăm sóc ban ngày; thanh toán sandbox không chuyển tiền thật. Hồ sơ/đánh giá mẫu được phân biệt với giao dịch thật. Chưa cung cấp bảo hiểm, xác minh sinh trắc học hay GPS trực tiếp; dịch vụ đi dạo/ghé thăm là hướng mở rộng.

## Tính năng và hình ảnh

| Vai trò | Chức năng chính |
| --- | --- |
| Khách / chủ nuôi | Tìm và lọc dịch vụ, đăng ký/đăng nhập, hồ sơ pet, Smart Match, đặt lịch, thanh toán mô phỏng, chat, nhật ký chăm sóc, đánh giá và báo cáo |
| Người chăm | Hồ sơ caregiver, tạo dịch vụ, lịch khả dụng, nhận/từ chối đơn, cập nhật chăm sóc và trao đổi với chủ nuôi |
| Quản trị viên | Duyệt dịch vụ/thay đổi, xử lý báo cáo, theo dõi đơn và dashboard quản trị |
| Tích hợp / vận hành | Google OAuth, Sanctum token abilities, Scramble, Redis queue/cache, Reverb, Telescope/Pulse và cảnh báo Slack |

Screenshot và kết quả kiểm tra được ghi tại [báo cáo kiểm chứng](docs/submission-verification.md).

Kiểm tra local ngày 21/11/2026: **467 backend tests / 5.283 assertions**, line coverage **86,66%**. Pint, PHPStan, TypeScript và production build thành công; ESLint không có lỗi, còn một cảnh báo sẵn có trong luồng OAuth. Xem báo cáo để biết phạm vi và giới hạn kiểm chứng.

## Bằng chứng nộp bài

- [Checklist và kịch bản quay 9 phút 40 giây](docs/submission-checklist.md): bao phủ sáu task của rubric.
- **Video demo: chưa quay/chưa có link.** Nhóm cần quay MP4 tối thiểu 720p, không quá 10 phút và bổ sung link có quyền xem trước khi nộp.
- **Google login thật và Slack nhận cảnh báo: còn chờ bằng chứng.** Code/config/test mock không thay thế việc chứng minh kết nối thật.
- Ví dụ Issue–PR: [Issue #16](https://github.com/Huuhoang-1208/PETTEL/issues/16) → nhánh `be-t03` → [PR #17](https://github.com/Huuhoang-1208/PETTEL/pull/17), merge `e4379f1`. Nguồn đối chiếu: [báo cáo BE-T03](<docs/05 — Implementation Plan/BE-T03 — Báo cáo thực hiện issue #16.md>). Liên kết dựa trên tài liệu bàn giao và lịch sử Git local; kiểm tra lại quyền truy cập/trạng thái trên GitHub khi quay.
- [Backend và frontend CI](https://github.com/Huuhoang-1208/PETTEL/actions): bổ sung run URL của commit nộp cuối; không dùng kết quả local thay cho GitHub CI.
- Mỗi thành viên cần hoàn tất Self-Report của cả 5 sprint theo brief.

## Chạy dịch vụ nền

Yêu cầu Docker Desktop đang chạy Linux containers (hoặc Docker Engine trên Linux) và Docker Compose v2 trở lên. Runtime trong image là PHP 8.3, MySQL 8.4 và Redis 7. Các lệnh dưới đây chạy bằng PowerShell tại thư mục gốc repo; không cần dùng PHP/Composer trên máy host.

### 1. Tạo cấu hình local và APP_KEY

```powershell
if (!(Test-Path .env)) {
    Copy-Item backend/.env.example .env
}
docker compose build
docker compose run --rm --no-deps backend php artisan key:generate --show
```

Sao chép chuỗi `base64:...` được tạo vào `APP_KEY=` trong **`.env` ở gốc repo**, rồi lưu file. Chỉ tạo key khi thiết lập lần đầu; giữ nguyên key khi khởi động lại. Không commit `.env`.

Compose đọc `.env` gốc để thay biến trong `compose.yaml`, sau đó truyền các biến đã khai báo trong `environment` cho Laravel. File `.env` gốc không được mount vào container; `key:generate --show` giúp lấy key để lưu đúng vị trí. Các tên host `db` và `redis` dành cho kết nối bên trong Docker.

### 2. Khởi động, migrate và seed

```powershell
docker compose up -d db redis backend frontend web
docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan db:seed --force
docker compose up -d worker scheduler
```

Migrate trước khi bật worker/scheduler để các tiến trình nền không sử dụng bảng chưa được tạo. Seeder chỉ cho chạy khi `APP_ENV=local` hoặc `testing`.

### 3. Kiểm tra dịch vụ

```powershell
docker compose ps
Invoke-RestMethod http://localhost:8080/
Invoke-WebRequest -Uri http://localhost:8080/up -UseBasicParsing | Select-Object StatusCode
docker compose exec -T redis redis-cli ping
```

Kết quả mong đợi: `/` trên cổng 8080 trả JSON trạng thái API, `/up` trả HTTP 200, Redis trả `PONG`; `web`, `frontend`, `db` và `redis` có trạng thái `healthy`. Worker/scheduler phải ở trạng thái chạy. Next.js được build sẵn trong service `web` tại http://localhost:3000; service `frontend` là Nginx/API Laravel ở cổng 8080.

Sau khi thiết lập lần đầu, chạy `docker compose up -d` hoặc Start các container của project PETTEL trong Docker Desktop để mở toàn bộ ứng dụng. Không cần chạy `npm run dev` riêng. Các service dùng `restart: unless-stopped`, nên Docker tự khởi động lại container khi engine khởi động nếu container chưa bị Stop thủ công.

Sau khi sửa frontend, chạy `docker compose up -d --build web` để cập nhật bản chạy trong Docker. Các biến `NEXT_PUBLIC_*` được đưa vào bundle lúc build; Compose lấy API URL và địa chỉ socket từ `.env` gốc, public Reverb key từ `REVERB_APP_KEY`. Không đưa Reverb secret vào frontend. Dừng Next.js chạy bằng `npm run dev` trên Windows trước khi bật `web` để tránh trùng cổng 3000.

Nếu thay `APP_PORT`, cập nhật đồng bộ `APP_URL`, `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` trong `.env` và dùng cổng mới trong các lệnh kiểm tra.

## REST API v1

Base URL local là `http://localhost:8080`; các API resource có prefix `/api/v1`. Tài liệu OpenAPI do Scramble tạo tại `/docs/api` và `/docs/api.json`; quyền xem tài liệu chỉ dành cho tài khoản ADMIN đã đăng nhập.

Ứng dụng frontend dùng phiên Sanctum cookie và CSRF. Client tích hợp bên ngoài dùng Sanctum Bearer token. Tạo token tại `POST /api/v1/tokens` bằng phiên đăng nhập hợp lệ, gửi JSON gồm tên token và abilities cần thiết:

```json
{
  "name": "reporting-client",
  "abilities": ["profile:read", "pets:read"]
}
```

Token chỉ được trả về một lần và hết hạn sau 30 ngày. Lưu token an toàn, không đưa vào Git hoặc frontend công khai. Các abilities được hỗ trợ:

| Ability | Quyền áp dụng |
| --- | --- |
| `profile:read` | Đọc `/user` và thông tin profile được cấp quyền |
| `pets:read` | Đọc danh sách và chi tiết thú cưng |
| `bookings:read` | Đọc danh sách và chi tiết booking |

Ví dụ gọi endpoint đọc user bằng token có `profile:read`:

```powershell
curl.exe -H "Accept: application/json" -H "Authorization: Bearer <TOKEN>" "http://localhost:8080/api/v1/user"
```

Nếu token hợp lệ nhưng thiếu ability cần thiết, API trả `403 Forbidden`. Ví dụ gửi `profile:read` để đọc `/api/v1/pets` sẽ bị từ chối. Request không hợp lệ trả `422 Unprocessable Content` theo error envelope có `error.code`, `error.message`, `error.details` và `error.request_id`.

Ví dụ lỗi validation khi yêu cầu trang review không hợp lệ:

Thay `107` bằng ID của một listing đang public trong database local.

```powershell
curl.exe -H "Accept: application/json" "http://localhost:8080/api/v1/listings/107/reviews?page=0"
```

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "<VALIDATION_MESSAGE>",
    "details": { "page": ["<PAGE_VALIDATION_DETAIL>"] },
    "request_id": "<REQUEST_ID>"
  }
}
```

## Tài khoản và dữ liệu mẫu

| Email | Vai trò | Mật khẩu khi tạo mới |
| --- | --- | --- |
| `owner@example.test` | OWNER | `DemoOnly123!` |
| `caregiver@example.test` | OWNER, CAREGIVER | `DemoOnly123!` |
| `admin@example.test` | ADMIN | `DemoOnly123!` |

Seed còn tạo pet `Demo Mochi`, `Demo Mèo Nấm`, `Demo Corgi Bắp` thuộc owner; 52 tài khoản sitter `sitter-001@example.test` đến `sitter-052@example.test`; 52 hồ sơ và listing đang hoạt động cùng lịch nhận đặt trong 6 tháng. Ảnh hồ sơ lấy từ `Pet sister/` của giao diện cũ, lưu thành media của listing và có thumbnail WebP. Dữ liệu điểm tin cậy, tiện ích, đánh giá hiển thị đều được đánh dấu là demo; seed không tạo booking hoặc đánh giá giao dịch.

Mật khẩu khởi tạo cho tài khoản sitter và tài khoản demo hiện có là `DemoOnly123!`. Tài khoản owner có thể đăng nhập để thử bộ lọc theo hồ sơ thú cưng; tài khoản sitter để thử các chức năng caregiver. Có thể chạy lại `db:seed`: seeder giữ nguyên mật khẩu tài khoản đã tồn tại, cập nhật dữ liệu catalog và không nhân bản listing/media/lịch demo. Thông tin đăng nhập chỉ dành cho local/test, không dùng cho môi trường public.

Khi cập nhật code hoặc fixture seed, áp dụng migration và nạp dữ liệu bằng:

```powershell
docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan db:seed --force
docker compose up -d --build backend web
```

## Worker và scheduler

Worker xử lý queue Redis với timeout 60 giây, nhỏ hơn `REDIS_QUEUE_RETRY_AFTER=90`. Khi `APP_ENV=local`, scheduler gửi job `InfrastructureHeartbeat` mỗi phút; worker ghi timestamp vào Redis cache với thời hạn 180 giây.

```powershell
docker compose exec -T backend php artisan schedule:list
docker compose logs --tail=30 worker scheduler
docker compose exec -T backend php artisan tinker --execute="dump(cache()->get('infra:heartbeat'));"
docker compose exec -T backend php artisan queue:failed
```

Sau khi khởi động, chờ hơn một phút rồi đọc heartbeat. Đọc lại sau một phút: timestamp phải tăng, đồng thời log worker phải có job `DONE`. Giá trị `null` sau thời gian chờ hoặc timestamp không tăng cần kiểm tra log, Redis và trạng thái worker/scheduler. Timestamp mặc định được ghi theo UTC.

## Cảnh báo lỗi qua Slack

Tạo Slack Incoming Webhook cho kênh `#pettel-alerts`, rồi lưu URL trong `.env` gốc (không commit URL):

```env
LOG_STACK=single,slack
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/REPLACE_WITH_YOUR_WEBHOOK
LOG_SLACK_LEVEL=error
```

Compose chuyển các biến này vào Laravel. Sau khi cập nhật `.env`, tạo lại các tiến trình Laravel rồi gửi thử một log lỗi:

```powershell
docker compose up -d --force-recreate backend worker scheduler
docker compose exec -T backend php artisan tinker --execute='Log::error("PETTEL Slack integration test");'
```

Thông báo thử nghiệm cần xuất hiện trong `#pettel-alerts`. Webhook URL là thông tin bí mật; nếu bị lộ, thu hồi URL đó trong Slack và tạo URL mới.

## Kiểm tra định dạng, migration, seed và test

Chạy tại gốc repo:

```powershell
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Runner thực hiện lần lượt:

1. Tạo `.env` rỗng trong container test và xóa config cache.
2. `migrate:fresh --seed` trên database `pettel_test`, rồi seed lần hai để kiểm tra khả năng chạy lặp.
3. Chạy PHPUnit với cả `Unit`, `Feature`, `Integration`; ghi coverage HTML/XML vào `artifacts/coverage/`.
4. Pint kiểm tra định dạng và PHPStan phân tích tĩnh (`composer ci:check`). Bước này vẫn chạy nếu PHPUnit thất bại; runner trả lỗi nếu test hoặc kiểm tra tĩnh thất bại.

`DemoSeederTest` kiểm tra số bản ghi, vai trò và bảo toàn mật khẩu sau khi seed lại. Test migration ở suite Integration kiểm tra đúng migration suspension-source, không phụ thuộc migration nào được thêm sau đó.

Frontend có Vitest + React Testing Library (DOM mô phỏng), chạy cùng lint/typecheck/build trong CI:

```powershell
cd frontend
npm ci
npm test
npm run lint -- src tests
npm run typecheck
npm run build
```

Các test kiểm tra CSRF trước request ghi, idempotency key và lỗi validation. Đây là unit tests, không thay thế E2E với backend thật. Ảnh catalog local được tối ưu bằng Next Image; thumbnail API dùng WebP, kích thước cố định và lazy loading. Media cần cookie vẫn được tải trực tiếp để giữ phân quyền.

Luôn dùng file Compose và project test riêng như lệnh trên. `db-test` dùng MySQL 8.4, database/tài khoản `pettel_test` và dữ liệu tạm trên `tmpfs`; không gắn volume demo hay mở cổng database ra host. Cache/session/mail dùng `array`, queue dùng `sync`. PHPUnit ép cấu hình test; `Tests\TestCase` kiểm tra môi trường, driver, database và tài khoản trước khi chạy các trait migration. Không chạy `migrate:fresh` trên service `backend` của demo.

Dọn môi trường test sau khi kiểm tra:

```powershell
docker compose -p pettel-test -f compose.test.yaml down -v --remove-orphans
```

Lệnh dọn này chỉ dành cho project `pettel-test`. Database demo vẫn thuộc project `pettel`. Để xác minh dữ liệu demo không bị tác động, đối chiếu tài khoản/pet demo trước và sau test; không chỉ dựa vào kết quả test xanh.

Workflow `.github/workflows/tests.yml` chạy cùng lệnh Docker trên pull request và khi push vào `main` hoặc `be-ha01`, in log database nếu lỗi và dọn môi trường test khi kết thúc. Sau khi commit/push, kiểm tra kết quả trong GitHub Actions trước khi bàn giao review.

## Cập nhật code và xử lý lỗi

Mã nguồn được COPY vào image, không có bind mount để tự nhận thay đổi. Sau khi sửa code:

```powershell
docker compose up -d --build
docker compose exec -T backend php artisan migrate --force
docker compose restart frontend
```

Nếu thay đổi liên quan seed, chạy thêm `docker compose exec -T backend php artisan db:seed --force` khi cần cập nhật dữ liệu demo. Worker và scheduler cũng cần được tạo lại từ image mới như lệnh `up --build` ở trên.

Nếu `/up` trả 502 sau khi backend được tạo lại, Nginx có thể còn giữ địa chỉ backend cũ. Kiểm tra và khởi động lại Nginx:

```powershell
docker compose logs --tail=50 frontend backend
docker compose restart frontend
Invoke-WebRequest -Uri http://localhost:8080/up -UseBasicParsing | Select-Object StatusCode
docker compose ps
```

Đợi healthcheck cập nhật và xác nhận `frontend` trở lại `healthy`. Nếu vẫn lỗi, kiểm tra log PHP-FPM và kết nối upstream; không coi việc container đang chạy là bằng chứng API hoạt động.

## Dịch vụ Compose

| Service | Vai trò |
| --- | --- |
| `frontend` | Nginx, cổng HTTP duy nhất mở ra host |
| `backend` | Laravel PHP-FPM |
| `worker` | Redis queue |
| `scheduler` | Laravel scheduler |
| `db` | MySQL 8.4, named volume |
| `redis` | Cache và queue, named volume |

MySQL không mở cổng ra host. `.env` chỉ dành cho local; không dùng secret mặc định cho demo public. `docker compose down` giữ dữ liệu; `docker compose down -v` xóa volume MySQL, Redis và storage.

## Cấu trúc

```text
backend/                    # Laravel JSON API
├── app/                    # Domain, HTTP, Models, Providers
├── bootstrap/              # Laravel bootstrap
├── config/                 # Framework configuration
├── database/               # Migrations, factories, seeders
├── public/                 # Laravel front controller và public assets
├── routes/                 # Web/API routes
├── tests/                  # Backend tests được bổ sung cùng tính năng
├── composer.json
└── Dockerfile
frontend/                   # Next.js App Router + React UI
docs/                       # Yêu cầu, thiết kế và kế hoạch PETTEL
compose.yaml                # Docker Compose
```

`backend/public/index.php` là entry point của Laravel và vẫn cần cho API. Giao diện Next.js/React thuộc `frontend/`; không đặt component giao diện này trong `backend/resources`. Filament vẫn dùng Blade/Livewire nội bộ cho trang quản trị Laravel.

## ERD cơ sở dữ liệu

Sơ đồ dưới đây tóm tắt các bảng nghiệp vụ chính và quan hệ khóa ngoại trong migrations. Các bảng hạ tầng của Laravel, Pulse, Telescope và các bảng nhật ký phụ trợ được lược bớt để sơ đồ dễ đọc.

```mermaid
erDiagram
    USERS {
        bigint id PK
    }
    USER_ROLES {
        bigint user_id PK, FK
        string role PK
        bigint assigned_by_user_id FK
    }
    PETS {
        bigint id PK
        bigint user_id FK
        bigint photo_media_id FK
    }
    CAREGIVER_PROFILES {
        bigint id PK
        bigint user_id FK
    }
    LISTINGS {
        bigint id PK
        bigint caregiver_profile_id FK
    }
    AVAILABILITY_SLOTS {
        bigint id PK
        bigint listing_id FK
        bigint created_by_user_id FK
    }
    LISTING_CHANGE_REQUESTS {
        bigint id PK
        bigint listing_id FK
        bigint requested_by_user_id FK
        bigint reviewed_by_user_id FK
    }
    MEDIA_OBJECTS {
        bigint id PK
        bigint owner_user_id FK
    }
    LISTING_MEDIA {
        bigint listing_id PK, FK
        bigint media_object_id PK, FK
    }
    BOOKINGS {
        bigint id PK
        bigint owner_id FK
        bigint pet_id FK
        bigint listing_id FK
        bigint caregiver_user_id FK
    }
    BOOKING_STATUS_EVENTS {
        bigint id PK
        bigint booking_id FK
        bigint actor_user_id FK
    }
    PAYMENT_ATTEMPTS {
        bigint id PK
        bigint booking_id FK
    }
    PAYMENT_EVENTS {
        bigint id PK
        bigint payment_attempt_id FK
    }
    MESSAGES {
        bigint id PK
        bigint booking_id FK
        bigint sender_id FK
    }
    MESSAGE_ATTACHMENTS {
        bigint id PK
        bigint message_id FK
        bigint media_object_id FK
    }
    CARE_UPDATES {
        bigint id PK
        bigint booking_id FK
        bigint sender_id FK
        bigint media_object_id FK
    }
    REVIEWS {
        bigint id PK
        bigint booking_id FK
        bigint owner_id FK
        bigint listing_id FK
    }
    REPORTS {
        bigint id PK
        bigint reporter_user_id FK
        bigint booking_id FK
        bigint listing_id FK
        bigint review_id FK
        bigint message_id FK
    }
    MODERATION_ACTIONS {
        bigint id PK
        bigint report_id FK
        bigint admin_user_id FK
    }

    USERS ||--o{ USER_ROLES : has
    USERS o|--o{ USER_ROLES : assigns
    USERS ||--o{ PETS : owns
    USERS ||--o| CAREGIVER_PROFILES : has
    USERS ||--o{ MEDIA_OBJECTS : owns
    USERS ||--o{ AVAILABILITY_SLOTS : creates
    USERS ||--o{ LISTING_CHANGE_REQUESTS : requests
    USERS o|--o{ LISTING_CHANGE_REQUESTS : reviews
    USERS ||--o{ BOOKINGS : requests_as_owner
    USERS ||--o{ BOOKINGS : serves_as_caregiver
    USERS o|--o{ BOOKING_STATUS_EVENTS : acts_on
    USERS ||--o{ MESSAGES : sends
    USERS ||--o{ CARE_UPDATES : sends
    USERS ||--o{ REVIEWS : writes
    USERS ||--o{ REPORTS : files
    USERS ||--o{ MODERATION_ACTIONS : performs

    MEDIA_OBJECTS o|--o{ PETS : photo
    CAREGIVER_PROFILES ||--o{ LISTINGS : publishes
    LISTINGS ||--o{ AVAILABILITY_SLOTS : offers
    LISTINGS ||--o{ LISTING_CHANGE_REQUESTS : receives
    LISTINGS ||--o{ LISTING_MEDIA : has
    MEDIA_OBJECTS ||--o{ LISTING_MEDIA : attached_to
    BOOKINGS ||--o{ BOOKING_STATUS_EVENTS : records
    BOOKINGS ||--o{ PAYMENT_ATTEMPTS : has
    PAYMENT_ATTEMPTS o|--o{ PAYMENT_EVENTS : records
    BOOKINGS ||--o{ MESSAGES : contains
    MESSAGES ||--o{ MESSAGE_ATTACHMENTS : has
    MEDIA_OBJECTS ||--o{ MESSAGE_ATTACHMENTS : attached_to
    BOOKINGS ||--o{ CARE_UPDATES : has
    MEDIA_OBJECTS o|--o{ CARE_UPDATES : illustrates
    BOOKINGS ||--o| REVIEWS : may_receive
    LISTINGS ||--o{ REVIEWS : receives
    BOOKINGS o|--o{ REPORTS : referenced_by
    LISTINGS o|--o{ REPORTS : referenced_by
    REVIEWS o|--o{ REPORTS : referenced_by
    MESSAGES o|--o{ REPORTS : referenced_by
    REPORTS ||--o{ MODERATION_ACTIONS : has
```

## Tài liệu

- [Mục lục](<docs/00 — PETTEL Documentation Index.md>)
- [Product](<docs/01 - Product Document/>)
- [Domain](<docs/02 — Domain Documentation/>)
- [Modules](<docs/03 — Modules Documentation/>)
- [Workflows](<docs/04 — Workflows/>)
- [Implementation Plan](<docs/05 — Implementation Plan/>)
- [Non-functional Requirements](<docs/06 — Non-functional Requirements/>)
- [Architecture & Technical Design](<docs/08 — Architecture & Technical Design/>)
- [Backlog 4 tuần](<docs/PETTEL — Task 4 tuần (A, B, C)/Chia task.md>)
