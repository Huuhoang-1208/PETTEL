# BE-H06 — Plan triển khai và checklist nghiệm thu

Nguồn: [Issue #29 — BE-H06: Hoàn thiện query/cache, API và điều phối Gate BE](https://github.com/Huuhoang-1208/PETTEL/issues/29). Owner: Hoàng. Reviewer: Hào. Phụ thuộc: BE-H03, BE-H04 và contract review BE-H05. Tài liệu này chỉ lập kế hoạch; chưa đánh dấu việc nào hoàn tất thay cho bằng chứng thực tế.

## Mục tiêu

Rà soát hiệu năng truy vấn public bằng phép đo có thể lặp lại; so sánh baseline không cache với phương án cache; sinh và review OpenAPI khớp hành vi đã kiểm thử; tích hợp kết quả các owner và chạy bốn workflow để đưa ra quyết định Gate BE có bằng chứng.

Gate BE chỉ được xác nhận khi đạt các điều kiện tại mục 5 của [backlog 4 tuần](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>) và có xác nhận của Hoàng, Thịnh, Hào. Nếu thiếu điều kiện hoặc người xác nhận, ghi rõ `Blocked`; không chuyển trạng thái frontend sang sẵn sàng.

## Hiện trạng đối chiếu

- API tìm kiếm public đã có validation query, phân trang, sắp xếp ổn định, eager-load media và tính rating theo listing. Các test hiện có bao phủ filter, pagination, availability, listing/review và các luồng liên quan.
- Cache review page hiện được bật sau benchmark so sánh baseline/cache; search, detail và availability tiếp tục đọc DB trực tiếp.
- Scramble đã cài; OpenAPI được sinh từ routes API. Contract OpenAPI vẫn cần rà soát schema và nghiệp vụ trước khi frontend dựa vào.
- `frontend/` hiện mới có `.gitkeep`.
- Backend có nhiều feature tests theo module. Các tests đó không thay thế việc chạy xuyên suốt bốn workflow và ghi kết quả Gate.
- Rà soát hiện tại chưa thấy API/workflow tạo và xử lý report hoàn chỉnh; chưa thấy vòng đời chăm sóc đến `COMPLETED` và release sandbox đầy đủ. Đây là các phụ thuộc cần kiểm tra với owner tương ứng trước khi khẳng định workflow Gate đạt.
- Hệ thống worker/scheduler, migrations và seed đã có trong project; cần kiểm tra chúng hoạt động trong cấu hình bàn giao, không chỉ tồn tại trong source.

## Quyết định và nguyên tắc triển khai

1. MySQL là nguồn chuẩn. Cache chỉ tối ưu dữ liệu công khai; không dùng cache quyết định quyền, payment, trạng thái booking hoặc giữ chỗ.
2. Baseline và lần đo có cache phải dùng cùng commit nền, cấu hình máy/container, dữ liệu, request, số người dùng và thời gian đo. Làm nóng app/DB như nhau; tách kết quả cache lạnh và cache nóng.
3. Chỉ bật cache nếu kết quả đo chứng minh có lợi và dữ liệu vẫn đúng theo giới hạn stale đã được owner chấp thuận. Nếu không có cải thiện đáng kể, ghi kết quả và giữ contract đọc trực tiếp từ DB.
4. Mọi thay đổi input/output, quyền, lỗi hoặc state phải khớp code, tests, API Contract và OpenAPI. Tài liệu sinh tự động không được xem là bằng chứng test.
5. Kết quả không có bằng chứng phải ghi `Not tested`; thiếu phụ thuộc hoặc chưa được owner xác nhận phải ghi `Blocked`, không suy diễn thành `Pass`.

## Task 1 — Query, pagination, cache public và invalidation

### Checklist thực hiện

- [x] Tạo môi trường đo riêng, không dùng database demo. Bộ dữ liệu tạo 5.000 listings, 10.000 bookings, 10.000 reviews và 5.000 slots; 5.000 listing-media links dùng một ảnh tổng hợp chia sẻ.
- [x] Ghi cấu hình đo: nhánh/commit nền, image PHP 8.3.36, MySQL 8.4, Redis 7, 20 CPU threads, 15 GiB host RAM, Nginx + PHP-FPM, kích thước dữ liệu và trạng thái cache. Container không bị giới hạn CPU/RAM.
- [x] Dùng cùng request mix: search mặc định, filter, trang sâu, listing detail và review list; warm-up tách riêng khỏi 60 giây đo.
- [x] Thêm listener chỉ hoạt động trong `APP_ENV=performance` để ghi query count, tổng SQL time và thống kê từng SQL template theo endpoint; bỏ binding.
- [x] Lưu `EXPLAIN ANALYZE` và ghi nhận index/rows/time của listing search-rating và review page tại [backend/benchmarks/results/explain-analyze.md](../../backend/benchmarks/results/explain-analyze.md).
- [x] Dùng k6 50 VU; lưu JSON từng lượt gồm latency p50/p95 (k6 median/p95), throughput, request và lỗi trong [backend/benchmarks/results](../../backend/benchmarks/results).
- [x] Chạy baseline không cache 3 lần, cùng request mix và dữ liệu; có thêm một lượt baseline được tag riêng để lấy p95 cho review endpoint.
- [x] Thử cache public review page với key theo listing/page/per-page/version, TTL 5 phút. Không cache quyền, listing visibility hoặc availability.
- [x] Chạy cache lạnh 1 lần và cache nóng 3 lần; ghi cache hit/miss, query count/time và p95.
- [x] Prime cache rồi kiểm invalidation sau khi tạo review, ẩn review, khôi phục review; feature tests xác nhận dữ liệu mới xuất hiện.
- [x] Xác nhận response schema không đổi; booking/accept không dùng cache và vẫn kiểm tra availability từ DB.
- [x] Quyết định: giữ cache review page vì warm p95/query count giảm rõ trong workload thử; không cache search/detail/availability vì NFR đã đạt và query kế hoạch chưa chứng minh cần thêm phạm vi invalidation.

### Tiêu chí hoàn thành

- Có báo cáo baseline và cache dùng cùng dữ liệu/cấu hình/request mix; có p50/p95, throughput, lỗi, query count, SQL time và cache hit/miss.
- Có kết quả `EXPLAIN ANALYZE` cho các truy vấn cần tối ưu và ghi rõ query/index nào là nút thắt hoặc không phải nút thắt.
- Benchmark đạt hoặc nêu rõ mức chênh so với NFR hiện hành: search p95 ≤ 2 giây, detail p95 ≤ 1 giây và server error < 1% trên workload đã mô tả.
- Nếu cache được bật: các invalidation liên quan có test; thay đổi dữ liệu public xuất hiện đúng theo giới hạn stale đã chốt; cache miss/cold path vẫn trả dữ liệu đúng.
- Nếu cache không được bật: có baseline và so sánh chứng minh quyết định; owner review quyết định này vì issue yêu cầu kiểm tra cache/invalidation.
- Không dùng số đo thiếu cấu hình/dữ liệu làm tuyên bố đã đạt NFR.

### Cách đo và lý do chọn công cụ

1. **Đo truy vấn trong Laravel:** gắn listener `DB::listen` chỉ trong tiến trình benchmark. Mỗi request gom số `QueryExecuted` và tổng `time` theo route; giữ SQL dạng placeholder để thấy cấu trúc query, bỏ `bindings` để không lưu giá trị tìm kiếm, token hoặc dữ liệu người dùng. Đây cho biết endpoint đang phát bao nhiêu query và thời gian nằm trong DB.
2. **Đo độ trễ phía người dùng:** chạy cùng mix bằng k6 ở 50 virtual users, sau warm-up. Lấy p50/p95, throughput và lỗi theo tag endpoint; chạy tối thiểu ba lượt. Đây đo cả thời gian HTTP (Laravel, DB, serialization và mạng), nên không thể thay bằng thời gian SQL.
3. **Tìm nguyên nhân query chậm:** chạy `EXPLAIN ANALYZE` riêng cho query đã chọn trên MySQL và cùng bộ dữ liệu; lưu index, số hàng thực tế, thời gian từng iterator. Đây cho biết MySQL thực thi kế hoạch nào, không đại diện latency HTTP toàn request.
4. **So sánh công bằng:** giữ nguyên commit, image, cấu hình, dữ liệu, endpoint mix và tải; reset cache trước lượt lạnh và warm cache theo quy tắc được ghi rõ trước lượt nóng. Chỉ thêm cache sau khi baseline chỉ ra endpoint nghẽn và phép đo chứng minh cải thiện. Nếu chưa có số liệu, không bật cache vì phải gánh thêm invalidation và nguy cơ dữ liệu public cũ (đặc biệt lịch/booking và review).

### Bằng chứng cần lưu

- File script/command tạo dữ liệu và kịch bản k6.
- Output JSON/CSV hoặc log của từng lượt đo; bảng so sánh baseline/cache.
- Kết quả `EXPLAIN ANALYZE` và ghi chú index/query.
- Tests cho cache hit/miss, invalidation và tính đúng của kết quả; CI pass ở commit được ghi trong báo cáo.

## Task 2 — Scramble, OpenAPI và contract cho frontend

### Checklist thực hiện

- [x] Kiểm tra tương thích Scramble với Laravel/PHP hiện tại; cài dependency và cập nhật lockfile.
- [x] Tạo inventory từ `php artisan route:list --path=api --json`, lọc hai web route `/docs/api*`; ghi method/path, controller/action, middleware, route name và so sánh với OpenAPI.
- [x] Hoàn thiện ma trận 66/66 operations: request/query/body, response codes, middleware/access, resource scope/state, API Contracts/module/BE-H03/04/05/T03 mapping, exact test methods hoặc `Not tested`. Cả 66 operations đều có method test gọi trực tiếp route; hai route từng thiếu bằng chứng nay có test auth/identity và pagination/owner scope/query validation. Owner Hoàng đã review và chấp thuận implementation handoff ngày 09/11/2026; Hào independent review thực hiện sau khi issue được bàn giao.
- [x] Đối chiếu route-level với API Contracts/module docs và BE-H03/04/05/HA03/HA05/BE-T03 theo phạm vi liên quan; ghi rõ các mismatch đã sửa và các contract gap/owner decisions trong ma trận. Không tự đánh dấu contract đã được owner duyệt.
- [x] Bổ sung summary/description cho đủ 67 OpenAPI operations; mô tả role/resource scope, state, idempotency, upload, private media, error envelope và response codes. Bổ sung `ApiErrorEnvelope`; thêm các lỗi 401/403/404/409/419/422 theo route contract và 201 cho create/replay khác status. Strict export và kiểm tra JSON mới hoàn tất.
- [x] Sinh JSON OpenAPI từ Scramble; inventory có 66 Laravel method/path operations. Do route attachment tùy chọn được biểu diễn thành hai URL cụ thể, export có 67 OpenAPI operations. Bản JSON có tên API và mô tả Sanctum session-cookie/CSRF; contract chưa được nghiệm thu.
- [x] Xử lý 34 `UnknownType` còn lại ở thời điểm rà soát trước: đã bổ sung kiểu/schema dựa trên validator, response code, resource và migration; export `scramble:export --fail-on-unknown` thành công với 0 warning. Ma trận ghi vị trí, nguyên nhân, cách xử lý và strict-export evidence; đối chiếu 66 operations được ghi ở các checklist tiếp theo.
- [x] Cấu hình OpenAPI security là Sanctum session cookie động theo `SESSION_COOKIE`, không phải bearer; đánh dấu route public bằng `security: []`.
- [x] Chạy kiểm tra docs UI/JSON bằng guest, non-admin và admin trên MySQL Compose; 3 tests / 14 assertions pass trong full suite.
- [x] Ghi toàn bộ 66 operations, exact test methods/assertion scope hoặc `Not tested`, contract mapping, mismatch, owner proposal và quyết định Hoàng đã chốt trong [Task 2 route and contract matrix](<BE-H06 — Task 2 route and contract matrix.md>).

### Tiêu chí hoàn thành

- Scramble cài được và sinh OpenAPI từ code hiện hành ở môi trường dự án.
- Các endpoint đã test có path, verb, input/output, auth/role, state và lỗi phù hợp với hành vi thực tế; mapping phải được xác nhận theo test method, không chỉ theo tên file.
- Có mapping endpoint → feature test/contract; endpoint chưa được test được đánh dấu rõ.
- Quyền truy cập tài liệu API trong demo được cấu hình và có bằng chứng kiểm tra.
- Bản OpenAPI đã được owner xem xét; lưu kết quả review cùng evidence và commit handoff. Hào thực hiện independent review sau handoff.

### Bằng chứng cần lưu

- Dependency/lockfile và cấu hình Scramble.
- OpenAPI đã sinh; lệnh sinh tài liệu và kết quả.
- Bảng coverage route → contract → test.
- Tests hoặc kiểm tra xác nhận quyền truy cập docs.

## Task 3 — Tích hợp bốn workflow, lỗi và Gate BE

### Checklist thực hiện

- [ ] Thu thập từ từng owner commit/branch, contract, feature tests, CI result, migration/seed, scheduler/worker instructions và giới hạn còn lại.
- [ ] Xác nhận tích hợp đang chạy trên cùng một nhánh/commit; kiểm tra migration order, route conflicts, middleware, response/error conventions và model/schema cross-module.
- [ ] Chạy pipeline CI chính thức trên database test cô lập; lưu commit SHA, command, exit code và summary.
- [ ] Chạy đủ bốn workflow và nhánh lỗi, ghi theo từng bước `Pass`, `Fail`, `Blocked` hoặc `Not tested`:

| Workflow | Nhánh chính cần chạy | Nhánh lỗi/quyền cần xác nhận |
|---|---|---|
| Khám phá dịch vụ | Search/filter → listing detail/media → reviews/rating | Listing không public/suspended; filter sai; trang rỗng; cache stale/invalidation nếu bật |
| Đăng và quản lý listing | Caregiver tạo → submit → admin duyệt → availability/media → public search | Sai role/owner; validation; approve/reject lặp; suspend/archive/change request; upload lỗi |
| Đặt lịch và thanh toán | Pet/quote → booking → caregiver accept → sandbox attempt/callback | IDOR, trùng lịch/race, timeout/hết hạn, callback sai/lặp/đến muộn, cancel/retry |
| Báo cáo và xử lý admin | Tạo report → admin review → quyết định/ẩn/suspend → audit và public/search cập nhật | Sai target, người không phải admin, report trùng/lặp, nội dung riêng tư/file không lộ, thao tác lặp |

- [ ] Kiểm tra điểm nối giữa workflow: review chỉ sau COMPLETED; report/moderation làm public data cập nhật; các thao tác admin có audit; private message/care update/media không lộ.
- [ ] Đối chiếu mục 5 Gate BE: toàn backend tích hợp/review, bốn workflow đạt, quyền/state/concurrency/payment callback đạt, worker/scheduler, seed, OpenAPI, Pulse bảo vệ admin và hướng dẫn kiểm tra.
- [ ] Ghi rõ các phụ thuộc còn thiếu. Đặc biệt xác nhận owner của report workflow và completion/release sandbox; không tự thay thế task BE-T06/BE-HA06 trong phạm vi H06.
- [ ] Nếu một workflow chưa thể chạy do thiếu module/owner input, đánh dấu Gate `Blocked`, ghi người/phần việc cần bổ sung và giữ frontend Blocked.
- [ ] Lấy xác nhận của Hoàng, Thịnh, Hào trên commit, kết quả CI và checklist; lưu cách xác nhận theo quy trình nhóm.

### Tiêu chí hoàn thành

- Có evidence theo commit cho từng workflow và từng nhánh lỗi; không dùng test module riêng thay cho chạy tích hợp.
- CI xanh; migrations/seed chạy đúng thứ tự; worker/scheduler được kiểm tra trong môi trường bàn giao.
- OpenAPI đã review; Pulse được bảo vệ theo quyền admin; README/hướng dẫn đủ để người khác tái hiện kiểm tra.
- Không còn lỗi chặn luồng chính hoặc rò dữ liệu; mọi giới hạn còn lại được ghi rõ.
- Gate BE chỉ là `Pass` khi đủ mọi điều kiện mục 5 và cả ba người xác nhận. Nếu chưa đủ, trạng thái là `Blocked` hoặc `Fail`, kèm lý do và bước tiếp theo.

## Trạng thái Gate và frontend

- Trước khi đủ điều kiện: ghi Gate `Blocked` hoặc `In progress`; giữ frontend `Blocked`. Không coi việc chuẩn bị contract/OpenAPI là cho phép bắt đầu UI tích hợp.
- Sau khi đủ điều kiện và có xác nhận: ghi commit Gate, CI result, checklist và danh sách giới hạn; khi đó mới đề nghị chuyển frontend sang sẵn sàng triển khai.
- H06 không tự đóng các khoảng trống thuộc report/admin hoặc completion/release; phải chuyển đúng owner, tích hợp và kiểm thử trước khi chấm Gate.

## Bảng tổng hợp nghiệm thu

| Task | Trạng thái | Bằng chứng/commit | Blocker hoặc ghi chú |
|---|---|---|---|
| 1. Query/cache/benchmark | Pass (synthetic workload) | [k6 raw results](../../backend/benchmarks/results), [EXPLAIN notes](../../backend/benchmarks/results/explain-analyze.md), [benchmark instructions/results](../../backend/benchmarks/README.md) | Search p95 ~0.76–0.79s, detail ~0.68–0.70s, HTTP errors 0%; cache warm review p95 mean 0.35s vs one tagged no-cache 0.62s. Synthetic data/host only; not a production capacity guarantee. |
| 2. Scramble/OpenAPI | Owner review complete; Hào review after handoff | `backend/config/scramble.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/tests/Feature/ApiDocumentationAccessTest.php`, [OpenAPI tạm sinh](<../08 — Architecture & Technical Design/OpenAPI — Scramble provisional.json>), [route/contract matrix](<BE-H06 — Task 2 route and contract matrix.md>) | Inventory có 66 route operations; export có 67 OpenAPI operations do mô hình hóa hai URL attachment hợp lệ. Export mới đã thêm header `Idempotency-Key` cho booking create/cancel/payment-start, `X-Payment-Signature` cho callback, bỏ key khỏi body schema và cập nhật các enum/response schemas. Strict export ban đầu tái lập 57 `UnknownType`; sau khi 23 trường server-managed được bỏ khỏi inferred request schema và 34 schema còn lại được định kiểu, `scramble:export --fail-on-unknown` thành công với 0 warning. Full compose MySQL run mới nhất trên worktree hiện tại: Pint, PHPStan và 389 tests / 4.490 assertions pass. Ma trận có disposition 66/66 operations, test method trực tiếp cho 66/66 và 0 `Not tested`; Hoàng đã chốt care-update outsider là 403, giữ fallback attachment, callback path hiện tại, report/completion là phase sau, admin routes trong OpenAPI và response 201/200 cho create/replay. Task 2 checklist implementation đã hoàn tất; các quyết định Hoàng xác nhận ngày 09/11/2026 được ghi trong ma trận; Hào review sau khi issue hoàn tất, theo thứ tự Hoàng đã xác nhận. |
| 3. Bốn workflow/Gate BE | Blocked | [Task 3 integration/E2E report](<BE-H06 — Task 3 integration and E2E verification report.md>); local Compose pipeline: 389 tests / 4.490 assertions, exit 0 trên worktree dirty dựa trên HEAD `09b4173`; hai E2E API workflow 1 và 2 pass | Report creation/admin triage chưa có API; booking completion/release chưa có; Pulse chưa được cài; runtime worker/scheduler đang dùng image cũ; chưa có official CI result trên commit sạch và xác nhận owners trên cùng commit. |
| Gate BE tổng thể | Blocked | — | Chưa đạt đầy đủ điều kiện mục 5 |
| Frontend | Blocked | — | Chỉ unblocked sau Gate BE Pass |

## Kết quả thực hiện ban đầu

- Scramble `dedoc/scramble` v0.13.53 đã cài và `composer.lock` được cập nhật. JSON OpenAPI đã sinh tại `docs/08 — Architecture & Technical Design/OpenAPI — Scramble provisional.json`; đây là bản rà soát, chưa được nghiệm thu làm contract cuối.
- Middleware Scramble mặc định chỉ mở ở `local`. Gate `viewApiDocs` hiện cho phép tài khoản `ACTIVE` có role `ADMIN`; feature tests xác nhận guest và non-admin bị từ chối, admin được đọc JSON.
- CI Docker với database MySQL test riêng chạy thành công: Pint/PHPStan, migrations/seed và 389 tests / 4.490 assertions. Hai workflow đầu đã có test E2E qua HTTP API; chưa thay cho kiểm tra browser/UI hoặc hoàn tất workflow 3 và 4.
- Benchmark baseline/cache đã chạy trong stack riêng; toàn bộ số liệu và giới hạn dữ liệu được ghi trong [backend/benchmarks/README.md](../../backend/benchmarks/README.md). Trên 5.000 listings, baseline 3 lượt đạt search p95 0,75–0,79 giây, detail p95 0,68–0,70 giây, lỗi HTTP 0%; cache warm 3 lượt cải thiện review list p95 trung bình khoảng 43% so với lượt no-cache có metric riêng và tăng throughput tổng trung bình khoảng 25% so với ba baseline. Các lượt dùng chung synthetic DB, request mix, 50 VU và cấu hình Docker; không suy rộng thành năng lực production.
- SQL cold review request ghi 4 query / 40,85 ms SQL; cache hit ghi 2 query / 13,03 ms. Lượt cache lạnh 50 VU có 1.098 hit / 2 miss trên 1.100 review reads; warm run có 1.100 hit / 0 miss. `EXPLAIN ANALYZE` chỉ ra review aggregation quét 10.000 reviews và lookup booking; dù vậy search p95 hiện dưới NFR, nên không cache search/availability.
- OpenAPI export có 67 operations từ 66 Laravel operations (attachment fallback thành hai path), summary/description và schema envelope cho lỗi, security session-cookie theo Sanctum/CSRF, `Idempotency-Key`, `X-Payment-Signature`, đúng response 201/200 cho create/replay và không quảng bá system-managed request fields. Strict export `--fail-on-unknown` pass với 0 warnings; JSON được xác nhận không có operation nào thiếu summary/description. Matrix ghi mapping test method trực tiếp cho 66/66 operations, 0 `Not tested`. Implementation checklist Task 2 đã xong; OpenAPI vẫn là bản provisional cho tới khi Hào review sau handoff.
- Kiểm tra docs access gồm guest/non-admin/admin cho JSON và UI, cookie security, public-route marker. Compose MySQL mới nhất pass cả 3 test `ApiDocumentationAccessTest` và toàn suite (389 tests / 4.490 assertions). Lượt đầu phát hiện HTTP JSON có title `Laravel`; đặt `ui.title = PETTEL` đã sửa và test admin hiện pass.
- Kiểm tra route/source cho thấy không có endpoint tạo report hay luồng admin triage report; review moderation có action nhưng không có API route gắn vào. Chưa có API/workflow hoàn chỉnh để chuyển booking sang `COMPLETED` và release sandbox. Pulse cũng chưa có trong dependency. Đây là blocker cho workflow/Gate, không nên tự đánh dấu Pass trong H06.
- Ma trận test tích hợp theo workflow hiện có:

| Workflow | Test/code evidence đã có | Trạng thái workflow/Gate |
|---|---|---|
| Khám phá dịch vụ | `PublicDiscoveryEndToEndTest::test_guest_discovers_a_public_listing_reads_its_image_and_sees_a_new_review_in_search_and_detail` | API E2E Pass; guest search/filter → detail/media/review → rating/cache invalidation → suspended visibility; không kiểm tra browser/UI. |
| Đăng/quản lý listing | `ListingManagementEndToEndTest::test_caregiver_publishes_a_listing_with_media_and_availability_then_admin_change_and_lifecycle_updates_public_search` | API E2E Pass; caregiver create/upload/slot → submit/admin approve → public search → change request → suspend/restore/archive; không kiểm tra browser/UI. |
| Đặt lịch/thanh toán | `BookingCreationTest`, `BookingAcceptanceTest`, `BookingAcceptanceConcurrencyTest`, `SandboxPaymentSimulationTest`, `PaymentCallbackCancellationConcurrencyTest`, `BookingCancellationTest`, `BookingExpiryTest` | Các state/callback/concurrency đã có test; workflow tổng thể Blocked vì thiếu completion/release sandbox. |
| Report/admin | `ReviewModerationTest` chỉ chứng minh action ẩn/khôi phục review | Blocked: chưa có route/API tạo report và admin triage; không thể chạy workflow report. |

- Worker/scheduler: README có lệnh vận hành; scheduler expiry/outbox được xác nhận bởi feature test, nhưng chưa chạy worker/scheduler như một stack bàn giao riêng. Pulse chưa cài. Chưa có xác nhận Hoàng/Thịnh/Hào trên commit này.
- Không thay trạng thái frontend: vẫn `Blocked` cho tới khi blocker được xử lý, CI và bốn workflow có evidence, OpenAPI được review và Hoàng/Thịnh/Hào xác nhận trên cùng commit.

## Tài liệu liên quan

- [Issue #29](https://github.com/Huuhoang-1208/PETTEL/issues/29)
- [Backlog 4 tuần và mục 5 Gate BE](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>)
- [Redis Cache Design](<../08 — Architecture & Technical Design/06 — Redis Cache Design.md>)
- [API Contracts & Error Conventions](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>)
- [Performance & Capacity](<../06 — Non-functional Requirements/02 — Performance & Capacity.md>)
- [Technology & Tools](<../08 — Architecture & Technical Design/10 — Technology & Tools — Responsibilities & Constraints.md>)
