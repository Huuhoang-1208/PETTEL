# BE-T06 — Bàn giao từ BE-H06 và nghiệm thu E2E

**Owner:** Thịnh · **Reviewer theo backlog:** Hoàng · **Bên tích hợp:** Hoàng/BE-H06
**Phạm vi:** report/moderation/audit, admin panel và vận hành backend theo backlog BE-T06.
**Trạng thái tại bàn giao:** report intake, reporter scope, admin queue/status/history/evidence/moderation và booking decision API hiện có trong worktree `be-ha06`; report/booking feature tests đã được bổ sung. Full pipeline local đạt trên worktree dirty (422 tests / 4.945 assertions), nhưng chưa có commit/SHA sạch hoặc official CI. Filament/Pulse chưa thấy trong source hiện tại; xác nhận riêng nếu chúng vẫn thuộc phạm vi nghiệm thu.

## 1. Nguồn hợp đồng và hiện trạng

- [Backlog BE-T06](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>): tạo/xem/xử lý report `OPEN → IN_REVIEW → RESOLVED|DISMISSED`; dùng action/policy chung để ẩn listing/review; actor/thời gian/lý do; giới hạn đọc chứng cứ riêng tư; Filament auth/Policy; Pulse admin-only; kiểm tra worker/scheduler, failed jobs và request ID.
- [M09 Reports & Admin](<../03 — Modules Documentation/M09 — Reports & Admin.md>): target hiện được liệt kê là listing, review, message hoặc booking; một target FK; admin xử lý có lý do/audit; ẩn listing phải loại khỏi search và chặn booking mới; report riêng tư không làm lộ evidence; dismiss phải có lý do.
- [P0 Decision Contract §4, §6](<../02 — Domain Documentation/05 — P0 Decision Contract.md>): report `IN_REVIEW` giữ booking `AWAITING_CONFIRMATION`; admin quyết định hoàn tất/release hoặc hủy ngoại lệ/refund; report đang mở và thời hạn retention ảnh hưởng dữ liệu booking/care update.
- [BE-H06 Task 3 report](<BE-H06 — Task 3 integration and E2E verification report.md>): ghi các report/admin routes, booking decisions, test workflow, strict OpenAPI export và runtime worker/scheduler mới nhất. Report API không đồng nghĩa với Filament/Pulse; hai phần đó chưa thấy trong `backend/composer.json`.

Nguồn có thể chưa thống nhất về target, khả năng reporter xem trạng thái, duplicate report, reopen, quyền xem message/care update và hành động moderation cho từng target. Ghi từng bất đồng, đưa ra đề xuất, xin Hoàng chốt trước khi biến thành contract hoặc schema/API mới. Không suy luận quyền mở evidence riêng tư chỉ từ quyền admin chung.

## 2. Việc cần triển khai

