# BE-T03 — Plan triển khai và checklist nghiệm thu

Issue: [#16](https://github.com/Huuhoang-1208/PETTEL/issues/16). Owner triển khai: **Thịnh**. Reviewer: **Hào**. Phối hợp search/booking: **Hoàng/Hào**.

Nguồn: [contract lifecycle, ảnh và lịch](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md>), [bản rà soát baseline](<BE-T03 — Rà soát phạm vi và đầu vào contract.md>). Kế hoạch được lập ngày 02/11/2026 và cập nhật theo contract v1.0 ngày 03/11/2026. Trạng thái triển khai và bằng chứng từng checkbox được cập nhật trong [báo cáo thực hiện issue #16](<BE-T03 — Báo cáo thực hiện issue %2316.md>); Task 01 đã chốt, các task khác dưới đây giữ làm checklist tiêu chí và đối chiếu trạng thái chi tiết ở báo cáo.

**Chủ dự án (người dùng) đã chốt phương án A cùng T03-D01–D12 tại contract §17 ngày 03/11/2026. Codex là bên audit và soạn đề xuất; chủ dự án xác nhận Hào đã nghiệm thu implementation ngày 03/11/2026.** Nếu contract đổi, cập nhật decision log/task và tiêu chí tương ứng trước khi code. Plan không mở rộng sang frontend, payment/expiry/refund, media pipeline/cleanup scheduler hoặc purge toàn hệ thống.

## Cách đánh dấu hoàn thành

Chỉ đánh `[x]` khi **tất cả tiêu chí của checkbox đạt** và có bằng chứng phù hợp: task thiết kế dùng audit/decision log đã chốt; task implementation cần code và kiểm thử hành vi, không chỉ helper/route tồn tại. SHA/CI trên commit bàn giao được chốt tại task 16/18; chưa commit không tự phủ nhận việc quyết định thiết kế đã được ghi trong working tree. Mỗi task ghi file/quyết định, commit, tên test/kết quả hoặc link CI phù hợp vào nhật ký. Review và đóng issue là các bước riêng, không tự suy ra từ test local.

Thứ tự khuyến nghị: **01 → 02 → 03 → 04 → 05 → 06 → 07 → 08 → 09 → 10 → 11 → 12 → 13 → 14 → 15 → 16 → 17 → 18**. Test hành vi được viết/chạy cùng task, không dồn toàn bộ tới cuối. Task 14–16 kiểm tra những thuộc tính tích hợp không thể chứng minh bằng test một endpoint riêng lẻ.

Timebox issue gốc là 10 giờ; kế hoạch ghi nhận đã sử dụng 10 giờ. Không theo dõi mức lệch so với timebox và không cắt test cạnh tranh, bảo vệ lịch hoặc quyền để ép phạm vi vào thời gian.

## A. Chốt đầu vào và chuẩn bị

- [x] **01 — Chốt contract và quyết định.**

  **Việc làm:** chốt phương án A/B và T03-D01–D12; xác định phiên bản contract dùng triển khai. Giữ nguyên các quy tắc P0/BE-HA03 đã được chốt trước đó.

  **Tiêu chí hoàn thành:** contract ghi rõ quyết định được chấp nhận hoặc điều chỉnh, ngày xác nhận, giả định NFR và nội dung không làm. Quyền restore, archive có booking, version/pending, overlap slot, giới hạn ảnh và schema bổ sung không còn mơ hồ. Không còn dùng một quyết định “chờ duyệt” như yêu cầu bắt buộc đã chốt.

  **Bằng chứng:** contract v1.0 §17: Codex audit baseline/working tree/tài liệu/sáu issue và soạn đề xuất kèm căn cứ/phương án/giới hạn; Chủ dự án (người dùng) chốt D01–D12 ngày 03/11/2026. Vai trò người chốt được sửa theo đính chính của chủ dự án; tại thời điểm chốt contract, Hào chưa nghiệm thu implementation, sign-off sau triển khai được ghi tại Task 18. **Đối chiếu:** contract §2–3, §17 và báo cáo audit. **Phụ thuộc:** không.

- [x] **02 — Xác nhận baseline, môi trường test và phạm vi thay đổi.**

  **Việc làm:** ghi nhánh/HEAD thực tế, tình trạng working tree; đối chiếu lại routes/schema/auth/search/create/accept; chuẩn bị MySQL test cô lập và các fixture có sẵn. Kiểm tra khả năng xử lý JPEG/PNG/WebP, thumbnail và cấu hình upload.

  **Tiêu chí hoàn thành:** ghi được baseline và danh sách thành phần tái sử dụng; test nền chạy trên DB test riêng hoặc lỗi nền được xác định rõ trước khi quy cho BE-T03. Có owner, thứ tự thực hiện và timebox 10 giờ được ghi nhận; không tạo lại bảng/migration/module đã bàn giao. Không tác động DB phát triển/production khi chạy migration/test phá dữ liệu.

  **Bằng chứng:** SHA baseline `46a4cd4e602f48e5e7b41df25e195d7769f5a75d`; snapshot baseline chạy bằng project Compose riêng với MySQL trên `tmpfs`: migration/seed lặp đạt, Pint 105 files, PHPStan 73 files/0 lỗi, 243 tests/2.763 assertions; timebox 10 giờ đã sử dụng. **Đối chiếu:** bản rà soát §3, §7; báo cáo thực hiện §02. **Phụ thuộc:** 01.

## B. Dữ liệu và nền quyền/transaction

- [x] **03 — Bổ sung dữ liệu phân biệt nguồn đình chỉ.**

  **Việc làm:** theo D02 đã chốt, tạo migration forward cho `suspension_source`; backfill SUSPENDED cũ thành ADMIN, thêm CHECK tương ứng; cập nhật model/resource cần thiết.

  **Tiêu chí hoàn thành:** migration chạy được trên DB trống và DB có listing ACTIVE/SUSPENDED; nguồn suspension đúng quy tắc null/non-null và CAREGIVER/ADMIN. Backfill không mở quyền tự restore listing cũ. Rollback/reapply migration mới không xóa listing, booking hoặc sửa migration cũ.

  **Bằng chứng:** full suite xác nhận migration DB mới; integration test `tests/Integration/ListingSuspensionSourceMigrationTest.php` chạy trên MySQL tmpfs xác nhận backfill dữ liệu legacy, CHECK chặn cặp trạng thái/nguồn sai, rollback/reapply vẫn giữ listing và booking (**1 test, 15 assertions**). **Đối chiếu:** contract §6, T03-D02; báo cáo thực hiện §03. **Phụ thuộc:** 02.

- [x] **04 — Hoàn thiện quyền tài nguyên, thứ tự lỗi, version và audit.**

  **Việc làm:** áp dụng kiểm tra owner/parent/action cho các endpoint mới; thống nhất transaction khóa listing trước tài nguyên con, đọc lại dữ liệu sau khóa; dùng audit hiện có. Không tạo một tầng abstraction chung nếu các action đơn giản đã đủ.

  **Tiêu chí hoàn thành:** chưa đăng nhập/sai role/CSRF trả theo contract; sai owner hoặc child khác listing trả 404 trước khi lộ payload; admin tự review và owner restore nguồn ADMIN trả 403. Trường hệ thống không được client quyết định. Version tăng đúng một lần khi nội dung/lifecycle thực sự đổi, không tăng vì slot/upload chưa gắn/no-op. Audit có actor/action/resource/reason/version/request ID, ghi cùng transaction; lỗi audit rollback toàn bộ mutation.

  **Bằng chứng:** `ListingLifecycleTest` kiểm tra owner/child scope, lỗi 404 trước body sai, admin tự review 403, không cho ghi suspension source, no-op/slot version, audit before/after + request ID và rollback listing khi audit lỗi. HTTP smoke test với app production-mode xác nhận request session thiếu CSRF trả 419. Full runner MySQL mới nhất: Pint 124 files, PHPStan 84 files/0 lỗi và **297 tests / 3.532 assertions**. **Đối chiếu:** contract §5–6, §10, §15; T03-02, T03-08; báo cáo thực hiện §04. **Phụ thuộc:** 03.

## C. Upload và quản lý ảnh

- [x] **05 — Xây API upload ảnh private và xử lý lỗi storage/DB.**

  **Việc làm:** triển khai upload media theo MIME/size/pixel đã chốt; decode, bỏ EXIF, sinh thumbnail, lưu metadata server; xử lý cleanup khi ghi DB thất bại. Điều chỉnh giới hạn web server/PHP để file hợp lệ không bị chặn sớm.

  **Tiêu chí hoàn thành:** JPEG/PNG/WebP hợp lệ upload được; MIME giả, SVG/executable, file quá byte/pixel bị từ chối; client không tự gán owner/key/state. Chỉ trả READY sau khi ảnh và thumbnail đồng bộ đã lưu. Storage/DB lỗi có cleanup bù; cleanup lỗi phải ghi log nội bộ đủ dữ kiện để retry thủ công, không tạo READY giả và không cần scheduler/pipeline mới. Request quá giới hạn tầng server có phản hồi 413 đã công bố theo tầng, không bắt proxy trả envelope JSON ứng dụng. Không lộ object key/hash qua JSON.

  **Bằng chứng:** `ListingMediaTest` kiểm tra JPEG/PNG/WebP, file đúng 10 MiB và vượt byte/pixel, nội dung MIME giả/SVG/executable, metadata server, cleanup khi DB lỗi và log retry khi cleanup lỗi. HTTP smoke test Nginx gửi 13 MiB nhận 413; cấu hình PHP upload/post là 12M/13M và Nginx là 12m. **Đối chiếu:** contract §11, §13, §15; T03-13; báo cáo thực hiện §05. **Phụ thuộc:** 02, 04; các ngưỡng theo 01.

- [x] **06 — Gắn/tháo/sắp xếp ảnh DRAFT và preview theo quyền.**

  **Việc làm:** triển khai PUT media trên DRAFT, preview owner và preview admin trong phạm vi listing/request; kiểm tra media READY/owner/tham chiếu riêng tư.

  **Tiêu chí hoàn thành:** thay toàn bộ danh sách ảnh và reorder thành công, kể cả swap thứ tự không vi phạm unique. ID không trùng, thứ tự 1..n, số ảnh đúng giới hạn. Media người khác/PENDING_DELETE/ảnh pet hoặc care update bị chặn; child sai listing không truy cập được. PENDING_REVIEW/ACTIVE/SUSPENDED không thay pivot trực tiếp; ARCHIVED không ghi. Detach không xóa file vật lý khi còn nghĩa vụ lưu giữ. Preview phải xác thực và không trả media chưa READY.

  **Bằng chứng:** `ListingMediaTest` kiểm tra thay danh sách, reorder/swap, list rỗng, ID trùng/owner/state/reference, giới hạn 10 ảnh, không ghi ngoài DRAFT, rollback pivot khi audit lỗi, preview caregiver/admin/request theo quyền và không trả media chưa READY. Sau detach file/thumbnail và media row còn nguyên. **Đối chiếu:** contract §11–14; T03-13–14; báo cáo thực hiện §06. **Phụ thuộc:** 05.

## D. Lifecycle và thay đổi listing đã duyệt

- [x] **07 — Hoàn thiện gửi duyệt, duyệt và từ chối lần đầu.**

  **Việc làm:** triển khai submit/approve/reject và resource/queue quản lý tối thiểu cần cho review.

  **Tiêu chí hoàn thành:** DRAFT submit thành PENDING_REVIEW; admin khác chủ approve thành ACTIVE hoặc reject về DRAFT có lý do. Profile/account/role/nội dung/media được kiểm tra lại khi approve. PENDING_REVIEW đóng sửa nội dung/ảnh; `published_at` set lần đầu và không bị ghi lại khi restore. Theo dự thảo hiện tại, không có ảnh/slot vẫn được review, nhưng chưa có slot thì không nhận booking. Action lặp không ghi event/version trùng. Listing ACTIVE được tạo qua API thật, không chỉ fixture DB.

  **Bằng chứng:** `ListingLifecycleContractTest` kiểm tra listing tạo qua API thật không có ảnh/slot vẫn submit/approve được, pending khóa nội dung, approve kiểm lại caregiver/nội dung/media, reject bắt buộc lý do, restore giữ `published_at`, replay không tăng version/audit; lỗi audit rollback. `ListingLifecycleTest` kiểm tra admin tự review và public visibility. **Đối chiếu:** contract §5, §8; T03-01–02. **Phụ thuộc:** 04, 06.

- [x] **08 — Hoàn thiện suspend, restore và archive.**

  **Việc làm:** triển khai action owner/admin, reason/audit, nguồn suspension và điều kiện archive; xử lý pending request khi có lifecycle mutation.

  **Tiêu chí hoàn thành:** owner chỉ restore suspension CAREGIVER; admin có thể nâng suspension CAREGIVER thành ADMIN và restore theo quyền. Restore kiểm tra caregiver còn đủ điều kiện. Archive bị chặn khi còn bất kỳ trạng thái giữ lịch nào, kể cả hold quá hạn; không có admin override trong #16. ARCHIVED terminal. Suspend/archive không sửa giá, snapshot, trạng thái hay event booking cũ; không tự cancel/refund. Pending bị WITHDRAWN cùng transaction đúng contract; ở task này tạo pending bằng fixture schema hiện có, task 09 kiểm chứng lại bằng request tạo qua API thật.

  **Bằng chứng:** `ListingLifecycleContractTest` kiểm tra caregiver/admin và suspension source, điều kiện restore, archive bị chặn bởi đủ `AWAITING_PAYMENT` (đã quá hạn), `CONFIRMED`, `IN_PROGRESS`, `AWAITING_CONFIRMATION`; ARCHIVED terminal. REQUESTED không chặn archive. Suspend/archive rút pending; booking và status events giữ nguyên; lỗi audit rollback cùng pending. **Đối chiếu:** contract §5–6; T03-03–05, T03-08. **Phụ thuộc:** 07.

- [x] **09 — Tạo, xem và rút change request của caregiver.**

  **Việc làm:** triển khai create/list/detail/withdraw; kiểm tra base version; merge/normalize input thành payload đầy đủ có schema version; mở cho ACTIVE/SUSPENDED theo contract.

  **Tiêu chí hoàn thành:** chỉ nhận allowlist và ít nhất một thay đổi thực sự; stale base trả VERSION_CONFLICT; tối đa một PENDING_REVIEW/listing. Không đổi listing/pivot/public data khi mới tạo request. Owner chỉ đọc/rút request của listing mình; request đã kết thúc không rút lại. Withdraw không tăng listing version hoặc sửa snapshot. Media pending được giữ như tham chiếu cần bảo toàn. Suspend/restore/archive hủy pending cũ đúng task 08.

  **Bằng chứng:** `ListingChangeRequestContractTest` kiểm tra allowlist cả payload media lồng nhau, no-op, stale base, một pending/listing, history/detail và 404 với caregiver khác hoặc request không thuộc listing. Withdraw giữ listing/version/public content và media/file; suspend/archive rút pending qua API. **Đối chiếu:** contract §6–7, §13; T03-06–08. **Phụ thuộc:** 06, 08.

- [x] **10 — Duyệt/từ chối change request nguyên tử.**

  **Việc làm:** triển khai admin queue/detail/approve/reject; kiểm tra lại listing/base version/caregiver/media trước apply; cập nhật nội dung/pivot/version/request/audit trong transaction.

  **Tiêu chí hoàn thành:** pending giữ bản public cũ; approve áp dụng đủ nội dung và ảnh đúng một lần, version tăng một; reject có lý do và không sửa bản cũ. Admin không tự review listing mình. Approve khi SUSPENDED không tự ACTIVE. Media được duyệt chuyển PUBLIC_ELIGIBLE nhưng không tự cấp quyền public ngoài listing. Stale hoặc request đã kết thúc không được apply lại. Lỗi audit/pivot rollback cả listing/request/version.

  **Bằng chứng:** `ListingChangeRequestContractTest` kiểm tra pending giữ bản public cũ; approve nội dung/ảnh đúng một lần, tăng version một và không sửa booking snapshot/event; reject bắt buộc lý do và giữ nguyên listing/media; có test stale/replay/self-review, caregiver mất điều kiện, approve khi SUSPENDED không tự restore, rollback khi audit hoặc pivot lỗi. **Đối chiếu:** contract §7, §11; T03-06–09. **Phụ thuộc:** 09.

## E. Lịch khả dụng và kết quả công khai

- [x] **11 — Xây CRUD slot, chống overlap và bảo vệ lịch booking.**

  **Việc làm:** triển khai list/create/update/soft-delete slot theo owner; chuẩn hóa UTC/microsecond; khóa listing, kiểm tra overlap và coverage sau thay đổi dự kiến.

  **Tiêu chí hoàn thành:** timestamp có offset, start < end và end > now khi tạo/sửa; slot chưa kết thúc có thể bắt đầu trong quá khứ theo contract. Server tự gán listing/creator. Slot overlap bị conflict; tiếp giáp giữ riêng, không tự merge; booking không bắc qua hai slot. Sửa/xóa không làm bất kỳ booking giữ lịch nào mất một slot bao trọn, kể cả hold quá hạn. REQUESTED không chặn sửa nhưng accept phải kiểm tra lại. Soft-deleted slot không được dùng; ARCHIVED không cho ghi; slot không tăng listing version.

  **Bằng chứng:** feature test biên thời gian/offset/microsecond, tiếp giáp/overlap, từng trạng thái giữ lịch và rollback coverage. **Đối chiếu:** contract §9–10; T03-10–11. **Phụ thuộc:** 04, 08.

- [x] **12 — Phục vụ ảnh công khai và tích hợp search/booking.**

  **Việc làm:** thêm public media route kiểm tra quyền mỗi request; thêm `media` vào public list/detail theo allowlist; kiểm tra ảnh/trạng thái/lịch từ API BE-T03 với search/quote/create/accept/reject hiện có.

  **Tiêu chí hoàn thành:** chỉ media READY/PUBLIC_ELIGIBLE trong pivot đã duyệt của listing đủ điều kiện mới được phục vụ. Ảnh pending không công khai; suspend/detach chặn request ảnh mới; no-store theo contract. List/detail không lộ raw slot, booking, snapshot, key hoặc hash. Sau lifecycle/lịch thay đổi qua API thật, search/quote/create/accept đọc cùng quy tắc; reject REQUESTED vẫn hợp lệ khi listing ngừng ACTIVE. Đổi giá qua approve giữ snapshot cũ; đổi service/price unit/rules khiến accept cũ trả INVALID_STATE.

  **Bằng chứng:** test hành trình xuyên module, test public allowlist cập nhật có chủ đích và test truy cập ảnh sau suspend/detach. **Đối chiếu:** contract §4, §12; T03-03, T03-06, T03-09, T03-14. **Phụ thuộc:** 07–11.

- [x] **13 — Rà đủ API/resource, phân trang và hợp đồng lỗi.**

  **Việc làm:** đối chiếu từng route caregiver/admin/public trong contract §13–15 với routes, request, resource và test tương ứng; hoàn thiện listing management/review queues/history.

  **Tiêu chí hoàn thành:** không thiếu endpoint đã chốt; GET caregiver vẫn mặc định DRAFT và hỗ trợ status/ALL; queues có filter/thứ tự/pagination đúng. JSON đơn bọc data, collections có data/links/meta, IDs là chuỗi, VND nguyên, thời gian UTC. Unknown fields/query và reason không hợp lệ trả 422; base stale/state/availability phân biệt đúng mã 409. 401/403/404/419 theo đúng thứ tự quyền; lỗi không lộ ID booking hoặc media người khác. PATCH/DELETE DRAFT cũ không bị đổi thành sửa ACTIVE/archive.

  **Bằng chứng:** bảng route → test/request mẫu, kiểm thử status/filter/page rỗng và lỗi theo contract. **Đối chiếu:** contract §13–15; T03-02, T03-14–15. **Phụ thuộc:** 05–12.

## F. Kiểm chứng tích hợp và bàn giao

- [x] **14 — Kiểm chứng cạnh tranh bằng MySQL và kết nối thật.**

  **Việc làm:** chạy các tình huống đồng thời trên DB test riêng, fixture đã commit; không mock khóa DB.

  **Tiêu chí hoàn thành:** hai create request chỉ một pending; hai admin approve/reject cùng request chỉ một quyết định thành công; hai ghi slot overlap chỉ một thành công. Với accept/sửa slot: hoặc hold thành công rồi sửa phá coverage bị chặn, hoặc sửa thành công rồi accept kiểm tra lịch mới. Với accept/suspend: booking đã accept được giữ, accept sau suspend bị chặn. Không có dữ liệu dở hoặc hai accept xung đột cùng thành công. Thứ tự khóa thực tế tương thích CreateBooking/ValidateBookingAcceptance; lỗi deadlock nếu phát sinh được retry có giới hạn, không che lỗi bằng retry vô hạn.

  **Bằng chứng:** test concurrency, kết quả hai phía, trạng thái DB/event/audit sau commit. **Đối chiếu:** contract §10; T03-07, T03-12. **Phụ thuộc:** 10–13.

- [x] **15 — Kiểm chứng rollback, quyền ảnh và giữ lịch sử.**

  **Việc làm:** tổng hợp các failure case xuyên module: audit lỗi, pivot lỗi, storage lỗi, DB lỗi sau upload, pending bị hủy, media còn tham chiếu; kiểm tra snapshot/history trước–sau.

  **Tiêu chí hoàn thành:** lỗi không để lệch listing/version/pivot/request/audit; không có READY giả hoặc file vừa tạo không có phương án cleanup. Detach/soft delete không xóa file khi còn tham chiếu/retention/report; ảnh pending/private không được phục vụ qua URL public. Lifecycle và review không viết đè giá/pet/listing/policy snapshots hay event booking đã tồn tại. Không thực hiện cancel/refund thay BE-T04.

  **Bằng chứng:** test lỗi cưỡng bức, DB assertions và storage assertions. **Đối chiếu:** T03-03, T03-08–09, T03-13–14. **Phụ thuộc:** 12–14.

- [x] **16 — Chạy hồi quy và ghi bằng chứng trên commit bàn giao.**

  **Việc làm:** chạy runner kiểm tra của dự án trên DB MySQL test riêng; kiểm tra định dạng, phân tích kiểu, migration/seed lặp, toàn bộ test liên quan và CI. Chỉ chạy thêm đo tải nếu NFR được chốt là điều kiện nghiệm thu của đợt này.

  **Tiêu chí hoàn thành:** kiểm tra bắt buộc của repo/CI đạt trên commit cuối; auth, HA02, search, create/idempotency, HA03 và BE-T03 không hồi quy. Có số test/assertions, SHA, cấu hình DB/môi trường và kết quả migration/seed. Không dùng báo cáo 243 tests lịch sử để thay lần chạy mới. Theo D12, hiệu năng/recovery kế thừa chưa đo ghi “chưa kiểm chứng”; không bổ sung load-test/SLA mới làm gate #16. Concurrency MySQL, quyền, rollback và snapshot vẫn phải kiểm chứng đủ.

  **Bằng chứng:** local runner: Pint 124 files, PHPStan 84 files/0 lỗi, migrate fresh + seed hai lần, 297 tests/3.532 assertions trên MySQL 8.4/tmpfs; migration upgrade/backfill/rollback thêm 1 test/15 assertions. GitHub Actions [Backend CI run #18](https://github.com/Huuhoang-1208/PETTEL/actions/runs/41580892240) pass trên PR #17, head SHA `62090311845925cd825aea0773954f0905899a9b`; merge SHA `e4379f130d90a4341ee7cb5389670e8ab4121402`. **Đối chiếu:** T03-15 và contract §3, §16; kết quả cụ thể ở báo cáo thực hiện. **Phụ thuộc:** 13–15.

- [x] **17 — Đồng bộ tài liệu và bàn giao các module phụ thuộc.**

  **Việc làm:** cập nhật P0/decision log, M03/lifecycle/business rules/workflow, database design/BE-T01, API chung và storage design theo contract đã duyệt. Ghi bàn giao cho H03/HA03/HA02.

  **Tiêu chí hoàn thành:** các nguồn không mâu thuẫn về 404/403, suspension source, archive, version/pending, slot và media. Có ví dụ API chạy được, schema/migration mới, thứ tự khóa, lỗi, giới hạn ảnh và phần ngoài phạm vi. Bảng T03-01–15 được ánh xạ tới test/bằng chứng. Tài liệu ghi đúng phần đã triển khai/chưa đo/chờ review, không ghi reviewer đồng ý nếu chưa có xác nhận.

  **Bằng chứng:** P0, M03, workflow, database design, API/error, storage và bảng traceability T03-01–15 được gộp trong PR [#17](https://github.com/Huuhoang-1208/PETTEL/pull/17), merge vào `main` tại SHA `e4379f130d90a4341ee7cb5389670e8ab4121402`; CI run #18 pass. PR liên kết issue [#16](https://github.com/Huuhoang-1208/PETTEL/issues/16), hiện đã đóng. Báo cáo thực hiện là biên bản bàn giao kèm SHA/CI và tình trạng NFR/reviewer. **Đối chiếu:** contract §18. **Phụ thuộc:** 16.

- [x] **18 — Hào review và nghiệm thu issue #16.**

  **Việc làm:** reviewer kiểm tra diff, contract, test concurrency, quyền, snapshot và hành trình listing → booking; xử lý các yêu cầu sửa. Chuẩn bị PR/issue checklist phản ánh kết quả thật.

  **Tiêu chí hoàn thành:** reviewer xác nhận trên commit cuối; CI của commit đó đạt; mọi yêu cầu sửa trong scope đã giải quyết; T03-01–15 đủ bằng chứng; downstream nhận được contract/schema/API cuối. Chỉ khi các điều kiện này đạt mới đủ căn cứ đóng #16. Việc đăng comment, gửi thông báo hoặc đóng issue trên GitHub thực hiện khi có chỉ dẫn phù hợp, không phải tác vụ tự động của bản plan này.

  **Bằng chứng:** PR #17 đã merge, CI pass, issue #16 đã đóng. Chủ dự án xác nhận Hào nghiệm thu trong cuộc trao đổi ngày 03/11/2026. PR không có review submission riêng; nguồn xác nhận được ghi là chủ dự án. **Phụ thuộc:** 01–17.

## Bảng đối chiếu ba checkbox gốc của issue

| Checkbox gốc #16 | Task trong plan | Điều kiện coi checkbox gốc hoàn thành |
| --- | --- | --- |
| Gửi duyệt, duyệt/từ chối có lý do, suspend, restore, archive | 03–04, 07–10, 13–16 | API đúng quyền/state/reason/audit; terminal archive, moderation và rollback đạt; đủ T03-01–08. |
| Quản lý lịch/ảnh; ACTIVE đổi trường trọng yếu phải duyệt lại | 05–06, 09–15 | Media/slot API đạt; bản đã duyệt còn công khai khi pending; coverage và concurrency đạt; đủ T03-06–14. |
| Test suspend chặn booking mới, đổi hiển thị, giữ lịch sử | 08, 12, 14–16 | Hành trình API thật chứng minh list/detail/media bị ẩn, tạo mới/accept bị chặn và snapshot/history giữ nguyên; hồi quy đạt. |

Task 17–18 bổ sung các mốc tài liệu/bàn giao và review vào tiêu chí nghiệm thu. “Đã viết test” không thay thế “test đã chạy đạt trên commit bàn giao”. **Cập nhật 03/11/2026:** PR #17 đã merge vào `main`, issue #16 đã đóng; chủ dự án xác nhận Hào nghiệm thu. Task 16–18 đã hoàn thành; nguồn xác nhận Hào được ghi tại báo cáo thực hiện.

## Nhật ký bằng chứng

Mỗi task dùng một dòng riêng; thêm dòng khi có commit sửa hoặc chạy lại do thay đổi liên quan. Không điền PASS trước khi thực hiện.

| Task | Commit/SHA | Test hoặc bằng chứng | Kết quả | Người xác nhận |
| --- | --- | --- | --- | --- |
| 01 | Working tree, baseline `46a4cd4` | Contract v1.0 §17 và báo cáo audit ngày 03/11/2026 | D01–D12 đã chốt; không phải nghiệm thu code | Chủ dự án (người dùng) — người chốt; Codex audit/soạn đề xuất |
