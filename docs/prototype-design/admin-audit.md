# Kiểm toán chức năng Admin PETTEL

**Ngày kiểm tra:** 11/11/2026  
**Checkout:** nhánh `fe`, HEAD `39bde51` (`origin/main`, `be-t06`)  
**Phạm vi:** đối chiếu PRD, Modules, Domain, Workflows, state lifecycle, API contracts, BE-T04/T05/T06, source Laravel và hai màn hình Admin của prototype. Đây là báo cáo chỉ đọc; không sửa code, Vue production, backend hay prototype.

## 1. Kết luận điều hành

- **Filament hiện là một panel tối giản:** `/admin` có Dashboard mặc định và đúng một Resource nghiệp vụ là Reports. Không có Filament Resource cho Listing, Booking, User, Payment hay Audit Log; không có standalone custom Page hoặc widget nghiệp vụ (ngoài page `ListReports` của Resource).
- **Pulse là dashboard riêng ở `/pulse`, không phải trang hay widget Filament.** Cấu hình nguồn khai báo storage database, ingest storage và 10 recorder; mặc định phần lớn được bật qua env fallback. Test xác nhận quyền truy cập trang, nhưng source trong checkout không chứng minh đã có metric records thực tế hoặc cho phép kiểm tra chính xác các card được render bởi package.
- **Nhiều thao tác Admin đã có backend nhưng chưa có màn hình quản trị:** duyệt và quản lý listing/change request, hàng đợi booking cần xác nhận hoàn tất, và retry void/refund cần manual review. Các thao tác này nằm ở API Admin, không phải Filament Resource.
- **Hai trang Listing Moderation và Reports của prototype phù hợp với phạm vi reference đã được chốt trong tài liệu prototype**, nhưng không phải giao diện backend hiện có. Listing page mô phỏng API-only workflows; Reports page mở rộng danh sách Filament thành case detail mô phỏng. Một số nhánh mock không khớp với điều kiện backend, nhất là quyết định booking report và thời điểm cập nhật trạng thái.
- Cần xử lý một bất đồng nghiệp vụ trước khi đặc tả moderation đầy đủ: M09/workflow nhắc tới ẩn message và cảnh báo caregiver, trong khi action backend hiện chỉ ẩn/khôi phục listing hoặc review.

## 2. Nguồn đã đối chiếu

### Tài liệu sản phẩm và nghiệp vụ

- [Product Overview](../01%20-%20Product%20Document/01%20%E2%80%94%20Product%20Overview.md), [Actors & Roles](../01%20-%20Product%20Document/04%20%E2%80%94%20Actors%20%26%20Roles.md), [Product Scope & MVP Priorities](../01%20-%20Product%20Document/05%20%E2%80%94%20Product%20Scope%20%26%20MVP%20Priorities.md).
- [Module Map](../03%20%E2%80%94%20Modules%20Documentation/00%20%E2%80%94%20Module%20Map%20%26%20Dependencies.md), [M03 Listings](../03%20%E2%80%94%20Modules%20Documentation/M03%20%E2%80%94%20Caregiver%20Profiles%20%26%20Listings.md), [M09 Reports & Admin](../03%20%E2%80%94%20Modules%20Documentation/M09%20%E2%80%94%20Reports%20%26%20Admin.md).
- [Business Rules](../02%20%E2%80%94%20Domain%20Documentation/02%20%E2%80%94%20Business%20Rules.md), [State Lifecycles](../02%20%E2%80%94%20Domain%20Documentation/04%20%E2%80%94%20State%20Lifecycles%20%E2%80%94%20Listing%2C%20Order%2C%20Payment.md), [P0 Decision Contract](../02%20%E2%80%94%20Domain%20Documentation/05%20%E2%80%94%20P0%20Decision%20Contract.md), [Workflow 04 — Reports/Admin](../04%20%E2%80%94%20Workflows/04%20%E2%80%94%20B%C3%A1o%20c%C3%A1o%20n%E1%BB%99i%20dung%20%26%20x%E1%BB%AD%20l%C3%BD%20qu%E1%BA%A3n%20tr%E1%BB%8B.md).
- [API Contracts & Error Conventions](../08%20%E2%80%94%20Architecture%20%26%20Technical%20Design/04%20%E2%80%94%20API%20Contracts%20%26%20Error%20Conventions.md), [BE-T04](../05%20%E2%80%94%20Implementation%20Plan/BE-T04%20%E2%80%94%20Ph%C3%A2n%20t%C3%ADch%20issue%2020%20v%C3%A0%20ti%C3%AAu%20ch%C3%AD%20ho%C3%A0n%20th%C3%A0nh.md), [BE-T05](../05%20%E2%80%94%20Implementation%20Plan/BE-T05%20%E2%80%94%20Plan%20tri%E1%BB%83n%20khai%20v%C3%A0%20checklist%20nghi%E1%BB%87m%20thu.md), [BE-T06](../05%20%E2%80%94%20Implementation%20Plan/BE-T06%20%E2%80%94%20B%C3%A0n%20giao%20t%E1%BB%AB%20BE-H06%20v%C3%A0%20nghi%E1%BB%87m%20thu%20E2E.md).
- [Prototype screen inventory](05-screen-inventory.md), [screen specifications](06-screen-specifications.md), [FE coverage matrix](10-fe-coverage-matrix.md), [open questions and decisions](11-open-questions-and-decisions.md), [builder brief](12-prototype-builder-brief.md).

