# BE-HA06 — Bàn giao từ BE-H06 và nghiệm thu E2E

**Owner:** Hào · **Reviewer theo backlog:** Thịnh · **Bên tích hợp:** Hoàng/BE-H06
**Phạm vi:** vòng đời chăm sóc và settlement payment sandbox, từ booking `CONFIRMED` tới kết thúc booking.
**Trạng thái tại bàn giao:** completion/release và report booking decision đã có trong worktree tích hợp `be-ha06`; full pipeline local đã pass trên worktree dirty (422 tests / 4.945 assertions), nhưng chưa có commit/SHA sạch hoặc official CI. Các bằng chứng hiện có và phần chưa xác minh được ghi tại mục 4 và BE-H06 Task 3 report.

## 1. Nguồn hợp đồng cần tuân thủ

- [Backlog BE-HA06](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>): hoàn thiện `CONFIRMED → IN_PROGRESS → AWAITING_CONFIRMATION`, owner xác nhận, release sandbox, tích hợp void/refund, lưu lịch sử và test lặp/sai actor/release lỗi/retry.
- [P0 Decision Contract §4](<../02 — Domain Documentation/05 — P0 Decision Contract.md>): caregiver báo hoàn tất; owner xác nhận; chỉ release thành công mới chuyển `COMPLETED`; nhắc sau 24 giờ, đưa vào admin queue sau 72 giờ; không tự complete/release theo thời gian; report `IN_REVIEW` giữ booking chờ xử lý; admin action phải có actor/lý do/audit; retry release cùng key ở mốc 1/5/15 phút và sau ba lần lỗi chuyển admin.
- [M06 Payment Sandbox](<../03 — Modules Documentation/M06 — Payment Sandbox.md>) và [Payment Sandbox Integration](<../08 — Architecture & Technical Design/07 — Payment Sandbox Integration.md>): payment chỉ mô phỏng, không thu dữ liệu/thanh toán thật; không phát sinh release hai lần.
- [BE-HA05 contract](<BE-HA05 — Contract payment sandbox và callback.md>): giữ tương thích với gateway, idempotency, event và lock order payment đã chốt.
- [BE-H06 Task 3 report](<BE-H06 — Task 3 integration and E2E verification report.md>): đã cập nhật route/action, E2E lifecycle và giới hạn runtime; kết quả full pipeline được ghi theo lần chạy hiện tại.

Nếu API Contracts, BE-HA05, P0 hoặc code hiện tại có mâu thuẫn, ghi bất đồng và xin Hoàng chốt trước khi coi đó là contract; không thay đổi hành vi payment/cancel đã có chỉ để làm E2E pass.

## 2. Việc cần triển khai

- [ ] Xác định/triển khai transition caregiver `CONFIRMED → IN_PROGRESS → AWAITING_CONFIRMATION`; quyền caregiver phải gắn với booking. Chỉ caregiver thuộc booking được đánh dấu đang chăm/đã hoàn tất.
- [ ] Cung cấp thao tác owner xác nhận hoàn tất. Xác thực actor là owner của booking và trạng thái hiện tại cho phép transition.
- [ ] Gọi sandbox release qua service/gateway dùng chung. Chỉ khi release thành công mới ghi payment `RELEASED`, booking `COMPLETED`, status event và audit tương ứng; lưu kết quả nguyên tử, không giữ DB transaction lock trong khi gọi mạng.
- [ ] Làm thao tác xác nhận/release idempotent. Request lặp cùng operation không tạo release, payment event, booking event hoặc audit trùng; replay trả kết quả được định nghĩa trong API contract.
- [ ] Release lỗi giữ booking ở `AWAITING_CONFIRMATION`; ghi nhận lỗi an toàn, enqueue retry với operation/idempotency key ổn định và lịch 1/5/15 phút. Sau ba lần lỗi, dừng retry tự động và đưa vào hàng xử lý admin; admin retry phải dùng cùng operation key.
- [ ] Thực hiện reminder một lần sau 24 giờ và đưa vào admin queue sau 72 giờ; không tự complete hoặc release theo timeout. Ghi nhận job, idempotency, retry và cách vận hành.
- [ ] Chặn completion/admin completion khi có report liên quan đang `IN_REVIEW`; phối hợp BE-T06 để dùng report state sau khi tích hợp. Khi chưa có module report, có thể dùng fixture/seed report cho test nhánh này nhưng phải ghi rõ đó chưa phải E2E tích hợp report.
- [ ] Cung cấp domain/service transition cho admin xác nhận thay owner hoặc giải quyết ngoại lệ theo P0: quyết định có lý do, actor, moderation/action audit, booking event và payment event nhất quán. Phối hợp BE-T06 để panel/Policy gọi service này; không làm trùng Filament UI và không cho admin sửa trực tiếp DB hoặc bypass release/refund.
- [ ] Hủy/void/refund giữ theo contract hiện tại; không cho cancellation flow thông thường đảo `RELEASED` hoặc trạng thái kết thúc.
- [ ] Cập nhật route request/response/error, API Contracts, OpenAPI và hướng dẫn vận hành khi thêm endpoint/job.

