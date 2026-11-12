# PETTEL Prototype Design — Kiểm kê nguồn và trạng thái bằng chứng

**Ngày kiểm kê:** 11/11/2026 (Asia/Ho_Chi_Minh)  
**Workspace snapshot:** branch `fe`, `HEAD=39bde512b997b64aae0650c0352cf7d3efbe62fe` (`update doc`); worktree sạch tại thời điểm kiểm tra.  
**Mục đích:** xác định nguồn cần dùng cho bộ tài liệu prototype, phân biệt quyết định sản phẩm với hành vi đã triển khai.

## 1. Nguyên tắc trạng thái

- **CONFIRMED — product decision:** có quyết định rõ trong P0 Decision Contract hoặc contract đã được chốt. Nhãn này xác nhận quy tắc cho prototype P0, không tự chứng minh code đã triển khai.
- **CONFIRMED — implementation evidence:** hành vi có route/code/test hoặc báo cáo tích hợp cụ thể. Khi cần xác nhận runtime/CI, phải ghi chính xác commit và phạm vi bằng chứng; không suy rộng từ test API sang browser E2E.
- **INFERRED:** suy ra từ nhiều nguồn nhưng chưa có câu chốt trực tiếp.
- **PROPOSED:** mục tiêu thiết kế, giả thuyết, đề xuất hoặc kế hoạch chưa được xác nhận là quyết định bắt buộc.
- **UNKNOWN:** thiếu nguồn, quyền truy cập hoặc bằng chứng đủ để kết luận.

## 2. Nguồn đã kiểm tra

| Nguồn | Nội dung dùng cho prototype | Tình trạng / độ tin cậy | Cách dùng |
|---|---|---|---|
| Hướng dẫn người dùng trong phiên và 3 tệp `Pasted text(1–3).txt` | Phạm vi công việc, 11 phase, 12 đầu ra, ràng buộc không code prototype | Ba tệp Library trùng nội dung; hướng dẫn người dùng mới nhất yêu cầu đi theo 4 checkpoint | Xem là một bộ yêu cầu duy nhất; tuân thủ chiến lược checkpoint mới nhất |
| `docs/00 — PETTEL Documentation Index.md` | Thứ tự nguồn, quy đổi thuật ngữ, giới hạn P0 | Index ghi ngày 26/10/2026; nói rõ nhiều tài liệu trong bộ là đề xuất và không phải bằng chứng triển khai/nghiên cứu | Dùng để định tuyến và không coi bản thảo là bằng chứng runtime |
| `docs/01 - Product Document/01–06` | Vấn đề, giá trị, actor, scope, mục tiêu, metric | Product Overview/Scope là bản thảo; User Research là kế hoạch đề xuất, không phải kết quả nghiên cứu | Dùng làm bối cảnh và mục tiêu; không tuyên bố giả thuyết/metric đã được xác minh |
| `docs/02 — Domain Documentation/01–05` | Glossary, business rules, model, lifecycle, P0 decisions | P0 Decision Contract là nguồn chuẩn cho các quy tắc P0 đã chốt; phần lớn Domain docs mang nhãn bản thảo | Dùng P0 Contract làm chuẩn nghiệp vụ; kiểm tra các quyết định mới trong log của contract |
| `docs/03 — Modules Documentation/00, M01–M09` | Ranh giới chức năng và tiêu chí chấp nhận từng module | Tài liệu module là bản thảo; nhiều phần được cập nhật bằng hợp đồng BE sau đó | Dùng để tra phạm vi; khi có khác biệt, ưu tiên P0 Contract và contract/implementation mới hơn |
| `docs/04 — Workflows/01–04` | Bốn workflow chính: khám phá, listing, booking/payment, report/admin | Bản thảo workflow, có nhiều chi tiết được cập nhật sau trong P0/API contracts | Dùng làm đường đi cấp cao, không để bản cũ ghi đè quyết định mới |
| `docs/05 — Implementation Plan/*` | Bàn giao, contract issue, status và evidence BE | Có tài liệu kế hoạch, review, report và handoff với ngày/commit khác nhau; là nguồn lịch sử theo thời điểm | Đối chiếu commit và code hiện tại trước khi phát biểu trạng thái hoàn thành |
| `docs/08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md` | Endpoint, payload, lỗi, giới hạn API | Contract thiết kế; implementation phải đối chiếu route/controller/requests/resources | Dùng để ánh xạ màn hình và ghi rõ thiếu endpoint |
| `README.md` và code backend (`backend/routes`, `app`, `tests`) | Stack hiện tại, route và bằng chứng implementation tại workspace | README nói Vue chưa scaffold; route/test/code có mặt trên branch snapshot | Bằng chứng trực tiếp về workspace; không khẳng định CI hiện thời nếu chưa chạy/không có run cụ thể |
| `docs/06 — Non-functional Requirements/04 — Usability & Accessibility.md` | Responsive, tiếng Việt, khả năng tiếp cận | Ghi là mục tiêu thiết kế đề xuất | Dùng thành tiêu chí đề xuất cho prototype, đánh dấu nguồn chưa phải nghiệm thu |
| Legacy repository `https://github.com/quangnguyentan/pettel` | Audit giao diện và tương tác tiền thân | **UNKNOWN:** GitHub/raw bị chặn trong môi trường này; clone thất bại do không phân giải được `github.com`; không có checkout legacy trong workspace | Không đưa ra kết luận visual chưa quan sát được; xem `02-legacy-ui-audit.md` |

