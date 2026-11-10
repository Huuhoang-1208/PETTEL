# BE-HA06 — Bàn giao từ BE-H06 và nghiệm thu E2E

**Owner:** Hào · **Reviewer theo backlog:** Thịnh · **Bên tích hợp:** Hoàng/BE-H06
**Phạm vi:** vòng đời chăm sóc và settlement payment sandbox, từ booking `CONFIRMED` tới kết thúc booking.
**Trạng thái cập nhật sau PR #34:** code BE-HA06 đã tích hợp vào `main`. Merge commit `457a7e1aea75be3a2ba301ca95c6c860cf7bfefb` và PR head `419d662c1f14b56d74efb8a359d506a30a262948` có cùng tree; workspace sạch tại merge commit. Người dùng xác nhận CI PR #34 thành công: [trang Checks](https://github.com/Huuhoang-1208/PETTEL/pull/34/checks). Hoàng, Thịnh và Hào đã đồng ý nghiệm thu trên commit tích hợp theo xác nhận của người dùng ngày 10/11/2026. Actions run ID riêng chưa được chép. Local 441 tests / 5.122 assertions là kết quả lịch sử trên SHA `f28834b`, không gán cho `457a7e1`.

## 1. Nguồn hợp đồng cần tuân thủ

- [Backlog BE-HA06](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>): hoàn thiện `CONFIRMED → IN_PROGRESS → AWAITING_CONFIRMATION`, owner xác nhận, release sandbox, tích hợp void/refund, lưu lịch sử và test lặp/sai actor/release lỗi/retry.
- [P0 Decision Contract §4](<../02 — Domain Documentation/05 — P0 Decision Contract.md>): caregiver báo hoàn tất; owner xác nhận; chỉ release thành công mới chuyển `COMPLETED`; nhắc sau 24 giờ, đưa vào admin queue sau 72 giờ; không tự complete/release theo thời gian; report `IN_REVIEW` giữ booking chờ xử lý; admin action phải có actor/lý do/audit; retry release cùng key ở mốc 1/5/15 phút và sau ba lần lỗi chuyển admin.
- [M06 Payment Sandbox](<../03 — Modules Documentation/M06 — Payment Sandbox.md>) và [Payment Sandbox Integration](<../08 — Architecture & Technical Design/07 — Payment Sandbox Integration.md>): payment chỉ mô phỏng, không thu dữ liệu/thanh toán thật; không phát sinh release hai lần.
- [BE-HA05 contract](<BE-HA05 — Contract payment sandbox và callback.md>): giữ tương thích với gateway, idempotency, event và lock order payment đã chốt.
- [BE-H06 Task 3 report](<BE-H06 — Task 3 integration and E2E verification report.md>): đã cập nhật route/action, E2E lifecycle và giới hạn runtime; kết quả full pipeline được ghi theo lần chạy hiện tại.

Nếu API Contracts, BE-HA05, P0 hoặc code hiện tại có mâu thuẫn, ghi bất đồng và xin Hoàng chốt trước khi coi đó là contract; không thay đổi hành vi payment/cancel đã có chỉ để làm E2E pass.

## 2. Việc cần triển khai

- [x] Caregiver transition `CONFIRMED → IN_PROGRESS → AWAITING_CONFIRMATION` được gắn booking; chỉ caregiver thuộc booking được chuyển trạng thái.
- [x] Owner xác nhận completion có kiểm tra actor, quyền sở hữu và trạng thái booking.
- [x] Sandbox release đi qua service/outbox; chỉ release thành công mới ghi payment `RELEASED`, booking `COMPLETED`, status event và audit.
- [x] Confirm/release có idempotency; các test release/replay kiểm tra không tạo operation hoặc payment event trùng.
- [x] Release lỗi giữ booking chờ, operation retry dùng key ổn định; E2E/test kiểm tra retry và admin queue. Runtime gần nhất chỉ chứng minh release thành công lần đầu, chưa mô phỏng gateway lỗi trên runtime.
- [x] Thực hiện reminder một lần sau 24 giờ và đưa vào admin queue sau 72 giờ; không tự complete hoặc release theo timeout. Đã kiểm tra bằng `BookingCompletionFollowupTest::test_reminder_is_sent_once_after_24_hours` và `::test_booking_is_queued_once_after_72_hours_without_changing_booking_or_payment`.
- [x] Completion/release bị chặn khi booking report đang `IN_REVIEW`; test tích hợp report decision xác nhận state guard.
- [x] Admin completion/refund dùng domain action riêng, có reason/actor/audit và không sửa booking trực tiếp từ controller/Filament.
- [x] Cancellation/void/refund giữ theo contract; payment events và idempotency được kiểm tra trong booking/payment tests.
- [x] Routes, API Contracts, OpenAPI và hướng dẫn vận hành đã được cập nhật cho completion/report operations.

## 3. Checklist E2E của BE-HA06

Chạy trên MySQL test cô lập với migrations/seed hiện tại và sandbox deterministic. E2E chính phải gọi các endpoint/job thật của source trên cùng branch/commit, không gọi action PHP trực tiếp thay cho API. Có thể chuẩn bị fixture cho user, pet, listing/slot; tạo booking và authorize qua API theo contract đang có, sau đó đi qua phần lifecycle mới.

