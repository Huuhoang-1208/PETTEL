# 01 — Project Context

**Trạng thái:** CONFIRMED cho các quyết định P0 có nguồn; INFERRED cho mô tả vai trò/luồng rút gọn; PROPOSED cho NFR/giả thuyết chưa được xác nhận bằng nghiên cứu.  
**Nguồn:** Product Overview, Product Scope, Actors & Roles, P0 Decision Contract, Module Map, README và code/routes hiện có. Chi tiết nguồn và giới hạn bằng chứng ở [00-source-inventory.md](00-source-inventory.md).

## 1. Sản phẩm và mục tiêu prototype

PETTEL là bản mẫu web giúp chủ nuôi tại Đà Nẵng tìm, so sánh và đặt dịch vụ chăm sóc ban ngày hoặc lưu trú cho thú cưng. Trọng tâm UX là giúp người dùng hiểu giá, thời gian, điều kiện nhận pet và bằng chứng đánh giá trước khi đặt; sau đó chuyển giao hướng dẫn chăm sóc và theo dõi trong booking.

Prototype là công cụ kiểm chứng luồng và giao diện, không phải frontend sản phẩm thứ hai. Agent xây prototype bằng HTML/CSS/JavaScript, tự chứa, dùng mock data; không kết nối Laravel, không nhận thanh toán thật. Agent khác có thể dùng package này để triển khai prototype mà không phải tự suy đoán domain.

## 2. Actors và ranh giới quyền

| Actor | Mục tiêu | Quyền/giới hạn ảnh hưởng UI |
|---|---|---|
| Khách chưa đăng nhập | Tìm, lọc, xem listing ACTIVE và nội dung công khai | Không xem pet profile, không nhắn tin, đặt lịch hoặc review |
| Owner | Quản lý pet; tạo/theo dõi booking; sandbox payment; chat/care updates; xác nhận hoàn tất; review đủ điều kiện; báo cáo | Chỉ đọc/sửa dữ liệu thuộc mình; review chỉ từ booking COMPLETED; dữ liệu pet và booking là riêng tư |
| Caregiver | Quản lý profile/listing/lịch; accept/reject booking; xem hướng dẫn liên quan; chat/care update; báo hoàn tất | Chỉ quản lý listing/profile của mình; không tự sửa review, cấp badge hoặc tùy tiện đổi payment |
| Admin | Duyệt/moderate listing, xử lý reports và booking exception | Hành động có lý do/audit; đọc nội dung riêng tư chỉ khi cần xử lý case; admin panel dùng Filament trong hệ thống |
| Payment sandbox | Trả kết quả mô phỏng cho payment attempt | Không có tiền thật, không nhập thông tin thẻ; callback/replay/amount là domain behavior, trong prototype chỉ mô phỏng nhánh đã được duyệt |

Một user có thể có cả role OWNER và CAREGIVER; account ADMIN được cấp riêng. Vai trò trong prototype phải đổi qua demo persona rõ ràng, không để UI ngụ ý rằng một role có thể thực hiện hành động của role khác.

## 3. Phạm vi và ưu tiên

### P0 — Hành trình demo khép kín

1. Đăng nhập/tài khoản và pet profile.
2. Caregiver profile, listing, availability và moderation.
3. Khám phá, tìm/lọc, listing detail và Smart Match giải thích được.
4. Tạo booking cho một pet, xem giá/chính sách, accept/reject, availability conflict.
5. Thanh toán sandbox success/failure/retry/expiry/cancel.
6. Chat theo booking, care update có ảnh, care lifecycle và xác nhận completion.
7. Review sau booking COMPLETED.
8. Report intake, admin queue/decision/history ở ranh giới phù hợp Filament.

P0 phải mô phỏng state và quyền thật của domain, kể cả nhánh pending/error/empty/expired; không rút gọn để làm happy path trái lifecycle.

### P1 — Chỉ sau khi P0 đạt

So sánh listing cạnh nhau, email notification, thống kê caregiver, nhiều pet trên một booking, tìm kiếm nâng cao.

### Ngoài phạm vi P0

Tiền/escrow thật; hồ sơ xác minh thật/PETTEL Pro thật; AI/ML matching; video/camera/streaming trực tiếp; native app; bảo hiểm hoặc phân xử tài chính hoàn chỉnh. Nếu xuất hiện dưới dạng minh họa phải gắn nhãn “minh họa”.

## 4. Business rules định hình UI