- [ ] Đối chiếu migration `reports` hiện hữu trước khi sửa schema: `reporter_user_id`, tối đa một target trong `listing_id|review_id|message_id|booking_id`, `reason_code`, `description`, `status`, timestamps/resolved_at; status DB cho phép `OPEN`, `IN_REVIEW`, `RESOLVED`, `DISMISSED`. Giữ FK/constraint hoặc viết migration tương thích nếu contract được owner chốt thay đổi.
- [ ] Tạo report intake API và validation cho loại target được owner chốt; xác minh target thực sự tồn tại, reporter hiện hành, quyền truy cập và chặn target giả/không hợp lệ. Không lưu file/evidence công khai hoặc đưa PII vào log.
- [ ] Tạo cách reporter xem report của chính mình nếu được contract chốt; user khác không được liệt kê/đọc report. Viết rõ response, state, lỗi và idempotency/duplicate behavior.
- [ ] Cung cấp admin queue/detail/decision API hoặc Filament resource/action dùng chung service/Policy. Hỗ trợ chuyển `OPEN → IN_REVIEW → RESOLVED|DISMISSED`; dismiss/resolve yêu cầu lý do; actor và thời gian lấy từ server. Chặn transition lặp/không hợp lệ theo quyết định đã duyệt.
- [ ] Gắn decision với hành vi moderation có căn cứ: review hide/restore gọi action chung (hiện có `SetReviewVisibility`); listing suspend/restore gọi lifecycle chung để search/media/booking reflect state. Không mutate bảng nghiệp vụ trực tiếp từ Filament.
- [ ] Xác định và kiểm quyền xem evidence riêng tư: admin-only, least privilege, chỉ evidence cần thiết cho report, audit mọi truy cập/decision, không trả URL/object key public. Người báo cáo/ngoài booking không được nhìn thấy nội dung riêng tư nếu không được contract cho phép.
- [ ] Ghi `moderation_actions` và `audit_logs` cho decision, target, actor, lý do, request ID, trạng thái trước/sau. Lỗi ghi audit phải rollback decision và target visibility cùng transaction.
- [ ] Phối hợp BE-HA06: `IN_REVIEW` liên quan booking ở `AWAITING_CONFIRMATION` phải chặn auto-completion/release; admin resolution phải gọi service completion/refund đã chốt, không tự sửa booking/payment.
- [ ] Cài Filament panel, khóa truy cập guest/non-admin và account không ACTIVE; policy cho listing/review/report/evidence; admin actions gọi service trên.
- [ ] Cài/cấu hình Laravel Pulse; dashboard admin-only, không để lộ PII/request body/secret; ghi hướng dẫn bật/tắt và vận hành.
- [ ] Xác minh worker/scheduler trên image build từ source đã chốt: queue worker nhận job, scheduler phát job theo lịch, retry/failed jobs quan sát được, request/trace ID nối được log. Hiện test Compose dùng `QUEUE_CONNECTION=sync`, nên cần run riêng với Redis/worker/scheduler nếu các queue đó là phần bàn giao.
- [ ] Cập nhật API Contracts, OpenAPI, M09, hướng dẫn triển khai/Compose và README; export OpenAPI mới nếu routes đổi.

## 3. Checklist E2E của BE-T06

Chạy trên MySQL test cô lập hoặc staging test có dữ liệu giả. E2E report phải đi từ report intake qua admin queue đến decision, sử dụng HTTP/API hoặc Filament panel theo mục tiêu; không chỉ insert report trực tiếp vào DB rồi gọi action PHP. Có thể tạo fixture report riêng để kiểm tra HA06 integration, nhưng không được tính đó là E2E report intake.

- [ ] **Intake:** owner/caregiver tạo report cho từng target được chốt; report lưu đúng reporter, một target, reason/description và `OPEN`; target invalid/missing, payload sai hoặc duplicate theo policy được chốt trả lỗi đúng và không ghi partial data.
- [ ] **Quyền reporter:** reporter chỉ đọc report của mình nếu endpoint này được duyệt; user khác, guest và non-admin không xem report/evidence riêng của người khác.
- [ ] **Admin workflow:** active admin mở queue/detail, chuyển sang `IN_REVIEW`, xem đúng evidence được phép, quyết định resolve hoặc dismiss có lý do; status, `resolved_at`, `moderation_actions` và `audit_logs` nhất quán.
- [ ] **Sai quyền/state:** guest, non-admin, suspended admin, sai target, transition lặp/không hợp lệ và lỗi audit bị từ chối hoặc xử lý theo contract, không để thay đổi một phần.
- [ ] **Review moderation:** khi report review được xử lý ẩn/khôi phục, public reviews/rating/search cập nhật và cache invalidation hoạt động; action/audit có một record cho mỗi thay đổi thực.
- [ ] **Listing moderation:** khi listing bị suspend, listing biến mất khỏi public search/detail/media và booking mới bị chặn; restore chỉ qua quyền/action hợp lệ; lịch sử moderation/audit ghi đúng.
- [ ] **Private evidence:** report nhắm message/booking không làm lộ message, care update hoặc ảnh cho reporter/ngoài booking; admin chỉ truy cập theo policy, mọi access/decision cần audit theo contract.
- [ ] **Tích hợp HA06:** report `IN_REVIEW` giữ booking ở `AWAITING_CONFIRMATION` và không release; sau admin quyết định, chỉ một nhánh completion/release hoặc exception refund/cancel được thực hiện bởi service BE-HA06. Nếu BE-HA06 chưa sẵn sàng, đánh dấu bước tích hợp `Blocked`, không giả lập Pass.
- [ ] **Filament/Pulse:** guest/non-admin không mở panel hoặc dashboard; active admin vào được; nội dung dashboard/log không có secret/PII không cần thiết.
- [ ] **Worker/scheduler:** chạy image từ cùng source SHA; chứng minh job thật được đưa vào queue, worker xử lý, scheduler phát job, retry/failed job được quan sát; ghi rõ môi trường và command. Không dùng trạng thái container `Up` làm bằng chứng xử lý được job.

