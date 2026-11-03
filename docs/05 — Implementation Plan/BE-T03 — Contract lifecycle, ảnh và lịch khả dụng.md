# BE-T03 — Contract lifecycle, ảnh và lịch khả dụng

Issue: [#16](https://github.com/Huuhoang-1208/PETTEL/issues/16). Owner triển khai: Thịnh. Reviewer: Hào. Ngày: 02/11/2026. Baseline khảo sát: `46a4cd4e602f48e5e7b41df25e195d7769f5a75d`.

**Trạng thái: ĐÃ CHỐT THIẾT KẾ — BE-T03 contract v1.0, ngày 03/11/2026. Người chốt D01–D12: Chủ dự án (người dùng). Codex thực hiện audit baseline, working tree, tài liệu và các issue phụ thuộc, đồng thời soạn đề xuất.** Quyết định và căn cứ nằm tại §17. Vai trò người chốt được ghi theo xác nhận của chủ dự án trong cuộc trao đổi ngày 03/11/2026. Codex là bên audit/soạn thảo; Hào chưa nghiệm thu implementation. Thịnh/Hào vẫn giữ vai trò triển khai/reviewer theo issue gốc; nghiệm thu code của reviewer là bước riêng. Bằng chứng implementation/test và các khoảng trống xem trong [báo cáo thực hiện issue #16](<BE-T03 — Báo cáo thực hiện issue %2316.md>). Chốt thiết kế không xác nhận code đã đáp ứng đầy đủ contract hoặc issue đã hoàn thành.

Đọc cùng [bản rà soát baseline](<BE-T03 — Rà soát phạm vi và đầu vào contract.md>). Quy tắc nền lấy từ [P0](<../02 — Domain Documentation/05 — P0 Decision Contract.md>), [M03](<../03 — Modules Documentation/M03 — Caregiver Profiles & Listings.md>), [schema](<../08 — Architecture & Technical Design/database design.md>), [API chung](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) và [bàn giao ảnh/lịch](<BE-HA02 — Bàn giao ảnh và lịch cho BE-T03.md>). §17 ghi rõ phần kế thừa, phần chốt bổ sung và đánh đổi. Tài liệu nguồn cũ vẫn cần đồng bộ theo §18; khi đối chiếu riêng BE-T03 dùng contract v1.0 này cùng các bất biến P0 đã chốt, không diễn giải các bản thảo cũ thành quyền hoặc trạng thái mới.

## 1. Mục tiêu và giới hạn đã xác nhận

- Hoàn thiện lifecycle listing, quyền caregiver/admin, change request, ảnh và lịch trên schema hiện có.
- Dùng cùng dữ liệu/khóa listing với search/create/accept; bảo toàn snapshot và lịch sử booking.
- Bàn giao API, mã lỗi, trạng thái, transaction và tiêu chí nghiệm thu cho Thịnh/Hào/Hoàng.
- Chưa thực hiện frontend/admin UI, payment/expiry/refund, công suất chung nhiều listing, verification thật hoặc giá linh hoạt.
- Không coi trạng thái CLOSED của issue phụ thuộc là thay thế kiểm thử tích hợp BE-T03.

## 2. Hai phương án khả thi

| Phương án | Cách xử lý | Đánh đổi |
| --- | --- | --- |
| **A — Chủ dự án chốt cho P0** | Mọi nội dung listing đã duyệt và danh sách ảnh đều đi qua change request. Lịch là dữ liệu vận hành riêng. Thêm một trường nguồn suspension; tái sử dụng audit. | Giữ PATCH chỉ dành cho DRAFT như API HA02; đổi mô tả hoặc thứ tự ảnh cũng phải chờ duyệt. Không cần nhánh sửa ACTIVE trực tiếp hoặc bảng revision mới. |
| B | Chỉ giá/service/rules/ảnh mới cần duyệt; title/description hoặc thứ tự ảnh được sửa trực tiếp. | Thao tác nhanh hơn nhưng phải quản lý allowlist, nội dung chưa duyệt, version stale và nhiều tổ hợp test hơn. |

Chốt A vì API baseline chưa cho sửa ACTIVE trực tiếp; schema chỉ nói trường trình bày “có thể” sửa trực tiếp, không bắt buộc phải có khả năng đó. B sẽ mở thêm nhánh quyền, ghi trực tiếp và xử lý pending/version. Không chuyển ACTIVE về PENDING_REVIEW khi sửa vì trái P0 giữ bản đã duyệt công khai. Đây là lựa chọn cho P0; đánh đổi là caregiver phải chờ duyệt cả thay đổi trình bày.

## 3. NFR kế thừa và giới hạn nghiệm thu

Giữ bối cảnh demo hiện có: 5.000 ACTIVE listings, 10.000 booking, 50 người đồng thời; mục tiêu p95 search ≤2 giây/detail ≤1 giây/booking write ≤800 ms khi không tranh chấp, server error <1%. Đây là mục tiêu NFR chưa đo, không phải số liệu đã đạt hoặc điều kiện hiệu năng mới để đóng #16. Issue này phải đạt tính đúng đắn/quyền/nhất quán và hồi quy theo §16; không mở thêm dự án load test, cache hoặc search engine để đáp ứng một số đo chưa có. Upload không bị gán ngân sách 800 ms của booking write.

Sanctum cookie/session, CSRF, account ACTIVE, role và quyền tài nguyên là bắt buộc. DB là nguồn chuẩn lịch; cache không được cấp quyền hoặc quyết định chỗ trống. Quyết định review/lifecycle/payload/version/audit phải nguyên tử. File/DB cần cơ chế bù khi lỗi, không giả lập transaction xuyên storage.

Giữ mục tiêu recovery demo RPO ≤24 giờ/RTO ≤4 giờ; chưa thêm uptime SLO. Thịnh sở hữu triển khai #16, Hào review, Hoàng phối hợp search; BE-T04 sở hữu expiry/payment. Không mở rộng sang purge toàn hệ thống hoặc tái cấu trúc booking trong #16.

## 4. Bất biến kế thừa

1. Chỉ ACTIVE chưa xóa mềm, profile chưa xóa mềm, caregiver ACTIVE/còn CAREGIVER mới công khai và nhận booking mới. ACTIVE không có slot vẫn có thể hiển thị khi không lọc ngày, nhưng không nhận booking cho khoảng chưa mở.
2. Slot dùng UTC, microsecond, khoảng `[start_at,end_at)`. Capacity=1 theo listing, chưa chia sẻ theo caregiver. Một slot phải bao trọn booking; không ghép slot lúc đọc.
3. AWAITING_PAYMENT/CONFIRMED/IN_PROGRESS/AWAITING_CONFIRMATION giữ lịch. REQUESTED không giữ lịch. Hold quá hạn vẫn giữ cho đến khi luồng booking thực sự thả.
4. Listing đổi không sửa giá/snapshot/event booking cũ. Accept giữ giá cũ nếu chỉ giá đổi; service/price unit/rules khác snapshot trả INVALID_STATE theo BE-HA03.
5. Suspend/archive không tự cancel/refund booking. Reject REQUESTED hợp lệ vẫn có thể thực hiện khi listing ngừng ACTIVE.
6. Caregiver không tự duyệt; ARCHIVED là terminal; không hard delete listing có lịch sử. DELETE hiện có chỉ xóa mềm DRAFT.

## 5. Quyền và lifecycle

Tất cả route caregiver dùng auth + account.active + CAREGIVER, tra tài nguyên trong phạm vi owner. Admin dùng auth + account.active + ADMIN. Admin không thay quyền owner trên route caregiver. Không cho admin duyệt/từ chối listing hoặc change request của chính mình; trả 403 FORBIDDEN.

| Thao tác | Trạng thái trước → sau | Actor/điều kiện bổ sung |
| --- | --- | --- |
| Submit | DRAFT → PENDING_REVIEW | Caregiver sở hữu; toàn bộ nội dung và media hợp lệ. |
| Approve lần đầu | PENDING_REVIEW → ACTIVE | Admin khác chủ listing; đọc lại nội dung, caregiver và media hợp lệ; set published_at nếu chưa có. |
| Reject lần đầu | PENDING_REVIEW → DRAFT | Admin khác chủ listing; reason bắt buộc. |
| Suspend | ACTIVE → SUSPENDED | Owner hoặc admin; reason bắt buộc; lưu nguồn CAREGIVER/ADMIN. |
| Admin áp moderation | SUSPENDED → SUSPENDED | Chỉ admin, đổi nguồn thành ADMIN nếu trước là CAREGIVER; reason/audit; không cho caregiver ghi đè nguồn. |
| Restore | SUSPENDED → ACTIVE | Owner chỉ khi nguồn CAREGIVER; admin xử lý cả hai nguồn; caregiver/profile vẫn đủ điều kiện. |
| Archive | ACTIVE/SUSPENDED → ARCHIVED | Owner/admin; reason bắt buộc; không còn booking ở bốn trạng thái giữ lịch. |

Không có override archive đang giữ lịch trong #16. Dùng suspend để ngừng nhận mới; giải quyết booking bằng luồng hợp lệ rồi archive. REQUESTED không chặn archive; accept sau đó trả INVALID_STATE, reject vẫn theo BE-HA03.

## 6. Dữ liệu lifecycle và replay

Thêm migration forward **một cột** `listings.suspension_source VARCHAR(16) NULL`, CHECK giá trị CAREGIVER/ADMIN; bắt buộc khác null ở SUSPENDED và null ở trạng thái khác. Không sửa migration đã bàn giao. Listing SUSPENDED cũ chưa có nguồn được backfill ADMIN để không tự mở quyền restore. Quyết định schema này cần ghi vào BE-T01/database design theo §18; không thêm listing status hoặc bảng moderation mới.

Reason/actor/time lưu trong `audit_logs`, cùng transaction với thay đổi, không thêm bảng lifecycle event khác. Mỗi event có action rõ, resource listing/change request, from/to status, from/to version, source và request ID; không log nội dung riêng/file bytes. Resource quản lý trả quyết định gần nhất từ audit, không trả toàn bộ metadata nội bộ.

Mọi thay đổi thực sự nội dung listing đã lưu, ảnh DRAFT hoặc trạng thái/source tăng `listings.version` một lần. Không tăng version vì slot, upload chưa gắn, tạo/reject/withdraw change request. Approve change request tăng đúng một lần. No-op PATCH DRAFT không tăng. `published_at` giữ mốc publish lần đầu.

Action lặp sai trạng thái trả 409 INVALID_STATE, không thêm audit hoặc gia hạn. Không phát minh idempotency key cho #16; client mất response đọc lại resource. Pending request được hủy tự động WITHDRAWN khi lifecycle/version nội dung thay đổi ngoài approve chính request đó; audit ghi lý do SYSTEM_LISTING_CHANGED trong cùng transaction.

## 7. Review và change request

Allowlist nội dung gồm `title`, `description`, `service_type`, `area_code`, `price_vnd`, `price_unit`, `acceptance_rules_json` và `media`. Validation giữ quy tắc HA02. `media` là ordered array ở §11. Owner/status/version/capacity/published_at do server quản lý.

DRAFT sửa nội dung qua PATCH hiện có; thay ảnh qua PUT media. PENDING_REVIEW đóng sửa nội dung/ảnh; admin reject trả DRAFT để sửa/gửi lại. ACTIVE và SUSPENDED chỉ thay nội dung/ảnh bằng change request. Lịch quản lý riêng ở §9. ARCHIVED chỉ đọc.

Create change request nhận `base_listing_version` và `changes` chứa ít nhất một trường thực sự đổi. Server merge với bản đã duyệt, chuẩn hóa và lưu **toàn bộ bản nội dung đề xuất** vào `proposed_data_json` có `schema_version=1`; không lưu input tùy ý. Mỗi listing tối đa một PENDING_REVIEW, kiểm tra dưới khóa listing.

Admin approve chỉ khi listing còn ACTIVE/SUSPENDED, base version khớp, chủ/media/nội dung còn hợp lệ. Áp dụng payload/ảnh, tăng version, APPROVED/audit nguyên tử; giữ listing SUSPENDED nếu trước đó SUSPENDED. Không tự restore. Reject cần reason; withdraw chỉ owner; cả hai giữ nguyên bản đã duyệt. Không sửa request đang chờ: withdraw rồi tạo mới.

Suspend/restore/archive làm pending cũ WITHDRAWN theo §6; caregiver vẫn có thể tạo request mới khi SUSPENDED. Pending đã kết thúc không được review lại. Nếu base stale bất thường còn pending, approve trả 409 VERSION_CONFLICT và không áp dụng bản cũ.

## 8. Điều kiện review lần đầu

Không ép listing phải có ảnh hoặc slot để submit/approve trong P0; cho phép `media=[]`. Đây là lựa chọn mới để tránh thêm điều kiện chưa có trong acceptance criteria. Media đã khai báo phải hợp lệ và đã xử lý file xong; chưa có slot đồng nghĩa chưa nhận booking cho khoảng cụ thể.

Submit/approve kiểm tra lại bảy trường HA02, cặp DAYCARE/HOUR hoặc BOARDING/NIGHT, capacity=1, rules v1, profile và account/role caregiver. Admin review nội dung qua resource quản lý, không dùng route public để đọc PENDING_REVIEW.

Lý do reject/suspend/archive bắt buộc chuỗi trim/NFC dài 1–1.000 ký tự; không nhận blank/null. Approve có thể kèm reason cùng giới hạn. Caregiver được thấy lý do liên quan listing của mình. Reject không lưu thông tin tài khoản admin ngoài ID cần thiết cho audit.

## 9. Contract ghi slot

Owner tạo/sửa/xóa slot trên DRAFT, PENDING_REVIEW, ACTIVE hoặc SUSPENDED; ARCHIVED từ chối ghi. Slot không tham gia review nội dung và không tăng listing version. Không nhận listing_id/creator từ client.

POST/PATCH nhận đủ `start_at`, `end_at`: ISO 8601 có offset/Z, chuẩn hóa UTC và giữ microsecond, start < end, end > server now. Cho phép start đã qua nếu slot chưa kết thúc để kéo dài/thu hẹp lịch đang mở; không đặt thêm giới hạn ngày đặt trước. DELETE là soft delete, cho phép dọn slot quá khứ nếu không ảnh hưởng booking giữ lịch.

**Không tự merge:** các slot chưa xóa mềm không được overlap; POST/PATCH overlap slot khác trả 409 AVAILABILITY_CONFLICT. Hai slot tiếp giáp được giữ riêng; booking bắc qua hai slot vẫn không hợp lệ. Muốn một slot liên tục, caregiver chủ động chỉnh lịch; batch replace không thuộc #16.

Sau thay đổi dự kiến, mọi booking ở bốn trạng thái giữ lịch phải vẫn được **một slot** chưa xóa mềm bao trọn. Kiểm tra cả hold quá hạn; không tự chuyển booking. Không chặn vì REQUESTED, nhưng accept sau đó phải kiểm tra lịch lại. Nếu slot thay đổi không đạt coverage, rollback và trả 409 AVAILABILITY_CONFLICT; không trả ID/chi tiết booking trong lỗi.

## 10. Khóa và cạnh tranh

Mỗi mutation slot/lifecycle/media/change request: xác định tài nguyên/owner → mở transaction → khóa listing `FOR UPDATE` → đọc lại quyền/account/profile cần thiết → khóa các resource phụ liên quan → kiểm tra bằng current reads → ghi dữ liệu/version/audit → commit. Không đọc availability từ cache hoặc dùng snapshot cũ làm quyết định sau khi chờ khóa.

Không khóa booking trước listing. Nếu cần nhiều hàng, lấy theo ID tăng dần và giữ thứ tự thống nhất giữa các action. Create booking vẫn có owner lock trước listing như code hiện có; BE-T03 không lấy owner lock sau khi giữ listing. Kiểm tra lại thứ tự user/role/profile thực tế với BE-HA03 khi triển khai; retry transaction do deadlock có giới hạn, không retry I/O upload trong transaction.

Khi accept và sửa slot/suspend cùng tranh listing: accept thắng trước thì đã có hold và sửa lịch phá coverage bị từ chối; suspend sau vẫn được ngừng nhận mới mà giữ booking đã accept. Sửa lịch/suspend thắng trước thì accept thấy nguồn mới và trả AVAILABILITY_CONFLICT/INVALID_STATE. Không có kết quả accept thành công dựa trên nguồn cũ sau mutation đã commit.

## 11. Ảnh và lưu file

Chốt JPEG/PNG/WebP, file đầu vào 1–10.485.760 byte (10 MiB), tối đa 20 triệu pixel và 10 ảnh/listing. Kiểm tra MIME bằng nội dung, decode ảnh, đổi tên ngẫu nhiên, bỏ EXIF; không nhận SVG/executable. Sinh thumbnail giới hạn cạnh dài 640 px **đồng bộ trong upload** trước khi media READY; không thêm trạng thái media ngoài READY/PENDING_DELETE hiện có. Ngưỡng byte làm rõ đề xuất “10 MB” trước đây theo cấu hình 10.240 KiB đang dùng; 20 MP và 10 ảnh là giới hạn P0 được chủ dự án chốt trên đề xuất của Codex để hữu hạn hóa tài nguyên, chưa phải kết quả benchmark.

Upload dùng Laravel private local disk hiện có, server tự lưu owner/key/hash/MIME/size của ảnh đã xử lý; thumbnail dùng key suy ra từ object_key theo một quy ước cố định, không nhận key từ client. Chỉ trả media READY khi ảnh và thumbnail đã lưu thành công. File I/O ngoài transaction; storage/DB thất bại thì xóa các file vừa tạo. Nếu cleanup thất bại, ghi log vận hành nội bộ có key và lỗi để retry thủ công; không tạo READY giả, không trả key trong JSON công khai. #16 không thêm pipeline media bất đồng bộ, cleanup scheduler hoặc purge tổng quát. Worker Redis đã có trong compose nhưng không bắt buộc cho upload listing; đây là giới hạn P0, phần dọn lại cần vận hành nếu storage vẫn lỗi.

`media` nhận toàn bộ mảng `[{"media_object_id":"20","sort_order":1}]`, tối đa 10 phần tử, ID không trùng, sort_order liên tục 1..n. Chỉ media READY thuộc caregiver sở hữu; không nhận file đang tham chiếu bởi pet/care update để đưa sang listing công khai. Kiểm tra lại lúc apply; thay pivot trong transaction dưới khóa listing, tránh unique order khi swap.

DRAFT thay pivot trực tiếp; ACTIVE/SUSPENDED đưa danh sách mới vào change request, bản cũ vẫn dùng pivot đã duyệt. Admin approve lần đầu/change request đánh dấu media được duyệt PUBLIC_ELIGIBLE cùng transaction; quyền public vẫn phụ thuộc route/pivot/listing ở §12. Media trong pending là tham chiếu cần giữ, không được cleanup như object mồ côi. Sau reject/withdraw cũng không xóa file ngay nếu còn retention/report/tham chiếu khác.

## 12. Phục vụ và thu hồi ảnh

Public chỉ stream ảnh/thumbnail qua route gắn listing; mỗi request kiểm tra listing đủ điều kiện công khai, media READY, PUBLIC_ELIGIBLE và nằm trong pivot **đã duyệt**. Không dùng URL bucket/public disk cố định. Với bản DRAFT/chờ review, owner hoặc admin reviewer xem qua route quản lý đúng phạm vi, không qua route public.

Public route trả 404 khi listing bị ẩn, ảnh đã tháo, hoặc media chỉ có trong proposed payload; không tiết lộ object key/owner/hash. Preview private cũng chỉ phục vụ media READY và cần xác thực lại mỗi request. Dùng `Cache-Control: no-store` cho P0 để request mới không tiếp tục nhận ảnh sau suspend/detach; không hứa thu hồi byte mà người dùng đã tải.

Resource public list/detail thêm duy nhất `media: [{media_object_id, sort_order, image_url, thumbnail_url}]` từ pivot được duyệt. Rỗng trả `[]`. Không thêm raw availability slots, booking IDs hoặc snapshot. URL là route ứng dụng có kiểm tra quyền, không phải storage key. Detail/list dùng chung điều kiện. Cập nhật test allowlist của H03 có chủ đích khi triển khai.

## 13. API caregiver

Prefix `L = /api/caregiver/listings/{id}`. ID route dạng số nguyên dương, response ID dạng chuỗi; chỉ truy vấn listing chưa xóa mềm thuộc owner. Bảng dưới là contract cần đáp ứng; route đã được thêm trong working tree nhưng mức tuân thủ/test xem báo cáo, không suy ra hoàn thành từ việc có route.

| Method/path | Request | Thành công |
| --- | --- | --- |
| GET `/api/caregiver/listings` | `status` enum hoặc ALL; page/per_page | 200 pagination; mặc định DRAFT để giữ HA02; ALL gồm mọi trạng thái chưa xóa mềm. |
| GET `L` | — | 200 resource quản lý, gồm status/version/media/suspension_source/latest_decision/pending_change_request_id. |
| POST `L/submit` | `{}` | 200 listing PENDING_REVIEW. |
| POST `L/suspend`, `L/archive` | `{reason}` | 200 listing sau chuyển trạng thái. |
| POST `L/restore` | `{}` | 200 listing ACTIVE, chỉ suspension CAREGIVER. |
| GET `L/change-requests` | page/per_page | 200 lịch sử request của listing. |
| POST `L/change-requests` | `{base_listing_version, changes}` | 201 change request. |
| GET `L/change-requests/{requestId}` | — | 200 request gồm đề xuất đầy đủ/decision; phải thuộc đúng listing. |
| POST `L/change-requests/{requestId}/withdraw` | `{}` | 200 WITHDRAWN. |
| GET `L/availability-slots` | page/per_page | 200 slot chưa xóa mềm, start_at/id tăng dần. |
| POST `L/availability-slots` | `{start_at,end_at}` | 201 slot. |
| PATCH `L/availability-slots/{slotId}` | `{start_at,end_at}` | 200 slot; slot phải thuộc listing. |
| DELETE `L/availability-slots/{slotId}` | — | 204. |
| PUT `L/media` | `{media:[...]}` | 200 listing; chỉ DRAFT. Bản đã duyệt dùng change request. |
| POST `/api/caregiver/media` | multipart `file` | 201 `{data:{id,mime_type,byte_size}}`, READY. |
| GET `/api/caregiver/media/{mediaId}/content` | `variant=image|thumbnail` | 200 bytes, owner only; default image. |

PATCH/DELETE listing DRAFT giữ semantics HA02; không mở PATCH cho ACTIVE hoặc coi DELETE là archive. Tất cả collections mặc định 20/tối đa 50; unknown query/body keys trả 422. Quản lý listings ID giảm dần; change requests ID giảm dần.

## 14. API admin và public media

Prefix `A = /api/admin/listings/{id}`. Admin vẫn phải khác chủ listing khi quyết định review.

| Method/path | Request | Thành công |
| --- | --- | --- |
| GET `/api/admin/listings` | status enum/ALL, page/per_page; mặc định PENDING_REVIEW | 200 listing quản lý ID tăng dần. |
| GET `A` | — | 200 nội dung listing, media, quyết định và pending request; không kèm dữ liệu pet/booking riêng. |
| POST `A/approve` | `{reason?}` | 200 listing ACTIVE. |
| POST `A/reject` | `{reason}` | 200 listing DRAFT. |
| POST `A/suspend`, `A/archive` | `{reason}` | 200 listing sau xử lý. |
| POST `A/restore` | `{reason}` | 200 listing ACTIVE; clear suspension source. |
| GET `/api/admin/listing-change-requests` | status enum/ALL, page/per_page; mặc định PENDING_REVIEW | 200 queue ID tăng dần. |
| GET `A/change-requests/{requestId}` | — | 200 đề xuất và decision đúng listing. |
| POST `A/change-requests/{requestId}/approve` | `{reason?}` | 200 request APPROVED kèm listing_version mới. |
| POST `A/change-requests/{requestId}/reject` | `{reason}` | 200 request REJECTED. |
| GET `A/media/{mediaId}/content` | `variant=image|thumbnail`, `change_request_id?` | 200 bytes; phải thuộc pivot hoặc request đúng listing được admin đọc. |
| GET `/api/listings/{id}/media/{mediaId}/content` | `variant=image|thumbnail` | 200 bytes nếu §12 cho phép, ngược lại 404. |

Review request resource: `{id,listing_id,base_listing_version,status,proposed_data_json,requested_by_user_id,requested_at,reviewed_by_user_id,reviewed_at,decision_reason}`. Withdraw có reviewed_by/reviewed_at null; audit ghi actor/time withdraw. Timestamp ISO 8601 UTC, microsecond khi nghiệp vụ yêu cầu. Single JSON resource bọc `data`, collections `data/links/meta`; binary không bọc JSON.

## 15. HTTP và thứ tự lỗi

| Trường hợp | HTTP/code |
| --- | --- |
| Chưa auth, account/role không cho phép, CSRF sai | Giữ auth contract: 401, 403, 419. |
| Resource không tồn tại/xóa mềm, sai owner hoặc child không thuộc listing | 404 NOT_FOUND. |
| Admin tự review, caregiver restore nguồn ADMIN | 403 FORBIDDEN, chỉ sau khi đã xác định quyền thấy tài nguyên. |
| Sai state; pending trùng; archive còn booking giữ lịch; media không còn READY | 409 INVALID_STATE, message phân biệt. |
| Base version không khớp | 409 VERSION_CONFLICT. |
| Slot overlap hoặc mutation phá coverage booking giữ lịch | 409 AVAILABILITY_CONFLICT. |
| Body/query/reason/file không hợp lệ; media sai owner/ID không dùng được | 422 VALIDATION_ERROR theo trường, thông báo chung không xác nhận media người khác tồn tại. |
| Hạ tầng không lưu được file/DB | 5xx theo handler chung; không trả thành công hoặc lỗi validation giả. |

Thứ tự cho route tài nguyên: auth/account/role → tra scope owner/parent (404) → quyền action (403) → cấu trúc/field (422) → khóa và đọc lại → state/version/invariant (409) → ghi nguyên tử. Các FormRequest mới phải giữ thứ tự này; không mặc định controller đã bảo đảm nếu validation chạy trước lookup.

Lỗi nghiệp vụ dùng `error:{code,message,details,request_id}`. Không trả ID booking xung đột hoặc trạng thái account caregiver cho bên ngoài. Upload vượt giới hạn ở web server/PHP có thể trả 413 trước ứng dụng; cấu hình deployment phải cho phép file trong giới hạn hợp lệ và công bố HTTP 413 ở tầng này. Theo D10, response 413 của proxy không bắt buộc có JSON/error.code ứng dụng; không đổi handler auth/CSRF hiện tại.

## 16. Tiêu chí nghiệm thu phải có

| ID | Tình huống qua API thật và kết quả cần chứng minh |
| --- | --- |
| T03-01 | DRAFT submit → admin approve ACTIVE; hoặc reject có reason → DRAFT; caregiver không tự duyệt, kể cả có ADMIN. |
| T03-02 | Thiếu auth/role/CSRF; listing người khác; child ID thuộc listing khác; đều bị chặn trước khi lộ trạng thái/payload. |
| T03-03 | Suspend từ owner/admin ẩn list/detail/media và chặn quote/create/accept mới; giữ booking đã accept/snapshot/event cũ. Reject REQUESTED vẫn theo HA03. |
| T03-04 | Owner không restore suspension ADMIN; admin restore cần reason và caregiver đủ điều kiện. Admin nâng suspension CAREGIVER thành ADMIN được audit. |
| T03-05 | Archive còn booking giữ lịch bị chặn, kể cả hold quá hạn; khi không còn thì terminal, không restore/sửa; không hard delete lịch sử. |
| T03-06 | Giá/service/rules/title/description/area/media thay đổi đều chờ review; public vẫn dùng bản cũ. Reject/withdraw không thay bản cũ; approve nguyên tử và tăng version đúng một. |
| T03-07 | Hai create change request đồng thời chỉ một pending; hai admin approve/reject đồng thời chỉ một quyết định; stale approve không ghi dữ liệu. |
| T03-08 | Lifecycle mutation hủy pending cũ nguyên tử; approve khi SUSPENDED giữ nguyên SUSPENDED. Audit lỗi rollback cả payload/pivot/state/version. |
| T03-09 | Sau approve đổi giá, booking REQUESTED cũ vẫn accept giá snapshot; service/price unit/rules đổi thì accept INVALID_STATE; snapshot không sửa. |
| T03-10 | Slot offset/UTC/microsecond; overlap bị chặn; tiếp giáp không overlap nhưng booking không bắc qua hai slot; slot xóa mềm không dùng được. |
| T03-11 | Sửa/xóa slot phá một-slot-coverage bị chặn ở cả bốn trạng thái giữ lịch; REQUESTED không khóa; slot hợp lệ mới làm search/quote/accept nhất quán. |
| T03-12 | Hai ghi slot overlap đồng thời chỉ một thắng; accept/sửa slot và accept/suspend có kết quả theo thứ tự khóa §10 trên MySQL thực, kết nối riêng, fixture commit. |
| T03-13 | MIME giả/SVG/quá size/quá pixel/sai owner/PENDING_DELETE bị chặn; file hoặc DB lỗi không để READY giả; reorder swap không lỗi unique và rollback khi có lỗi. |
| T03-14 | Ảnh pending không công khai; suspend/detach chặn request ảnh mới; media pet/care update không bị lộ; public allowlist không thêm raw slot/booking/snapshot/key/hash. |
| T03-15 | Regression auth, HA02, search, create/idempotency và HA03 vẫn đạt; không đổi quy tắc expiry thuộc BE-T04. |

Không dùng fixture ghi status ACTIVE trực tiếp để thay T03-01/03/06. Test cạnh tranh cần DB MySQL riêng và khóa thật. Sau triển khai chạy runner dự án, ghi commit/CI/điều kiện môi trường; các mục tiêu tải §3 chỉ đánh dấu đạt khi có số đo.

## 17. Decision log đã chốt

**Người chốt cả D01–D12: Chủ dự án (người dùng). Ngày chốt: 03/11/2026, Asia/Ho_Chi_Minh. Phiên bản: v1.0. Bên audit và soạn đề xuất: Codex (AI assistant).** Chủ dự án yêu cầu Codex audit, cân nhắc và soạn phương án với phạm vi hẹp, tận dụng trạng thái dự án; chủ dự án là người chốt các quyết định theo đính chính trong cuộc trao đổi. Hào chưa nghiệm thu code. Không còn yêu cầu xác nhận riêng D01–D12 để tiếp tục trong phạm vi này.

### Audit trước khi quyết định

Audit đối chiếu **hai mốc**: baseline `46a4cd4e602f48e5e7b41df25e195d7769f5a75d` bằng `git show` và code BE-T03 chưa commit trong working tree `be-t03`. Không dùng việc đã viết một nhánh code làm lý do duy nhất để chọn thiết kế. Đã đọc lại nội dung issue bằng GitHub connector ngày 03/11/2026: #16 còn mở; #1/#2/#9/#12/#13 đã đóng. Audit này là đọc source/tài liệu; không thực hiện migration, thay đổi DB hoặc chạy lại test/đo tải.

| Nguồn được đối chiếu | Phát hiện liên quan tới quyết định |
| --- | --- |
| [#16](https://github.com/Huuhoang-1208/PETTEL/issues/16), [#1](https://github.com/Huuhoang-1208/PETTEL/issues/1), [#2](https://github.com/Huuhoang-1208/PETTEL/issues/2), [#9](https://github.com/Huuhoang-1208/PETTEL/issues/9), [#12](https://github.com/Huuhoang-1208/PETTEL/issues/12), [#13](https://github.com/Huuhoang-1208/PETTEL/issues/13) | #16 chỉ bao phủ lifecycle/ảnh/lịch và tích hợp quyền/booking; không yêu cầu UI, auto cancel/refund, tải lớn hoặc hệ thống moderation mới. Quyền/CSRF và quy tắc booking đã có chủ sở hữu ở issue phụ thuộc. |
| [P0 §2/8/9/12](<../02 — Domain Documentation/05 — P0 Decision Contract.md>), [M03](<../03 — Modules Documentation/M03 — Caregiver Profiles & Listings.md>), [schema M03](<../08 — Architecture & Technical Design/database design.md>) | Bản ACTIVE cũ phải tồn tại trong lúc chờ; snapshot giá/lịch sử giữ nguyên; trường trình bày chỉ “có thể” sửa trực tiếp, chưa có yêu cầu bắt buộc mở quyền này. |
| Baseline `backend/routes/api.php`, `CaregiverListingController::ensureDraft()` và [request HA02](../../backend/app/Http/Requests/ListingRequest.php) | PATCH/DELETE chỉ cho DRAFT; chưa có API sửa ACTIVE trực tiếp. A giữ semantics này; B cần thêm một đường ghi và quy tắc phối hợp pending/version. |
| [Migration BE-T01](../../backend/database/migrations/2026_10_28_000002_create_profiles_and_listing_schema.php), [bàn giao HA02](<BE-HA02 — Bàn giao ảnh và lịch cho BE-T03.md>) | Đã có JSON proposal, base version, audit, pivot ảnh có unique order và slot soft-delete. Chưa có nguồn suspension. Tái sử dụng chúng đủ cho #16; không cần revision/media/availability schema mới. |
| [CreateBooking](../../backend/app/Domain/Booking/Actions/CreateBooking.php), [ValidateBookingAcceptance](../../backend/app/Domain/Booking/Actions/ValidateBookingAcceptance.php), [ApplyListingAvailability](../../backend/app/Domain/Booking/Actions/ApplyListingAvailability.php), [bàn giao trong comment #13](https://github.com/Huuhoang-1208/PETTEL/issues/13#issuecomment-6577909352) | Create khóa owner trước listing; accept khóa listing trước booking. Reader và comment HA03 yêu cầu một slot bao trọn, bốn trạng thái giữ lịch và cùng listing lock cho BE-T03; hold quá hạn không tự mất hiệu lực. Không đổi khóa/reader/schema booking để thực hiện #16. |
| [Workflow listing](<../04 — Workflows/02 — Đăng bán & quản lý listing.md>), [API HA02/HA03](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) | Workflow bản thảo cũ ghi sai owner 403, trong khi contract/code HA02 đã dùng 404. Chốt theo API đã bàn giao, giữ auth/CSRF response hiện tại. |
| [Storage design](<../08 — Architecture & Technical Design/08 — File Storage Design.md>), [compose](../../compose.yaml), [media API hiện tại](../../backend/app/Http/Controllers/ListingMediaController.php) | Private local disk và worker Redis đã có; media schema chỉ có READY/PENDING_DELETE. Thumbnail đồng bộ phù hợp #16 hơn thêm processing state và pipeline queue; không cần S3/CDN/purge mới. |
| [Performance](<../06 — Non-functional Requirements/02 — Performance & Capacity.md>), [Recovery](<../06 — Non-functional Requirements/03 — Reliability & Recovery.md>), [test compose](../../compose.test.yaml) | NFR là mục tiêu demo chưa đo; MySQL test riêng đã có. Chốt mục tiêu kế thừa, không biến thành SLA/load-test project mới. Test concurrency và rollback vẫn bắt buộc vì trực tiếp bảo vệ acceptance criteria. |

### T03-D01

**Chốt A cho P0:** toàn bộ allowlist nội dung và danh sách/thứ tự ảnh trên ACTIVE/SUSPENDED đi qua change request; lịch là vận hành riêng. PATCH/DELETE cũ vẫn chỉ DRAFT.

**Căn cứ và đánh đổi:** P0 yêu cầu review tối thiểu giá/service/rules; schema cho phép nhưng không bắt buộc sửa trực tiếp presentation. Baseline HA02 chưa mở nhánh ACTIVE trực tiếp. Chọn A giữ một đường apply dưới review, dùng JSON proposal đã có. B nhanh hơn khi sửa mô tả nhưng cần thêm quyền/đường ghi và quyết định pending stale. **Giới hạn:** không thêm API sửa trực tiếp, revision table hoặc mở rộng schema; caregiver chờ duyệt cả thay đổi trình bày. Có nhu cầu sửa nhanh được xác nhận sau P0 thì xét B bằng issue riêng.

### T03-D02

**Chốt một cột `suspension_source=CAREGIVER|ADMIN|null` và migration forward hiện tại.** Owner chỉ restore nguồn CAREGIVER; admin xử lý cả hai nguồn; admin có thể nâng nguồn CAREGIVER thành ADMIN. Backfill SUSPENDED cũ thành ADMIN.

**Căn cứ và đánh đổi:** lifecycle nguồn đã cho owner/admin cùng đình chỉ nhưng chưa lưu nguồn. Chỉ nhìn SUSPENDED sẽ cho owner vượt moderation; suy nguồn từ audit không có schema/action lịch sử thống nhất và thêm truy vấn phụ thuộc dữ liệu cũ. Một cột cùng CHECK là thay đổi nhỏ có thể kiểm chứng. **Giới hạn:** dùng audit hiện có lưu lý do/lịch sử; không thêm status hoặc hệ thống moderation. Backfill bảo thủ có thể cần admin restore listing cũ do caregiver tự dừng; chấp nhận để không tự mở quyền.

### T03-D03

**Chốt archive bị chặn nếu còn bất kỳ booking AWAITING_PAYMENT/CONFIRMED/IN_PROGRESS/AWAITING_CONFIRMATION, kể cả thời gian đã qua hoặc hold quá hạn; không admin override.** REQUESTED không giữ lịch nên không chặn archive. Suspend là cách ngừng nhận mới khi còn nghĩa vụ.

**Căn cứ và đánh đổi:** workflow đã yêu cầu không làm mất nghĩa vụ; HA03 coi bốn status này vẫn giữ lịch tới khi luồng booking chuyển trạng thái thật. Dựa vào thời gian hoặc tự cancel/refund sẽ thay đổi BE-T04/payment. **Giới hạn:** chỉ kiểm tra status theo listing dưới khóa chung; không đổi booking, event, snapshot hoặc bổ sung thao tác xử lý nghĩa vụ. ARCHIVED vẫn terminal.

### T03-D04

**Chốt không bắt buộc ảnh hoặc slot để submit/approve lần đầu; `media=[]` hợp lệ.** Nội dung đã khai báo, caregiver và media được gắn phải hợp lệ khi review. Chưa có slot thì không có khoảng hợp lệ để tạo/accept booking.

**Căn cứ và đánh đổi:** không có minimum ảnh/slot trong #16, P0 hay HA02; reader search/booking đã tự kiểm tra availability. Thêm minimum sẽ tạo tiêu chí xuất bản và bài toán duy trì slot tương lai mới. **Giới hạn:** giữ validation bảy trường HA02/capacity/rules; listing không có ảnh/lịch có thể công khai nhưng không được tuyên bố là còn chỗ khi không khớp lịch.

### T03-D05

**Chốt version tăng đúng một cho thay đổi thực sự nội dung/pivot DRAFT/lifecycle/source và approve proposal.** Upload chưa gắn, CRUD slot, create/reject/withdraw request không tăng version. Lifecycle mutation rút pending cũ WITHDRAWN trong cùng transaction; không tự rebase. No-op không tăng.

**Căn cứ và đánh đổi:** đã có `listings.version`/`base_listing_version` và WITHDRAWN; không cần content-version riêng. Để slot tăng version sẽ làm proposal stale chỉ vì vận hành lịch. Giữ pending qua lifecycle đổi version đòi rebase hoặc review một base đã thay đổi. **Giới hạn:** dùng cột/status/audit hiện tại; caregiver cần gửi lại proposal sau thay đổi lifecycle. `published_at` giữ lần công khai đầu tiên.

### T03-D06

**Chốt ACTIVE/SUSPENDED được tạo change request; approve khi SUSPENDED giữ nguyên SUSPENDED; PENDING_REVIEW khóa sửa nội dung/ảnh.** Muốn sửa request chờ thì withdraw rồi tạo lại; restore luôn là action riêng có điều kiện D02.

**Căn cứ và đánh đổi:** hỗ trợ sửa thông tin để khắc phục đình chỉ bằng workflow review đã có; không cần đưa listing về DRAFT hoặc thêm trạng thái “sửa sau moderation”. Cho sửa pending tại chỗ làm admin có thể quyết định một payload khác payload vừa đọc. **Giới hạn:** không thêm edit-pending/reopen/archive-restore API; ARCHIVED chỉ đọc.

### T03-D07

**Chốt overlap slot trả 409 AVAILABILITY_CONFLICT, không tự merge; slot tiếp giáp giữ riêng.** Booking phải nằm trọn trong một slot. Sau sửa/xóa, mọi booking ở bốn status giữ lịch phải còn một slot bao trọn; REQUESTED không chặn thay lịch. Slot UTC/microsecond, đầu vào có offset, start < end và end > now khi tạo/sửa; cho start quá khứ nếu chưa kết thúc.

**Căn cứ và đánh đổi:** bàn giao HA02 yêu cầu chuẩn hóa không overlap dưới listing lock; HA03/search đã đọc một-slot coverage. Merge tự động hoặc batch replace đổi ID/semantics, đòi sửa reader/đường cập nhật hoặc logic gom khoảng. **Giới hạn:** CRUD riêng lẻ + soft delete hiện có; không thêm recurrence, capacity chung, timezone/calendar service hoặc migration mới.

### T03-D08

**Chốt JPEG/PNG/WebP; 10 MiB đầu vào, 20 triệu pixel, tối đa 10 ảnh; thumbnail cạnh dài tối đa 640 px xử lý đồng bộ, READY sau khi đủ cả hai file.** Private local disk, GD và metadata hiện có đủ cho P0. Ghi file ngoài transaction; lỗi thì bù bằng xóa file, cleanup lỗi phải có log nội bộ để vận hành retry thủ công.

**Căn cứ và đánh đổi:** storage/NFR đã nêu ba MIME và giới hạn khoảng 10 MB; code dùng 10.240 KiB. 20 MP/10 ảnh/640 px là ngưỡng mới được chủ dự án chốt trên đề xuất của Codex để giới hạn tài nguyên; chưa có benchmark bảo đảm latency. Queue worker đã tồn tại nhưng chuyển upload sang async cần trạng thái xử lý/chờ thumbnail và retry thêm. Sync đơn giản hơn với giới hạn hữu hạn và schema READY/PENDING_DELETE. **Giới hạn:** không thêm processing state, media pipeline, cleanup scheduler hoặc purge hệ thống; cleanup lỗi cần can thiệp vận hành, không được im lặng tạo READY giả. Phạm vi byte/pixel phải kiểm thử; không coi GD xử lý mọi file hợp lệ đã được chứng minh.

### T03-D09

**Chốt ảnh listing được phục vụ qua route ứng dụng kiểm lại listing/pivot/media mỗi request và `Cache-Control: no-store`; file vẫn private.** Proposal dùng ordered media IDs trong JSON sẵn có, bản công khai đọc pivot đã duyệt. Preview cần auth và đúng scope.

**Căn cứ và đánh đổi:** HA02 đã chốt PUBLIC_ELIGIBLE không phải quyền public; source storage cho phép stream qua controller. URL bucket/CDN/presigned dài hạn khó chặn ngay request mới sau suspend/detach; thêm revision/media-reference table không cần thiết vì proposal JSON đã có. **Giới hạn:** không thêm CDN/token revocation/S3; giảm hiệu quả cache là đánh đổi P0 chấp nhận. Không hứa thu hồi byte đã tải; giữ tham chiếu khi cleanup theo retention về sau.

### T03-D10

**Chốt 404 khi sai owner/parent; 403 khi nhìn thấy tài nguyên nhưng sai quyền action; 409 phân biệt INVALID_STATE, VERSION_CONFLICT và AVAILABILITY_CONFLICT.** Lỗi validation nghiệp vụ mới dùng VALIDATION_ERROR 422 cùng convention HA02; auth/role/account/CSRF giữ handler/status hiện tại của BE-H01. Không sửa global exception handler để ép auth về envelope mới.

**Căn cứ và đánh đổi:** code/contract HA02 đã dùng 404 và scoped lookup, còn workflow cũ ghi 403. Chốt theo module đã bàn giao; VERSION_CONFLICT chỉ thêm mã rõ cho base version đã có, không thêm status HTTP/framework lỗi. **Giới hạn:** sửa lỗi/envelope và thứ tự lookup cục bộ ở API BE-T03 khi cần; không refactor mọi module. Proxy/PHP có thể trả 413 trước ứng dụng và phải được tài liệu hóa theo tầng; không bắt buộc proxy có JSON `error.code` của ứng dụng.

### T03-D11

**Chốt transaction và listing row lock làm điểm tuần tự hóa cho mutation BE-T03; khóa/read lại child và dữ liệu quyết định trong cùng transaction; audit cùng commit; retry DB tối đa 3 lần theo pattern hiện tại.** Không lấy owner lock sau khi đã giữ listing; không retry file I/O trong transaction.

**Căn cứ và đánh đổi:** CreateBooking giữ owner lock rồi listing; accept giữ listing rồi caregiver/role/booking và current reads lịch. Redis lock hoặc chỉ khóa booking không bảo vệ slot chưa có booking hay hai review. Giữ cùng nguyên tắc giúp tránh sửa lại booking/idempotency. **Giới hạn:** không thêm lock service/distributed lock/isolation-level mới. Code có `lockForUpdate` chưa đủ bằng chứng; phải test MySQL cạnh tranh, rollback và reread sau chờ khóa theo T03-07/12.

### T03-D12

**Chốt kế thừa NFR demo hiện có làm mục tiêu chưa đo; không thêm SLA hoặc điều kiện load-test mới cho #16.** Giữ correctness/security/consistency và test hồi quy/concurrency là tiêu chí bắt buộc. Thịnh là owner triển khai, Hào reviewer và timebox 10 giờ là dữ kiện từ issue; chủ dự án là người chốt contract, Codex audit/soạn đề xuất; việc này không thay thế vai trò nghiệm thu Hào.

**Căn cứ và đánh đổi:** Performance/Recovery đang ghi rõ mục tiêu đề xuất, #16 acceptance tập trung quyền/lifecycle/booking snapshot. Thêm dự án benchmark, cache/search engine hoặc cam kết deadline mới sẽ mở phạm vi. **Giới hạn:** NFR và recovery vẫn ghi “chưa kiểm chứng”; không khẳng định đã đạt p95/RPO/RTO hoặc hoàn thành đủ scope trong 10 giờ. Giữ bất biến snapshot/booking, BE-T04 expiry/payment, frontend/admin UI, capacity chung, verification, purge và hạ tầng media nâng cao ngoài phạm vi này.

### Kết quả audit và tác động triển khai

Chốt A và giữ các cấu trúc/quy tắc đã có. Hai điều chỉnh làm hẹp nghĩa vụ từ bản dự thảo là D08 (thumbnail đồng bộ, cleanup bù + log retry thủ công thay pipeline/job cleanup mới) và D12 (NFR là mục tiêu kế thừa, không tự trở thành gate load-test mới). D10 làm rõ giữ auth/CSRF response hiện hành và 413 theo tầng. Các điểm này không cho phép bỏ validation, audit nguyên tử, bảo vệ lịch, test cạnh tranh hoặc giữ snapshot.

Audit lúc chốt contract phát hiện các phần chưa khớp: validation nội dung/media ở submit/approve, caregiver eligibility lúc approve change request, PUT media rỗng, thứ tự scope/validation, resource/pagination/audit và cleanup lỗi. Implementation hiện tại đã xử lý các phát hiện này và bổ sung kiểm thử integration/concurrency; traceability từng T03-01–15 và kết quả runner mới nhất nằm trong [báo cáo thực hiện](<BE-T03 — Báo cáo thực hiện issue %2316.md>). Các kết quả lịch sử 248/263 tests là snapshot trước các lượt bổ sung, không phải trạng thái hiện tại.

## 18. Tài liệu cần đồng bộ sau khi chốt

1. P0: bổ sung decision log BE-T03 và link contract; giữ các bất biến booking đã chốt.
2. M03/lifecycle/business rules/workflow: quyền restore, archive, trường cần review, 404 sai owner và giữ nghĩa vụ booking.
3. Database design/BE-T01: một cột suspension_source, backfill, version, pending và tham chiếu media JSON.
4. API chung: endpoint/resource/lỗi BE-T03, public media allowlist; phân biệt đã có route/code với đã đạt contract/test.
5. Storage design: chốt MIME/size/pixel/số ảnh, thumbnail và policy thu hồi; NFR vẫn là mục tiêu cần đo.
6. Bàn giao H03/HA03/HA02: ghi contract tích hợp đã chốt; giữ kết quả test/sign-off đúng bằng chứng lịch sử.

Chủ dự án đã chốt thiết kế tại §17 trên đề xuất audit của Codex; không chờ người dùng xác nhận D01–D12 thêm lần nữa. **Cập nhật trạng thái 03/11/2026:** các đoạn liên quan trong P0, M03/workflow, database design, API/error và storage đã được đồng bộ trong working tree; báo cáo implementation có traceability T03-01–15. Chưa có commit/SHA/CI bàn giao hoặc biên bản downstream, nên Task 16–17 chưa hoàn tất theo tiêu chí bàn giao. Reviewer nghiệm thu implementation và đóng issue vẫn là bước riêng, chưa có xác nhận.
