# PETTEL

Nền tảng tìm và đặt dịch vụ chăm sóc thú cưng tại Đà Nẵng. Stack đích: Laravel 13/PHP 8.3 cho JSON API, Vue 3 SPA trong `frontend/`, Sanctum stateful cookie/CSRF, MySQL 8.4, Redis 7 và Docker Compose. Breeze API cung cấp scaffold xác thực; Filament dành cho admin, Scramble cho OpenAPI, Pulse cho quan sát. Chi tiết stack và các ràng buộc nằm trong [Technology & Tools](<docs/08 — Architecture & Technical Design/10 — Technology & Tools — Responsibilities & Constraints.md>).

## Trạng thái hiện tại

Repo đã được dọn phần giao diện Livewire/Fortify cũ. Laravel còn là API shell; Vue app và Breeze/Sanctum auth sẽ được dựng theo kế hoạch trong [Implementation Plan](<docs/05 — Implementation Plan/05 — Kế hoạch 4 tuần — Backend trước, frontend sau.md>). Vì vậy các luồng nghiệp vụ P0 chưa được triển khai.

## Chạy dịch vụ nền

Yêu cầu Docker Engine và Docker Compose v2. Cấu hình `APP_KEY` trong `.env` trước khi chạy; không commit `.env`.

```sh
test -f .env || cp backend/.env.example .env
docker compose up --build -d
docker compose exec app php artisan migrate
```

API shell hiện trả trạng thái tại <http://localhost:8080/>. Vue SPA chưa được scaffold nên các trang người dùng chưa khả dụng.

## Dịch vụ Compose

| Service | Vai trò |
| --- | --- |
| `web` | Nginx, cổng HTTP duy nhất mở ra host |
| `app` | Laravel PHP-FPM |
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
frontend/                   # Vue 3 SPA (sẽ scaffold theo Implementation Plan)
docs/                       # Yêu cầu, thiết kế và kế hoạch PETTEL
compose.yaml                # Docker Compose
```

`backend/public/index.php` là entry point của Laravel và vẫn cần cho API. Giao diện Vue thuộc `frontend/`; không đặt component giao diện Vue trong `backend/resources`.

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