### Bằng chứng phải ghi khi nghiệm thu

- [ ] Branch, SHA đã kiểm thử và SHA tích hợp; migration/seed, config và danh sách service/container dùng.
- [ ] E2E test method/API route/panel path cụ thể; expected/actual cho intake, quyền, decision, audit, cache/public visibility, private evidence và nhánh lỗi.
- [ ] Lệnh, exit code, số test/assertions, local/CI URL; worker/scheduler/Redis run evidence và failed-job result.
- [ ] Pulse/Filament access matrix cho guest, non-admin, admin; bằng chứng không lộ dữ liệu nhạy cảm.
- [ ] Bảng bất đồng contract còn lại, đề xuất và người cần chốt; reviewer Hoàng xác nhận task.

**Hoàn thành BE-T06** khi intake/triage/moderation, admin/policy, private-evidence boundaries và yêu cầu vận hành của task được triển khai/test; E2E riêng của report đạt; Pulse/worker/scheduler có evidence; handoff có SHA và CI/runtime result. Thịnh phụ trách đóng E2E của task. Hoàng/BE-H06 sau đó tích hợp và chạy lại workflow 4 trên commit chung; bước phụ thuộc BE-HA06 chỉ được đánh dấu Pass sau khi completion/release thực sự được tích hợp.

## 4. Trạng thái hiện có từ BE-H06

- Branch hiện tại `be-ha06`, HEAD `b0efd9cf614ddaf01051761cce36a37d79b4c684`; worktree dirty, chưa có SHA sạch và chưa có official CI result.
- `ReportWorkflowTest` bao phủ report intake, reporter-only list, admin status/history, private evidence, listing/review moderation, booking evidence và `DISMISSED`. `BookingReportDecisionTest` bao phủ COMPLETE/refund operation, retry/idempotency và state guard.
- API Contracts, Scramble operation notes, route/test matrix và OpenAPI JSON hiện đã có report endpoints. Strict export và `ApiDocumentationAccessTest` đã pass trong lần kiểm tra trước; xem Task 3 report để biết ngày/kết quả.
- QueueContextPropagationTest đạt 3 tests / 31 assertions; Pint cho `routes/console.php` và test context đạt. Worker/scheduler runtime chạy với `schedule_run_id`; booking commands không tìm thấy operation đến hạn. `queue:failed` rỗng.
- Filament/Pulse chưa thấy trong source; nếu chúng thuộc phạm vi Gate, để mục này Blocked/Not implemented cho tới khi có implementation và tests. Full pipeline local đã pass; chưa thay thế official CI hoặc review trên commit sạch (xem Task 3 report).

### Tài liệu liên quan

- [Task 3 integration/E2E report](<BE-H06 — Task 3 integration and E2E verification report.md>)
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>)
- [M09 Reports & Admin](<../03 — Modules Documentation/M09 — Reports & Admin.md>)
- [Security & Privacy](<../06 — Non-functional Requirements/01 — Security & Privacy.md>)
- [Reliability & Recovery](<../06 — Non-functional Requirements/03 — Reliability & Recovery.md>)
- [Observability](<../06 — Non-functional Requirements/05 — Observability.md>)
