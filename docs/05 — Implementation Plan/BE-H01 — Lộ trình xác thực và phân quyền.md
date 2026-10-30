# BE-H01 — Lộ trình xác thực, Identity và phân quyền

**Nguồn:** [GitHub issue #1](https://github.com/Huuhoang-1208/PETTEL/issues/1) · **Owner:** Hoàng · **Reviewer:** Hào · **Mốc kế hoạch:** N1–N3 · **Timebox:** 10 người-giờ, gồm ít nhất 1 giờ review.

Tài liệu này là lộ trình thực hiện issue, **không phải báo cáo đã triển khai**. Ngày N1–N3 theo [kế hoạch 4 tuần](<05 — Kế hoạch 4 tuần — Backend trước, frontend sau.md>); nếu lịch khởi động đổi, dịch mốc cùng toàn dự án. Schema M01 lấy từ [Database Design — PETTEL P0](<../08 — Architecture & Technical Design/database design.md>) và các quyền P0 lấy từ [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>). Phạm vi BE-H01 là xác thực, schema Identity và hợp đồng quyền nền. Policy theo tài nguyên và kiểm thử truy cập dữ liệu người khác được hoàn thiện trong BE-T02, BE-H02, BE-HA02 và các issue module tương ứng.

**Cập nhật 27/10/2026:** Breeze API + Sanctum, schema Identity, đăng ký OWNER, tự bật CAREGIVER có audit, middleware kiểm tra role và lệnh CLI cấp/thu hồi ADMIN đã được triển khai. 15 feature test đạt. MySQL 8.4 migration up/down/up đạt; kiểm tra HTTP tạm xác nhận `/sanctum/csrf-cookie` và đăng ký có CSRF trả 204, request thiếu CSRF trả 419. Chưa có reviewer sign-off; policy riêng cho booking/report và xử lý đầy đủ `DELETION_PENDING` thuộc issue downstream.

## 1. Quyết định đã chốt và phối hợp đầu vào

| Nội dung | Quyết định/việc phối hợp |
| --- | --- | --- |
| Schema Identity | Database Design đã chọn giữ migration Laravel nền, thêm `users.status` mặc định `ACTIVE`, `users.deletion_requested_at` nullable và bảng `user_roles`; BE-T01 điều phối thứ tự migration, Hoàng triển khai phần M01. |
| Role và quyền | Đăng ký công khai chỉ nhận `OWNER`. User đã đăng nhập tự bật `CAREGIVER` bằng thao tác riêng có audit; chỉ listing cần admin duyệt trước `ACTIVE`. `ADMIN` cấp/thu hồi ngoài luồng tự đăng ký với audit; seed demo không phải quy trình cấp quyền thật. |
| Trạng thái tài khoản | `ACTIVE`, `SUSPENDED`, `DELETION_PENDING`, `DEACTIVATED`. Policy/middleware nền phải phân biệt trạng thái; `DELETION_PENDING` chỉ được xử lý booking/report còn mở, `DEACTIVATED` không đăng nhập. Luồng yêu cầu xóa, ẩn danh và purge phối hợp issue account/privacy sau, không tự rút gọn thành xóa cứng. |
| Origin và session SPA | Demo ưu tiên frontend/API cùng origin. Hào/BE-HA01 xác nhận origin thực tế; nếu tách subdomain, cấu hình stateful domains, session domain, CORS credentials và CSRF trước khi tích hợp. |
| Email | Chỉ bật email verification nếu có luồng gửi mail demo đáng tin. Password reset cần kênh gửi và kiểm thử cụ thể; nếu chưa có, ghi blocker và không tuyên bố luồng hoàn chỉnh. |
| Package | Hoàng kiểm tra Breeze **API stack** và Sanctum với Laravel 13/PHP 8.3 bằng dependency resolution trước khi cài; lưu `composer.lock`. |

**Schema M01 cần tạo:** giữ `users.id/name/email/password/email_verified_at/remember_token` và các bảng `password_reset_tokens`, `sessions` hiện có; không đổi `password` thành `password_hash`. Migration mới thêm `status` (`ACTIVE` mặc định) và `deletion_requested_at` nullable vào `users`; tạo `user_roles(user_id, role, assigned_at, assigned_by_user_id)` với PK `(user_id, role)`, FK tới user/assigner và index `(role, user_id)`. Giá trị role chỉ gồm `OWNER`, `CAREGIVER`, `ADMIN`. Không thêm role enum đơn trong `users`, không tạo token SPA riêng và không coi `email_verified_at` là email đã xác minh khi chưa bật verification. `sessions.user_id` hiện chỉ có index; không tự thêm FK ngoài thiết kế.

**Ranh giới audit:** Database Design định nghĩa `audit_logs` append only với `actor_user_id`, `action`, `resource_type`, `resource_id`, `reason`, `request_id`, `metadata_json`, `created_at`. BE-H01 cần ghi việc bật/cấp/thu hồi role. Hoàng và BE-T01 thống nhất đưa migration bảng audit dùng chung vào trước các thao tác role (hoặc nhận migration này từ issue hạ tầng trước khi mở endpoint); không tạo bảng audit M01 riêng hoặc ghi log chứa mật khẩu/token/PII.

## 2. Thứ tự thực hiện trong 10 giờ

| Mốc | Ngân sách | Công việc và đầu ra kiểm được |
| --- | ---: | --- |
| N1 — rà baseline và schema | 2 giờ | Đã đối chiếu `User`, auth/session config, bootstrap, routes và migration nền với Database Design M01. Không có passkeys/2FA hoặc dữ liệu user cũ cần backfill. Migration users/roles/audit dùng chung đã được thêm theo contract. |
| N1–N2 — scaffold auth | 3 giờ | Đã cài Breeze API stack + Sanctum; hoàn thiện đăng ký, login, logout, yêu cầu/hoàn tất reset. API riêng tư dùng `auth:sanctum`; kiểm tra `/sanctum/csrf-cookie`, session cookie, CSRF hợp lệ/sai và throttle login/reset. Chưa tạo Vue UI. |
| N2 — schema Identity và quyền nền | 2 giờ | Đã thêm `users.status`/`deletion_requested_at`, `user_roles` và `audit_logs`; đăng ký tạo `OWNER` phía server trong transaction. Endpoint tự bật `CAREGIVER` kiểm tra status, idempotent và có audit; CLI cấp/thu hồi `ADMIN` có lý do/audit. |
| N2–N3 — feature tests và sửa lỗi | 2 giờ | 15 feature test đạt: migration SQLite test, đăng ký/login/logout/reset/throttle, session cookie hết hạn, 401/403, role OWNER mặc định, caregiver tự bật, user thiếu ADMIN bị từ chối, chống tự cấp ADMIN, chặn thu hồi admin cuối cùng, admin provisioning và reset response không tiết lộ email. MySQL 8.4 up/down/up và HTTP CSRF 204/419 được kiểm tra riêng. |
| N3 — review và bàn giao | 1 giờ | Chờ Hào review auth, quyền và migration; cần ghi PR/commit, kết quả review và blocker downstream trước khi đóng issue. |

Nếu origin/session, mail hoặc migration audit dùng chung chưa sẵn sàng, ghi rõ blocker tương ứng; các quyết định schema và role P0 ở trên đã chốt, không mở lại như giả định mới.

## 3. Hợp đồng phải bàn giao

- **Auth:** Vue SPA lấy `/sanctum/csrf-cookie`, gửi cookie và CSRF cho auth/request ghi; API riêng tư dùng `auth:sanctum`. Logout hủy session. SPA không lưu bearer/personal access token.
- **Phân quyền:** public chỉ xem listing ACTIVE; user mới có `OWNER`; user `ACTIVE` có thể tự bật `CAREGIVER` qua endpoint riêng có audit, không trở thành người chăm “đã xác minh”. `ADMIN` không tự đăng ký và chỉ cấp theo quy trình tin cậy có audit. Backend kiểm tra role, trạng thái user và ownership/state ở policy của từng module; ẩn nút trên UI không thay kiểm tra server.
- **Lỗi:** API riêng tư chưa đăng nhập hoặc hết session trả 401; đã đăng nhập nhưng thiếu quyền trả 403; CSRF không hợp lệ trả 419. Với JSON API, đối chiếu khuôn `error: { code, message, details?, request_id }` trong [API Contracts](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>); xác nhận cách chuyển lỗi mặc định của Breeze/Laravel trước khi chốt contract với frontend.
- **Bảo mật:** throttle login/reset; reset response không tiết lộ tài khoản; không log mật khẩu, token, session ID hoặc PII. Cookie Secure/HttpOnly/SameSite phù hợp môi trường, demo public dùng HTTPS. Không bật verification khi mail chưa sẵn sàng.

## 4. Tiêu chí đóng issue và bằng chứng

- [x] Migration M01 chạy trên MySQL 8.4 trống: giữ bảng auth nền, thêm đúng cột `users`, PK/FK/index `user_roles` và audit dùng chung theo Database Design; rollback rồi chạy lại thành công trên database cô lập.
- [x] Breeze/Sanctum xác thực cookie/session + CSRF; logout hủy session; session cookie hết hạn/không hợp lệ bị từ chối. HTTP smoke test: CSRF cookie và request hợp lệ trả 204, request thiếu token trả 419.
- [x] 401, 403 và 419 được kiểm tra; route riêng tư mẫu bảo vệ bằng middleware và quyền server.
- [x] Đăng ký tạo `OWNER` duy nhất phía server; user `ACTIVE` tự bật `CAREGIVER` qua endpoint riêng và audit, gọi lặp không tạo role trùng; payload đăng ký không tự cấp caregiver/admin. Có lệnh CLI cấp/thu hồi admin với audit và lý do; không seed mật khẩu admin cố định trong Git.
- [ ] `SUSPENDED`/`DEACTIVATED` không sử dụng auth/API riêng; `DELETION_PENDING` chỉ được thao tác tối thiểu cho booking/report còn mở theo P0 Decision Contract; module downstream nhận contract trạng thái này.
- [x] Password reset có throttle và phản hồi không tiết lộ email tồn tại; email verification chưa bật do luồng mail chưa được chốt.
- [ ] Hào review; ghi commit/PR, môi trường, lệnh test, expected/actual, kết quả và giới hạn còn lại. Chỉ đánh dấu Done sau review và tích hợp theo quy tắc backlog.

**Bàn giao phụ thuộc:** BE-T02, BE-H02 và BE-HA02 nhận contract quyền ở N2, nghiệm thu sau BE-H01 N3. FE-H01 nhận API auth/session/CSRF đã nghiệm thu ở Gate BE, rồi mới dựng Vue auth UI trong tuần 4. Kiểm thử IDOR của pet/listing, Policy theo booking/message/review/report, Filament admin UI và quyền đọc dữ liệu riêng tư thuộc issue module sau.

## Tài liệu đối chiếu

- [Backlog BE-H01, BE-T01 và các phụ thuộc](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>)
- [M01 — Identity & Roles](<../03 — Modules Documentation/M01 — Identity & Roles.md>)
- [Database Design — PETTEL P0, M01 và thứ tự migration](<../08 — Architecture & Technical Design/database design.md>) và [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>)
- [Authentication & Authorization Design](<../08 — Architecture & Technical Design/05 — Authentication & Authorization Design.md>)
- [FE & BE Structure](<../08 — Architecture & Technical Design/02 — FE & BE Structure.md>) và [Technology & Tools](<../08 — Architecture & Technical Design/10 — Technology & Tools — Responsibilities & Constraints.md>)
- [Actors & Roles](<../01 - Product Document/04 — Actors & Roles.md>) và [Security & Privacy](<../06 — Non-functional Requirements/01 — Security & Privacy.md>)
