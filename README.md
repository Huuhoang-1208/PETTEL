# PETTEL

**PETTEL kết nối người nuôi thú cưng với người chăm tại Đà Nẵng.** Người dùng có thể tìm dịch vụ, xem hồ sơ người chăm, trao đổi nhu cầu và quản lý lịch chăm sóc trong cùng một nền tảng.

## Dự án giải quyết điều gì?

Khi cần gửi thú cưng, chủ nuôi cần tìm được người chăm phù hợp, biết trước chi phí và thống nhất lịch rõ ràng. PETTEL cung cấp luồng quản lý xuyên suốt từ hồ sơ thú cưng, tìm dịch vụ, đặt lịch đến trao đổi trong thời gian chăm sóc.

## Chức năng chính

- **Chủ nuôi:** tạo hồ sơ thú cưng, tìm dịch vụ theo khu vực và nhu cầu, xem thông tin người chăm, đặt lịch, nhắn tin, theo dõi cập nhật chăm sóc, đánh giá và gửi báo cáo.
- **Người chăm:** tạo hồ sơ và dịch vụ, thiết lập lịch khả dụng, tiếp nhận hoặc từ chối yêu cầu, cập nhật tình hình chăm sóc và trao đổi với chủ nuôi.
- **Quản trị viên:** xem xét dịch vụ, xử lý báo cáo và quản lý hoạt động trên nền tảng.
- **Tích hợp:** đăng nhập Google, API token với quyền truy cập, tài liệu API, hàng đợi tác vụ và công cụ theo dõi ứng dụng.

Các luồng hiện tập trung vào dịch vụ lưu trú qua đêm và chăm sóc ban ngày. Thanh toán sandbox phục vụ trình diễn quy trình, không thu hay chuyển tiền thật. Hồ sơ, lượt đánh giá và dữ liệu sitter mẫu trong môi trường demo không đại diện cho giao dịch thực tế. Video trực tiếp và cuộc gọi trong bản demo là mô phỏng.

## Công nghệ

| Phần                     | Công nghệ                             |
| ------------------------ | ------------------------------------- |
| Giao diện                | Next.js App Router, React, TypeScript |
| API và nghiệp vụ         | Laravel 13, PHP 8.3, Sanctum          |
| Cơ sở dữ liệu            | MySQL 8.4                             |
| Cache và hàng đợi        | Redis 7                               |
| Quản trị và tài liệu API | Filament, Scramble / OpenAPI          |
| Chạy ứng dụng            | Docker Compose                        |

## Chạy bản demo local

Cần Docker Desktop hoặc Docker Engine cùng Docker Compose. Thiết lập `.env` và `APP_KEY` theo [thiết kế Docker và triển khai](<docs/08 — Architecture & Technical Design/09 — Docker Topology & Deployment Design.md>), sau đó chạy các lệnh tại thư mục gốc:

```powershell
docker compose up -d --build
docker compose exec -T backend php artisan migrate --force
docker compose exec -T backend php artisan db:seed --force
```

Mở giao diện tại [http://localhost:3000](http://localhost:3000); API chạy tại [http://localhost:8080](http://localhost:8080). Tài khoản demo và hướng dẫn khởi động chi tiết có trong [hướng dẫn frontend](frontend/README.md) và phần [tài khoản, dữ liệu mẫu](#tài-khoản-demo).

### Tài khoản demo

| Tài khoản                | Vai trò                | Mật khẩu local |
| ------------------------ | ---------------------- | -------------- |
| `owner@example.test`     | Chủ nuôi               | `DemoOnly123!` |
| `caregiver@example.test` | Chủ nuôi và người chăm | `DemoOnly123!` |
| `admin@example.test`     | Quản trị viên          | `DemoOnly123!` |

Chỉ dùng các thông tin này trong môi trường local/test; không dùng trên hệ thống public.

## Cấu trúc mã nguồn

- `backend/`: Laravel API, nghiệp vụ, migrations, seeders và kiểm thử PHP.
- `frontend/`: ứng dụng Next.js, component, trang và kiểm thử frontend.
- `docs/`: thiết kế sản phẩm, mô hình nghiệp vụ, kiến trúc và kế hoạch triển khai.
- `compose.yaml`: các dịch vụ để chạy ứng dụng bằng Docker.

## Tài liệu dự án

- [Hướng dẫn frontend](frontend/README.md)
- [Tổng quan tích hợp frontend và backend](docs/frontend-backend-integration.md)
- [Tài liệu dự án và ERD](docs/00%20%E2%80%94%20PETTEL%20Documentation%20Index.md)
- [Checklist và kịch bản quay demo](docs/submission-checklist.md)
- [Báo cáo kiểm chứng local](docs/submission-verification.md)

## Nhóm phát triển

Dự án được thực hiện trong học phần **Advanced Web — CSB35050, VNUK**. Thông tin issue, pull request và đóng góp của từng thành viên được ghi trong lịch sử Git và các tài liệu triển khai của nhóm.
