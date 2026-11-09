# BE-H06 — Task 3: Integration and E2E verification report

- **Ngày kiểm tra:** 09/11/2026 (Asia/Ho_Chi_Minh)
- **Branch:** `be-h06`
- **HEAD:** `09b41732608e75dca65edf5ce93ac5dc1a321e1b`
- **Base đã tích hợp:** `origin/main` = `36592d9589cbe6257fd675a1480b47f3fd89d0dc` (là ancestor của HEAD)
**Trạng thái worktree khi chạy:** dirty; có thay đổi Task 2 chưa commit cùng hai test E2E mới. Kết quả pipeline chạy trên source dựa trên HEAD này cộng các thay đổi chưa commit, không thể tái tạo chỉ từ SHA HEAD.

## Kết luận

- Pipeline CI tương đương đã chạy local trên MySQL test cô lập và pass.
- Migration fresh/seed, phân tích tĩnh và feature suite pass. Đây là bằng chứng chất lượng trên worktree hiện tại, **không phải GitHub Actions result** và chưa phải evidence trên commit bàn giao sạch.
- Hai workflow đầu đã có mỗi workflow một kịch bản API end-to-end liên tục, gồm nhánh lỗi/quyền chính; chúng không kiểm thử qua trình duyệt UI.
- Booking/payment dừng ở `CONFIRMED`: chưa có luồng API owner-completion + sandbox release để đạt `COMPLETED`.
- Report schema và review-moderation action có, nhưng chưa có API tạo report hoặc admin triage; workflow report không thể chạy E2E.
- Source đăng ký scheduler, nhưng runtime dev đang chạy image cũ: trong container hiện tại `schedule:list` báo không có scheduled task và `route:list --path=api --json` trả 0 routes. Source hiện tại trên test image đăng ký ba lịch. Chưa xác minh worker/scheduler runtime với image từ source hiện tại.
- **Overall: Task 3 / Gate BE = Blocked. Frontend tiếp tục Blocked.**

## 1. Bàn giao, nhánh và nguồn

| Hạng mục | Owner theo backlog | Bằng chứng trong repository | Kết quả kiểm tra |
|---|---|---|---|
| Identity, booking creation, discovery, Smart Match, review | Hoàng (BE-H01/H02/H04/H05) | Implementation, feature tests và handoff/review docs; API contracts/modules | Có trong integration base; review/CI evidence có mức độ khác nhau theo issue. Không có xác nhận mới của owner trên SHA Task 3. |
| Schema, pet, listing, availability, care/report | Thịnh (BE-T01/T02/T03/T05; BE-T06 phụ thuộc còn lại) | Migration/schema, module docs, feature tests; T03 report/CI evidence | Phần pet/listing/care đã tích hợp. BE-T06 report/admin chưa được triển khai trong source hiện tại theo tiêu chí workflow. |
| Runtime, acceptance/payment sandbox | Hào (BE-HA01/HA02/HA03/HA05; BE-HA06 phụ thuộc còn lại) | Compose, scheduler/worker services, payment contract và tests | Payment sandbox/expiry/cancel hiện có. Completion/release workflow BE-HA06 chưa có trong source; vận hành image hiện tại chưa phản ánh source. |
| Git integration point | Hoàng điều phối; cả ba xác nhận Gate | `be-h06` HEAD `09b4173`; `origin/main` `36592d9` | `origin/main` là ancestor. Task 2 thay đổi còn chưa commit; không tồn tại một SHA sạch đại diện cho toàn worktree test. |

Các tài liệu cũ của T04/HA03 có ghi nhận trạng thái khác nhau giữa thời điểm viết và implementation sau đó. Source hiện tại cùng test mới hơn được dùng làm bằng chứng kỹ thuật; owner nên đồng bộ lại tài liệu bàn giao T04/HA03 trước khi ký Gate. Không tự sửa contract từ các tài liệu mâu thuẫn.

Branch refs có sẵn tại thời điểm audit (không fetch remote mới): local `be-h01`/`be-t01`=`3678041`, `be-h04`=`5840f1a`, `be-t02`=`36d3bb3`, `be-t03`=`d1cecf2`, `be-t05`=`4c94a47`; remote `origin/be-h03`=`bcb0955`, `origin/be-t04`=`c432ade`, `origin/be-ha01`=`9bfa2a5`, `origin/be-ha02`=`eeb242e`, `origin/be-ha03`=`f8a7444`, `origin/main`=`36592d9`. Không có branch ref `BE-T06` hoặc `BE-HA06` trong snapshot; đây là công việc downstream theo backlog, chưa có handoff branch để tích hợp. Các branch refs là trạng thái local đã fetch trước đó, không chứng minh PR/review mới nhất trên GitHub.

## 2. Kiểm tra tích hợp