## 3. Nguồn chuẩn khi có khác biệt

1. Quyết định P0 hiện hành trong `02 — Domain Documentation/05 — P0 Decision Contract.md` và decision log mới hơn.
2. API contract hiện hành, sau đó kiểm tra route/controller/validation/model/test trong workspace.
3. Test và báo cáo nghiệm thu gắn với SHA cụ thể; test API không phải browser E2E.
4. Product Scope và acceptance criteria để xác định phạm vi demo.
5. Module/workflow bản thảo để định vị luồng.
6. Website tiền thân chỉ tham khảo visual khi truy cập được; không định nghĩa nghiệp vụ.

Nếu code/test và contract không khớp, ghi discrepancy, impact và trạng thái cần xác nhận; không tự chọn một quy tắc nghiệp vụ mới.

## 4. Điểm xác minh từ workspace

- **CONFIRMED — implementation structure:** Laravel API có các route public listing/reviews/media; auth/profile/pets; caregiver listing/availability/media; booking, payment sandbox, care lifecycle, messages, care updates, reviews, reports và admin endpoints. Xem `backend/routes/api.php`.
- **CONFIRMED — frontend gap:** `frontend/` chỉ có `.gitkeep`; README ghi Vue SPA chưa được scaffold. Chưa có component/page/router/API client để audit hoặc tái sử dụng.
- **CONFIRMED — admin boundary:** Filament panel nằm ở `/admin`; workspace chỉ có `ReportResource` và trang dashboard Filament trong `backend/app/Filament`. Listing moderation cũng có admin API routes. Điều này không đồng nghĩa toàn bộ admin experience đã có Filament UI.
- **CONFIRMED — test source exists:** backend có feature tests cho authentication, pets, discovery, Smart Match, listing lifecycle, booking, payment, messaging/care updates, reviews, reports và Filament. Không chạy test trong bước khảo sát này.
- **INFERRED — source recency:** workspace hiện tại là branch `fe` với commit sau mốc merge được nêu trong một số handoff; không fetch được remote để xác minh branch `main`/CI mới nhất. Trạng thái workspace không được đánh đồng với trạng thái remote.

## 5. Các rủi ro/điểm cần xác minh tiếp

| ID | Điểm | Ảnh hưởng | Trạng thái / cách xử lý |
|---|---|---|---|
| SRC-01 | Tài liệu sản phẩm và NFR có nhãn bản thảo/đề xuất; P0 Contract chốt quy tắc demo nhưng không chứng minh đã code | Dễ biến mục tiêu/giả thuyết thành chức năng có thật | Giữ nhãn bằng chứng ở từng đặc tả; tách business state khỏi mock state |
| SRC-02 | Legacy GitHub không truy cập được | Chưa thể xác nhận reuse/adapt/redesign, màu, font, bố cục, assets hay responsive | Để các kết luận visual là UNKNOWN và xin người dùng cung cấp archive/screenshots hoặc quyền truy cập khi cần quyết định này |
| SRC-03 | README và workspace cho biết frontend chưa scaffold | Không thể kiểm chứng giao diện Vue/API integration hay tái dùng component | Prototype độc lập; mapping sang Vue chỉ ở mức đề xuất |
| SRC-04 | Chỉ thấy một Filament ReportResource; các listing moderation routes hiện diện trong API | Người dùng xác nhận ngày 11/11/2026: prototype dùng một số màn hình tham chiếu Filament cho listing moderation và reports, không tạo Admin SPA riêng | ADM-01/ADM-02 mô phỏng tối thiểu; booking operation thể hiện trong case report liên quan. Không khẳng định UI Filament chưa quan sát được đã tồn tại |
| SRC-05 | Một số implementation handoff có trạng thái/commit cũ hơn workspace; remote không được fetch | Có thể báo sai Gate BE hoặc CI hiện tại | Dùng branch/SHA workspace để ghi trạng thái; không tuyên bố remote mới nhất hay CI mới nhất |
| SRC-06 | Route list không có `GET /bookings` hoặc chi tiết booking tổng quát | UI cần dashboard/history có thể thiếu API đọc danh sách hiện tại | Prototype có thể dùng mock fixtures; đánh dấu API dependency/gap cho Vue và không giả vờ endpoint đã có |

## 6. Phạm vi prototype căn cứ nguồn

- **P0:** auth/account; pet profile; caregiver profile/listing/availability; public discovery/filter/Smart Match; booking và trạng thái; sandbox payment; chat/care updates; completion; review; report/admin resolution.
- **P1:** so sánh listing, email notifications, caregiver stats, nhiều pet/booking, tìm kiếm nâng cao — chỉ nếu P0 đạt.
- **Ngoài P0:** tiền thật/escrow, xác minh lý lịch/Verified/Pro thật, AI/ML matching, live video/camera/streaming, native app, bảo hiểm và phân xử/đền bù tài chính thật.
- **NFR đề xuất:** UI tiếng Việt; responsive desktop/mobile, ưu tiên mobile; mục tiêu WCAG 2.2 AA; 320 px và zoom 200% là tiêu chí kiểm tra được nêu trong NFR.

