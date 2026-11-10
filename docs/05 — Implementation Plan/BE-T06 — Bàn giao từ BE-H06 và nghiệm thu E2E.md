# BE-T06 — Bàn giao từ BE-H06 và nghiệm thu E2E

**Owner:** Thịnh · **Reviewer theo backlog:** Hoàng · **Bên tích hợp:** Hoàng/BE-H06
**Phạm vi:** report/moderation/audit, admin panel và vận hành backend theo backlog BE-T06.
**Trạng thái cập nhật sau PR #34:** report API/admin, booking decision, Filament Report Resource, Pulse và backend của BE-T06 đã tích hợp vào `main`. Merge commit `457a7e1aea75be3a2ba301ca95c6c860cf7bfefb` và PR head `419d662c1f14b56d74efb8a359d506a30a262948` có cùng tree; workspace sạch. Người dùng xác nhận CI PR #34 pass: [trang Checks](https://github.com/Huuhoang-1208/PETTEL/pull/34/checks). Hoàng, Thịnh, Hào đã đồng ý nghiệm thu trên commit tích hợp theo xác nhận của người dùng ngày 10/11/2026. Actions run ID riêng chưa được chép; local 441 tests / 5.122 assertions là kết quả lịch sử trên SHA `f28834b`, không gán cho merge commit.

## 1. Nguồn hợp đồng và hiện trạng

- [Backlog BE-T06](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>): tạo/xem/xử lý report `OPEN → IN_REVIEW → RESOLVED|DISMISSED`; dùng action/policy chung để ẩn listing/review; actor/thời gian/lý do; giới hạn đọc chứng cứ riêng tư; Filament auth/Policy; Pulse admin-only; kiểm tra worker/scheduler, failed jobs và request ID.
- [M09 Reports & Admin](<../03 — Modules Documentation/M09 — Reports & Admin.md>): target hiện được liệt kê là listing, review, message hoặc booking; một target FK; admin xử lý có lý do/audit; ẩn listing phải loại khỏi search và chặn booking mới; report riêng tư không làm lộ evidence; dismiss phải có lý do.
- [P0 Decision Contract §4, §6](<../02 — Domain Documentation/05 — P0 Decision Contract.md>): report `IN_REVIEW` giữ booking `AWAITING_CONFIRMATION`; admin quyết định hoàn tất/release hoặc hủy ngoại lệ/refund; report đang mở và thời hạn retention ảnh hưởng dữ liệu booking/care update.
- [BE-H06 Task 3 report](<BE-H06 — Task 3 integration and E2E verification report.md>): ghi các report/admin routes, booking decisions, test workflow, strict OpenAPI export, Filament/Pulse và runtime worker/scheduler mới nhất.

Nguồn có thể chưa thống nhất về target, khả năng reporter xem trạng thái, duplicate report, reopen, quyền xem message/care update và hành động moderation cho từng target. Ghi từng bất đồng, đưa ra đề xuất, xin Hoàng chốt trước khi biến thành contract hoặc schema/API mới. Không suy luận quyền mở evidence riêng tư chỉ từ quyền admin chung.

## 2. Việc cần triển khai

- [x] Schema report đã được đối chiếu; migration/status/history lưu một target, reason/description và các trạng thái đã chốt.
- [x] Report intake API kiểm tra target, reporter, quyền, validation và không lưu evidence công khai/PII trong log.
- [x] Reporter chỉ xem report của mình; admin queue/detail/history và status transitions có validation/audit.
- [x] Admin queue/decision API và Filament resource gọi domain actions; reason/actor lấy phía server, transition sai bị chặn.
- [x] Moderation listing/review gọi action/lifecycle chung; public visibility, rating và cache invalidation có test.
- [x] Private evidence được giới hạn bằng policy; access có audit, response không lộ URL/object key công khai.
- [x] `moderation_actions`/`audit_logs` ghi decision, target, actor, lý do, request ID và thay đổi trạng thái; tests kiểm tra rollback/không có thay đổi một phần.
- [x] Tích hợp BE-HA06: report `IN_REVIEW` chặn completion/release; admin booking decision tạo release/refund operation thay vì sửa booking/payment trực tiếp.
- [x] Filament panel và Report Resource có access/action tests cho active admin, non-admin, guest và account không active.
- [x] Laravel Pulse dùng storage mặc định và admin-only authorization; không dùng Redis ingest chung với queue.
- [x] Worker/scheduler, request/schedule context và booking outbox runtime đã được kiểm tra. Giới hạn: không có request-driven business job trong source; runtime không có failed job thật để thử `queue:retry`; outbox được scheduler gọi trực tiếp.
- [x] API Contracts, OpenAPI, M09, Compose/README và hướng dẫn vận hành đã được cập nhật cho report/admin routes.

## 3. Checklist E2E của BE-T06

Chạy trên MySQL test cô lập hoặc staging test có dữ liệu giả. E2E report phải đi từ report intake qua admin queue đến decision, sử dụng HTTP/API hoặc Filament panel theo mục tiêu; không chỉ insert report trực tiếp vào DB rồi gọi action PHP. Có thể tạo fixture report riêng để kiểm tra HA06 integration, nhưng không được tính đó là E2E report intake.