| Kiểm tra | Trạng thái | Bằng chứng / giới hạn |
|---|---|---|
| Migration order và schema | **PASS** | `migrate:fresh --seed` trên MySQL 8.4 chạy theo thứ tự: identity → profile/listing → booking/payment → content/moderation → listing suspension → cancellation operations → message attachments → care-update retention. Demo seed chạy hai lần thành công. Không kiểm tra upgrade từ mọi production snapshot. |
| Route inventory / route conflicts | **PASS (source)** | Task 2 inventory ghi 66 API operations và 67 OpenAPI paths do attachment optional path expansion. API source hiện không có report intake hoặc booking completion route. Runtime dev image route inventory là 0, không dùng để nghiệm thu source. |
| Middleware / access | **PASS cho route đã triển khai, coverage theo test** | Routes dùng Sanctum, account-active, role và resource checks; feature tests xác nhận các trường hợp chính, gồm ownership, outsider, admin và private content. Chưa kiểm chứng report/completion endpoints vì chưa tồn tại. |
| Response/error conventions | **PASS cho API hiện có theo Task 2 audit** | API Contracts/OpenAPI và error envelope được cập nhật; full tests pass. Không suy rộng contract sang các operation chưa triển khai. |
| Cross-module model/schema | **PASS trong migration + feature suite hiện có; E2E còn thiếu** | Foreign keys, checks, snapshots, moderation/audit và cancellation outbox được exercise trong tests. Không chứng minh report intake hay booking completion/release tích hợp. |
| Cache | **PASS có giới hạn** | Search/listing/availability không dùng cache kết quả; review cache có version invalidation khi create/moderation. `PublicReviewTest::test_reads_reflect_creation_hiding_and_restoring` xác nhận đọc công khai phản ánh create/hide/restore. Không cần search availability invalidation hiện tại. |

## 3. CI evidence

| Field | Result |
|---|---|
| Commit checked out | `09b41732608e75dca65edf5ce93ac5dc1a321e1b` |
| Tested tree | Worktree trên branch `be-h06`, dirty với các thay đổi Task 2 chưa commit |
| Command | `docker compose -p pettel-h06-e2e -f compose.test.yaml run --build --rm test` |
| Exit code | `0` |
| Pint | PASS — 188 files |
| PHPStan | PASS — 131 files, no errors |
| Database | MySQL 8.4 isolated test service; migrations fresh pass; demo seed pass hai lần |
| Feature/unit suite | PASS — 389 tests, 4,490 assertions |
| GitHub Actions | **Not tested / unavailable in this run.** Workflow definition được đọc; `gh` CLI không cài và không có run URL/result gắn SHA để xác nhận. Local command là cùng lệnh CI gọi, nhưng không được gọi là official CI. |
| Cleanup | Project `pettel-h06-e2e` được dừng/xóa sau run; test DB tmpfs/network không còn giữ trạng thái test. |

Lần chạy này không phải bằng chứng CI trên commit bàn giao vì worktree dirty. Cần commit Task 2, lấy SHA sạch, rồi chạy official CI hoặc lặp command trên đúng SHA.

## 4. Bốn workflow và nhánh lỗi

| Workflow / step | Trạng thái | Bằng chứng và giới hạn |
|---|---|---|
| **1. Khám phá dịch vụ — search/filter → detail/media/reviews → review mới → cache invalidation/visibility** | **PASS (API E2E)** | `PublicDiscoveryEndToEndTest::test_guest_discovers_a_public_listing_reads_its_image_and_sees_a_new_review_in_search_and_detail`. Một luồng liên tục xác nhận lọc tìm kiếm, trang chi tiết/media, review rỗng rồi tạo review hợp lệ, số rating/review cập nhật ở reviews/detail/search, invalid filter, kết quả rỗng, listing suspended và media/reviews/listing không còn public. Booking đã hoàn tất là precondition được dựng trong fixture; luồng hoàn tất booking không thuộc workflow này. |
| **2. Listing — create/submit/admin approve → slot/media/search → change request → suspend/restore/archive** | **PASS (API E2E)** | `ListingManagementEndToEndTest::test_caregiver_publishes_a_listing_with_media_and_availability_then_admin_change_and_lifecycle_updates_public_search`. Một luồng liên tục xác nhận quyền caregiver/admin, validation, upload lỗi và upload hợp lệ, slot hợp lệ/sai, draft ẩn, submit/approve, public search/detail/media, change request chỉ hiện sau admin duyệt, suspend/restore/archive và audit. |
| Nhánh lỗi/quyền của workflow 1 và 2 | **PASS trong hai E2E trên** | Cả hai test chạy trong suite MySQL full pass: tổng hai test mới đóng góp 107 assertions. Đây là E2E qua HTTP API trong Laravel feature test; không phải kiểm tra browser/UI hoặc worker Redis runtime. |
| **3. Booking & payment — pet/quote → booking → accept → sandbox callback** | **PASS đến `CONFIRMED` (API tests)** | `BookingCreationTest`, `BookingAcceptanceTest`, `SandboxPaymentSimulationTest`; concurrency ở `BookingAcceptanceConcurrencyTest` và `PaymentCallbackCancellationConcurrencyTest`. Auth, idempotency, callback signature/replay/late callback, expiry/cancel có test. |
| Workflow 3 — timeout/expiry/cancel/retry/race | **PASS (API/job tests)** | `BookingExpiryTest`, `BookingCancellationTest`, `SandboxPaymentSimulationTest`, `PaymentCallbackCancellationConcurrencyTest`. Full stack job delivery/runtime chưa xác minh. |
| Workflow 3 — caregiver care → owner completion → sandbox release → `COMPLETED` | **NOT IMPLEMENTED** | Không tìm thấy route/controller/action để hoàn tất booking và release sandbox. Review tests đặt trạng thái booking thành `COMPLETED` bằng fixture; không chứng minh lifecycle thật. Dependency: **BE-HA06 — Hào**, theo backlog. |
| **4. Report & moderation — submit report → admin queue/decision → audit/public update** | **NOT IMPLEMENTED / BLOCKED** | `reports` schema và `SetReviewVisibility` action có; `ReviewModerationTest` tạo fixture report trực tiếp trong DB rồi gọi action. Không có API tạo report, admin queue/triage route hoặc Filament panel; chưa thể E2E. Dependency: **BE-T06 — Thịnh**; contract/moderation review từ H05 là input. |
| Workflow 4 — invalid target, duplicate report, private evidence boundaries | **NOT TESTED end-to-end** | Có schema/FK và action-level review tests cho review target; không có intake/queue API để kiểm thử các nhánh theo contract. |

