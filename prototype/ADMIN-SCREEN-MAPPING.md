# PETTEL Admin prototype — screen mapping and scope

**Ngày cập nhật:** 11/11/2026  
**Phạm vi:** redesign Admin trong prototype HTML/CSS/JS hiện tại; không sửa Vue production hoặc Laravel.

## Nguyên tắc phân biệt trạng thái

| Nhãn | Ý nghĩa |
|---|---|
| **AS-IS backend** | Được xác nhận trong audit/source/test Laravel; không đồng nghĩa đã có Admin UI. |
| **Prototype simulation** | Route, dữ liệu và thao tác chạy cục bộ trong `localStorage`; không gọi API hoặc Filament. |
| **TO-BE UI** | Cách sắp xếp màn hình mới để nhóm review; cần tích hợp Vue/API riêng nếu được duyệt. |

Yêu cầu hiện tại mở rộng prototype cũ từ hai màn moderation/report sang sitemap Admin đầy đủ, gồm Booking exceptions và System monitoring. Đây là phạm vi prototype theo chỉ dẫn hiện tại; không sửa tài liệu nghiệp vụ và không coi các màn hình mới là chức năng Filament đã triển khai.

## Sitemap và ánh xạ backend

| Route trong prototype | Nội dung | AS-IS backend | Prototype simulation / TO-BE UI |
|---|---|---|---|
| `#/admin` | Dashboard: listing chờ duyệt, report OPEN/IN_REVIEW, completion quá hạn, cancellation MANUAL_REVIEW, mock audit gần đây. | `/admin` Filament dùng Dashboard mặc định với AccountWidget và FilamentInfoWidget; không có PETTEL KPI/queue. | Dashboard mới trong prototype. KPI chỉ tính trên fixtures/local state và có nhãn MOCK DATA. |
| `#/admin/listings` | Listing queue, search/status filter, detail, approve/reject, reason, suspend/restore/archive, version/history, change request compare/decision. | Listing lifecycle và change request có Admin API/test, chưa có Filament Resource. Các mutation có state/version guard, reason và audit. | UI mới đề xuất trên API hiện hữu. Change request sử dụng fixture `ui.pendingChange` khi Caregiver tạo yêu cầu; trạng thái không có request có empty state. |
| `#/admin/reports` | Queue với reporter/reason code/time, filters/search, case detail, history, evidence có điều kiện, start review, resolve/dismiss, Hide/Restore và booking decision. | `ReportResource` là Resource nghiệp vụ Filament duy nhất. History/evidence mở API route riêng; Hide/Restore chỉ cho listing/review ở IN_REVIEW; booking decision gọi operation. | Queue được làm rõ và case detail được mô phỏng. Case detail/history/evidence là UX đề xuất, không phải Filament detail page. Evidence demo chỉ mở cho report BOOKING/MESSAGE ở IN_REVIEW. |
| `#/admin/booking-exceptions` | Tab completion review quá hạn và cancellation manual review; operation status, lý do retry, worker simulation. | API có queue completion review và `/{id}/complete`; cancellation MANUAL_REVIEW và `/{id}/retry`. Không tìm thấy Filament page. Không có retry release route. | Màn hình mới theo phạm vi được yêu cầu. Retry chỉ xuất hiện cho cancellation MANUAL_REVIEW; release MANUAL_REVIEW chỉ xem trạng thái/lỗi. Worker controls ghi rõ là mô phỏng, không phải Admin API. |
| `#/admin/system` | Điều hướng tới Laravel Pulse và API Documentation; nêu rõ các giới hạn đã xác minh. | Pulse ở `/pulse`, Docs ở `/docs/api` và `/docs/api.json`, đều được gate cho Admin ACTIVE. Pulse config khai báo 10 recorders; exact card/runtime samples chưa xác minh. | Chỉ là link/tóm tắt ranh giới. Không dựng Pulse cards, request rate, p95, 5xx, payment hoặc queue metrics giả. |

## Quy tắc và tương tác đã ánh xạ

### Persona và quyền mô phỏng

- Chỉ persona `admin` với `ui.adminStatus === 'ACTIVE'` render nội dung quản trị. Header có công tắc **ACTIVE · thử khóa / INACTIVE · mở lại** để review nhánh bị chặn.
- Persona switch là công cụ prototype, không phải authorization server. Laravel vẫn kiểm tra account `ACTIVE`, role `ADMIN`, panel policy/API policy ở backend.

### Listing lifecycle

- Chỉ listing `PENDING_REVIEW` có action approve/reject. Reason bắt buộc; approve tăng version và đưa listing sang ACTIVE, reject trả listing về DRAFT.
- Suspend/restore/archive nhận expected version trong mô phỏng; thao tác Admin tăng version và ghi mock audit. Restore chỉ cho listing đang SUSPENDED do Admin. Archive bị từ chối nếu listing còn booking giữ lịch.
- Listing change request so sánh public version với bản đề xuất. Apply chỉ khi version đề xuất vẫn là `current + 1`; version công khai cũ giữ nguyên tới khi apply.
- Đây là UX/state mock. Nó không kiểm thử transaction, concurrency hay server authorization.

### Reports, evidence và moderation