## 3. Checklist E2E của BE-HA06

Chạy trên MySQL test cô lập với migrations/seed hiện tại và sandbox deterministic. E2E chính phải gọi các endpoint/job thật của source trên cùng branch/commit, không gọi action PHP trực tiếp thay cho API. Có thể chuẩn bị fixture cho user, pet, listing/slot; tạo booking và authorize qua API theo contract đang có, sau đó đi qua phần lifecycle mới.

- [ ] **Luồng thành công:** owner tạo booking → caregiver accept → owner sandbox payment success → booking `CONFIRMED` → caregiver chuyển `IN_PROGRESS` → gửi care update (API BE-T05 hiện có) → caregiver báo xong (`AWAITING_CONFIRMATION`) → owner xác nhận → sandbox `RELEASED` và booking `COMPLETED`.
- [ ] **Quyền/state:** owner khác, caregiver khác, guest/non-active user và sai trạng thái bị từ chối theo API contract; không đổi booking/payment/event khi bị từ chối.
- [ ] **Replay/race:** gửi xác nhận hoàn tất lặp và request đồng thời; chỉ một release hiệu lực, tổng `RELEASED` mỗi booking không vượt quá một, event/audit không nhân đôi.
- [ ] **Release failure:** sandbox fail/timeout; booking giữ `AWAITING_CONFIRMATION`, operation bền vững; retry 1/5/15 dùng cùng operation key; chỉ chuyển `COMPLETED` sau khi release thành công; sau ba lần thất bại có thể thấy trong hàng admin.
- [ ] **Owner không phản hồi:** reminder chạy một lần sau 24 giờ; booking được đưa admin queue sau 72 giờ; scheduler không tự release/complete.
- [ ] **Admin:** admin completion có lý do và audit; admin retry release lỗi an toàn; admin không thể bỏ qua `IN_REVIEW` report hoặc tạo release trùng. Xác minh quyền admin/non-admin.
- [ ] **Void/refund hồi quy:** test các nhánh cancel/void/refund có liên quan tiếp tục đạt; không làm sống lại booking cancelled, không release/refund lặp.
- [ ] **Log/an toàn:** log có request/operation ID để truy dấu, không chứa secret, token hoặc dữ liệu nhạy cảm không cần thiết.

### Bằng chứng phải ghi khi nghiệm thu

- [ ] Branch, SHA được test và SHA chứa implementation; ghi rõ có hay không thay đổi chưa commit.
- [ ] Tên kịch bản/test method cụ thể cho từng hàng checklist; command, môi trường DB/sandbox, exit code và số test/assertions.
- [ ] Bảng expected/actual cho success, quyền/state, replay/race, release failure/retry, timeout/admin và void/refund.
- [ ] Link CI chính thức hoặc ghi rõ local-only; migration/seed, worker/scheduler command và kết quả vận hành nếu chạy.
- [ ] Known gaps, contract decision còn chờ, owner quyết định và reviewer Thịnh xác nhận.

**Hoàn thành BE-HA06** khi các transition, release/refund phù hợp P0, E2E task trên branch của Hào đạt và có handoff đủ bằng chứng. Hào phụ trách đóng E2E của task. Hoàng/BE-H06 sau đó tích hợp commit và chạy lại workflow 3 trên commit chung; task pass riêng không tự mở Gate BE.

## 4. Trạng thái hiện có từ BE-H06

- Branch hiện tại `be-ha06`, HEAD `b0efd9cf614ddaf01051761cce36a37d79b4c684`; worktree dirty và chưa có SHA sạch bàn giao.
- `BookingLifecycleEndToEndTest::test_booking_reaches_completed_after_release_retry` hiện kiểm tra luồng booking tới `COMPLETED` sau release/retry. `BookingReportDecisionTest` kiểm tra admin COMPLETE/refund, retry, idempotency và bảo vệ state.
- Lần runtime gần nhất: worker/scheduler image build và chạy; lịch expiry/cancellation/release chạy với `schedule_run_id`, nhưng không có operation đến hạn (`selected: 0`, `processed: 0`). Đây không phải bằng chứng xử lý một booking operation thật.
- Queue context test đạt 3 tests / 31 assertions; Pint hai file vận hành đạt. Full pipeline local đạt: Pint 220 files, PHPStan 153 files không lỗi, migration/seed đạt và 422 tests / 4.945 assertions; xem Task 3 report để biết command và giới hạn.
- Các acceptance items về reminder 24h/72h/admin queue và sign-off trên commit chung cần được xác nhận riêng; không đánh dấu Pass chỉ dựa trên booking E2E.

### Tài liệu liên quan

- [Task 3 integration/E2E report](<BE-H06 — Task 3 integration and E2E verification report.md>)
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>)
- [BE-HA05 payment contract](<BE-HA05 — Contract payment sandbox và callback.md>)
- [M06 Payment Sandbox](<../03 — Modules Documentation/M06 — Payment Sandbox.md>)
- [M07 Messaging & Care Updates](<../03 — Modules Documentation/M07 — Messaging & Care Updates.md>)
