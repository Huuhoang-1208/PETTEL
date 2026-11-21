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
| Frontend Vitest | Còn 3 test request ghi; bộ test lọc đã được gỡ theo yêu cầu. Chưa chạy lại sau thay đổi này. |
| TypeScript | `tsc --noEmit` thành công |
| ESLint `src tests` | 0 errors, 1 warning có sẵn tại `auth-form.tsx:54` về điều hướng `window.location.href` trong luồng OAuth |
| Production frontend | Docker build thành công; container `web` đã được cập nhật |

Backend chạy bằng `docker compose -p pettel-test -f compose.test.yaml run --build --rm test`, dùng database test riêng. Log local: `docs/evidence/submission-backend-check.log`. Log build: `docs/evidence/submission-frontend-build.log`. Thư mục `docs/evidence/` bị Git ignore; các số liệu chính được lưu trong báo cáo này để đi cùng mã nguồn.

Trước khi gỡ hai mục theo yêu cầu, frontend đã chạy `npm test`, `npm run typecheck`, `npx eslint src tests`; production image được build bằng `docker compose up -d --build --no-deps web`. Ba test request frontend còn lại dùng jsdom và API mock, chưa phải bộ E2E đầy đủ.

## Bằng chứng còn cần nhóm thực hiện

- Video demo thực tế chưa quay; dùng [kịch bản 9 phút 40 giây](submission-checklist.md).
- Chưa xác minh đăng nhập Google thật hoặc Slack thực nhận cảnh báo trong đợt kiểm tra này. Test mock và cấu hình không thay thế hai bằng chứng này.
- Bổ sung link CI của commit cuối sau khi nhóm commit/push; kiểm tra quyền xem repo, video và các liên kết Issue–PR.
- Hoàn tất Self-Report của từng thành viên cho cả 5 sprint theo brief.

Giảng viên đã đồng ý dùng Next.js/React theo xác nhận của nhóm. Báo cáo này ghi kết quả kiểm chứng kỹ thuật; điểm cuối cùng do giảng viên đánh giá theo rubric và hồ sơ nộp thực tế.
