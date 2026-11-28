# Kiểm chứng bản hoàn thiện PETTEL

Ngày kiểm tra: **21/11/2026**, múi giờ Asia/Saigon. Kết quả áp dụng cho working tree dựa trên commit `ab50521`, bao gồm các thay đổi chưa commit của đợt hoàn thiện này. Đây là kiểm tra local, không phải kết quả GitHub CI hoặc bằng chứng triển khai production.

## Các thay đổi đã hoàn thành

| Hạng mục | Kết quả |
| --- | --- |
| Backend tests | PHPUnit chạy thêm `tests/Integration`. Test migration nhắm đúng migration suspension source, không phụ thuộc migration mới nhất và không seed ảnh catalog không liên quan. |
| Frontend tests | Thêm Vitest, Testing Library và bước test trong workflow frontend. Có kiểm thử request ghi cần CSRF/idempotency. |
| Ảnh dịch vụ | Khai báo kích thước, lazy loading và async decoding cho ảnh API; ảnh local dùng Next Image có responsive sizes. Ảnh hero đầu tiên được tải sớm. Media cần cookie tiếp tục tải trực tiếp từ trình duyệt. |
| Hồ sơ nộp | README có phạm vi demo, ví dụ Issue–PR, liên kết báo cáo và checklist/kịch bản quay. |

## Kết quả kiểm tra tự động

| Kiểm tra | Kết quả quan sát |
| --- | --- |
| PHPUnit Unit + Feature + Integration | **467 tests, 5.283 assertions — PASS** |
| Backend line coverage | **86,66% (6.488/7.487 dòng)** |
| Laravel Pint | PASS, 279 files |
| PHPStan | No errors, 203 files |
| Frontend Vitest | Bổ sung kiểm tra ngày 27/11/2026: 1 test file, 3 tests — PASS. |
| TypeScript | `tsc --noEmit` thành công |
| ESLint `src tests` | 0 errors, 1 warning có sẵn tại `auth-form.tsx:56` về điều hướng `window.location.href` trong luồng OAuth |
| Production frontend | Docker build thành công; container `web` đã được cập nhật |

Backend chạy bằng `docker compose -p pettel-test -f compose.test.yaml run --build --rm test`, dùng database test riêng. Log local: `docs/evidence/submission-backend-check.log`. Log build: `docs/evidence/submission-frontend-build.log`. Thư mục `docs/evidence/` bị Git ignore; các số liệu chính được lưu trong báo cáo này để đi cùng mã nguồn.

Trước khi gỡ hai mục theo yêu cầu, frontend đã chạy `npm test`, `npm run typecheck`, `npx eslint src tests`; production image được build bằng `docker compose up -d --build --no-deps web`. Ba test request frontend còn lại dùng jsdom và API mock, chưa phải bộ E2E đầy đủ.

## Bằng chứng còn cần nhóm thực hiện

- Video demo thực tế chưa quay; dùng [kịch bản 9 phút 40 giây](submission-checklist.md).
- Google OAuth thật chưa được xác minh. Slack đã nhận cảnh báo slow-query và server-resource trong đợt kiểm tra bổ sung ngày 27/11/2026; xem phần Task 5 bên dưới.
- Bổ sung link CI của commit cuối sau khi nhóm commit/push; kiểm tra quyền xem repo, video và các liên kết Issue–PR.
- Hoàn tất Self-Report của từng thành viên cho cả 5 sprint theo brief.

## Bổ sung kiểm chứng Task 5 — 27–28/11/2026

| Hạng mục | Kết quả và bằng chứng |
| --- | --- |
| 5(b) API errors/logging | `ApiErrorHandlingTest`: 6 tests, 38 assertions — PASS (kết quả terminal đã lưu trong lịch sử làm việc). `error.tsx` và `not-found.tsx` được triển khai; API errors dùng envelope thống nhất và `request_id`. Ngày 28/11/2026, exception `RuntimeException` được kích hoạt qua route chẩn đoán local và Slack nhận cảnh báo `ERROR` trong `#pettel-alerts`; `request_id` và `origin_request_id` khớp nhau. Route chẩn đoán đã được xóa khỏi source. Ảnh: [exception-slack-1.png](screenshots/exception-slack-1.png), [exception-slack-2.png](screenshots/exception-slack-2.png), [exception-slack-3.png](screenshots/exception-slack-3.png). |
| 5(c) Slow-query alert | `SlowQueryAlertTest`: 4 tests, 9 assertions — PASS. Slack nhận `monitoring.slow_query_detected`, duration 1206.84 ms với threshold 1000 ms. Ảnh: [task-5c-slow-query-alert.png](screenshots/task-5c-slow-query-alert.png). |
| 5(d) Server-resource alert | `ServerResourceMonitoringTest` và `AdminDashboardAuthorizationTest`: 6 tests, 14 assertions — PASS. Monitoring bật, scheduler chạy mỗi phút. Slack nhận `monitoring.server_resource_threshold_exceeded` cho `disk_percent` (10.77%, ngưỡng thử 0%). Ngưỡng đã khôi phục về 90%. Ảnh: [task-5d-server-resource-alert.png](screenshots/task-5d-server-resource-alert.png). |
| Telescope/Pulse access | `TelescopeAccessTest` và `TelescopeRedactionTest`: 3 tests, 17 assertions — PASS. `AdminDashboardAuthorizationTest` cũng kiểm tra quyền `/pulse`. |
| Frontend | Vitest: 3 tests — PASS; `npm run typecheck` — PASS; `npm run lint` — 0 errors, 1 warning OAuth có sẵn; `npm run format:check` — PASS sau khi chạy `npm run format`. |
| PHP format | Laravel Pint `--test` đã PASS trên các file PHP liên quan theo kết quả terminal trong lịch sử làm việc. |

Đợt kiểm tra gộp cuối ngày 27/11/2026 chạy sáu file backend ở bảng trên cùng `AdminDashboardAuthorizationTest`: **19 tests, 78 assertions — PASS**, PHP 8.3.37. Laravel Pint `--test` trên 14 file PHP liên quan: **PASS**. Frontend sau format: Vitest **3/3 PASS**, typecheck **PASS**, `format:check` **PASS**, ESLint **0 errors** và còn 1 warning điều hướng OAuth có sẵn tại `auth-form.tsx:56`.

### Trích log scheduler

Các mốc dưới đây là timestamp do container scheduler in ra. Lệnh đã chạy thành công ở hai phút liên tiếp:

```text
2026-11-27 09:50:00 Running ['artisan' monitoring:check-server-resources] 338.50ms DONE
2026-11-27 09:51:00 Running ['artisan' monitoring:check-server-resources] 418.67ms DONE
```

Log gốc được lưu tại [`task-5-scheduler-evidence.log`](task-5-scheduler-evidence.log). Ảnh Slack chứng minh 5(c)/5(d) được lưu riêng trong `docs/screenshots/`; webhook không nằm trong source hoặc hồ sơ.

Giảng viên đã đồng ý dùng Next.js/React theo xác nhận của nhóm. Báo cáo này ghi kết quả kiểm chứng kỹ thuật; điểm cuối cùng do giảng viên đánh giá theo rubric và hồ sơ nộp thực tế.
