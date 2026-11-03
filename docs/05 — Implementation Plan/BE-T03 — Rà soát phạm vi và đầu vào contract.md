# BE-T03 — Rà soát phạm vi và đầu vào contract

Ngày rà soát: 02/11/2026. Issue: [#16](https://github.com/Huuhoang-1208/PETTEL/issues/16). Owner triển khai: Thịnh; reviewer: Hào. Timebox issue: 10 giờ, N6–N8; nhánh đề xuất `codex/4w-be-t03`.

**Trạng thái tài liệu:** bản khảo sát lịch sử tại baseline ngày 02/11/2026; các câu “chưa triển khai/chưa chốt” dưới đây mô tả mốc khảo sát đó. Cập nhật 03/11/2026: **Codex đã audit bổ sung và soạn đề xuất D01–D12 trong [contract v1.0 §17](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md#17-decision-log-đã-chốt>) theo yêu cầu của chủ dự án; người chốt là Chủ dự án (người dùng), theo đính chính trong cuộc trao đổi ngày 03/11/2026. Codex audit/soạn đề xuất; reviewer Hào chưa nghiệm thu code.** Xem [báo cáo hiện tại](<BE-T03 — Báo cáo thực hiện issue %2316.md>) để biết implementation/test/khoảng trống; giữ nguyên bằng chứng baseline trong tài liệu này, không suy ra nghiệm thu code từ quyết định thiết kế.

Baseline local: nhánh `be-t03`, HEAD `46a4cd4e602f48e5e7b41df25e195d7769f5a75d` (merge PR #15, BE-HA03). Working tree sạch trước khảo sát. Bằng chứng code dưới đây thuộc baseline này. Không chạy lại test/runtime trong đợt khảo sát tài liệu.

## 1. Kết luận về việc issue #16 cần làm

BE-T03 hoàn thiện **quyền chuyển trạng thái và quản lý dữ liệu listing**, trên schema đã bàn giao; không tạo lại module profile/listing nháp, search hoặc accept/reject booking.

Ba checkbox của issue phải đọc thành các nhóm hành vi sau:

| Nhóm | Công việc cần hoàn thiện theo nguồn hiện có | Điều kiện kết quả |
| --- | --- | --- |
| Lifecycle | Caregiver gửi DRAFT đi duyệt; admin duyệt hoặc trả về DRAFT có lý do; suspend/restore/archive theo quyền. | Caregiver không tự duyệt; ARCHIVED kết thúc; chỉ ACTIVE đủ điều kiện mới công khai/nhận booking. |
| Thay đổi bản đã duyệt | Change request cho giá, dịch vụ và điều kiện nhận pet trọng yếu; admin approve/reject, caregiver withdraw. | Bản cũ tiếp tục công khai trong lúc chờ; áp dụng bản mới và tăng version nguyên tử. |
| Lịch | API quản lý slot và kiểm tra quyền/thời gian/ảnh hưởng booking. | Không phá khoảng đã giữ lịch; dùng cùng dữ liệu và khóa listing với create/accept. |
| Ảnh | Upload, gắn/tháo/sắp xếp và phục vụ ảnh theo quyền/lifecycle. | Ảnh private mặc định; chỉ ảnh được phép công khai; không lộ object key hoặc ảnh riêng qua ID. |
| Tích hợp | Thay đổi trạng thái/lịch/giá qua API thật rồi kiểm tra search, quote/create, accept/reject. | Không chỉ sửa status bằng fixture rồi coi lifecycle đã hoàn tất. |
| Bàn giao | Ma trận quyền/trạng thái, API/lỗi, bất biến, quyết định còn thiếu và checklist nghiệm thu. | BE-H03/BE-HA03 có contract thống nhất để tích hợp; phân biệt contract với kết quả test/review. |

Phạm vi không bao gồm payment/expiry/refund (BE-T04), công suất chung giữa nhiều listing, verification thật, giá linh hoạt theo ngày hoặc frontend/admin UI. Đây là giới hạn P0/module hiện có, chưa phải quyết định bổ sung tính năng cho #16.

## 2. Đối chiếu các issue liên quan

GitHub tại thời điểm khảo sát: #1, #2, #9, #12, #13 đều CLOSED/completed; #16 OPEN, chưa có comment. Trạng thái issue không tự chứng minh mọi điều kiện tích hợp trong tài liệu đã được nghiệm thu.

| Nguồn | Đầu vào BE-T03 được kế thừa | Phần #16 vẫn phải làm |
| --- | --- | --- |
| [BE-H01 #1](https://github.com/Huuhoang-1208/PETTEL/issues/1) | Sanctum cookie/session + CSRF; account ACTIVE; role OWNER/CAREGIVER/ADMIN; admin không tự đăng ký. Nền `audit_logs`. | Quyền theo listing/media/slot/change request và các route admin; policy module không được thay bằng việc chỉ có role. |
| [BE-T01 #2](https://github.com/Huuhoang-1208/PETTEL/issues/2) | Bảng listing/media/slot/change request; status chuẩn; UTC/microsecond; VND nguyên; version và snapshot booking. | Nghiệp vụ, model/action/API còn thiếu. Không tạo migration trùng các bảng đã có. Nếu cần thêm dữ liệu phân biệt moderation thì ghi rõ thay đổi schema và lý do. |
| [BE-HA02 #9](https://github.com/Huuhoang-1208/PETTEL/issues/9) | Profile, CRUD DRAFT theo owner; validation; quan hệ ảnh theo thứ tự và slot chưa xóa mềm; fixture dùng chung. | Lifecycle, change request, upload/CRUD lịch, kiểm tra nghiệp vụ và quyền phục vụ ảnh. Model relation không cung cấp những API này. |
| [BE-H03 #12](https://github.com/Huuhoang-1208/PETTEL/issues/12) | Search/detail công khai, lọc lịch/giá; chỉ ACTIVE và caregiver hợp lệ; một slot bao trọn; bộ trạng thái giữ lịch. | Kiểm chứng lại khi nguồn listing/lịch được thay đổi qua API BE-T03. Resource công khai hiện chưa có ảnh. |
| [BE-HA03 #13](https://github.com/Huuhoang-1208/PETTEL/issues/13) | Accept/reject, khóa listing trước booking; current reads; snapshot không đổi; transaction/event; test cạnh tranh accept. | CRUD lịch/lifecycle/change request dùng cùng khóa listing; nghiệm thu cạnh tranh giữa BE-T03 và accept. Không viết lại accept/reject. |

Comment [bàn giao BE-HA03](https://github.com/Huuhoang-1208/PETTEL/issues/13#issuecomment-6577909352) báo local 243 tests/2.763 assertions, Pint/PHPStan/migration/seed PASS. Đây là **kết quả người bàn giao báo cáo**, không phải kết quả vừa chạy lại trong phiên này. Tài liệu H03/HA03 local còn câu “chờ tích hợp”/“chưa xác nhận reviewer”; không tự chuyển thành sign-off chỉ vì issue đóng.

## 3. Bằng chứng trực tiếp trong project

Đường dẫn dưới đây tính từ repository root.

| File | Hiện trạng quan sát được | Ý nghĩa với contract |
| --- | --- | --- |
| `backend/routes/api.php` | Có public listings, caregiver profile/listing DRAFT, quote/create booking, accept/reject; chưa có route submit/review/suspend/restore/archive, change request, media hoặc CRUD slot. | Các API chính của BE-T03 chưa được triển khai tại baseline. |
| `backend/app/Http/Controllers/CaregiverListingController.php` | Index chỉ DRAFT; show cho owner xem các trạng thái; PATCH/DELETE chỉ DRAFT, chạy transaction/lock listing; không phải archive. | Cần xác định cách quản lý các listing đã gửi/đang hoạt động; không vô tình thay đổi semantics DELETE DRAFT. |
| `backend/app/Http/Requests/ListingRequest.php` | Chặn client tự gửi status/version/capacity/owner; không nhận ảnh/lịch lồng; service pair và rules v1. | Duyệt/trạng thái/ảnh/lịch không đi qua mass assignment của API nháp. |
| `backend/database/migrations/2026_10_28_000002_create_profiles_and_listing_schema.php` | Có `listings`, `availability_slots`, `media_objects`, `listing_media`, `listing_change_requests`; FK RESTRICT, unique thứ tự ảnh, CHECK trạng thái/range. | Không thiếu schema nền; CHECK không kiểm tra overlap, quyền owner hoặc coverage booking. |
| Cùng migration | Change request có base version/payload/actor/reviewer/reason/time; index listing/status nhưng không có unique “một pending”. Listing không có cột lý do review/suspend hoặc actor suspend. | Một pending/listing phải được bảo vệ trong transaction theo BE-T01. Cần xác định nơi lưu và đọc lý do review/moderation. |
| `backend/database/migrations/2026_10_27_000001_create_identity_schema.php` | `audit_logs` có actor/action/resource/reason/request ID/metadata. | Có nền audit để kế thừa, không cần tự tạo bảng audit thứ hai. |
| `backend/app/Models/Listing.php`, `MediaObject.php`, `AvailabilitySlot.php` | Có quan hệ ảnh, slot, owner; slot giữ microsecond và soft delete; media ẩn owner/key/hash khi serialize model. | Quan hệ và hidden fields không thay thế policy phục vụ file hoặc validation ảnh/lịch. |
| `backend/app/Http/Controllers/PublicListingController.php` | Query dùng ACTIVE + profile/user/role hợp lệ. | Suspend phải ẩn đồng thời list/detail; không thêm ngoại lệ công khai cho chủ listing/admin. |
| `backend/app/Http/Resources/PublicListingResource.php` | Allowlist hiện chưa chứa ảnh hoặc raw slots. | Bổ sung ảnh cần contract response rõ; không xuất toàn bộ media/booking qua relation. |
| `backend/app/Domain/Booking/Actions/ApplyListingAvailability.php` | Search và accept kiểm tra một slot bao trọn, overlap nửa mở, bốn trạng thái giữ lịch; không bỏ qua hold chỉ vì hết hạn. | BE-T03 phải bảo toàn các bất biến này khi ghi slot. Không thêm nguồn availability khác. |
| `backend/app/Domain/Booking/Actions/CreateBooking.php` | Khóa owner rồi listing; tạo REQUESTED và snapshot/event nguyên tử; replay giữ kết quả cũ. | BE-T03 cần tương thích thứ tự khóa của luồng hiện có, tránh khóa booking trước listing. |
| `backend/app/Domain/Booking/Actions/ValidateBookingAcceptance.php` | Khóa listing trước booking, đọc lại quyền/profile; so service/price unit/rules với snapshot, không chỉ so version. | Chỉ đổi giá vẫn cho accept giá cũ; đổi service/rules làm yêu cầu cũ không còn đủ điều kiện accept. |
| `backend/config/filesystems.php` | Có local private disk và public disk riêng. | Listing media phải tuân thủ thiết kế private storage; không suy ra được phép đưa file vào public disk. |

## 4. Những quy tắc đã có nguồn chuẩn

Đây là tổng hợp quyết định đã tồn tại trong tài liệu, không phải thiết kế mới:

- Listing: `DRAFT → PENDING_REVIEW → ACTIVE`; admin reject về DRAFT kèm lý do. `ACTIVE → SUSPENDED → ACTIVE`; `ACTIVE/SUSPENDED → ARCHIVED`. ARCHIVED terminal.
- Caregiver sở hữu được suspend/restore/archive; admin moderation cần lý do/audit. Nguồn hiện tại chưa phân biệt đầy đủ suspension do caregiver và do admin.
- Change request có `PENDING_REVIEW → APPROVED | REJECTED | WITHDRAWN`; tối đa một pending/listing. Duyệt phải khóa listing/request, kiểm tra version, áp dụng payload và tăng version cùng transaction.
- Sửa giá, dịch vụ hoặc điều kiện nhận pet của ACTIVE cần change request. Bản đã duyệt còn công khai/nhận booking trong lúc chờ; caregiver phải suspend nếu không còn đáp ứng được bản cũ.
- Listing công khai/nhận booking mới: ACTIVE, chưa xóa mềm; profile chưa xóa mềm; user caregiver ACTIVE và còn CAREGIVER. Listing ACTIVE chưa có slot vẫn có thể được tìm thấy khi không lọc thời gian, nhưng không đủ điều kiện nhận một booking cụ thể.
- Lịch UTC/microsecond, khoảng `[start_at,end_at)`, capacity=1 theo listing; chưa có slot thì không nhận booking; một slot chưa xóa mềm phải bao trọn, không tự ghép slot rời ở bước đọc.
- `AWAITING_PAYMENT`, `CONFIRMED`, `IN_PROGRESS`, `AWAITING_CONFIRMATION` giữ lịch. REQUESTED không giữ lịch. Hold quá hạn vẫn giữ lịch cho đến khi luồng booking/payment thực sự chuyển trạng thái.
- Bàn giao HA02 yêu cầu chuẩn hóa slot không chồng nhau trong transaction khóa listing. Workflow quản lý listing cấm xóa/đổi khoảng đã khóa bởi booking.
- Suspend/archive không sửa booking cũ hoặc snapshot. Reject REQUESTED hợp lệ vẫn được phép khi listing không ACTIVE; accept bị chặn. Chỉ giá đổi thì accept giữ snapshot cũ; service/price unit/rules đổi so snapshot thì yêu cầu tạo booking mới.
- Ảnh: ID dạng chuỗi, thứ tự nguyên 1..n; không trùng media hoặc sort order trong listing; media READY và đúng owner; mặc định PRIVATE. PUBLIC_ELIGIBLE chưa đủ quyền phục vụ công khai.
- Không xóa file vật lý chỉ vì tháo ảnh hoặc soft delete listing; kiểm tra tham chiếu pet/care update/listing và retention/report.

Nguồn: [M03](<../03 — Modules Documentation/M03 — Caregiver Profiles & Listings.md>), [P0](<../02 — Domain Documentation/05 — P0 Decision Contract.md>), [lifecycle](<../02 — Domain Documentation/04 — State Lifecycles — Listing, Order, Payment.md>), [database design](<../08 — Architecture & Technical Design/database design.md>), [API contract](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>), [bàn giao ảnh/lịch](<BE-HA02 — Bàn giao ảnh và lịch cho BE-T03.md>), [workflow listing](<../04 — Workflows/02 — Đăng bán & quản lý listing.md>).

## 5. Nội dung chưa đủ rõ hoặc đang lệch nhau

| Điểm cần chốt trong contract | Bằng chứng/vướng mắc |
| --- | --- |
| 403 hay 404 với listing người khác | Workflow cũ ghi 403; API contract HA02 và controller hiện tại dùng 404. Cần ghi rõ nguồn ưu tiên khi đồng bộ workflow; không áp dụng hai mã tùy endpoint ngoài chủ đích. |
| Caregiver restore listing do admin suspend | Cả hai actor được suspend/restore, nhưng schema chưa lưu trực tiếp nguồn suspension. Chưa đủ quy tắc để bảo đảm caregiver không gỡ moderation. |
| Archive khi có booking tương lai/đang thực hiện | Workflow cấm nếu làm mất nghĩa vụ; state table rút gọn chỉ nói ACTIVE/SUSPENDED → ARCHIVED. Cần điều kiện theo booking status/thời gian, quyền admin ngoại lệ và cách giữ nghĩa vụ, không tự cancel booking. |
| Điều kiện gửi duyệt/approve lần đầu | Chưa có quy định bắt buộc bao nhiêu ảnh/slot; không thể tự coi có ảnh hoặc còn slot tương lai là điều kiện duyệt đã được chốt. |
| Allowlist duyệt lại | Giá/service/rules đã chốt là trọng yếu; còn title, description, area, ảnh và thứ tự ảnh chưa đủ chi tiết. Ảnh mới không được tự xuất hiện công khai chỉ vì listing đang ACTIVE. |
| Sửa khi PENDING_REVIEW hoặc SUSPENDED | API nháp chỉ sửa DRAFT; tài liệu nói change request cho ACTIVE. Chưa có ma trận thao tác đầy đủ cho hai trạng thái còn lại hoặc thay đổi trong lúc chờ review. |
| Base version và ghi đồng thời | Có `base_listing_version`, nhưng chưa định nghĩa mọi thao tác nào tăng version, no-op/replay, conflict khi admin review một bản cũ, xử lý pending khi suspend/archive. |
| Chuẩn hóa slot | Đã yêu cầu không overlap nhưng chưa chốt merge hay từ chối overlap, xử lý hai slot tiếp giáp, ID cũ sau merge, batch replace và validation slot quá khứ. Quyết định merge ảnh hưởng trực tiếp quy tắc một slot bao trọn. |
| Sửa slot và booking | Phải giữ khoảng đã khóa; cần cụ thể hóa theo coverage sau sửa, biên microsecond, hold quá hạn, REQUESTED chưa khóa và cạnh tranh accept/sửa lịch. Không chỉ kiểm tra slot có booking ID hay không vì booking không FK tới slot. |
| Upload/ảnh công khai | Storage design mới đề xuất JPEG/PNG/WebP, 10 MB/ảnh; chưa chốt số ảnh, giới hạn pixel, điều kiện phục vụ/thu hồi URL, xử lý file/DB lỗi và ảnh của change request. |
| HTTP contract | Chưa có endpoint, request/response, pagination quản lý/review queue, mã lỗi cụ thể hoặc replay cho BE-T03. Không xem tên route dự kiến là đã triển khai. |
| Metadata lifecycle | Lý do reject lần đầu/suspend, người quyết định, thời điểm và dữ liệu để reviewer/caregiver xem lại chưa có contract đọc/ghi. Có thể kế thừa audit nhưng cần thiết kế rõ. |
| Bằng chứng nghiệm thu | Test hiện tại dùng fixture/DB trực tiếp tạo ACTIVE/slot/status. Chưa có kiểm thử API BE-T03 và cạnh tranh với BE-HA03. |

## 6. Ràng buộc phi chức năng cần đưa vào bước chốt

Các tài liệu NFR hiện ghi là **mục tiêu đề xuất cho demo**, không phải SLA hoặc số đo đã đạt:

- Scale/performance: 5.000 ACTIVE listings, 10.000 booking, 50 người đồng thời; p95 search ≤2 giây, detail ≤1 giây, booking write ≤800 ms khi không tranh chấp; server error <1%. Chưa có ngân sách riêng cho upload/review/CRUD slot BE-T03. Khi dùng các mốc này cần ghi môi trường và đo thực tế.
- Security/privacy: Sanctum + CSRF, role và quyền tài nguyên, allowlist response, MIME thật/private storage, không log nội dung riêng. Public media không được trở thành lối đọc ảnh pet/care update.
- Reliability: DB là nguồn quyết định lịch; trạng thái/payload/version/audit liên quan cần nhất quán, không cập nhật dở. File storage và DB không chung transaction: contract phải nêu xử lý lỗi/dọn file; không trả thành công giả khi lưu file lỗi.
- Availability/recovery: tài liệu hiện đề xuất demo RPO ≤24 giờ/RTO ≤4 giờ; chưa có availability SLO riêng BE-T03. Redis lỗi không được làm sai quyết định lịch.
- Maintenance/ownership: Thịnh triển khai #16, Hào review; Hoàng/Hào nhận ảnh hưởng search/booking. BE-T04 sở hữu expiry/payment. Không coi việc reviewer đã chấp thuận là mặc định.

Nguồn: [performance](<../06 — Non-functional Requirements/02 — Performance & Capacity.md>), [security](<../06 — Non-functional Requirements/01 — Security & Privacy.md>), [reliability](<../06 — Non-functional Requirements/03 — Reliability & Recovery.md>), [file storage](<../08 — Architecture & Technical Design/08 — File Storage Design.md>).

## 7. Bằng chứng kiểm thử hiện có và khoảng trống

Đã đọc code test, chưa chạy lại:

- `ListingRelationsTest`: thứ tự media, slot microsecond/xóa mềm và bảo toàn relation; không kiểm chứng API upload/CRUD slot.
- `CaregiverListingTest`, `ListingValidationTest`, `CaregiverAccessTest`: nền nháp/validation/quyền; không thay cho lifecycle và admin moderation.
- `PublicListingTest`, `PublicListingAvailabilityTest`, `PublicListingAcceptanceTest`: quyền công khai, lịch và allowlist. Test acceptance hiện so chính xác danh sách key public; contract thêm ảnh phải cập nhật có chủ đích và giữ kiểm tra riêng tư.
- `BookingCreationTest`: giá/snapshot và điều kiện nguồn; có sửa giá/status trực tiếp DB nên chưa chứng minh flow approve/change request.
- `BookingAcceptanceConditionsTest`, `BookingAcceptanceBusinessTest`, `BookingAcceptanceTest`: trạng thái listing, điều kiện thay đổi, snapshot và rollback.
- `BookingAcceptanceConcurrencyTest`: MySQL thực, process riêng và fixture đã commit; cạnh tranh accept/accept, không phải accept/sửa slot hoặc accept/suspend.

Contract sau khi chốt cần có tiêu chí kiểm chứng cho lifecycle qua API thật, một pending request khi gửi đồng thời, approve bản stale, bảo toàn booking/ảnh đã duyệt, và tranh chấp giữa quản lý lịch/trạng thái với create/accept. Bộ test cũ cần hồi quy; báo cáo lịch sử 243 tests không bảo đảm phần BE-T03 chưa viết.

## 8. Phạm vi đã xác nhận trước thiết kế

1. Đầu ra là contract BE-T03 cho owner triển khai/reviewer đối chiếu, chưa triển khai chức năng.
2. Bao phủ lifecycle, quyền, change request, media và lịch; tái sử dụng schema/auth/search/booking đã có.
3. Giữ bản ACTIVE đã duyệt trong khi chờ thay đổi; không đơn giản chuyển ACTIVE về PENDING_REVIEW.
4. DB lock và bất biến lịch phải tương thích create/accept hiện tại; không thêm nguồn cache quyết định.
5. Snapshot/lịch sử booking và nghĩa vụ chăm sóc phải được bảo toàn.
6. Contract cần API/lỗi/ma trận trạng thái, giả định NFR, quyết định và tiêu chí nghiệm thu; mọi chi tiết mới phải được phân biệt với quy tắc đã chốt.

**Giả định chưa xác nhận:** dùng các mục tiêu demo NFR hiện có làm bối cảnh; không tự đặt SLA mới, số lượng ảnh, giới hạn pixel hoặc điều kiện review. Chưa chọn phương án cho các khoảng trống §5.

**Xác nhận phạm vi:** người dùng trả lời “Đồng ý phạm vi trên” ngày 02/11/2026. Xác nhận này cho phép chuyển sang thiết kế, không được diễn giải thành duyệt các giới hạn ảnh, quyền moderation, endpoint hoặc quyết định schema chưa trình bày.

**Decision log:** các quyết định nền được tổng hợp tại §4. Đề xuất thiết kế mới T03-D01–D12 nằm trong [dự thảo contract §17](<BE-T03 — Contract lifecycle, ảnh và lịch khả dụng.md>), đang chờ duyệt; chưa đồng bộ vào nguồn chuẩn.