- Public discovery chỉ hiển thị listing ACTIVE; search kết quả không đảm bảo còn chỗ. Thời điểm accept kiểm tra lại availability và overlap.
- Một booking gắn một pet và một listing. Availability slot phải bao trọn booking; một listing có capacity 1.
- Loại dịch vụ P0: `DAYCARE` theo giờ và `BOARDING` theo block tối đa 24 giờ. Giá VND số nguyên, server tính tổng; UI giải thích tổng và đơn vị trước khi gửi.
- Booking chính: `REQUESTED → AWAITING_PAYMENT → CONFIRMED → IN_PROGRESS → AWAITING_CONFIRMATION → COMPLETED`, cùng các nhánh `CANCELLED`/expiry/reject. Không tự complete hoặc release khi Owner im lặng.
- Accept giữ lịch 15 phút, retry không gia hạn; `REQUESTED` hết hạn sau 24 giờ hoặc lúc start, tùy mốc sớm hơn.
- Payment chỉ sandbox. `COMPLETED` chỉ sau release mô phỏng thành công; lỗi release để trạng thái chờ và phải retry/đưa admin theo contract.
- Caregiver tạo care update chỉ trong `IN_PROGRESS`; chat/care update có quyền đọc theo hai participant và retention. Không tiết lộ pet notes, địa chỉ chi tiết hoặc media riêng cho người ngoài.
- Review chỉ do Owner của booking `COMPLETED` tạo, tối đa một review/booking. Seed/demo review không tính rating công khai và không được gọi verified.
- Report `IN_REVIEW` có thể giữ booking chờ xử lý; quyết định admin cần lý do và audit. Không giả lập chuyển tiền/thay đổi booking trực tiếp từ giao diện nếu service chưa hoàn tất.

Nguồn chuẩn đầy đủ: [P0 Decision Contract](../02%20%E2%80%94%20Domain%20Documentation/05%20%E2%80%94%20P0%20Decision%20Contract.md), [State Lifecycles](../02%20%E2%80%94%20Domain%20Documentation/04%20%E2%80%94%20State%20Lifecycles%20%E2%80%94%20Listing,%20Order,%20Payment.md) và [Business Rules](../02%20%E2%80%94%20Domain%20Documentation/02%20%E2%80%94%20Business%20Rules.md).

## 5. Hệ thống hiện tại và prototype boundary

| Area | Workspace evidence | Prototype implication |
|---|---|---|
| Backend | Laravel JSON API, Sanctum, domain actions/models/controllers, feature tests | Có thể ánh xạ màn hình vào contract/route; không gọi API trong prototype |
| Frontend | `frontend/` hiện chỉ có `.gitkeep`; README nói Vue SPA chưa scaffold | Không có UI hiện tại để tái dùng hoặc audit; HTML/CSS/JS prototype độc lập |
| Admin | Filament panel tại `/admin`; workspace có `ReportResource`, dashboard mặc định; admin API routes gồm reports/listing moderation/booking queues | User confirmed 11/11/2026: limited Filament-style reference screens for listing moderation/reports; booking operation state appears in relevant report case; no separate Admin SPA |
| Data | README nói seed có owner/caregiver/admin, pet và profile nhưng chưa có listing/booking | Prototype cần fixtures nội bộ liên kết nhất quán; đánh dấu mock/demo |
| APIs | Route file hiện có search/listing/reviews/media; pet CRUD; caregiver listing/profile/availability; booking write/actions; messages/care updates; reports/admin | Không thấy `GET /bookings` tổng quát ở `backend/routes/api.php`; booking dashboard/history cần mock trong prototype và ghi gap API cho Vue |

Branch/commit khảo sát là `fe` / `39bde51`; không fetch được GitHub remote nên đây là trạng thái workspace, không phải xác nhận mới nhất của `main`/CI. Không chạy tests trong khảo sát.

## 6. Ngôn ngữ, responsive và accessibility

**PROPOSED / NFR design target:** UI P0 bằng tiếng Việt, layout mobile-first nhưng hỗ trợ desktop, thao tác ở 320 px và zoom 200%, bàn phím/focus/label/heading/contrast/screen reader; không dùng màu đơn lẻ để thể hiện state. Dùng copy dễ hiểu như “Thanh toán mô phỏng thành công”; từ kỹ thuật chỉ ở tài liệu.

Đây là tiêu chí thiết kế trong NFR, không phải bằng chứng đã được đánh giá với người dùng. Nghiên cứu người dùng và metric trong tài liệu hiện là kế hoạch đề xuất, chưa có kết quả phỏng vấn/kiểm thử được lưu tại nguồn đã kiểm tra.

## 7. Traceability tối thiểu

Mọi màn hình P0 phải trỏ tới ít nhất một actor, workflow, business rule/state, API contract/route (nếu có) và nguồn. Dùng nhãn `CONFIRMED`, `INFERRED`, `PROPOSED`, `UNKNOWN` để không trộn quyết định, implementation, mock và đề xuất UX.

