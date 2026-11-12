# PETTEL interactive prototype

Bản prototype tương tác độc lập để review luồng P0 trước khi triển khai Vue 3. Dùng HTML, CSS và JavaScript thuần; không gọi Laravel/API, không dùng framework hoặc CDN, không có payment gateway và không chứa dữ liệu production.

## Chạy local

Từ thư mục gốc repository:

```bash
python3 -m http.server 4173 --directory prototype
```

Mở <http://localhost:4173/>. Có thể dùng VS Code Live Server trên `prototype/index.html`. Prototype lưu trạng thái mô phỏng trong `localStorage` của trình duyệt; mở **Demo controls → Đặt lại dữ liệu demo** để khôi phục fixtures.

## Điều khiển demo

- **Prototype persona** ở góc phải: Khách, Owner, Caregiver hoặc Admin reference. Đây là chuyển persona phục vụ trình diễn, không phải cấp quyền trong sản phẩm.
- **Demo controls → Giờ demo**: 18/11/2026 08:00 mặc định; tùy chọn +16 phút, +24 giờ, +72 giờ để kiểm tra hold/expiry/reminder. Mốc 24/72 giờ không tự hoàn tất booking.
- Một số trang có scenario selector (thành công, thất bại, callback muộn). Mọi kết quả đều được gắn nhãn mô phỏng.

## Hành trình gợi ý

1. Guest: Khám phá → mở dịch vụ → kiểm tra điều kiện, lịch, giá và đánh giá minh họa.
2. Owner: chọn persona → Thú cưng → Smart Match (Mochi có kích cỡ; Miu thiếu kích cỡ) → tạo booking → phát lại idempotency → theo dõi booking.
3. Caregiver: Tổng quan/Lượt đặt → nhận `booking-requested-01`; thử nhận `booking-conflict-01` để thấy xung đột; từ chối các yêu cầu khác; thao tác thanh toán và chăm sóc.
4. Owner: thanh toán mô phỏng thất bại rồi retry thành công; hoặc thử expiry; trong booking đang chăm sóc mở Tin nhắn/Cập nhật chăm sóc → gửi yêu cầu hoàn tất.
5. Owner: dùng `booking-admin-overdue-01` để thử giải ngân thất bại rồi thành công (reset dữ liệu trước nếu đã xử lý ở Admin); booking `booking-awaiting-confirmation-01` đang bị report IN_REVIEW nên xác nhận hoàn tất bị chặn. Đánh giá chỉ mở sau `COMPLETED`.
6. Admin: chọn Admin persona → `#/admin` → Listing management, Reports & Moderation, Booking exceptions và System monitoring. Dùng report `report-01` để kiểm tra COMPLETE tạo operation PENDING, sau đó thử worker result. Dùng cancellation operation MANUAL_REVIEW để kiểm tra reason và retry.
7. Owner → Báo cáo → Admin reference → Reports: mở case, xem evidence có ghi audit, thử dismiss hoặc COMPLETE/CANCEL_REFUND với operation bất đồng bộ.
8. Caregiver → Dịch vụ: tạo bản nháp, lưu, gửi duyệt; Admin → Listing management. Với listing ACTIVE, tạo đề xuất thay đổi rồi phê duyệt/từ chối để thấy version công khai cũ/new.
9. Thử menu, filter, form, dialog và card ở viewport 320 px, tablet và desktop; dùng Tab/Shift+Tab/Enter/Escape để kiểm tra thao tác bàn phím.

## Admin workspace

Admin prototype có sitemap riêng: `#/admin`, `#/admin/listings`, `#/admin/reports`, `#/admin/booking-exceptions` và `#/admin/system`. Giao diện desktop-first dùng PETTEL/Petty indigo, mauve và neutrals; breakpoint chuyển sidebar thành drawer cho tablet/mobile. Persona Admin có trạng thái ACTIVE/INACTIVE mô phỏng để kiểm tra access gate. Demo controls có lựa chọn `ready/loading/error` để review empty/loading/error presentation; `Thử tải lại` khôi phục ready.

Dashboard và queue numbers lấy từ fixtures/localStorage. Listing, report, operation actions có reason/state/version guard mô phỏng; booking state chỉ đổi sau worker demo success. Retry có cho cancellation MANUAL_REVIEW; không có Retry Release. Evidence chỉ hiện trong report BOOKING/MESSAGE đang IN_REVIEW. System monitoring chỉ liên kết tới Laravel `/pulse` và `/docs/api`, không dựng metric giả.

Xem [ADMIN-SCREEN-MAPPING.md](ADMIN-SCREEN-MAPPING.md) để phân biệt AS-IS backend, mock simulation và TO-BE UI; xem [REDESIGN-ANALYSIS.md](REDESIGN-ANALYSIS.md) để biết palette/component Petty đã đối chiếu. Source Admin nằm trong `js/app.js`, `js/domain/rules.js`, `js/data/fixtures.js`, `js/services/mock-store.js` và `css/admin.css`.

## Source layout

- `index.html` — static entry và app mount.
- `css/tokens.css`, `css/styles.css`, `css/petty-theme.css`, `css/admin.css` — design tokens, user-facing theme và responsive Admin workspace.
- `js/data/fixtures.js` — persona/entity/scenario seed tổng hợp.
- `js/domain/rules.js` — quote, Smart Match, booking transitions, payment/completion rules.
- `js/services/mock-store.js` — single mock state, local persistence và reset.
- `js/app.js` — hash navigation, screen rendering và interaction handlers.
- `assets/` — SVG minh họa nội bộ, không lấy từ website ngoài.

## Giới hạn đã biết

- Đây là mô phỏng UI/state trong một browser, không kiểm chứng concurrency, transaction lock, queue/outbox, authorization server hoặc Laravel runtime.
- Booking list/detail dùng mock vì backend snapshot hiện không có general GET read model/route đã xác nhận.
- Legacy website `quangnguyentan/pettel` không truy cập được trong phiên khảo sát. Visual redesign hiện tham chiếu Figma Petty (Community), không khẳng định là bộ nhận diện chính thức của PETTEL.
- Email verification và giao diện yêu cầu xóa tài khoản chưa được xác nhận; không thêm thành flow chính thức.
- Upload chỉ mô phỏng trong browser; không lưu/truyền file, không bảo đảm xử lý ảnh production.
- Admin là prototype UI riêng cho sitemap quản trị đã yêu cầu; các trang Listing/Booking và phần detail UX là mô phỏng API, không phải Filament UI đã tồn tại. Xem mapping và giới hạn browser trong [ADMIN-SCREEN-MAPPING.md](ADMIN-SCREEN-MAPPING.md).
- No external photo assets/CDNs. Ảnh SVG là minh họa, không đại diện cho người chăm hoặc bằng chứng dịch vụ.

## Verification status

Xem [TEST-RESULTS.md](TEST-RESULTS.md) để biết các kiểm tra đã chạy. Cú pháp JS, cấu trúc HTML/CSS và các assertion domain Admin/Owner/Caregiver đã pass. Browser walkthrough chưa chạy được trong phiên này vì runtime không cho bind local server và browser policy chặn URL `file://`; không dùng đường vòng. Visual rendering, responsive và keyboard acceptance cần được review trong browser local theo hướng dẫn chạy ở trên.