## 5. Worker, scheduler, seed, OpenAPI, Pulse

- Compose source defines `backend`, `worker` (`queue:work redis`) and `scheduler` (`schedule:work`); local runtime containers đều đang Up.
- Trong runtime hiện tại, `docker compose exec -T backend php artisan schedule:list` trả `No scheduled tasks have been defined`, và `route:list --path=api --json` trả 0. Container được tạo 12 ngày trước, trong khi source có migration/route mới hơn và không được bind-mount; đây là image/runtime stale, nên process Up không chứng minh job của source hiện tại chạy.
- Trên source hiện tại, lệnh cô lập `docker compose -p pettel-h06-e2e -f compose.test.yaml run --build --rm test php artisan schedule:list` liệt kê expiry mỗi phút, cancellation outbox mỗi phút và care-update retention hằng ngày. Cùng test suite có scheduler registration test cho expiry/outbox.
- CI test cấu hình `QUEUE_CONNECTION=sync`; không kiểm chứng worker Redis xử lý job thực tế. Chưa chạy `queue:work`, scheduler tick end-to-end, failed job/retry trên runtime mới.
- Pulse không nằm trong `backend/composer.json` và chưa có dashboard/authorization test.
- OpenAPI Task 2 hiện sinh được 67 operations, 0 `UnknownType`; vẫn provisional cho tới Hào review. `DemoSeeder` lặp lại thành công trong CI test.

## 6. Blockers, owners và bước tiếp theo

| Blocker | Owner / dependency | Cần có để gỡ |
|---|---|---|
| Current worktree dirty; chưa có SHA sạch cho toàn bộ Task 2 + Task 3 evidence | Hoàng điều phối | Commit Task 2, xác nhận SHA tích hợp, chạy official CI/result trên SHA đó. |
| Booking completion/release chưa có runtime | BE-HA06 — Hào; phụ thuộc BE-HA05 và lifecycle contract | Implement/test owner confirmation, release success/failure/retry, idempotency/audit, rồi chạy booking E2E tới `COMPLETED`. |
| Report intake/admin triage chưa có runtime | BE-T06 — Thịnh; phụ thuộc BE-HA03/HA04 và H05 contract | Implement report create/read/queue/decision, policy/private evidence/audit/moderation updates; thêm API tests rồi chạy report E2E. |
| Scheduler/worker containers không chạy source hiện tại | Hào (runtime/CI theo backlog) | Rebuild/redeploy chỉ sau khi chốt commit; kiểm tra `schedule:list`, queue worker, scheduler tick, failed jobs và an toàn log trên isolated/staging stack. |
| Pulse chưa cài/bảo vệ | BE-T06 — Thịnh theo backlog | Cài, cấu hình quyền admin, test guest/non-admin/admin, và thêm hướng dẫn vận hành. |
| Official CI và xác nhận của cả ba trên cùng commit chưa có | Hoàng, Thịnh, Hào | Official Actions run + CI URL/SHA; checklist workflow; xác nhận theo quy trình nhóm. |

**Gate BE:** `Blocked`. Không chuyển frontend sang Ready. Có thể hoàn tất các kiểm tra độc lập, nhưng Gate chỉ đổi sau khi hai workflow thiếu module được tích hợp, worker/scheduler/Pulse được xác minh trên source cùng commit, official CI pass và cả ba owner xác nhận.
