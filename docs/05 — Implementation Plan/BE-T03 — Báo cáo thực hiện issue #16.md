# BE-T03 — Báo cáo thực hiện issue #16

Ngày báo cáo: **03/11/2026** (Asia/Ho_Chi_Minh)
Issue: [#16](https://github.com/Huuhoang-1208/PETTEL/issues/16)
Plan đối chiếu: [BE-T03 — Plan triển khai và checklist nghiệm thu](<BE-T03 — Plan triển khai và checklist nghiệm thu.md>)

## Kết luận

Đã hoàn thiện implementation và kiểm chứng các tiêu chí 03–18. PR [#17](https://github.com/Huuhoang-1208/PETTEL/pull/17) đã merge vào `main`; issue [#16](https://github.com/Huuhoang-1208/PETTEL/issues/16) được liên kết và đóng; CI Backend thành công. Chủ dự án xác nhận Hào đã nghiệm thu trong cuộc trao đổi ngày 03/11/2026; nguồn xác nhận được ghi rõ tại Task 18.

**Chủ dự án (người dùng) đã chốt D01–D12 ngày 03/11/2026, contract BE-T03 v1.0; Task 01 hoàn thành.** Codex là bên audit và soạn đề xuất. Issue #16 đã đóng theo merge PR #17; CI trên PR đạt. Chủ dự án xác nhận Hào đã nghiệm thu; PR không có review submission riêng thể hiện xác nhận đó.

### Bằng chứng môi trường

- Branch: `be-t03`.
- Commit implementation trên nhánh PR: `62090311845925cd825aea0773954f0905899a9b`. GitHub merge commit vào `main`: `e4379f130d90a4341ee7cb5389670e8ab4121402`. PR #17 đã merge ngày 03/11/2026; issue #16 được liên kết và đóng.
- Runner cuối chạy bằng `docker compose -p pettel-be-t03-final -f compose.test.yaml run --build --rm test` tại repo root. Đây là project Compose riêng, MySQL 8.4 với data directory `tmpfs`; `migrate:fresh`, seed hai lần, Pint **124 files PASS**, PHPStan **84 files/0 lỗi**, **297 tests / 3.532 assertions**. Project test đã dừng sau khi chạy; container test của người dùng vẫn healthy.
- **Test nền sạch:** xuất snapshot đúng baseline SHA `46a4cd4e602f48e5e7b41df25e195d7769f5a75d` ra thư mục tạm, chạy `docker compose -p pettel-be-t03-baseline -f compose.test.yaml run --build --rm test`. DB dùng project/network riêng và `/var/lib/mysql` trên `tmpfs`; không dùng working tree BE-T03 hay DB dev/production. `migrate:fresh` và seed hai lần đạt; Pint **105 files PASS**; PHPStan **73 files, 0 errors**; **243 tests passed, 2.763 assertions**, 14.55s.
- Migration upgrade/backfill/rollback trên MySQL có dữ liệu cũ được kiểm riêng bằng `ListingSuspensionSourceMigrationTest`: **1 test / 15 assertions**. Hiệu năng/recovery chưa đo; theo D12 đây là mục tiêu NFR kế thừa chưa kiểm chứng, không phải gate bổ sung cho phần D.

## Báo cáo từng checkbox

Ký hiệu: `[x]` đủ tiêu chí và bằng chứng; `[~]` có triển khai/bằng chứng nhưng còn thiếu ít nhất một tiêu chí; `[ ]` chưa làm hoặc cần người khác xác nhận.

### Kết quả chốt §17 sau audit ngày 03/11/2026

**Người chốt: Chủ dự án (người dùng). Bên audit và soạn đề xuất: Codex (AI assistant). Contract: BE-T03 v1.0.** Vai trò này được sửa theo đính chính của chủ dự án trong cuộc trao đổi ngày 03/11/2026. Cả 12 quyết định đã được chủ dự án chốt trên đề xuất sau audit, không còn chờ xác nhận riêng. Hào là reviewer implementation; xác nhận nghiệm thu của Hào được chủ dự án báo lại tại Task 18.

Audit ban đầu ngày 03/11 đối chiếu baseline `46a4cd4e602f48e5e7b41df25e195d7769f5a75d` bằng `git show`, working tree BE-T03, P0/M03/schema/API/storage/NFR và nội dung GitHub #16/#1/#2/#9/#12/#13. Tại thời điểm audit, #16 còn mở; năm issue phụ thuộc đã đóng. Sau đó PR #17 đã merge và đóng #16. Audit ban đầu là đọc code/tài liệu; các nguồn/căn cứ và phương án bị loại được ghi tại [contract §17](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#17-decision-log-đã-chốt>).

| Quyết định — người chốt đều là Chủ dự án (người dùng), 03/11/2026 | Kết luận | Giữ phạm vi dự án bằng cách |
| --- | --- | --- |
| [D01](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d01>) | A: nội dung/ảnh bản đã duyệt qua change request. | Giữ PATCH chỉ DRAFT như baseline; không thêm đường sửa trực tiếp ACTIVE hoặc bảng revision. Đánh đổi: sửa mô tả cũng chờ duyệt. |
| [D02](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d02>) | Một cột suspension source; caregiver chỉ tự restore nguồn CAREGIVER. | Ngăn vượt moderation bằng một migration forward, không thêm status/bảng moderation. |
| [D03](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d03>) | Archive bị chặn khi còn bốn trạng thái giữ lịch; không admin override. | Dùng suspend để ngừng nhận mới, không thêm cancel/refund hoặc đổi booking. |
| [D04](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d04>) | Không bắt buộc ảnh/slot để review; dữ liệu đã khai báo phải hợp lệ. | Không tạo tiêu chí xuất bản mới; booking reader tiếp tục chặn khoảng chưa mở. |
| [D05](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d05>) | Version cho nội dung/lifecycle, không cho slot/upload; lifecycle rút pending. | Dùng version và WITHDRAWN sẵn có; không content-version riêng/rebase. |
| [D06](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d06>) | Cho proposal khi SUSPENDED, approve không restore; PENDING_REVIEW đóng sửa. | Dùng workflow review/restore riêng hiện có; không trạng thái hoặc edit-pending API mới. |
| [D07](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d07>) | Chặn overlap, không merge, slot tiếp giáp riêng; bảo toàn one-slot coverage. | Giữ reader search/create/accept; không batch replace, recurrence hoặc capacity chung. |
| [D08](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d08>) | Ba MIME, 10 MiB/20 MP/10 ảnh, thumbnail 640 px đồng bộ; cleanup bù và log lỗi để retry thủ công. | Dùng GD/private local disk/schema hiện có; không processing state, media queue pipeline, cleanup scheduler hay purge mới. |
| [D09](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d09>) | Media qua route kiểm quyền từng request, no-store; proposal dùng JSON IDs. | Không S3/CDN/token revocation hay bảng media revision; chấp nhận giảm cache. |
| [D10](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d10>) | 404 owner/parent, 403 action, conflict phân biệt; auth/CSRF giữ hiện hành. | Theo HA02 đã bàn giao, không global exception-handler rewrite; sửa envelope cục bộ BE-T03. |
| [D11](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d11>) | Listing lock trước child, current reads, audit cùng transaction; retry DB tối đa 3 lần. | Giữ pattern create/HA03, không distributed lock hoặc lock service mới. |
| [D12](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#t03-d12>) | NFR demo là mục tiêu chưa đo; vai trò/timebox giữ issue; correctness/concurrency vẫn bắt buộc. | Không thêm SLA, cam kết deadline hoặc gate load-test mới cho #16. |

A được chọn dựa trên baseline DRAFT-only và schema chỉ nói trường trình bày “có thể” sửa trực tiếp, không chỉ vì code BE-T03 đã viết A. D08 làm rõ khác biệt với storage draft: xử lý đồng bộ và cleanup/log cục bộ giúp giữ READY đúng nghĩa mà không phải bổ sung trạng thái xử lý/luồng retry mới. Hạ tầng worker Redis đã có, nhưng không buộc upload listing phụ thuộc vào worker. Chi tiết sizing/performance vẫn chưa được benchmark.

### Kết quả đóng các phát hiện audit

Các khoảng trống trong audit đầu kỳ đã được xử lý trong code/test và tài liệu đã merge vào `main`. Bảng này lưu bằng chứng cho các phát hiện đã đóng; Task 16–18 hiện đã có kết quả ở những mục tương ứng.

| Phát hiện audit | Cách xử lý và bằng chứng hiện tại |
| --- | --- |
| Thứ tự scope/action/validation ở endpoint mới. | Lifecycle, change request, slot và media kiểm tra listing/owner/child trước khi validate payload; test lỗi 404/403 trước payload và unknown field/query trả 422. `ListingLifecycleTest`, `ListingChangeRequestContractTest`, `ListingAvailabilityContractTest`, `ListingMediaTest`. |
| History/queue, summary và pagination chưa theo contract. | Chuẩn hóa collection thành `data/links/meta`, ID chuỗi, timestamp UTC; thêm latest decision/pending summary; test caregiver mặc định DRAFT, status=ALL, pagination hai trang và admin mặc định PENDING_REVIEW/filter/page. `ListingChangeRequestContractTest`. |
| Withdrawal metadata chưa đúng semantics. | Withdraw đặt `reviewed_by_user_id`, `reviewed_at`, `decision_reason` về null; audit giữ actor/time/request ID trên resource change request. `ListingChangeRequestContractTest` xác nhận metadata và audit. |
| Thiếu bằng chứng cạnh tranh cho mutation mới. | Sáu tình huống chạy đồng thời với process riêng trên MySQL 8.4: hai proposal, hai review, hai slot overlap, accept thắng/sửa slot, sửa slot thắng/accept, accept-vs-suspend theo cả hai thứ tự. `ListingWorkflowConcurrencyTest` kiểm response và trạng thái DB/event/audit sau commit. |

### A. Chốt đầu vào và chuẩn bị

- [x] **01 — Chốt contract và quyết định.**
  - **Tiêu chí:** phương án A/B và T03-D01–D12 được xác nhận; NFR, quyền restore, archive khi có booking, version/pending, overlap, giới hạn ảnh và schema rõ ràng.
  - **Bằng chứng/kết quả:** [contract v1.0 §17](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#17-decision-log-đã-chốt>) ghi người chốt **Chủ dự án (người dùng)**, bên audit/soạn đề xuất **Codex**, ngày **03/11/2026**, phạm vi audit, căn cứ, phương án đã xét, đánh đổi và giới hạn từng D01–D12. Chốt A; tái sử dụng schema/reader/auth; chỉ thêm một cột suspension source; giữ NFR kế thừa là mục tiêu chưa đo; thu hẹp pipeline/cleanup ảnh theo D08. Không còn quyết định chờ xác nhận trong task này. Kết quả nghiệm thu implementation được ghi tại Task 18.

- [x] **02 — Baseline, môi trường test và phạm vi.**
  - **Tiêu chí:** baseline và thành phần tái sử dụng được ghi nhận; DB test biệt lập; phạm vi/owner/thứ tự được xác định; timebox 10 giờ được ghi nhận; không ảnh hưởng DB dev/production.
  - **Bằng chứng/kết quả:** baseline SHA nêu ở trên; owner/order ghi trong plan; đã sử dụng timebox 10 giờ. Runner test dùng MySQL riêng trên `tmpfs`; người dùng bổ sung `docker ps` với container `8d82851b5526` / `pettel-be-t03-db-test-1`, image `mysql:8.4`, trạng thái healthy; `docker inspect` trả Compose project `pettel-be-t03`; [ảnh Portainer do người dùng cung cấp](/tmp/codex-clipboard-00a12fc9-954f-4af2-b49c-ed33fa9907c8.png) cho thấy cùng stack/container và không có published ports. Đây là output/ảnh người dùng cung cấp; lần xác minh live sau test cũng thấy container `pettel-be-t03-db-test-1` vẫn healthy. Đã chạy baseline sạch bằng snapshot SHA `46a4cd4e602f48e5e7b41df25e195d7769f5a75d` tại `/tmp/pettel-be-t03-baseline` với project Compose riêng `pettel-be-t03-baseline`: migration/seed hai lần đạt; Pint **105 files PASS**; PHPStan **73 files, 0 errors**; **243 tests passed, 2.763 assertions**, 14.55s. Full suite mới nhất trên working tree: Pint **124 files PASS**, PHPStan **84 files/0 lỗi**, migrate fresh + seed hai lần và **297 tests/3.532 assertions**; migration upgrade integration riêng **1 test/15 assertions** tại Task 03. Không tạo lại bảng/module đã bàn giao. SHA/CI của commit bàn giao thuộc Task 16/18.

### B. Dữ liệu và quyền/transaction

- [x] **03 — Nguồn đình chỉ.**
  - **Tiêu chí:** migration chạy trên DB trống và DB có dữ liệu cũ; backfill SUSPENDED thành ADMIN; CHECK giữ đúng trạng thái/source; rollback/reapply không mất dữ liệu.
  - **Bằng chứng/kết quả:** migration mới thêm `suspension_source`, backfill SUSPENDED cũ thành `ADMIN`, thêm MySQL CHECK. Ngoài `migrate:fresh`, integration test MySQL riêng `tests/Integration/ListingSuspensionSourceMigrationTest.php` đạt **1 test/15 assertions**: schema legacy có ACTIVE lẫn SUSPENDED, backfill đúng nguồn, DB từ chối cặp trạng thái/source sai, rollback/reapply giữ listing và booking CONFIRMED.

- [x] **04 — Quyền, thứ tự lỗi, version và audit.**
  - **Tiêu chí:** quyền owner/admin, 404/403, trường hệ thống, version/no-op và audit nguyên tử đáp ứng contract; lỗi audit phải rollback mutation.
  - **Bằng chứng/kết quả:** route giữ middleware role/account và Sanctum stateful; lookup owner/parent được thực hiện trước validation body ở lifecycle/slot/change review; caregiver sai owner/child nhận 404, admin tự duyệt nhận 403, client bị chặn ghi `suspension_source`. No-op DRAFT và slot không tăng version; audit ghi `from`/`to`, version và request ID tương quan (`X-Request-ID` hoặc UUID theo request), nằm cùng transaction. Test giả lập lỗi audit xác nhận listing rollback. Production-mode HTTP smoke test không gửi CSRF token trả **419**. Full suite MySQL mới nhất: **297 tests / 3.532 assertions**, Pint **124 files PASS**, PHPStan **84 files / 0 lỗi**.

### C. Upload và quản lý ảnh

- [x] **05 — Upload ảnh private.**
  - **Tiêu chí:** JPEG/PNG/WebP; MIME giả/SVG/executable và giới hạn byte/pixel bị chặn; ảnh/thumbnail đồng bộ trước READY; lỗi storage/DB có cleanup, cleanup lỗi ghi log cho retry thủ công theo D08; không lộ key/hash; 413 được công bố theo tầng.
  - **Bằng chứng/kết quả:** `ListingMediaTest` xác nhận upload JPEG/PNG/WebP, byte limit 10 MiB và từ chối file lớn hơn/ảnh hơn 20 MP, MIME giả/SVG/executable. Owner/key/hash/state/visibility lấy từ server; JSON không lộ key/hash; READY chỉ có sau khi ảnh và thumbnail tồn tại. Lỗi DB xóa cả hai file; lỗi ghi thumbnail không tạo media row, lỗi cleanup log key còn cần retry; client nhận lỗi tổng quát. `backend/Dockerfile` đặt upload/post limit 12M/13M và `docker/nginx/default.conf` đặt 12m; HTTP smoke test qua Nginx xác nhận payload 13 MiB trả **413**.

- [x] **06 — Gắn/tháo/sắp xếp ảnh và preview.**
  - **Tiêu chí:** media DRAFT được thay/reorder; ownership/state/reference được kiểm tra; pivot chỉ đổi ở trạng thái được phép; preview cần auth; detach không xóa file còn nghĩa vụ lưu.
  - **Bằng chứng/kết quả:** `PUT /caregiver/listings/{id}/media` nhận danh sách rỗng để tháo hết, đổi thứ tự có swap, chặn ID trùng, owner khác, `PENDING_DELETE`, ảnh đang dùng cho pet/care update và danh sách quá 10. Chỉ DRAFT được đổi pivot; test bao phủ PENDING_REVIEW/ACTIVE/SUSPENDED/ARCHIVED và rollback pivot/version khi audit lỗi. Preview caregiver/admin và media trong change request được scope đúng listing/request, yêu cầu xác thực và chỉ phục vụ READY; resource không trả media chưa READY. Detach giữ nguyên DB media cùng ảnh/thumbnail vật lý. Bằng chứng nằm trong `ListingMediaTest`.

### D. Lifecycle và change request

- [x] **07 — Submit/approve/reject lần đầu.**
  - **Tiêu chí:** DRAFT → PENDING_REVIEW; admin khác chủ approve thành ACTIVE hoặc reject về DRAFT có lý do; kiểm tra caregiver/nội dung/media lúc approve; không lặp version/event; `published_at` giữ lần đầu.
  - **Bằng chứng/kết quả:** `ListingLifecycleContractTest` xác nhận listing tạo qua API thật không có ảnh/slot vẫn submit/approve; pending khóa nội dung; approve kiểm lại caregiver/nội dung/media; reject cần lý do; restore giữ `published_at`; replay không tăng version/audit; lỗi audit rollback. `ListingLifecycleTest` bao phủ self-review và public visibility. Đã chạy trên MySQL 8.4 biệt lập.

- [x] **08 — Suspend/restore/archive.**
  - **Tiêu chí:** caregiver chỉ restore nguồn CAREGIVER; admin có thể suspend/restore theo quyền; caregiver được kiểm tra lại; archive chặn mọi booking giữ lịch, kể cả hold quá hạn; ARCHIVED terminal; lịch sử booking được giữ; pending bị rút nguyên tử.
  - **Bằng chứng/kết quả:** `ListingLifecycleContractTest` kiểm tra caregiver/admin và suspension source; caregiver không restore ADMIN, admin nâng source và restore; restore kiểm điều kiện caregiver. Archive bị chặn bởi cả `AWAITING_PAYMENT` quá hạn, `CONFIRMED`, `IN_PROGRESS`, `AWAITING_CONFIRMATION`; ARCHIVED terminal. REQUESTED không chặn archive. Suspend/archive rút pending trong transaction; booking và status events giữ nguyên; lỗi audit rollback cả mutation và withdrawal.

- [x] **09 — Create/read/withdraw change request.**
  - **Tiêu chí:** allowlist/no-op/stale base/one pending; chỉ owner đọc/rút; withdraw không tăng version; request không đổi public content; media pending được giữ; lifecycle vô hiệu request.
  - **Bằng chứng/kết quả:** `ListingChangeRequestContractTest` kiểm tra allowlist cấp field và media lồng nhau, no-op, stale base, một pending/listing, owner history/detail/withdraw và 404 với chủ khác hoặc request lệch listing. Create/withdraw không đổi bản public/version; media tham chiếu và file còn nguyên; suspend/archive hủy pending. Bằng chứng cạnh tranh hai create đồng thời thuộc Task 14.

- [x] **10 — Approve/reject change request.**
  - **Tiêu chí:** approve áp dụng toàn bộ nội dung/media đúng một lần và tăng version một; reject có reason nhưng không đổi bản duyệt; không self-review; SUSPENDED không tự restore; rollback audit/pivot lỗi.
  - **Bằng chứng/kết quả:** `ListingChangeRequestContractTest` kiểm tra approve nội dung/media một lần và tăng version một; reject cần lý do và giữ bản duyệt; stale/replay/self-review; caregiver mất điều kiện; approve khi SUSPENDED không tự ACTIVE; lỗi audit/pivot rollback; booking row và status events giữ nguyên. Field lạ bị từ chối đúng 422 `VALIDATION_ERROR`.

### E. Lịch và public integration

- [x] **11 — CRUD slot, overlap và bảo vệ lịch booking.**
  - **Tiêu chí:** offset/time ordering hợp lệ; slot overlap conflict; tiếp giáp giữ riêng; server gán owner; mutation không làm booking giữ lịch mất slot phủ; REQUESTED không block sửa nhưng accept phải kiểm lịch mới; ARCHIVED không ghi; slot không tăng listing version.
  - **Bằng chứng/kết quả:** `ListingAvailabilityContractTest` kiểm offset và UTC microsecond, interval nửa mở, slot tiếp giáp/overlap, pagination, đủ bốn trạng thái booking giữ lịch kể cả hold `AWAITING_PAYMENT` đã quá hạn, REQUESTED không chặn sửa nhưng accept đọc lịch mới, slot ARCHIVED không ghi và version không tăng. `ListingWorkflowConcurrencyTest` kiểm hai lần ghi overlap và cả hai thứ tự đua accept/sửa lịch. Full MySQL suite đạt.

- [x] **12 — Public media/search/booking integration.**
  - **Tiêu chí:** chỉ media thuộc listing public được phục vụ; ảnh private/pending bị ẩn; suspend/detach chặn request mới; response không lộ nội bộ; search/quote/create/accept dùng lifecycle/lịch đồng nhất; reject REQUESTED và snapshot rules được giữ.
  - **Bằng chứng/kết quả:** `ListingPublicBookingIntegrationTest` dùng API thật kiểm slot API → public search/quote/create/accept; suspend ẩn public listing/media, chặn quote/create/accept mới nhưng vẫn cho reject REQUESTED; approve đổi giá giữ snapshot booking cũ còn service/rules không tương thích khiến accept bị từ chối; detach ẩn ảnh public nhưng giữ file. Public media route kiểm listing/media/pivot/state/visibility và trả `no-store`/`nosniff`. Full suite hồi quy các test search/booking hiện có.

- [x] **13 — API/resource/pagination/error contract.**
  - **Tiêu chí:** đủ route đã chốt; status/filter/pagination; resource allowlist/ID/time; lỗi 401/403/404/409/422 đúng code/thứ tự; unknown fields/query; DRAFT PATCH/DELETE không đổi semantics.
  - **Bằng chứng/kết quả:** đã đối chiếu route caregiver/admin/public với contract §13–15. `ListingChangeRequestContractTest` kiểm caregiver listing mặc định DRAFT, `status=ALL`, hai trang `per_page`, admin mặc định PENDING_REVIEW, status filter/pagination, queue/history pagination, summary `latest_decision`/`pending_change_request_id`, ID dạng chuỗi, thời gian UTC và unknown status/query 422. Test các feature contract xác nhận 404 owner/child trước body sai, 403 quyền, 409 state/availability, 422 payload/query; route PATCH/DELETE DRAFT hiện có giữ semantics cũ. Resource/envelope BE-T03 đã chuẩn hóa.

### F. Tích hợp và bàn giao

- [x] **14 — Concurrency MySQL.**
  - **Tiêu chí:** kiểm thử song song create request, review, slot write; race accept/slot và accept/suspend; assertions sau commit; thứ tự khóa tương thích API booking.
  - **Bằng chứng/kết quả:** `ListingWorkflowConcurrencyTest` chạy sáu race bằng process HTTP riêng trên MySQL 8.4, không mock lock: chỉ một create pending, chỉ một review apply, chỉ một slot overlap; accept thắng thì đổi slot bị chặn, slot thắng thì accept đọc lịch mới; accept/suspend chạy theo cả hai winner order. Assertions kiểm response, listing/request/slot/booking, audit và booking events sau commit; full runner đạt.

- [x] **15 — Rollback, quyền ảnh và lịch sử.**
  - **Tiêu chí:** lỗi không để lệch listing/version/pivot/request/audit; file có cleanup; ảnh pending/private không public; snapshot/event cũ không bị sửa; không cancel/refund.
  - **Bằng chứng/kết quả:** fault-injection xác nhận lỗi audit rollback lifecycle/change/pivot và withdrawal; lỗi DB upload xóa ảnh + thumbnail, lỗi cleanup ghi log retry mà không trả key; lỗi pivot rollback nội dung/version/request/audit. Tests kiểm pending/private media không public, detach giữ media row/file/thumbnail, approve/reject/lifecycle không sửa booking snapshot hoặc status events. `ListingSuspensionSourceMigrationTest` rollback/reapply migration giữ listing và booking. Không thêm cancel/refund.

- [x] **16 — Hồi quy trên commit bàn giao.**
  - **Tiêu chí:** repo CI check/tests đạt trên commit cuối; có SHA/DB/migration/seed/test evidence; không hồi quy các module liên quan.
  - **Bằng chứng/kết quả:** local runner trên MySQL 8.4/tmpfs đạt Pint **124 files PASS**, PHPStan **84 files/0 lỗi**, migrate fresh, seed hai lần, **297 tests / 3.532 assertions**; migration upgrade/backfill/rollback riêng đạt **1 test / 15 assertions**. GitHub Actions [Backend CI run #18](https://github.com/Huuhoang-1208/PETTEL/actions/runs/41580892240) kết luận **success** cho PR #17, head SHA `62090311845925cd825aea0773954f0905899a9b`; merge commit trên `main` là `e4379f130d90a4341ee7cb5389670e8ab4121402`. Ảnh PR xác nhận check pass và không có conflict. Theo D12, hiệu năng/recovery vẫn chưa đo.

- [x] **17 — Đồng bộ tài liệu và bàn giao downstream.**
  - **Tiêu chí:** tài liệu nghiệp vụ/schema/API/error/lock thống nhất; bảng T03-01–15 có truy vết; bàn giao cho H03/HA03/HA02 nêu đúng trạng thái.
  - **Bằng chứng/kết quả:** M03, workflow, P0, database design, API/error và storage design cùng bảng traceability T03-01–15 đã được đưa vào PR #17 (**45 files**) và merge vào `main` ở SHA `e4379f130d90a4341ee7cb5389670e8ab4121402`. CI run #18 thành công. PR liên kết issue [BE-T03 #16](https://github.com/Huuhoang-1208/PETTEL/issues/16), hiện đã đóng. Contract, API, migration, test và trạng thái NFR/reviewer được ghi trong báo cáo này; Task 17 hoàn thành.

- [x] **18 — Reviewer Hào nghiệm thu.**
  - **Tiêu chí:** reviewer xác nhận diff/contract/concurrency/permissions/snapshots; CI đạt trên commit cuối; các yêu cầu sửa xong; downstream nhận schema/API cuối.
  - **Bằng chứng/kết quả:** PR #17 đã merge, CI pass và issue #16 đã đóng. Chủ dự án xác nhận Hào đã nghiệm thu trong cuộc trao đổi ngày **03/11/2026**. GitHub không có review submission riêng trên PR #17, nên hồ sơ ghi nguồn xác nhận là chủ dự án, không gán xác nhận đó cho ảnh PR.

### Traceability contract T03-01–15 → bằng chứng

| Contract | Test/bằng chứng chính |
| --- | --- |
| T03-01 | `ListingLifecycleContractTest::test_api_created_listing_without_media_or_slots_can_be_reviewed_once`; submit/approve/reject và replay. |
| T03-02 | `ListingLifecycleTest`, `ListingChangeRequestContractTest`, `ListingAvailabilityContractTest`, `ListingMediaTest`, `CaregiverListingTest`; auth, owner/child scope và thứ tự lỗi. |
| T03-03 | `ListingPublicBookingIntegrationTest::test_suspension_hides_public_data_blocks_new_and_accept_but_allows_rejecting_requested`; snapshot/event booking được giữ. |
| T03-04 | `ListingLifecycleContractTest`; nguồn suspension, restore owner/admin, reason và điều kiện caregiver. |
| T03-05 | `ListingLifecycleContractTest`; hold quá hạn và các trạng thái giữ lịch chặn archive; archive terminal, không hard-delete history. |
| T03-06 | `ListingChangeRequestContractTest`; public content không đổi khi pending/reject/withdraw, approve nguyên tử và version tăng một. |
| T03-07 | `ListingWorkflowConcurrencyTest`; concurrent create/review chỉ một mutation thắng. |
| T03-08 | `ListingLifecycleContractTest`, `ListingChangeRequestContractTest`; pending withdrawal và rollback khi audit/pivot lỗi. |
| T03-09 | `ListingPublicBookingIntegrationTest::test_approved_price_changes_keep_request_snapshot_acceptable_but_service_and_rules_changes_invalidate_it`; booking snapshot giữ nguyên. |
| T03-10 | `ListingAvailabilityContractTest`, `PublicListingAvailabilityTest`; offset/UTC/microsecond, half-open interval, adjacency, overlap và soft delete. |
| T03-11 | `ListingAvailabilityContractTest`, `ListingPublicBookingIntegrationTest::test_slot_api_controls_public_search_quote_create_and_accept`; coverage, REQUESTED và đồng nhất search/quote/accept. |
| T03-12 | `ListingWorkflowConcurrencyTest`; sáu race trên MySQL 8.4 với process riêng và assertions sau commit. |
| T03-13 | `ListingMediaTest`; MIME/size/pixel/owner/state, cleanup, thumbnail, reorder/swap và rollback. |
| T03-14 | `ListingMediaTest`, `ListingPublicBookingIntegrationTest`; pending/private hidden, suspend/detach 404, public allowlist và giữ file. |
| T03-15 | Toàn bộ suite; auth, HA02, search, create/idempotency, HA03 và BE-T03 đạt **297 tests / 3.532 assertions**. |

## Tóm tắt quyết định bàn giao

- Code đã bổ sung: listing lifecycle; suspension source + migration; change request; availability slot CRUD/coverage; upload/media access; public media resource/route; audit hooks; cập nhật fixture regression.
- Đã chạy local: Pint **124 files PASS**, PHPStan **84 files/0 lỗi**, migration/seed hai lần trên MySQL 8.4 test riêng, toàn bộ **297 tests/3.532 assertions**, migration upgrade/backfill/rollback **1 test/15 assertions**, cùng sáu race MySQL BE-T03. GitHub CI run #18 pass trên PR #17; head SHA `6209031`, merge SHA `e4379f1`.
- Thiết kế đã chốt bởi Chủ dự án (người dùng), trên đề xuất audit của Codex: D01–D12, contract v1.0 ngày 03/11/2026. Căn cứ/phương án bị loại và giới hạn scope đã được ghi; không cần hỏi người dùng xác nhận riêng các quyết định này.
- Task 16–18 hoàn thành theo bằng chứng CI, tài liệu/traceability đã merge và xác nhận nghiệm thu Hào do chủ dự án cung cấp. PR #17 đã merge và issue #16 đã đóng.
- Báo cáo cập nhật ngày 03/11/2026. GitHub PR không lưu review submission riêng; nguồn xác nhận nghiệm thu reviewer là chủ dự án.
