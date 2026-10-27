# BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ

**Nguồn:** [GitHub issue #2](https://github.com/Huuhoang-1208/PETTEL/issues/2) · **Owner triển khai:** Thịnh · **Người chốt schema/state/API contract:** Codex · **Bên nhận bàn giao:** Hoàng và Hào.
**Lịch:** N1–N2 · **Timebox:** 8 người-giờ, gồm ít nhất 1 giờ review · **Branch đề xuất:** `codex/4w-be-t01`.
**Ngày triển khai:** 28/10/2026 · **Trạng thái:** schema và contract BE-T01 đã được triển khai; kết quả MySQL up/down/up được ghi trong §7.

## 1. Mục tiêu và nguồn chuẩn

Chốt đầu vào để các owner viết migration và nghiệp vụ nhất quán: quan hệ dữ liệu, trạng thái/quyền chuyển, transaction, validation, lỗi và idempotency. Codex là người quyết định contract BE-T01; không cần Hoàng hoặc Hào phê duyệt trước. Sau khi chốt, Hoàng và Hào nhận bàn giao và dùng contract này cho phần việc phụ thuộc. Thịnh giữ vai trò owner triển khai issue #2. Tái sử dụng P0 đã có; các quyết định chi tiết hóa dưới đây là nguồn chuẩn khi tài liệu rút gọn hoặc baseline code chưa đồng bộ.

| Tài liệu | Cách sử dụng trong BE-T01 |
| --- | --- |
| [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) | Nguồn chuẩn nghiệp vụ: giá, thời hạn, hủy/hoàn tất, quyền, retention và mã dữ liệu. |
| [Database Design](<../08 — Architecture & Technical Design/database design.md>) | Schema đích, ERD §3, bảng/cột §4, transaction §5, index §7, idempotency §8 và migration §9. |
| [Business Rules](<../02 — Domain Documentation/02 — Business Rules.md>), [Domain Model](<../02 — Domain Documentation/03 — Domain Model.md>), [State Lifecycles](<../02 — Domain Documentation/04 — State Lifecycles — Listing, Order, Payment.md>) | Đối chiếu bất biến và các nhánh trạng thái; mô tả rút gọn phải đọc cùng P0 Contract. |
| [API Contracts](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) | Request/response, quote, mã lỗi, HTTP status và idempotency. |
| [M05 — Booking](<../03 — Modules Documentation/M05 — Booking & Availability.md>), [M06 — Payment](<../03 — Modules Documentation/M06 — Payment Sandbox.md>), [Workflow đặt dịch vụ](<../04 — Workflows/03 — Tạo đơn & thanh toán sandbox.md>) | Ranh giới module và điều kiện nghiệm thu theo hành trình. |
| [Kế hoạch 4 tuần](<05 — Kế hoạch 4 tuần — Backend trước, frontend sau.md>), [Backlog](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>), [Lộ trình BE-H01](<BE-H01 — Lộ trình xác thực và phân quyền.md>) | Owner, thứ tự phụ thuộc, timebox và phần Identity đã bàn giao. |

Theo lịch gốc, N1–N2 là 26–27/10/2026; tài liệu được lập sau mốc này, không suy ra issue đã hoàn tất. Nếu khởi động thực tế trễ, nhóm dịch mốc theo kế hoạch chung; không tự thêm ngày cuối tuần hay đổi deadline riêng BE-T01.

## 2. Baseline thực tế và quyết định áp dụng

Baseline trước BE-T01 có migration Laravel nền và `2026_10_27_000001_create_identity_schema.php`; Identity đã thêm `users.status`, `deletion_requested_at`, `user_roles`, `audit_logs` cùng auth/role. BE-T01 bổ sung bốn migration forward cho status, schema pet/listing, booking/payment và content/moderation. Không tạo dữ liệu nghiệp vụ legacy hoặc seed mặc định.

| Điểm lệch hoặc chưa đủ chi tiết | Quyết định BE-T01 | Tác vụ đồng bộ |
| --- | --- | --- |
| Tài liệu kế hoạch cũ mô tả baseline Laravel shell | Kế thừa `users`, `user_roles`, `audit_logs`; không tạo bảng trùng, không chuyển dữ liệu legacy. | Đã cập nhật Implementation Plan và Backlog ngày 28/10. |
| Migration Identity cũ dùng MySQL `ENUM`; Database Design chọn `VARCHAR + CHECK` | Giữ giá trị hiện tại, dùng migration forward chuyển hai cột; migration cũ không sửa. | Đã triển khai và kiểm chứng rollback/reapply trên MySQL 8.4. |
| Data Model rút gọn dùng `reports.target_type/target_id` | Chuẩn dùng bốn FK nullable (`listing_id`, `review_id`, `message_id`, `booking_id`) và CHECK đúng một đích. | Đã cập nhật Data Model và M09; migration tạo FK/CHECK. |
| `listing_change_requests` chưa có schema/status cụ thể | Các cột payload/version/actor/reason/time và status `PENDING_REVIEW`, `APPROVED`, `REJECTED`, `WITHDRAWN`; một request chờ/listing được bảo vệ dưới lock listing. | Đã thêm migration và cập nhật Database Design, P0 Contract, State Lifecycles, M03. |
| `pets.status` chưa định nghĩa; `caregiver_profiles.status` không có lifecycle riêng | Pet dùng `ACTIVE`/`ARCHIVED`; caregiver profile không có status, account status/role và listing status là nguồn chuẩn. | Đã thêm CHECK/migration và đồng bộ M02/M03/Database Design. |

Các lựa chọn ở bảng trên là quyết định BE-T01 và đã được đồng bộ vào P0 Contract, Database Design, Data Model, State Lifecycles, API Contract và các module liên quan. Migration Identity cũ được giữ nguyên; thay đổi status/role đi qua migration forward.

**Báo cáo quyết định đã chốt:** schema trạng thái dùng `VARCHAR + CHECK` trên MySQL 8.4; Identity được chuyển bằng migration mới; pet có `ACTIVE/ARCHIVED`; caregiver profile không có status riêng; listing change request có bốn trạng thái và một request chờ/listing; report dùng bốn FK thật; audit dùng bảng chung hiện có. Các giá trị và điều kiện chi tiết dưới đây là contract để các issue phụ thuộc áp dụng.

## 3. Thứ tự thực hiện trong 8 giờ

| Mốc | Ngân sách | Việc thực hiện | Đầu ra kiểm được |
| --- | ---: | --- | --- |
| N1 — xác nhận baseline | 1 giờ | Kiểm kê migration Identity và xác nhận MySQL 8.4/database test cô lập. | Danh sách migration đã có/chưa có; blocker môi trường. |
| N1 — chốt schema và phân lịch | 2 giờ | Áp dụng ERD Database Design cùng quyết định ở §2; xác định FK/index, snapshots, vòng FK và owner viết migration. | ERD và lịch migration theo §4; contract duy nhất cho module phụ thuộc. |
| N1–N2 — chốt state/API | 1,5 giờ | Chốt ma trận §5 và hợp đồng §6 theo P0, bao gồm quyền, thời hạn, khóa lịch, cancel/callback/release và lỗi. | Contract trạng thái/API và checklist tình huống cạnh tranh giao downstream. |
| N2 — kiểm chứng migration | 2,5 giờ | Chạy migration từ DB trống, rollback bốn migration BE-T01 rồi chạy lại trên MySQL 8.4. Kiểm tra CHECK, kiểu status/role và bảo toàn dữ liệu Identity. | Migration up/down/up, danh sách constraint/status và kết quả giữ nguyên `DELETION_PENDING`/`OWNER` sau conversion. |
| Cuối N2 — kiểm tra/bàn giao | 1 giờ | Ghi decision log, kết quả kiểm chứng, migration/commit và issue nhận bàn giao. Hoàng/Hào nhận danh sách contract đã chốt. | BE-H02/BE-HA02/BE-T02 dùng contract N2; checklist đóng issue phản ánh đúng bằng chứng. |
| **Tổng** | **8 giờ** | | |

Codex chốt contract; Thịnh điều phối và triển khai DDL dùng chung. BE-T01 tạo toàn bộ schema P0 để các owner module phát triển model/service/API theo phụ thuộc, không tạo migration trùng. Issue module vẫn chịu trách nhiệm hoàn thiện hành vi và quyền truy cập.

## 4. Hợp đồng schema và thứ tự migration

ERD đầy đủ dùng [Database Design §3](<../08 — Architecture & Technical Design/database design.md>). Quan hệ áp dụng: user có nhiều role/pet, tối đa một caregiver profile; profile có nhiều listing; listing có nhiều slot/booking; booking gắn đúng một owner/pet/listing và caregiver, có nhiều status event/payment attempt; booking trỏ tới một current attempt. Các bảng messaging/review/report/media/audit theo cùng ERD và FK của Database Design, không tạo bảng `payments` cạnh tranh với `payment_attempts`.

| Thứ tự | Bảng / ràng buộc | Owner triển khai theo module và điều phối |
| --- | --- | --- |
| 0 — kế thừa | Laravel users/reset/session/cache/jobs; Identity mở rộng users, `user_roles`, `audit_logs` đã có; migration forward đổi các cột trạng thái/role sang VARCHAR + CHECK | BE-T01 thêm migration sửa tương thích. Giữ `password`, không đổi sang `password_hash`; `sessions.user_id` không có FK. |
| 1 — schema đã tạo | `media_objects`, `pets`, `caregiver_profiles`, `listings`, `availability_slots`, `listing_change_requests`, `listing_media` | BE-T01 tạo DDL; Hoàng/BE-H02 và Hào/BE-HA02–BE-HA03 triển khai model/service/API, không tạo lại migration trùng. |
| 2 — booking/payment đã tạo | `bookings`, `booking_status_events`, `payment_attempts`, `payment_events`, gồm FK vòng current attempt được thêm sau | BE-T01 tạo DDL; BE-T02–BE-T06 triển khai nghiệp vụ trên schema này. |
| 3 — content/moderation đã tạo | `messages`, `care_updates`, `reviews`, `reports`, `moderation_actions`; dùng `audit_logs` hiện có | BE-T01 tạo DDL; các owner module triển khai API, policy, event và UI theo phụ thuộc. |



Các migration được tách theo phụ thuộc DDL và đã tạo đầy đủ bảng P0. Issue module tiếp theo làm model, service, policy, API, tests và UI trên schema đã bàn giao; không tạo migration trùng khi chưa có thay đổi schema được ghi nhận.

Các bất biến cần ghi trong schema checklist:

- ID/FK cùng `BIGINT UNSIGNED`; response ID dạng chuỗi. Tiền `BIGINT UNSIGNED` VND nguyên; giá listing dương, kiểm tra tràn số khi nhân. Thời gian nghiệp vụ UTC; API ISO 8601 có offset, hiển thị `Asia/Ho_Chi_Minh`; khoảng `[start_at,end_at)` và `start_at < end_at`.
- `user_roles` PK `(user_id,role)`; caregiver profile unique theo user; booking unique `(owner_id,request_idempotency_key)`; attempt unique `(booking_id,attempt_no)` và `(booking_id,idempotency_key)`; `provider_ref` unique nullable; payment event unique `(provider,external_event_id)`.
- Booking lưu giá tổng, snapshot pet/listing/policy và hướng dẫn theo allowlist có version; giữ `pricing_version=1`, `cancellation_policy_version=1`, đơn giá/đơn vị/số lượng. Sửa giá/pet về sau không sửa lịch sử. Notes/PII vẫn phải redact theo retention, không coi mọi JSON là bất biến vĩnh viễn.
- Index conflict `(listing_id,status,start_at,end_at)`; timeline `(booking_id,created_at,id)`; các index khác theo Database Design §7. CHECK/FK đơn lẻ không chứng minh pet thuộc owner, attempt thuộc booking hoặc không overlap: service phải kiểm tra trong transaction.
- Soft delete pet/profile/listing; không cascade xóa lịch sử booking/payment/review/report/audit. Review unique theo booking; report đúng một trong bốn FK đích. Retention giữ theo P0 Contract, không thêm purge tự động khi chưa kiểm tra FK/media/report mở.

## 5. Ma trận trạng thái và quyền phải bàn giao

### Listing

| Chuyển trạng thái | Actor / điều kiện |
| --- | --- |
| `DRAFT → PENDING_REVIEW` | Caregiver sở hữu gửi duyệt. |
| `PENDING_REVIEW → ACTIVE` hoặc `DRAFT` | Admin duyệt hoặc từ chối có lý do. |
| `ACTIVE → SUSPENDED → ACTIVE` | Caregiver sở hữu được tạm ngưng/khôi phục listing của mình; admin cũng có thể suspend/restore với lý do và audit. Suspend ẩn listing và chặn booking mới; booking đang tồn tại không đổi. |
| `ACTIVE/SUSPENDED → ARCHIVED` | Caregiver sở hữu có thể archive; admin có thể archive với lý do/audit. ARCHIVED là terminal, giữ booking cũ và không nhận booking mới. |
| Sửa trường trọng yếu khi `ACTIVE` | Tạo change request `PENDING_REVIEW`; bản duyệt cũ vẫn công khai. Admin duyệt → `APPROVED` và áp dụng payload/tăng version nguyên tử; từ chối → `REJECTED` có lý do; caregiver có thể rút trước khi xử lý → `WITHDRAWN`. Tối đa một request `PENDING_REVIEW`/listing, kiểm tra dưới row lock listing. Nếu không đáp ứng bản cũ, caregiver phải suspend listing. |

Listing change request không có thêm state `PENDING`; `PENDING_REVIEW` là tên chuẩn để thống nhất với lifecycle listing, nhưng hai state thuộc hai bảng khác nhau.

### Pet và caregiver profile

| Tài nguyên | Trạng thái/điều kiện chuẩn |
| --- | --- |
| Pet | `ACTIVE` mặc định; owner có thể `ARCHIVED`. Chỉ pet ACTIVE được dùng cho booking mới. Lưu `deleted_at` riêng cho xóa mềm/privacy; archive hoặc xóa mềm không xóa snapshot/history. |
| Caregiver profile | Không có cột `status`. Quyền đủ điều kiện đến từ account `users.status=ACTIVE` và role CAREGIVER; khả năng công khai/đặt dịch vụ được quyết định bởi `listings.status`. |

### Booking

| Chuyển trạng thái | Actor / điều kiện bắt buộc | Ảnh hưởng lịch/payment |
| --- | --- | --- |
| Tạo `REQUESTED` | Owner đăng nhập, pet thuộc mình, listing ACTIVE, thời gian/điều kiện/slot hợp lệ; server chụp snapshot | Chưa khóa lịch; nhiều request giao nhau có thể cùng tồn tại. |
| `REQUESTED → AWAITING_PAYMENT` | Caregiver của listing accept trước `min(created_at + 24 giờ, start_at)`; kiểm tra lại listing, điều kiện trọng yếu, slot và overlap trong transaction | Khóa lịch; `payment_due_at = accept + 15 phút`, retry không gia hạn. |
| `REQUESTED → CANCELLED` | Owner hủy, caregiver từ chối hoặc job hết hạn với `REQUEST_EXPIRED` | Chưa có attempt, không cần giải phóng hold. |
| `AWAITING_PAYMENT → CONFIRMED` | Callback sandbox hợp lệ, current attempt AUTHORIZED, đúng amount và `now < payment_due_at` | Tiếp tục khóa lịch; không confirm từ frontend hoặc callback muộn. |
| `AWAITING_PAYMENT → CANCELLED` | Owner/caregiver hủy hoặc job hết hạn | PENDING phải VOIDED thành công trước; FAILED giữ lịch sử; chưa có attempt thì không có thao tác void. Lỗi adapter giữ state cũ, retry/admin; chưa thả lịch. |
| `CONFIRMED → CANCELLED` | Owner/caregiver hủy trước `start_at` | AUTHORIZED phải REFUNDED mô phỏng thành công rồi mới hủy/thả lịch. |
| `CONFIRMED → IN_PROGRESS` | Caregiver bắt đầu chăm theo workflow; BE-T06 cụ thể hóa kiểm tra thời điểm bắt đầu, không tự thêm ngưỡng thời gian mới trong BE-T01 | Vẫn khóa lịch. |
| `IN_PROGRESS → AWAITING_CONFIRMATION` | Caregiver báo xong | Vẫn khóa lịch; dừng quyền tạo care update. |
| `AWAITING_CONFIRMATION → COMPLETED` | Owner xác nhận, hoặc admin xác nhận thay theo P0 với lý do/audit, không có report đang xử lý chặn release | Chỉ complete khi release thành công; RELEASED tối đa một lần. |
| `IN_PROGRESS/AWAITING_CONFIRMATION → CANCELLED` | Chỉ admin xử lý ngoại lệ sau xác minh/tranh chấp, có lý do và audit | Refund mô phỏng thành công trước khi hủy; ghi moderation/booking/payment event. |

Chỉ `AWAITING_PAYMENT`, `CONFIRMED`, `IN_PROGRESS`, `AWAITING_CONFIRMATION` khóa lịch. Không tự complete khi owner im lặng: nhắc sau 24 giờ, đưa admin sau 72 giờ. Release lỗi giữ `AWAITING_CONFIRMATION`, retry cùng key sau 1/5/15 phút, tối đa 3 lần rồi admin xử lý. State cuối không được đảo bằng thao tác thường.

### Payment attempt

| Chuyển trạng thái | Điều kiện / quyền |
| --- | --- |
| Chưa có attempt → `PENDING` | Owner của booking tạo payment khi `AWAITING_PAYMENT` còn hạn; amount lấy từ snapshot. `NOT_STARTED` là giá trị suy ra, không lưu row. |
| `PENDING → AUTHORIZED/FAILED` | Chỉ adapter/callback đã xác thực và service kiểm tra reference, current attempt, event ID, amount, state và hạn giữ chỗ. |
| `PENDING → VOIDED` | Service hủy/hết hạn điều phối với adapter; thành công mới chốt hủy booking. |
| `AUTHORIZED → REFUNDED` | Hủy hợp lệ hoặc admin ngoại lệ; chỉ mô phỏng, có audit tương ứng. |
| `AUTHORIZED → RELEASED` | Xác nhận hoàn tất hợp lệ; service bảo vệ tối đa một release/booking. |
| `FAILED → attempt PENDING mới` | Key mới, booking vẫn chờ payment và còn hạn; không sửa attempt FAILED thành PENDING. |

Không lưu thêm `bookings.payment_status`; đọc current attempt. Callback authorize muộn không cấp lại lịch: nếu adapter đã authorize, tạo tác vụ bù refund/void có audit. Không đặt unique `(booking_id,status)` vì sẽ chặn nhiều lần FAILED hợp lệ.

## 6. Validation, lỗi và idempotency chung

- **Thời gian/giá:** DAYCARE/HOUR 1–12 giờ trong cùng ngày Việt Nam, quantity làm tròn lên giờ; BOARDING/NIGHT 12 giờ–30 ngày, quantity làm tròn lên block 24 giờ. Tính thời lượng bằng UTC theo công thức phút của P0 Contract. Quote và create dùng cùng công thức; create tính lại trong transaction, không tin amount/owner/status client gửi.
- **Lịch:** booking phải nằm trọn trong một slot mở liên tục; không có slot thì không nhận. Capacity 1/listing, không khóa chéo mọi listing của cùng caregiver. Overlap khi `existing.start_at < new.end_at AND existing.end_at > new.start_at`; hai khoảng sát nhau không xung đột.
- **Mã và text:** dùng service/species/size/area_code và giới hạn ký tự Unicode sau chuẩn hóa/trim của P0 Contract §7. Không free text cho mã lọc; pet thiếu size không tự phù hợp listing giới hạn size.
- **Quyền:** backend kiểm tra role, account status, ownership và resource state. OWNER mặc định, CAREGIVER tự bật có audit; ADMIN không tự cấp. `DELETION_PENDING` chỉ xử lý tối thiểu booking/report đang mở, không tạo mới; `DEACTIVATED` không đăng nhập. Chat cho hai bên từ REQUESTED đến AWAITING_CONFIRMATION, terminal chỉ đọc; care update chỉ caregiver trong IN_PROGRESS; admin đọc private có lý do/audit.
- **Lỗi:** JSON `error: { code, message, details?, request_id }`; 400 cấu trúc sai, 401 thiếu session, 403 sai quyền, 404 không tồn tại/không được lộ, 409 cho `AVAILABILITY_CONFLICT`, `INVALID_STATE`, `IDEMPOTENCY_CONFLICT`, `REQUEST_EXPIRED`, `PAYMENT_EXPIRED`; 422 `VALIDATION_ERROR` theo field, 429 throttle; CSRF lỗi 419 theo M01. Không lộ stack trace/PII.
- **Idempotency:** bắt buộc key cho create booking/payment; booking scope owner, payment scope booking; hash payload chuẩn hóa. Cùng key/payload trả kết quả cũ, khác payload trả 409. Callback dedupe `(provider,external_event_id)` sau xác thực; event khác ID vẫn kiểm tra state/current attempt. Hủy, job expiry và release gọi lặp không tạo transition/release thứ hai.
- **Transaction:** nếu đụng nhiều loại row, lock theo `listing → booking → payment attempt`; callback/job chỉ cần booking/attempt thì khóa booking trước. Accept và sửa slot cùng khóa listing. Transition + status event ghi cùng transaction; queue sau commit. Không giữ lock khi gọi mạng/xử lý file; adapter lỗi giữ state hợp lệ và có retry/admin.

## 7. Kiểm chứng và tiêu chí đóng issue

Kết quả thực hiện BE-T01 ngày 28/10/2026:

- [x] ERD, danh mục bảng, state matrix, quyền, validation/error/idempotency contract được ghi trong tài liệu chuẩn và đồng bộ với P0 Contract, Data Model, State Lifecycles, API Contract và module liên quan.
- [x] Migrations forward tạo schema P0 từ baseline Laravel/Identity; không phụ thuộc dữ liệu legacy. Audit dùng lại `audit_logs`; report dùng 4 FK nullable và CHECK đúng một đích.
- [x] MySQL 8.4 sạch: `migrate:fresh --force` chạy đủ migration nền, Identity và bốn migration BE-T01 thành công.
- [x] `migrate:rollback --step=4` gỡ hết schema BE-T01; `migrate --force` áp dụng lại thành công.
- [x] Conversion ENUM → VARCHAR + CHECK bảo toàn row kiểm thử `users.status=DELETION_PENDING` và `user_roles.role=OWNER` qua rollback/reapply.
- [x] Kiểm tra `information_schema`: status/role là VARCHAR; business time là DATETIME(6); tiền là BIGINT UNSIGNED; các CHECK cho state, range, giá/capacity, vùng, rating, care update và đúng một report target hiện diện.
- [x] Checklist race/expiry/callback/release được bàn giao cho issue triển khai nghiệp vụ; migration test không được ghi thành bằng chứng những workflow đó đã chạy.

BE-T01 hoàn tất schema và contract. Các workflow race/expiry/callback/release vẫn là acceptance của BE-T03–BE-T06 và chưa được đánh dấu là đã test trong issue này.

## 8. Bàn giao và theo dõi quyết định

| Bên nhận | Đầu vào nhận từ BE-T01 | Mốc theo backlog |
| --- | --- | --- |
| Hoàng — BE-H01/BE-H02 | Identity đã đối chiếu, pet ownership/dictionary, snapshot và lịch FK media | Contract N2; pet contract/fixture N3, tích hợp N5. |
| Hào — BE-HA02/BE-HA03 | Schema caregiver/listing/change request/slot, state và cùng lock listing | Schema N2; listing fixture ACTIVE N3; availability contract N6. |
| Thịnh — BE-T02 | Booking REQUESTED, quote/giá/snapshot, key/hash/unique và lỗi | Triển khai N4–N5 sau đầu vào N3. |
| Thịnh — BE-T03/BE-T04 | Accept/overlap, hold 15 phút, request expiry, cancel/void/refund contract | N7–N10; không nghiệm thu phần payment chưa tích hợp bằng mock. |
| Thịnh — BE-T05/BE-T06 | Attempt/event/current FK, callback/late event, release và admin ngoại lệ | Callback contract chi tiết N11; triển khai/tích hợp N11–N15. |
| Hoàng/Hào — BE-H05/BE-H06/BE-HA04–BE-HA06 | State mở/đóng chat/update/review, report/audit/retention, error contract | Theo backlog tới Gate BE N15. |

Decision log của BE-T01 ghi các lựa chọn ở §2 là contract hiện hành. Khi phát sinh thay đổi thực sự so với P0, ghi theo mẫu: `ID · nội dung cũ/mới · lý do · owner · issue nhận ảnh hưởng · tài liệu/commit · ngày cập nhật`; phân biệt rõ thay đổi sản phẩm với chi tiết triển khai. Các issue phụ thuộc dùng file này làm đầu vào và không tự tạo biến thể schema/state/API. Không coi tiền thật, xác minh thật hay P1 là điều kiện chặn schema P0.