- [x] **Luồng thành công:** `BookingLifecycleEndToEndTest::test_booking_reaches_completed_after_release_retry` đi từ booking tới `COMPLETED` qua care lifecycle và sandbox release.
- [x] **Quyền/state:** booking lifecycle/completion tests kiểm tra sai actor, sai owner/caregiver và state không hợp lệ; request bị từ chối không làm đổi booking/payment.
- [x] **Replay/race:** release retry/idempotency tests xác nhận operation/payment event không bị nhân đôi; acceptance/callback concurrency có test riêng.
- [x] **Release failure:** E2E release failure/retry giữ booking `AWAITING_CONFIRMATION` tới khi release thành công; runtime lần gần nhất release thành công ngay lần đầu.
- [x] **Owner không phản hồi:** reminder chạy một lần sau 24 giờ; booking được đưa admin queue sau 72 giờ; scheduler không tự release/complete. `BookingCompletionFollowupTest` xác nhận idempotency và booking/payment giữ nguyên ở mốc 72 giờ.
- [x] **Admin:** admin completion có reason/audit; release lỗi có retry test; report `IN_REVIEW` chặn completion; quyền admin/non-admin được kiểm tra.
- [x] **Void/refund hồi quy:** booking cancellation, payment callback race và report refund tests kiểm tra idempotency/state cuối.
- [x] **Log/an toàn:** request/schedule correlation và không ghi private input được kiểm tra trong `QueueContextPropagationTest`; runtime scheduler ghi `schedule_run_id`.

### Bằng chứng phải ghi khi nghiệm thu

- [x] Branch/SHA tích hợp và trạng thái worktree được ghi ở đầu handoff; pipeline local lịch sử được phân biệt với PR CI.
- [x] Test method, command, environment và số test/assertions đã ghi trong Task 3 report; PR Checks được liên kết ở đầu handoff.
- [x] Expected/actual cho lifecycle, authorization, release retry, 24/72h, admin decision, refund và runtime release được ghi ở trên/Task 3 report.
- [x] CI PR #34 được người dùng xác nhận pass; migration/seed, worker/scheduler command và runtime result được ghi. Run ID/URL và SHA check cụ thể vẫn cần chép từ Checks.
- [x] Known gaps/giới hạn runtime được ghi ở trên; nhóm đã xác nhận nghiệm thu, gồm reviewer Thịnh.

**Hoàn thành BE-HA06** khi các transition, release/refund phù hợp P0, E2E task trên branch của Hào đạt và có handoff đủ bằng chứng. Hào phụ trách đóng E2E của task. Hoàng/BE-H06 sau đó tích hợp commit và chạy lại workflow 3 trên commit chung; task pass riêng không tự mở Gate BE.

## 4. Trạng thái hiện có từ BE-H06

- Branch tích hợp hiện tại `main`, merge commit `457a7e1aea75be3a2ba301ca95c6c860cf7bfefb`; PR head `419d662c1f14b56d74efb8a359d506a30a262948`. PR #34 đã merge và workspace sạch.
- `BookingLifecycleEndToEndTest::test_booking_reaches_completed_after_release_retry` hiện kiểm tra luồng booking tới `COMPLETED` sau release/retry. `BookingReportDecisionTest` kiểm tra admin COMPLETE/refund, retry, idempotency và bảo vệ state.
- `BookingCompletionFollowupTest` đạt 7 tests / 66 assertions. `test_admin_completion_uses_one_release_operation_and_waits_for_release_success` xác nhận lỗi release chưa đặt `resolved_at`; retry thành công mới đặt `resolved_at`, đồng thời booking chuyển `COMPLETED`. Admin completion được kiểm tra trên booking không cần report; test riêng xác nhận report `IN_REVIEW` sẽ chặn completion.
- Runtime sandbox gần nhất chứng minh outbox xử lý một release thật: booking `1` đi qua API workflow tới `AWAITING_CONFIRMATION`; owner confirmation tạo release operation `1` ở `PENDING`; scheduler xử lý operation lúc `2026-11-10 08:27:00 UTC` với `schedule_run_id=2407c88e-4abd-485a-8032-7739deb0b42e`, exit code `0`. Operation hoàn tất ở attempt `1`, booking thành `COMPLETED`, payment attempt thành `RELEASED`, và payment event `RELEASED` được ghi. Request được thực hiện qua Laravel HTTP Kernel trong container; không chèn operation trực tiếp vào DB.
- Giới hạn runtime: lần này gateway thành công ngay, nên lỗi/retry được chứng minh bằng E2E test chứ chưa mô phỏng trên runtime; `queue:failed` rỗng và không có UUID thật để chạy `queue:retry`.
- Queue context test đạt 3 tests / 31 assertions trong lần chạy đã ghi. Full pipeline local gần nhất được ghi nhận đạt Pint 234 files, PHPStan 164 files, migration/seed và 441 tests / 5.122 assertions trên SHA cũ `f28834b`; không gán kết quả đó cho `457a7e1`.
- Người dùng xác nhận CI PR #34 thành công; xem [PR Checks](https://github.com/Huuhoang-1208/PETTEL/pull/34/checks). PR head và merge commit có cùng tree. Reminder 24h/72h, admin queue và admin completion có test. Hoàng, Thịnh, Hào đã đồng ý nghiệm thu trên `457a7e1` theo xác nhận người dùng; Actions run ID riêng chưa được chép.

### Tài liệu liên quan

- [Task 3 integration/E2E report](<BE-H06 — Task 3 integration and E2E verification report.md>)
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>)
- [BE-HA05 payment contract](<BE-HA05 — Contract payment sandbox và callback.md>)
- [M06 Payment Sandbox](<../03 — Modules Documentation/M06 — Payment Sandbox.md>)
- [M07 Messaging & Care Updates](<../03 — Modules Documentation/M07 — Messaging & Care Updates.md>)