- [x] **Intake:** `ReportWorkflowTest` kiểm tra target hợp lệ/sai, payload, reporter, status OPEN và lưu atomically.
- [x] **Quyền reporter:** reporter-only list, outsider, guest/non-admin và private evidence được kiểm tra trong `ReportWorkflowTest`.
- [x] **Admin workflow:** queue, detail, IN_REVIEW, evidence policy/audit, RESOLVED/DISMISSED, history và moderation được kiểm tra.
- [x] **Sai quyền/state:** role/status sai, transition không hợp lệ/lặp và lỗi audit có test.
- [x] **Review moderation:** hide/restore cập nhật public review/rating/search và cache invalidation; audit/action được kiểm tra.
- [x] **Listing moderation:** suspend/hide làm listing không còn public và chặn booking mới; restore đi qua action/lifecycle và audit.
- [x] **Private evidence:** booking/message/care evidence bị giới hạn theo participant/admin policy; không lộ nội dung cho outsider và access admin có audit.
- [x] **Tích hợp HA06:** report `IN_REVIEW` giữ booking chờ; quyết định COMPLETE/CANCEL_REFUND tạo operation; booking chỉ đổi sau release/refund thành công.
- [x] **Filament/Pulse:** dashboard authorization tests và Report Resource tests xác nhận access, action dùng domain logic và HTML không lộ private evidence.
- [x] **Worker/scheduler:** scheduler/heartbeat và release outbox thật đã chạy trong runtime; QueueContextPropagationTest xác nhận cơ chế request/schedule context. Giới hạn: chưa có request-driven business queue job hoặc failed job thật; không khẳng định đã kiểm tra các trường hợp đó trên runtime.

### Bằng chứng phải ghi khi nghiệm thu

- [x] Branch, SHA merge và trạng thái worktree đã ghi ở đầu handoff; migration/seed/config/runtime có trong Task 3 report.
- [x] Test method và evidence về intake, quyền, decision, audit, public visibility, private evidence và lỗi được dẫn trong report/handoff.
- [x] Lệnh runtime, test counts và PR Checks link được ghi; còn thiếu run ID/URL/SHA check cụ thể và failed-job runtime (không có job để retry).
- [x] Pulse/Filament access matrix và privacy test evidence được ghi trong test/handoff.
- [x] Known gaps và giới hạn runtime được ghi ở trên; nhóm đã xác nhận nghiệm thu, gồm reviewer Hoàng.

**Hoàn thành BE-T06** khi intake/triage/moderation, admin/policy, private-evidence boundaries và yêu cầu vận hành của task được triển khai/test; E2E riêng của report đạt; Pulse/worker/scheduler có evidence; handoff có SHA và CI/runtime result. Thịnh phụ trách đóng E2E của task. Hoàng/BE-H06 sau đó tích hợp và chạy lại workflow 4 trên commit chung; bước phụ thuộc BE-HA06 chỉ được đánh dấu Pass sau khi completion/release thực sự được tích hợp.

## 4. Trạng thái hiện có từ BE-H06

- Branch tích hợp hiện tại `main`, merge commit `457a7e1aea75be3a2ba301ca95c6c860cf7bfefb`; PR head `419d662c1f14b56d74efb8a359d506a30a262948`. PR #34 đã merge; workspace sạch.
- `ReportWorkflowTest` bao phủ report intake, reporter-only list, admin status/history, private evidence, listing/review moderation, booking evidence và `DISMISSED`. `BookingReportDecisionTest` bao phủ COMPLETE/refund operation, retry/idempotency và state guard.
- API Contracts, Scramble operation notes, route/test matrix và OpenAPI JSON hiện đã có report endpoints. Strict export và `ApiDocumentationAccessTest` đã pass trong lần kiểm tra trước; xem Task 3 report để biết ngày/kết quả.
- QueueContextPropagationTest đạt 3 tests / 31 assertions trong lần chạy đã ghi. Runtime scheduler với `schedule_run_id` đã xử lý release operation sandbox thật thành công; không có failed job runtime để retry.
- Filament/Pulse có trong source; Pulse dùng storage mặc định, không bật Redis ingest. Full pipeline local gần nhất được ghi nhận đạt 441 tests / 5.122 assertions trên SHA cũ `f28834b`; không gán số liệu này cho merge commit. Người dùng xác nhận CI PR #34 pass; xem [PR Checks](https://github.com/Huuhoang-1208/PETTEL/pull/34/checks). Run ID/URL và SHA check chưa được lưu.
- **Nghiệm thu:** Hoàng, Thịnh và Hào đồng ý trên merge commit `457a7e1` theo xác nhận người dùng ngày 10/11/2026. CI PR #34 được xác nhận pass; PR head có cùng tree với merge commit. Actions run ID riêng chưa được chép vào handoff.

### Tài liệu liên quan

- [Task 3 integration/E2E report](<BE-H06 — Task 3 integration and E2E verification report.md>)
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>)
- [M09 Reports & Admin](<../03 — Modules Documentation/M09 — Reports & Admin.md>)
- [Security & Privacy](<../06 — Non-functional Requirements/01 — Security & Privacy.md>)
- [Reliability & Recovery](<../06 — Non-functional Requirements/03 — Reliability & Recovery.md>)
- [Observability](<../06 — Non-functional Requirements/05 — Observability.md>)