- `OPEN` cho Start review hoặc Dismiss. `IN_REVIEW` mới cho Resolve, Hide/Restore hoặc booking decision. Booking report không có Resolve genérico.
- Hide/Restore chỉ áp dụng listing/review target; không thêm hide message, cảnh báo caregiver hoặc moderation action cho booking.
- Evidence hiển thị chỉ trong report BOOKING/MESSAGE ở IN_REVIEW. Booking evidence giới hạn ở listing, booking status và thời gian; message evidence giới hạn ở sender, thời điểm và body của message liên quan. Không hiển thị pet snapshot, instructions, payment data, care-update image hoặc target ngoài allowlist.
- Mở evidence ghi event vào history mô phỏng. Report description/target summary là mock data.

### Booking operation lifecycle

- Completion review queue chỉ lấy booking `AWAITING_CONFIRMATION` có `completionRequestedAt` quá 72 giờ. Action tạo RELEASE operation `PENDING`; report/status request không chuyển booking/payment thành hoàn tất.
- Booking report `COMPLETE`/`CANCEL_REFUND` yêu cầu report `IN_REVIEW`, payment attempt `AUTHORIZED` và booking state tương ứng. Khi tạo operation, report chuyển `RESOLVED`; operation có trạng thái riêng và ban đầu `PENDING`.
- Worker demo thành công mới cập nhật booking/payment mock. Worker demo thất bại chuyển operation sang `MANUAL_REVIEW`, không sửa booking/payment.
- Chỉ cancellation operation `MANUAL_REVIEW` có Retry, reason 10–500 ký tự. Mô phỏng giữ operation key, đưa operation về `PENDING`, không đổi booking ở request. `PAYMENT_STATE_MISMATCH` không được retry.
- Không có nút **Retry Release**. Release operation `MANUAL_REVIEW` chỉ hiển thị trạng thái/lỗi vì backend không có retry action đã xác nhận.

## Bằng chứng backend đã đối chiếu

- Filament panel/resource/access: [AdminPanelProvider.php](../backend/app/Providers/Filament/AdminPanelProvider.php#L22), [ReportResource.php](../backend/app/Filament/Resources/Reports/ReportResource.php#L26), [User.php](../backend/app/Models/User.php#L58), [ReportPolicy.php](../backend/app/Policies/ReportPolicy.php#L9).
- Admin routes và listing moderation: [api.php](../backend/routes/api.php#L108), [ListingLifecycleController.php](../backend/app/Http/Controllers/ListingLifecycleController.php#L34), [ListingChangeRequestContractTest](../backend/tests/Feature/ListingChangeRequestContractTest.php).
- Report actions/evidence/booking operations: [ReportResource.php](../backend/app/Filament/Resources/Reports/ReportResource.php#L107), [ReportController.php](../backend/app/Http/Controllers/Admin/ReportController.php#L18), [DecideBookingReport.php](../backend/app/Domain/Report/Actions/DecideBookingReport.php#L62), [BookingReportDecisionTest](../backend/tests/Feature/BookingReportDecisionTest.php).
- Booking queues: [BookingCompletionReviewController.php](../backend/app/Http/Controllers/Admin/BookingCompletionReviewController.php#L14), [BookingCancellationQueueController.php](../backend/app/Http/Controllers/Admin/BookingCancellationQueueController.php#L14), [BE-T06](../docs/05%20%E2%80%94%20Implementation%20Plan/BE-T06%20%E2%80%94%20B%C3%A0n%20giao%20t%E1%BB%AB%20BE-H06%20v%C3%A0%20nghi%E1%BB%87m%20thu%20E2E.md).
- Pulse/docs: [config/pulse.php](../backend/config/pulse.php#L20), [AdminDashboardAuthorizationTest](../backend/tests/Feature/AdminDashboardAuthorizationTest.php), [ApiDocumentationAccessTest](../backend/tests/Feature/ApiDocumentationAccessTest.php).
- Nguồn/tài liệu tổng hợp: [admin-audit.md](../docs/prototype-design/admin-audit.md), [Petty Figma analysis](REDESIGN-ANALYSIS.md).

## Ghi chú vận hành prototype

- Chạy từ repository root: `python3 -m http.server 4173 --directory prototype`, rồi mở `http://localhost:4173/`.
- Chọn persona Admin từ header persona. Có thể vào trực tiếp `#/admin` hoặc các route trong sitemap.
- Loading/error trong Demo controls chỉ áp dụng màn hiện tại; khi mở route Admin mới hoặc tải trực tiếp route, màn tự trở về `ready` để trạng thái demo không che trang đích.
- Dùng Demo controls → Đặt lại dữ liệu demo để khôi phục fixtures. `mock-store.js` merge các field Admin mới vào state v1 cũ, không xóa dữ liệu Owner/Caregiver đã lưu.
- Liên kết `/pulse` và `/docs/api` dùng origin hiện tại. Khi prototype chạy ở port riêng, mở hai path trên host Laravel đang chạy.

## Giới hạn hiện tại

- Toàn bộ Admin UI và dữ liệu mới là local mock; không có network request, không kiểm tra Filament/API runtime.
- Bảng local đồng bộ không phát request thật. Demo controls có thể bật skeleton loading và error state để review UI; đây là presentation scenario, không mô phỏng API timeout hoặc backend failure. Empty state và validation/stale version errors có tương tác. Loading của Pulse/API Docs thuộc backend page mở ngoài prototype.
- Browser walkthrough và screenshots không hoàn tất được trong runtime này: local server bind trả `PermissionError: Operation not permitted`, còn browser policy chặn `file://` và cấm đường vòng qua browser khác/raw CDP. Cú pháp, domain behavior và state transitions đã được kiểm tra qua Node, nhưng visual/responsive/click QA vẫn cần chạy trong browser local do người dùng mở.