### Source và test chính

- [AdminPanelProvider](../../backend/app/Providers/Filament/AdminPanelProvider.php#L22), [ReportResource](../../backend/app/Filament/Resources/Reports/ReportResource.php#L26), [ListReports](../../backend/app/Filament/Resources/Reports/Pages/ListReports.php#L8), [User::canAccessPanel](../../backend/app/Models/User.php#L58), [ReportPolicy](../../backend/app/Policies/ReportPolicy.php#L9).
- [API routes](../../backend/routes/api.php#L108), [ListingLifecycleController](../../backend/app/Http/Controllers/ListingLifecycleController.php#L34), [ReportController](../../backend/app/Http/Controllers/Admin/ReportController.php#L18), [BookingCompletionReviewController](../../backend/app/Http/Controllers/Admin/BookingCompletionReviewController.php#L14), [BookingCancellationQueueController](../../backend/app/Http/Controllers/Admin/BookingCancellationQueueController.php#L14).
- [Pulse config](../../backend/config/pulse.php#L20), [Pulse migration](../../backend/database/migrations/2026_11_10_070610_create_pulse_tables.php#L7), [dashboard authorization test](../../backend/tests/Feature/AdminDashboardAuthorizationTest.php#L10), [Report Resource test](../../backend/tests/Feature/Filament/ReportResourceTest.php#L15).
- Test nghiệp vụ được dẫn trong các mục tương ứng: `ReportWorkflowTest`, `BookingReportDecisionTest`, `ListingLifecycleContractTest`, `ListingChangeRequestContractTest`, `BookingCompletionFollowupTest`, `BookingCancellationTest`, `AdminRoleCommandTest`, `ApiDocumentationAccessTest`.

Test được dùng làm bằng chứng có trong repository và trong handoff BE-T06; báo cáo này **không chạy lại test suite**. `backend/vendor/laravel/pulse` không có trong checkout nên không thể kiểm tra trực tiếp view/card package hoặc đọc metric records runtime.

## 3. Filament hiện triển khai những gì?

### Panel, Page và Widget

| Thành phần | Trạng thái thực tế | Bằng chứng |
|---|---|---|
| Panel `admin`, đường dẫn `/admin`, có login | Đã cấu hình trong `AdminPanelProvider`; màu primary Amber. | [AdminPanelProvider.php:24–33](../../backend/app/Providers/Filament/AdminPanelProvider.php#L24) |
| Dashboard | Dùng `Filament\Pages\Dashboard` mặc định. | [AdminPanelProvider.php:34–43](../../backend/app/Providers/Filament/AdminPanelProvider.php#L34) |
| Filament Widgets | Khai báo `AccountWidget` và `FilamentInfoWidget`; không có widget nghiệp vụ custom. | [AdminPanelProvider.php:39–43](../../backend/app/Providers/Filament/AdminPanelProvider.php#L39) |
| Filament Pages do ứng dụng tự viết | Không tìm thấy file trong `backend/app/Filament/Pages`; chỉ đăng ký Dashboard mặc định và page ListReports của Resource. | [Filament tree](../../backend/app/Filament/Resources/Reports/Pages/ListReports.php#L8) |
| Filament Resources | Chỉ có `Reports\ReportResource`; không có Resource Listing, Booking, User, Payment hoặc Audit. | [ReportResource.php:26–36](../../backend/app/Filament/Resources/Reports/ReportResource.php#L26) |

`/admin` Dashboard vì vậy không phải business overview: source chỉ khai báo widget tài khoản và thông tin Filament; không có KPI PETTEL, queue counts hoặc chart nghiệp vụ được định nghĩa trong app.

### Reports Resource: dữ liệu và hành động

Resource là màn hình nghiệp vụ Filament duy nhất. Bảng liệt kê Report ID, target summary (`LISTING`, `REVIEW`, `MESSAGE`, `BOOKING` + ID), Reporter ID, reason code, trạng thái và thời điểm tạo; có filter theo bốn trạng thái `OPEN`, `IN_REVIEW`, `RESOLVED`, `DISMISSED`. Không cho tạo, sửa, xóa report bằng CRUD. Xem [ReportResource.php:38–105](../../backend/app/Filament/Resources/Reports/ReportResource.php#L38).

Các table actions được khai báo tại [ReportResource.php:107–145](../../backend/app/Filament/Resources/Reports/ReportResource.php#L107):

- `Start review`: chỉ khi `OPEN`, chuyển sang `IN_REVIEW` và bắt buộc lý do.
- `Resolve`: chỉ khi `IN_REVIEW` và không phải booking report; booking report phải dùng booking decision.
- `Dismiss`: khi `OPEN` hoặc `IN_REVIEW`, cần lý do.
- `Hide target` / `Restore target`: chỉ report `IN_REVIEW` có listing hoặc review target; cần xác nhận và lý do.
- `Booking decision`: chỉ booking report `IN_REVIEW`; lựa chọn `COMPLETE` hoặc `CANCEL_REFUND`, bắt buộc lý do. Backend tạo release/refund operation; không sửa trạng thái/payment trực tiếp trong action.
- `History` và `View permitted evidence`: mở route API tương ứng ở tab mới. Đây không phải Filament detail pages.

Test [ReportResourceTest](../../backend/tests/Feature/Filament/ReportResourceTest.php#L19) xác nhận admin mở được queue, non-admin bị từ chối, nội dung riêng tư không xuất hiện trong table; các test sau đó kiểm tra action listing/review, booking decision tạo operation và evidence route có policy/audit.

### Quyền quản trị

- User vào Filament khi `status=ACTIVE` và có role `ADMIN`; điều kiện nằm ở [User.php:58–63](../../backend/app/Models/User.php#L58). Panel còn dùng middleware xác thực Filament tại [AdminPanelProvider.php:44–57](../../backend/app/Providers/Filament/AdminPanelProvider.php#L44).
- Resource áp dụng `ReportPolicy`: xem queue/report, đổi status, moderation và xem evidence là quyền riêng. Evidence private chỉ được xem cho report `IN_REVIEW`, target booking/message và đúng một target; xem [ReportPolicy.php:11–75](../../backend/app/Policies/ReportPolicy.php#L11).
- API Admin dùng session Sanctum, account active và role Admin theo route group; policy/action còn kiểm tra trạng thái và quyền cụ thể. Một số hành động chặn Admin tự review listing/review của chính mình; audit lưu actor, thời điểm, lý do/request ID. Xem [api.php:108–146](../../backend/routes/api.php#L108), [ListingLifecycleController.php:225–248](../../backend/app/Http/Controllers/ListingLifecycleController.php#L225), [ReportController.php:83–142](../../backend/app/Http/Controllers/Admin/ReportController.php#L83).
- Quyền mở panel và Pulse được test theo guest, Owner và Admin không active; test cho thấy chỉ Admin active truy cập được hai dashboard: [AdminDashboardAuthorizationTest.php:14–51](../../backend/tests/Feature/AdminDashboardAuthorizationTest.php#L14).
- Cấp/thu hồi role Admin **không có giao diện**. Artisan command `identity:admin {grant|revoke} {email} {reason}` ghi audit và cấm thu hồi Admin active cuối cùng; xem [AdminRoleCommand.php:8–92](../../backend/app/Console/Commands/AdminRoleCommand.php#L8) và [AdminRoleCommandTest.php](../../backend/tests/Feature/Auth/AdminRoleCommandTest.php#L13).

## 4. Laravel Pulse: cấu hình, recorder, cards và mức xác minh

| Hạng mục | Bằng chứng trong checkout | Kết luận |
|---|---|---|
| Gói | `composer.lock` khóa `laravel/pulse` `v1.8.1`; `composer.json` yêu cầu `laravel/pulse`. | Tích hợp package có trong dependency manifest. |
| Route | Config mặc định path `pulse`; middleware `web` + `Laravel\Pulse\Http\Middleware\Authorize`. | Dashboard riêng `/pulse`; không nằm trong Filament nav. |
| Authorize | Gate `viewPulse` chỉ cho user `ACTIVE` có role `ADMIN`. | Phân quyền server-side; test GET `/pulse` cho Admin trả 200 và các persona còn lại bị chặn. |
| Storage/ingest | Mặc định storage driver `database`, ingest driver `storage`, retention cấu hình 7 ngày, chunk database 1.000. Có migration `pulse_values`, `pulse_entries`, `pulse_aggregates`. | Schema và driver được cấu hình; env triển khai vẫn có thể override các giá trị mặc định. |
| Recorders | Có 10 recorder trong `config/pulse.php`, danh sách bên dưới. | Mặc định recorder có cờ `enabled` dùng fallback `true`; `Servers` có cấu hình server name/directories nhưng không có cờ enabled ở khai báo này. |
| Cards | Không có cấu hình card custom, class card custom hoặc đăng ký Pulse card trong `backend/app`. | Không thể khẳng định tên/bố cục card package đang render: vendor package không có trong checkout. |
| Ghi metric runtime | BE-T06/BE-H06 ghi nhận authorization test và Pulse storage mặc định. | Không có bằng chứng trong tài liệu/source đã đọc chứng minh từng recorder ghi bản ghi metric thực tế ở runtime; test `GET /pulse` xác nhận truy cập page, không xác nhận dữ liệu được thu thập. |

Recorder được khai báo trong [config/pulse.php:139–234](../../backend/config/pulse.php#L139):

| Recorder | Mức cấu hình đọc được từ source |
|---|---|
| `CacheInteractions` | Fallback enabled, sample rate 1; bỏ qua vendor cache keys và group job-exceptions. |
| `Exceptions` | Fallback enabled, sample rate 1; location mặc định true. |
| `Queues` | Fallback enabled, sample rate 1. |
| `Servers` | server name mặc định hostname; theo dõi thư mục cấu hình, mặc định `/`. |
| `SlowJobs` | Fallback enabled, sample rate 1, threshold mặc định 1.000 ms. |
| `SlowOutgoingRequests` | Fallback enabled, sample rate 1, threshold mặc định 1.000 ms. |
| `SlowQueries` | Fallback enabled, sample rate 1, threshold mặc định 1.000 ms; bỏ qua câu query trên bảng Pulse/Telescope. |
| `SlowRequests` | Fallback enabled, sample rate 1, threshold mặc định 1.000 ms; loại trừ endpoint dashboard Pulse và Telescope. |
| `UserJobs` | Fallback enabled, sample rate 1. |
| `UserRequests` | Fallback enabled, sample rate 1; loại trừ endpoint dashboard Pulse và Telescope. |

**Không đồng nhất recorder với card đã xác minh.** Từ tên class có thể xác nhận các nhóm thu thập được cấu hình (cache, exception, queue, server, tác vụ/request/query chậm, activity theo user), nhưng không thể xác nhận exact card title, số liệu render hay có dữ liệu hiện thời. NFR yêu cầu thêm request rate, p95, 5xx, payment callback lỗi, booking quá hạn, release lỗi và upload lỗi; source config không khai báo custom Pulse recorder cho các business metrics này. Không có căn cứ để coi chúng đang xuất hiện trên Pulse.

BE-T06 ghi Pulse dùng storage mặc định, không dùng Redis ingest chung với queue, và authorization test pass. BE-H06 cũng ghi runtime queue/scheduler observations nhưng không ghi metric sample/card verification. Xem [BE-T06 §§2–4](../05%20%E2%80%94%20Implementation%20Plan/BE-T06%20%E2%80%94%20B%C3%A0n%20giao%20t%E1%BB%AB%20BE-H06%20v%C3%A0%20nghi%E1%BB%87m%20thu%20E2E.md#L18) và [BE-H06 §5](../05%20%E2%80%94%20Implementation%20Plan/BE-H06%20%E2%80%94%20Task%203%20integration%20and%20E2E%20verification%20report.md#L74). Vì vậy trạng thái chính xác là **đã cấu hình và có kiểm tra quyền; chưa đủ bằng chứng để xác nhận metric/card đang hoạt động trong runtime hiện tại**.

## 5. Backend Admin có nhưng chưa có giao diện quản trị

| Nghiệp vụ | Khả năng đã có backend | Giao diện hiện tại |
|---|---|---|
| Listing moderation | Admin list/filter theo trạng thái và xem management resource gồm trạng thái/version, suspension source, media READY, quyết định gần nhất và pending change request; approve/reject DRAFT đang chờ duyệt; suspend/restore/archive; audit. | API `/api/admin/listings*`; media stream có route bảo vệ; chưa có Filament Resource/Page. [ListingLifecycleController.php:34–46, 49–55](../../backend/app/Http/Controllers/ListingLifecycleController.php#L34), [API Contract §Admin listing](../08%20%E2%80%94%20Architecture%20%26%20Technical%20Design/04%20%E2%80%94%20API%20Contracts%20%26%20Error%20Conventions.md#L392), [api.php:134–145](../../backend/routes/api.php#L134). |
| Listing change requests | Queue/pagination; xem yêu cầu và media được phép; approve/reject request `PENDING_REVIEW`, kiểm tra version/state và ghi audit. | API `/api/admin/listing-change-requests` và `/api/admin/listings/{id}/change-requests/*`; chưa có Filament UI. [api.php:141–145](../../backend/routes/api.php#L141), [API Contract §Admin route](../08%20%E2%80%94%20Architecture%20%26%20Technical%20Design/04%20%E2%80%94%20API%20Contracts%20%26%20Error%20Conventions.md#L409). |
| Report detail/evidence/history | JSON detail có reporter ID, target summary, reason code, description, status và timestamp; evidence booking/message qua policy và audit; history trả status event + moderation action. Message evidence có body/sender/time; booking evidence chỉ có listing, status, start/end (không có pet snapshot/instructions/payment). Không có target/report evidence riêng cho care update trong schema/API được kiểm tra; ảnh care update kiểm tra participant booking. | Detail/evidence/history API; Filament table chỉ mở history/evidence endpoint ở tab mới, không có Resource detail page. [ReportController.php:63–143, 221–255](../../backend/app/Http/Controllers/Admin/ReportController.php#L63), [reports migration](../../backend/database/migrations/2026_10_28_000004_create_booking_content_and_moderation_schema.php#L46), [CareUpdateController.php:147–180](../../backend/app/Http/Controllers/CareUpdateController.php#L147). |
| Booking completion review | Queue booking `AWAITING_CONFIRMATION` đã đến hạn Admin, gồm booking ID/status/start/end/thời điểm chờ/đưa vào queue; trả thêm release operations `MANUAL_REVIEW` với ID, source, reason code, attempt count, lỗi cuối và ngày tạo. Action complete cần lý do và tạo release operation. | API `/api/admin/booking-completion-reviews` và `/{id}/complete`; không có Filament page. [BookingCompletionReviewController.php:16–89](../../backend/app/Http/Controllers/Admin/BookingCompletionReviewController.php#L16), [api.php:125–133](../../backend/routes/api.php#L125). |
| Cancellation manual review | Queue cancellation operations `MANUAL_REVIEW` gồm booking ID, loại/source/reason code, số lần thử, lỗi cuối và ngày tạo; không trả payment data nhạy cảm. Admin retry cần lý do, audit và giữ gateway operation key; retry không đổi booking ngay request. | API `/api/admin/booking-cancellations` và `/{id}/retry`; không có Filament page. [api.php:123–124](../../backend/routes/api.php#L123), [API Contract §Admin cancellation queue](../08%20%E2%80%94%20Architecture%20%26%20Technical%20Design/04%20%E2%80%94%20API%20Contracts%20%26%20Error%20Conventions.md#L428). |
| Cấp/thu hồi Admin role | `identity:admin grant|revoke email reason`; audit; không thể thu hồi Admin active cuối cùng. | Trusted CLI command, không có UI. [AdminRoleCommand.php](../../backend/app/Console/Commands/AdminRoleCommand.php#L8). |
| API reference | `/docs/api` và `/docs/api.json`; Gate `viewApiDocs` giới hạn Admin active. | Công cụ tài liệu/API riêng, không phải Filament. [ApiDocumentationAccessTest.php:14–85](../../backend/tests/Feature/ApiDocumentationAccessTest.php#L14), [AppServiceProvider.php:760–765](../../backend/app/Providers/AppServiceProvider.php#L760). |

### Phạm vi điều khiển booking cần hiểu đúng

- Report booking chỉ quyết định `COMPLETE` hoặc `CANCEL_REFUND` qua `DecideBookingReport`; phải đang `IN_REVIEW`, có payment attempt `AUTHORIZED`, và trạng thái booking hợp lệ. Action ghi report `RESOLVED` cùng operation, còn worker mới cập nhật booking sau khi gateway thành công. [DecideBookingReport.php:62–115, 169–230](../../backend/app/Domain/Report/Actions/DecideBookingReport.php#L62); test Filament xác nhận operation `PENDING` nhưng booking vẫn `AWAITING_CONFIRMATION` trong [ReportResourceTest.php](../../backend/tests/Feature/Filament/ReportResourceTest.php#L205).
- Admin completion review độc lập với report queue: completion follow-up quá 72 giờ tạo hàng chờ; endpoint complete tạo release operation. Trong code queue còn trả các release operations `MANUAL_REVIEW`, nhưng controller chỉ có action `complete`; không thấy route retry release riêng trong `api.php`. Không suy rộng rằng Admin có thể chỉnh trực tiếp payment hoặc retry release bằng UI.
- Cancellation queue chỉ cho retry operation ở `MANUAL_REVIEW`; `PAYMENT_STATE_MISMATCH` cần xử lý/đối soát trước, retry giữ stable operation key và audit. Admin không dùng booking cancel endpoint thông thường.

## 6. Hai trang Admin của prototype phản ánh backend đến đâu?

Tài liệu prototype đã xác nhận hai **limited Filament-style reference screens**, không phải Admin SPA và không tuyên bố đây là những trang Filament đã tồn tại. Điều này được ghi trong [screen inventory ADM-01/02](05-screen-inventory.md#L36), [screen specifications](06-screen-specifications.md#L155), [Q-01](11-open-questions-and-decisions.md#L23). Prototype tự ghi rõ đây là local simulation, không kết nối backend ở [README](../../prototype/README.md#L32) và [adminLayout/app.js](../../prototype/js/app.js#L188).

### Listing Moderation

| Prototype hiện tại | Backend đối chiếu | Đánh giá |
|---|---|---|
| Có queue cho listing `PENDING_REVIEW`; approve/reject có lý do. | API approve chuyển sang `ACTIVE`, reject về `DRAFT`; validate listing và ngăn Admin self-review. | Đúng workflow cốt lõi; UI chưa phải Filament thật. Mock không thực hiện đầy đủ kiểm tra server/media/version/concurrency. |
| Có một change request demo; approve/reject và giữ bản public cũ trong lúc chờ. | Backend hỗ trợ queue nhiều request, status filter/pagination, detail/media, version check; bản công khai cũ tiếp tục active cho đến khi approve. | Mô phỏng đúng nguyên tắc public version, nhưng thiếu danh sách/chi tiết/media/lifecycle/history thực. |
| Có bảng listing `ACTIVE`/`SUSPENDED` với suspend, restore, archive. | API hỗ trợ các thao tác trên, có reason, suspension source, kiểm tra caregiver, audit; archive bị chặn nếu booking còn giữ lịch. | Nhánh archive hold được mô phỏng trong rules, nhưng `changeListingStatus()` không tăng listing version như backend; mock cũng không mô phỏng nguồn đình chỉ và kiểm tra đầy đủ. |
| Chỉ queue pending + một `pendingChange`; không có filter mọi trạng thái. | Admin API có filter `ALL`, `DRAFT`, `PENDING_REVIEW`, `ACTIVE`, `SUSPENDED`, `ARCHIVED`; change request có queue/pagination. | Thiếu khả năng bao phủ toàn bộ trạng thái và quản lý lịch sử. |
| Lý do bắt buộc cho cả approve và reject trong handler. | Backend cho phép reason approve tùy chọn; reject yêu cầu reason. | UI nghiêm hơn contract đối với approve; cần chỉnh spec/mocks nếu prototype muốn parity. |

Bằng chứng UI: [app.js:191–196](../../prototype/js/app.js#L191), [rules.js:171–184](../../prototype/js/domain/rules.js#L171). Bằng chứng API: [ListingLifecycleController.php:71–179](../../backend/app/Http/Controllers/ListingLifecycleController.php#L71), [ListingChangeRequestController.php](../../backend/app/Http/Controllers/ListingChangeRequestController.php#L1), [ListingLifecycleContractTest](../../backend/tests/Feature/ListingLifecycleContractTest.php#L32), [ListingChangeRequestContractTest](../../backend/tests/Feature/ListingChangeRequestContractTest.php#L301).

**Kết luận Listing:** phản ánh đúng các thao tác P0 chính ở mức UX prototype, nhưng thiếu coverage queue/filter/details/media/history và chưa phản ánh đầy đủ server rules. Không được gọi đây là giao diện Filament đang chạy.

### Reports

| Prototype hiện tại | Backend đối chiếu | Đánh giá |
|---|---|---|
| Queue lọc bốn report status; case có target, reason/description, evidence placeholder, timeline và booking operation state. | Filament queue có filter/status; API có report metadata, private evidence tách endpoint, history tách endpoint. | Cấu trúc case phù hợp ý định ADM-02; nhưng detail/timeline/evidence trong prototype là mock, không phải màn hình hiện có của Filament. |
| Report table hiển thị reference/target/reason/status. | Filament còn hiển thị reporter ID và created time; dùng reason code. | Prototype thiếu hai trường để triage và đang hiển thị reason đã humanize thay cho field `reason_code`. |
| Case render description và một evidence text tổng hợp cho mọi target. | API detail trả report description; private evidence chỉ cho booking/message trong `IN_REVIEW`, allowlist trường, có audit; Filament queue không render description/evidence. | Description có API detail, nhưng evidence placeholder không mô phỏng dữ liệu/permission thật. Không hiển thị như bằng chứng thực. |
| Có `Start review`, `Resolve`/`Dismiss` và booking decision. | Generic transition `OPEN → IN_REVIEW|DISMISSED`, `IN_REVIEW → RESOLVED|DISMISSED`; booking `RESOLVED` bắt buộc đi qua booking decision. | Mock cho phép submit quyết định report từ cả `OPEN` và `IN_REVIEW`; backend yêu cầu `IN_REVIEW`. Vì vậy mock có thể nhận Resolve/booking decision mà API sẽ trả conflict. |
| Mock booking operation có selector thành công/thất bại; thành công đổi booking/payment ngay; thất bại giữ report `IN_REVIEW`. | Backend ghi report `RESOLVED` khi tạo operation, kể cả operation ban đầu `PENDING`; worker chỉ đổi booking khi release/refund thành công. `COMPLETE` và `CANCEL_REFUND` có state/payment guards; không chỉnh booking trực tiếp ở request. | Đây là mismatch quan trọng về lifecycle/asynchronous state. Prototype hiện trình bày operation fail như còn report đang xử lý và success như đồng bộ; chưa đúng model operation thực. |
| `CANCEL_REFUND` mock cho phép booking ở `AWAITING_PAYMENT`, `CONFIRMED`, `AWAITING_CONFIRMATION`. | `DecideBookingReport` giới hạn nhánh refund ngoại lệ vào `IN_PROGRESS` hoặc `AWAITING_CONFIRMATION`, cần attempt `AUTHORIZED` và không có release/cancel operation xung đột. | Nhánh `AWAITING_PAYMENT`/`CONFIRMED` trong mock không tương ứng booking-report decision backend. |
| Không có nút Hide/Restore bên trong report case. | Filament có `hideTarget`/`restoreTarget` cho listing/review report `IN_REVIEW`; API moderation dùng action chung. | Thiếu thao tác report moderation đã được triển khai. Listing hide/restore ở trang riêng không thay cho action gắn với report/reason/history. |

Bằng chứng prototype: [adminReports/reportCase](../../prototype/js/app.js#L197), [decideReport](../../prototype/js/domain/rules.js#L150), [submit handler](../../prototype/js/app.js#L355). Bằng chứng backend: [ReportResource.php:107–145](../../backend/app/Filament/Resources/Reports/ReportResource.php#L107), [DecideBookingReport.php:62–115](../../backend/app/Domain/Report/Actions/DecideBookingReport.php#L62), [ReportWorkflowTest](../../backend/tests/Feature/ReportWorkflowTest.php#L115), [BookingReportDecisionTest](../../backend/tests/Feature/BookingReportDecisionTest.php#L42), [ReportResourceTest](../../backend/tests/Feature/Filament/ReportResourceTest.php#L205).

**Kết luận Reports:** mô hình danh sách/case đúng hướng P0 và đã được phê duyệt làm UX reference; không parity với Filament UI hiện hữu. Trước khi dùng để chốt implementation, cần sửa các điều kiện/status/async behavior kể trên và mô phỏng Hide/Restore + evidence authorization đúng hơn.

## 7. Đề xuất sitemap Admin

### A. Đang triển khai và có giao diện

| Sitemap | Mức hiện thực | Phạm vi |
|---|---|---|
| `/admin/login` | Đã có | Đăng nhập panel Filament; chỉ account active có Admin role mới qua được panel. |
| `/admin` → Dashboard | Đã có, mặc định | Account widget + Filament info widget; không có PETTEL KPI/queue. |
| `/admin/reports` → Reports | Đã có, Resource duy nhất | Queue/filter; status action, listing/review hide/restore, booking decision; history/evidence mở API route. Không có create/edit/delete CRUD. |
| `/pulse` → Operations monitoring | Đã có, độc lập Filament | Pulse package dashboard, Gate Admin active; nguồn xác nhận recorder config, chưa xác nhận card/records runtime. |
| `/docs/api` và `/docs/api.json` | Đã có, công cụ riêng | API docs/contract; chỉ Admin active. Không phải chức năng xử lý dữ liệu nghiệp vụ. |

### B. Có backend nhưng chưa có Admin UI

| Sitemap đề xuất | Backend có thể dùng | Trạng thái UI |
|---|---|---|
| **Nội dung → Listing moderation**: queue, filter trạng thái, detail/media, approve/reject, suspend/restore/archive, decision audit | Đã có admin API và tests. | **Mới đề xuất giao diện**; chưa có Filament Resource. |
| **Nội dung → Listing change requests**: queue/filter, compare payload/version, media, approve/reject | Đã có admin API và tests. | **Mới đề xuất giao diện**; có thể cùng Resource Listing nhưng đây là lựa chọn thiết kế UI. |
| **Reports → Case detail**: metadata, lý do, evidence có kiểm soát, history, Hide/Restore, booking decision | Report API/policy/action đã có; table Reports Filament hiện hữu. | Nâng cấp UX/detail; hiện Filament chỉ có queue và các action, history/evidence là API route. |
| **Booking exceptions → completion review** | Queue overdue + release `MANUAL_REVIEW`, complete bằng operation API. | **Mới đề xuất UI.** Tài liệu prototype đã chốt không tạo dashboard booking riêng; trong prototype hiện tại chỉ thể hiện operation trong report case. Nếu cần phủ queue độc lập, cần mở lại quyết định scope. |
| **Booking exceptions → cancellation retry** | Queue `MANUAL_REVIEW` + retry action có audit qua API. | **Mới đề xuất UI.** Không có Filament page; không được nhập nhằng với report booking decision. |

### C. Chưa có cơ sở để thêm thành chức năng UI

- Trang quản lý user/role Admin: hiện tại chỉ có trusted CLI command; chưa thấy API/UI quản trị user và role.
- Audit Log explorer tổng quát: hệ thống ghi audit từ nhiều workflow nhưng chưa có Resource/read API tổng quát được xác định trong route/source đã kiểm tra.
- Message moderation/warning workflow: tài liệu nhắc tới, nhưng backend hiện không hỗ trợ action này; cần quyết định nghiệp vụ/API trước khi đưa thành sitemap chức năng.
- Pulse cards/custom business metrics: không thêm KPI giả định vào Filament Dashboard. Muốn có business dashboard cần chọn metric, nguồn dữ liệu, semantics và quyền truy cập trước.

## 8. Mâu thuẫn và quyết định cần xác nhận

1. **Message report và cảnh báo caregiver:** Workflow 04 nêu ẩn message hoặc cảnh báo caregiver; M09 target cho phép `message`; nhưng `ReportPolicy::moderate` chỉ cho listing/review và `ModerateReportedTarget` trả 422 cho message/booking. Đây là chênh lệch tài liệu/code, không nên tự thêm hành động moderation. BE-T06 cũng ghi nhận nguồn chưa thống nhất về action moderation cho từng target. Cần chốt message report hiện chỉ được dismiss/resolve hay phải có workflow hide/warning riêng.
2. **Care-update evidence và quyền Admin:** BE-T06 nói private evidence bao gồm care evidence, nhưng schema report chỉ cho bốn target `listing/review/message/booking`, report evidence controller chỉ trả message hoặc booking evidence. Route ảnh care update lại yêu cầu actor là participant của booking. Chưa tìm thấy Admin-specific care-update evidence route/policy trong source. Cần chốt liệu report có cần target care update hay Admin chỉ được đọc nội dung trong phạm vi report khác; không mở rộng quyền đọc ảnh/tin nhắn từ role Admin chung.
3. **Booking operations trong Admin:** backend có completion review queue và cancellation retry queue; quyết định prototype Q-01 trước đây nói không tạo dashboard booking riêng, chỉ đưa operation state vào report case. Nếu Admin product cần hàng chờ vận hành đầy đủ ngoài report, cần xác nhận phạm vi UI trước khi thêm navigation.
4. **Pulse cards và dữ liệu hoạt động:** source xác nhận cấu hình recorder/defaults và route authorization; không có package vendor hoặc metric sample hiện tại trong checkout để xác nhận exact cards/metric runtime. Trước khi viết hướng dẫn vận hành dựa trên card, cần kiểm tra bản deployment thực tế hoặc cài dependencies và xác nhận ở runtime.
5. **BE-T04 có đoạn mô tả baseline cũ:** mục đối chiếu ban đầu của tài liệu ghi thời điểm chưa có expiry/cancel; phần checklist/bàn giao về sau và source hiện tại ghi đã có cancellation queue/API/tests. Báo cáo dùng source hiện tại, route và test; không dùng baseline lịch sử làm trạng thái hiện tại.

Không có quyết định nào được tự áp dụng vào business rules hoặc source trong nhiệm vụ audit này.

## 9. Trạng thái kiểm tra và giới hạn

- Đã đọc source/config/routes/tests và dùng các test method/handoff hiện có làm bằng chứng chức năng. Không chạy lại test suite, không mở browser; mục tiêu yêu cầu là kiểm tra repository và báo cáo read-only.
- Không thể xác nhận Pulse card/UI dữ liệu trực tiếp vì `backend/vendor` chưa có trong checkout. Handoff BE-T06 xác nhận test authorization, không chứng minh các recorder đã ghi metric thực tế trong runtime hiện tại.
- Prototype README ghi rõ Admin reference chỉ dùng local/mock, persona switch không phải quyền thật, và prototype không kết nối Laravel/API. Vì vậy kết quả mô phỏng không được xem là bằng chứng server authorization, audit, payment operation hay concurrency.
- Chỉ file báo cáo này được tạo; không sửa code, backend, Vue production hoặc prototype.
