# PETTEL — Backlog 27 issue trong 4 tuần

## Hoàng • Thịnh • Hào

# 1\. Mục tiêu và điều kiện lập kế hoạch

Hoàn thiện backend trong tuần 1–3, sau đó cả ba chuyển sang Vue SPA và Filament admin ở tuần 4. Cuối tuần 4 kiểm thử toàn luồng, triển khai và bàn giao bản demo. Stack đích là Laravel 13/PHP 8.3, Breeze API, Sanctum stateful cookie/CSRF, Vue 3, MySQL, Redis và Docker Compose; Scramble sinh OpenAPI, Pulse theo dõi hiệu năng, Telescope chỉ ở local/dev.

Màn hình Vue và Filament chỉ triển khai sau Gate BE. Tuần 1–3 làm database, API/service, Policy, package setup, lưu trữ, worker/scheduler và kiểm thử bằng API client/test tự động.

Lịch tính theo 20 ngày làm việc từ 26/10 đến 20/11/2026 (Asia/Ho_Chi_Minh); 21–22/11 là khoảng bàn giao, không mặc định làm cuối tuần:

* Tuần 1: N1–N5 (26–30/10) — nền tảng backend.
* Tuần 2: N6–N10 (02–06/11) — nghiệp vụ lõi.
* Tuần 3: N11–N15 (09–13/11) — hoàn tất backend và Gate BE.
* Tuần 4: N16–N18 (16–18/11) — Vue/Filament; N19–N20 (19–20/11) — kiểm thử, sửa lỗi và bàn giao.

Không mặc định làm cuối tuần. Khi đổi ngày bắt đầu, giữ thứ tự phụ thuộc và dịch các mốc tương ứng. Thứ tự trình bày và phân công trong mỗi nhóm issue là **Hoàng → Thịnh → Hào**; các phần độc lập vẫn triển khai song song theo phụ thuộc.

## Ngân sách dự kiến

Giả định mỗi người dành 20 giờ/tuần, tương đương 80 giờ/người trong bốn tuần.

| Nhóm công việc | Số issue | Tổng giờ |
| :---- | :---- | :---- |
| Backend | 18 | 144 |
| Frontend | 6 | 36 |
| Kiểm thử và bàn giao | 3 | 24 |
| Dự phòng tuần 1–3 | — | 36 |
| **Tổng** | **27** | **240** |

Mỗi người nhận 9 issue, 68 giờ giao việc và 12 giờ dự phòng: 48 giờ backend, 12 giờ frontend, 8 giờ QA. Timebox là tổng người-giờ cho triển khai, test và review thường lệ (ít nhất 1 giờ review/issue); vòng review chéo cân bằng số issue giữa ba người. Giờ dự phòng dùng cho tích hợp và lỗi phát sinh. Tuần 4 đã giao đủ 20 giờ/người nên phải khóa backend ở N15; nếu trượt, điều chỉnh lại lịch và công bố ảnh hưởng, không giả định có thêm giờ cuối tuần.

Đây là ước lượng ban đầu. Cuối mỗi tuần ghi giờ đã dùng, giờ còn lại và blocker; ưu tiên bốn workflow P0, quyền, chống trùng lịch, idempotency và dữ liệu riêng tư. Thu gọn độ bóng giao diện trước khi cắt luồng nghiệp vụ. Nếu vẫn vượt năng lực, nhóm phải thống nhất lại phạm vi hoặc hạn nộp.

# 2\. Nguồn và phạm vi

Nguồn lập kế hoạch:

* [Product Scope & MVP Priorities](<../01%20-%20Product%20Document/05%20%E2%80%94%20Product%20Scope%20%26%20MVP%20Priorities.md>).  
* [Business Rules](<../02%20%E2%80%94%20Domain%20Documentation/02%20%E2%80%94%20Business%20Rules.md>).  
* [State Lifecycles](<../02%20%E2%80%94%20Domain%20Documentation/04%20%E2%80%94%20State%20Lifecycles%20%E2%80%94%20Listing%2C%20Order%2C%20Payment.md>).  
* [Module Map & Dependencies](<../03%20%E2%80%94%20Modules%20Documentation/00%20%E2%80%94%20Module%20Map%20%26%20Dependencies.md>).  
* [API Contracts & Error Conventions](<../08%20%E2%80%94%20Architecture%20%26%20Technical%20Design/04%20%E2%80%94%20API%20Contracts%20%26%20Error%20Conventions.md>).  
* [Kế hoạch backend trước, frontend sau](<../05%20%E2%80%94%20Implementation%20Plan/05%20%E2%80%94%20K%E1%BA%BF%20ho%E1%BA%A1ch%204%20tu%E1%BA%A7n%20%E2%80%94%20Backend%20tr%C6%B0%E1%BB%9Bc%2C%20frontend%20sau.md>).

Trong phạm vi: tài khoản/quyền, pet, caregiver/listing, tìm kiếm/Smart Match theo quy tắc, booking, payment sandbox, tin nhắn/ảnh cập nhật, review, report/admin và triển khai demo.

Để sau: video/camera, AI matching, xác minh lý lịch thật, thanh toán thật và tính năng P1.

Hiện trạng sau đợt khởi tạo lại ngày 27/10: `backend/` là Laravel 13 API shell, `frontend/` chỉ có `.gitkeep`; Breeze API, Sanctum, Vue SPA, Filament, Scramble và Pulse chưa được cài. Migration passkeys/2FA mồ côi đã được gỡ khỏi source; migration nền users/password reset/session của Laravel vẫn còn. Không có dữ liệu user từ repo cũ cần chuyển hoặc bảo toàn. BE-H01 cài Breeze API + Sanctum trên baseline mới; FE-H01 tạo Vue SPA. Các issue mô tả việc cần làm, không phải tiến độ đã hoàn thành. N1 xác nhận baseline Laravel, migration, Docker và CI để giao việc đúng; không chuyển thành công việc thay thế một auth scaffold cũ.

Mã issue dưới đây là mã kế hoạch. Khi đưa lên tracker, tìm issue tương đương để cập nhật hoặc liên kết, tránh tạo trùng.

# 3\. Phân công và phối hợp

| Thành viên | Trách nhiệm chính | Reviewer |
| :---- | :---- | :---- |
| **Hoàng** | Identity, pet, search, Smart Match, review; giao diện chung và chủ nuôi | Hào |
| **Thịnh** | Schema nghiệp vụ, booking, chống trùng lịch, payment; giao diện đặt lịch | Hoàng |
| **Hào** | Listing, messaging, report/admin, hạ tầng; giao diện người chăm/quản trị | Thịnh |

Quy ước mã issue: **H \= Hoàng**, **T \= Thịnh**, **HA \= Hào**. Ví dụ: BE-H01 thuộc Hoàng; BE-T01 thuộc Thịnh; BE-HA01 thuộc Hào.

Hoàng điều phối bố cục và thành phần frontend dùng chung sau Gate BE. Thịnh điều phối schema và thứ tự migration; mỗi owner viết migration của module mình. Hào điều phối môi trường chạy và CI.

Không hai người sửa cùng file dùng chung đồng thời. N2 Thịnh công bố schema/state; N3 Hoàng và Hào công bố pet/listing contract và fixture ACTIVE để Thịnh làm booking; N6 Hào công bố availability/listing lifecycle contract; N11 Thịnh công bố payment callback contract; sáng N16 Hoàng bàn giao Vue shell/API client. Thay đổi input/output phải báo owner liên quan và cập nhật OpenAPI trước khi tích hợp.

# 4\. Quyết định P0 và việc cần kiểm tra đầu tuần 1

Các quy tắc sản phẩm P0 đã chốt trong [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>): một pet/booking; capacity 1/listing; giữ chỗ 15 phút từ accept; giá DAYCARE/HOUR, BOARDING/NIGHT; không phí hủy sandbox; xử lý callback muộn, owner im lặng, chat, role caregiver, retention và mã dữ liệu. BE-T01 dùng contract này làm đầu vào schema/API; không đánh dấu các quy tắc trên là Blocked.

Đầu tuần 1 còn phải kiểm tra tương thích và chốt phiên bản Breeze API, Sanctum, Filament, Scramble, Pulse với Laravel 13/PHP 8.3; lưu dependency lockfile. Cần chốt môi trường demo, storage và kênh gửi email thực tế trước khi tích hợp. Chính sách tiền thật, xác minh thật và tuân thủ pháp lý nằm ngoài P0.

Hoàng chuẩn bị quyền và dữ liệu pet; Thịnh chuẩn bị booking/payment; Hào chuẩn bị listing, storage và quản trị. Khi đổi contract, cả ba cập nhật tài liệu liên quan trước khi code phụ thuộc.

# 5\. Cổng kiểm tra

**Gate tuần 1 (N5):** môi trường chạy được; migration/seed/test lặp được; có Breeze/Sanctum auth, pet, listing nháp và booking REQUESTED từ fixture ACTIVE; kiểm thử 401/403/419 và owner ID.

**Gate tuần 2 (N10):** qua API/service thực hiện được tạo listing → admin duyệt → tìm kiếm → gửi booking → caregiver chấp nhận; test sai quyền, lịch trùng, hết hạn/hủy và quyền đọc tin nhắn. Filament UI chưa thuộc gate này.

**Gate BE cuối tuần 3:**

* Toàn bộ backend trong phạm vi đã tích hợp và được review.  
* Bốn workflow khám phá, đăng dịch vụ, đặt lịch/thanh toán và báo cáo chạy được.  
* Kiểm thử quyền, chuyển trạng thái, lịch trùng và payment callback đạt.  
* Worker/scheduler hoạt động; có seed, OpenAPI từ Scramble đã review, Pulse được bảo vệ theo quyền admin và hướng dẫn kiểm tra.
* Không còn lỗi chặn luồng chính hoặc rò dữ liệu.  
* Hoàng, Thịnh và Hào cùng xác nhận.

Toàn bộ issue frontend giữ Blocked đến khi Gate BE đạt. Nếu Gate BE trượt, cả ba tiếp tục sửa backend trước khi làm giao diện.

**Gate FE cuối N18:** Vue SPA và Filament admin dùng dữ liệu thật; bốn workflow thao tác được; 401/419, biểu mẫu, lỗi, trạng thái rỗng và quyền truy cập được kiểm tra.

**Gate bàn giao cuối N20:** có bản demo, CI xanh, kết quả kiểm thử theo commit, README, tài khoản mẫu, kịch bản demo và danh sách giới hạn còn lại.

# 6\. Backlog chi tiết

## Tuần 1 — Nền tảng backend

### BE-H01 — Khởi tạo xác thực, Identity và phân quyền

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N1–N3 \| **Timebox:** 10 giờ

**Phụ thuộc:** BE-T01 chốt cấu trúc users/roles; nhóm chốt ma trận quyền và origin/session của SPA

**Phạm vi:** M01 — Identity & Roles

**Branch đề xuất:** codex/4w-be-h01

**Tasks to do:**

☐ Xác nhận baseline auth sạch: `User`, auth config, routes và migration users/password reset/session khớp M01; không đưa passkeys/2FA ngoài phạm vi P0 trở lại. Không backfill user từ repo cũ.

☐ Cài Breeze API stack tương thích Laravel 13 và Sanctum; hoàn thiện đăng ký, đăng nhập, đăng xuất, đặt lại mật khẩu, session cookie, CSRF và throttle login/reset. Email verification chỉ bật nếu nhóm chốt được luồng gửi mail.

☐ Thiết lập role/auth contract: đăng ký công khai không nhận role tùy ý và không thể tạo admin; cấp admin qua quy trình tin cậy, seed chỉ dùng cho demo. Một user có thể đồng thời là owner và caregiver. Tạo guard/policy nền; policy theo từng tài nguyên do issue module tương ứng hoàn thiện.

☐ Viết feature test cho đăng ký/login/logout/reset password, session hết hạn, CSRF hợp lệ/sai (419), API riêng tư không session (401), role không đủ quyền (403), chống tự gán admin và thông báo reset không tiết lộ email có tồn tại. Kiểm thử truy cập dữ liệu người khác thuộc BE-H02/BE-HA02.

**Acceptance criteria:** Migration thuộc scope auth chạy được trên database trống; không có migration auth ngoài phạm vi đã chọn. Breeze/Sanctum xác thực SPA bằng cookie/session và CSRF; logout hủy session; API riêng tư trả 401 khi chưa đăng nhập/hết session, thao tác sai quyền trả 403, CSRF sai trả 419. User mới mặc định là owner; payload đăng ký không thể tự cấp caregiver/admin; admin chỉ được cấp qua quy trình tin cậy. Password reset có throttle và phản hồi không tiết lộ tài khoản tồn tại. SPA không lưu bearer token.

### BE-T01 — Chốt schema, trạng thái và hợp đồng nghiệp vụ

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N1–N2 \| **Timebox:** 8 giờ

**Phụ thuộc:** Kickoff và xác nhận baseline Laravel; chưa có schema nghiệp vụ hoặc dữ liệu user cần kế thừa

**Phạm vi:** Schema chung; Booking/Payment

**Branch đề xuất:** codex/4w-be-t01

**Tasks to do:**

☐ Thiết kế schema mới và thống nhất quan hệ users/roles, pets, listings, bookings, payments cùng các bảng liên quan với owner.

☐ Chốt trạng thái, quyền chuyển trạng thái, khoảng thời gian \[start,end), tiền VND nguyên và thời gian UTC.

☐ Phân lịch migration; thống nhất validation, lỗi, idempotency và ghi lại quyết định nghiệp vụ.

**Acceptance criteria:** Có sơ đồ dữ liệu và bảng trạng thái thống nhất; migration chạy được từ database trống và không phụ thuộc dữ liệu/schema của repo cũ; mọi thay đổi so với P0 Decision Contract được ghi rõ cùng owner và issue phụ thuộc.

### BE-HA01 — Môi trường chạy, CI và dữ liệu mẫu

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N1–N3 \| **Timebox:** 8 giờ

**Phụ thuộc:** Runtime và database kiểm thử được xác định

**Phạm vi:** Docker, MySQL, Redis, worker/scheduler, CI

**Branch đề xuất:** codex/4w-be-ha01

**Tasks to do:**

☐ Kiểm tra và hoàn thiện môi trường chạy, .env.example, worker/scheduler và hướng dẫn khởi động.

☐ Thiết lập CI kiểm tra định dạng, migration và test trên database riêng.

☐ Tạo tài khoản mẫu theo vai trò và dữ liệu demo không chứa thông tin cá nhân thật.

**Acceptance criteria:** Thành viên chạy được dự án theo README; migration/seed/test tái lập được; test không tác động database demo.

### BE-H02 — Quản lý hồ sơ thú cưng và quyền dữ liệu

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N3–N5 \| **Timebox:** 8 giờ

**Phụ thuộc:** Auth/Policy contract từ BE-H01 có ở N2; schema liên quan từ BE-T01. Nghiệm thu sau BE-H01 N3

**Phạm vi:** M02 — Pet Profiles

**Branch đề xuất:** codex/4w-be-h02

**Tasks to do:**

☐ Hoàn thiện thêm/xem/sửa/xóa mềm pet; kiểm tra loài, tuổi và giới hạn ghi chú.

☐ Áp dụng quyền owner; xác định dữ liệu cần chia sẻ cho caregiver trong booking.

☐ Cung cấp dữ liệu snapshot cho booking; test chỉnh sửa pet không làm đổi hướng dẫn đã đặt.

**Acceptance criteria:** Owner quản lý nhiều pet của mình; người ngoài không đọc hồ sơ riêng; pet có lịch sử booking không bị xóa cứng.

### BE-T02 — Tạo booking và lưu snapshot

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N4–N5 \| **Timebox:** 8 giờ

**Phụ thuộc:** BE-T01; auth contract từ BE-H01 và pet/listing contract + fixture ACTIVE từ BE-H02/BE-HA02 công bố N3. Nghiệm thu tích hợp sau khi BE-H02/BE-HA02 hoàn tất N5

**Phạm vi:** M05 — Booking creation

**Branch đề xuất:** codex/4w-be-t02

**Tasks to do:**

☐ Tạo booking REQUESTED từ pet thuộc owner và listing ACTIVE; dùng listing mẫu để kiểm thử trước khi có luồng duyệt.

☐ Server xác định giá và owner; lưu snapshot thời gian, giá, hướng dẫn và chính sách.

☐ Xử lý idempotency; test pet trái quyền, listing không hợp lệ, thời gian sai và gửi lại request.

**Acceptance criteria:** Request hợp lệ tạo một booking; gửi lại cùng key/payload không tạo trùng; sửa dữ liệu nguồn không thay snapshot booking.

### BE-HA02 — Hồ sơ người chăm và listing nháp

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N3–N5 \| **Timebox:** 8 giờ

**Phụ thuộc:** Auth/Policy contract từ BE-H01 có ở N2; schema liên quan từ BE-T01. Nghiệm thu sau BE-H01 N3

**Phạm vi:** M03 — Caregiver Profiles & Listings

**Branch đề xuất:** codex/4w-be-ha02

**Tasks to do:**

☐ Hoàn thiện caregiver profile và CRUD listing DRAFT theo quyền sở hữu.

☐ Kiểm tra giá, loại dịch vụ, điều kiện nhận pet; chuẩn bị cấu trúc ảnh và lịch khả dụng.

☐ Tạo fixture listing ACTIVE cho test; kiểm tra listing nháp không xuất hiện công khai.

**Acceptance criteria:** Caregiver quản lý được bản nháp của mình; người khác không sửa được; dữ liệu không hợp lệ bị từ chối.

## Tuần 2 — Nghiệp vụ lõi

### BE-H03 — Tìm kiếm và lọc dịch vụ

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N6–N8 \| **Timebox:** 8 giờ

**Phụ thuộc:** BE-HA02; tích hợp BE-HA03 trước nghiệm thu

**Phạm vi:** M04 — Discovery & Search

**Branch đề xuất:** codex/4w-be-h03

**Tasks to do:**

☐ Tìm/lọc theo từ khóa, loại dịch vụ, khu vực, ngày, giá và loài; bổ sung phân trang.

☐ Chỉ trả listing ACTIVE; loại lịch đã bị giữ hoặc đặt theo quy tắc booking.

☐ Test kết hợp bộ lọc, khoảng ngày, listing bị ẩn và kết quả rỗng.

**Acceptance criteria:** Kết quả đúng bộ lọc và quyền công khai; không lộ dữ liệu pet; danh sách và chi tiết áp dụng cùng quy tắc hiển thị.

### BE-T03 — Nhận/từ chối booking và chống trùng lịch

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N7–N9 \| **Timebox:** 10 giờ

**Phụ thuộc:** BE-T02; availability/listing lifecycle contract từ BE-HA03 công bố N6. Nghiệm thu tích hợp sau BE-HA03

**Phạm vi:** M05 — Booking acceptance

**Branch đề xuất:** codex/4w-be-t03

**Tasks to do:**

☐ Hoàn thiện accept/reject theo caregiver và trạng thái booking.

☐ Dùng transaction/khóa DB để kiểm tra giao nhau khi accept; không dùng cache làm nguồn quyết định.

☐ Test hai accept đồng thời, sai caregiver, listing ngừng nhận và chuyển trạng thái không hợp lệ.

**Acceptance criteria:** Hai REQUESTED có thể trùng nhưng chỉ một được accept khi lịch xung đột; thao tác còn lại nhận lỗi rõ; không tồn tại trạng thái ghi dở.

### BE-HA03 — Duyệt listing và lịch khả dụng

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N6–N8 \| **Timebox:** 10 giờ

**Phụ thuộc:** BE-HA02, BE-H01 và trạng thái từ BE-T01; công bố availability contract N6 cho BE-T03/BE-H03

**Phạm vi:** M03 — Listing lifecycle & availability

**Branch đề xuất:** codex/4w-be-ha03

**Tasks to do:**

☐ Hoàn thiện gửi duyệt, duyệt/từ chối có lý do, suspend, khôi phục và archive.

☐ Quản lý lịch khả dụng và ảnh; sửa trường trọng yếu của ACTIVE phải duyệt lại.

☐ Test suspend chặn booking mới, thay đổi hiển thị công khai và bảo toàn lịch sử booking.

**Acceptance criteria:** Chỉ ACTIVE được công khai và nhận booking; người chăm không tự duyệt; thay đổi listing không sửa giá booking cũ.

### BE-H04 — Smart Match và giới hạn dữ liệu trả về

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N8–N10 \| **Timebox:** 8 giờ

**Phụ thuộc:** BE-H02, BE-H03

**Phạm vi:** M04 — Smart Match

**Branch đề xuất:** codex/4w-be-h04

**Tasks to do:**

☐ Gợi ý theo loài, tuổi/kích cỡ nếu có, lịch, giá và điều kiện hỗ trợ đã khai.

☐ Trả lý do phù hợp; cho phép lọc thủ công khi chưa chọn pet.

☐ Kiểm tra quyền pet và lọc dữ liệu riêng tư; test không có kết quả phù hợp.

**Acceptance criteria:** Gợi ý giải thích được bằng quy tắc; không tuyên bố AI hoặc tự cấp badge xác minh; không trả ghi chú pet ra công khai.

### BE-T04 — Giữ chỗ, hết hạn và hủy booking

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N9–N10 \| **Timebox:** 6 giờ

**Phụ thuộc:** BE-T03 công bố accept/expiry contract đầu N9 và tích hợp cuối N9; áp dụng thời hạn/chính sách hủy trong P0 Decision Contract

**Phạm vi:** M05 — Expiry & cancellation

**Branch đề xuất:** codex/4w-be-t04

**Tasks to do:**

☐ Ghi thời điểm hết hạn khi accept; scheduler hủy booking chờ thanh toán quá hạn.

☐ Hủy theo actor/trạng thái và chính sách đã chốt; chuẩn bị tích hợp void/refund sandbox.

☐ Test job chạy lại, hủy lặp, sai trạng thái và giải phóng lịch.

**Acceptance criteria:** Hết hạn/hủy giải phóng lịch đúng một lần; chạy lại job không làm sai trạng thái; không cho hủy bằng luồng thường sau khi bắt đầu chăm.

### BE-HA04 — Tin nhắn theo booking và lưu trữ riêng tư

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N9–N10 \| **Timebox:** 6 giờ

**Phụ thuộc:** BE-T02, BE-HA01; chốt quyền nhắn tin theo trạng thái

**Phạm vi:** M07 — Messaging & Storage

**Branch đề xuất:** codex/4w-be-ha04

**Tasks to do:**

☐ API đọc/gửi tin theo booking; phân trang và thứ tự ổn định.

☐ Áp dụng quyền hai bên; chuẩn bị private storage và truy cập ảnh có kiểm soát.

☐ Test outsider, tệp sai loại/kích thước và lỗi upload; không tạo bản ghi thành công giả.

**Acceptance criteria:** Hai bên trao đổi được theo chính sách; người ngoài không đọc tin/tải tệp; ảnh không có URL công khai vĩnh viễn.

## Tuần 3 — Hoàn tất và khóa backend

### BE-H05 — Review và rating công khai

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N11–N15 \| **Timebox:** 8 giờ

**Phụ thuộc:** Trạng thái từ BE-T01 và fixture COMPLETED cho test; tích hợp BE-T06 trước nghiệm thu N15

**Phạm vi:** M08 — Reviews & Trust Signals

**Branch đề xuất:** codex/4w-be-h05

**Tasks to do:**

☐ Cho owner của booking COMPLETED viết một review, rating 1–5.

☐ Tính rating từ review công khai; loại review bị ẩn và làm mới dữ liệu tổng hợp.

☐ Test đánh giá sớm, sai owner, tạo trùng và review bị moderation.

**Acceptance criteria:** Không review trước hoàn tất hoặc tạo trùng; review bị ẩn không tính điểm; dữ liệu seed được nhận diện là minh họa.

### BE-T05 — Payment sandbox và callback idempotent

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N11–N13 \| **Timebox:** 10 giờ

**Phụ thuộc:** BE-T03, BE-T04; chốt quy tắc callback đến muộn

**Phạm vi:** M06 — Payment Sandbox

**Branch đề xuất:** codex/4w-be-t05

**Tasks to do:**

☐ Tạo payment attempt; mô phỏng success/failure/timeout và retry.

☐ Kiểm tra secret/chữ ký, event ID, booking reference, amount và attempt hiện hành trong transaction.

☐ Test callback lặp, khác thứ tự, sai amount, đến muộn và chạy đồng thời với hủy.

**Acceptance criteria:** Chỉ callback hợp lệ đổi trạng thái; không authorize hai lần; payment lỗi không xác nhận booking; toàn bộ là tiền mô phỏng.

### BE-HA05 — Ảnh cập nhật chăm sóc và bảo vệ dữ liệu

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N11–N15 \| **Timebox:** 6 giờ

**Phụ thuộc:** BE-HA04; trạng thái từ BE-T01 và tích hợp BE-T06

**Phạm vi:** M07 — Care Updates

**Branch đề xuất:** codex/4w-be-ha05

**Tasks to do:**

☐ Tạo cập nhật chăm sóc có nội dung, ảnh, người gửi và thời điểm.

☐ Chỉ cho gửi ảnh cập nhật trong trạng thái được phép; hiển thị theo múi giờ Việt Nam.

☐ Test quyền đọc ảnh, liên kết hết hạn và upload thất bại; giữ lịch sử theo chính sách.

**Acceptance criteria:** Hai bên xem được cập nhật đúng booking; người ngoài bị chặn; không gửi cập nhật chăm sóc trước trạng thái cho phép.

### BE-H06 — Hoàn thiện query/cache, API và điều phối Gate BE

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N13–N15 \| **Timebox:** 6 giờ

**Phụ thuộc:** BE-H03/BE-H04 và contract review từ BE-H05; toàn bộ BE được tích hợp trước khi xác nhận Gate BE

**Phạm vi:** Search, API contracts và tích hợp backend

**Branch đề xuất:** codex/4w-be-h06

**Tasks to do:**

☐ Kiểm tra truy vấn, phân trang, cache công khai và invalidation khi listing/review đổi; ghi số đo trên dữ liệu mẫu.

☐ Cài Scramble, sinh OpenAPI; tổng hợp endpoint/service, input/output, quyền và lỗi từ từng owner; review bản hợp đồng dùng cho Vue và giới hạn truy cập docs ở demo.

☐ Cả ba chạy bốn workflow và các nhánh lỗi; ghi Pass/Fail/Blocked/Not tested cùng kết quả kiểm thử.

**Acceptance criteria:** OpenAPI Scramble khớp endpoint đã test và nêu rõ quyền/lỗi/state; Gate BE chỉ đạt khi đủ điều kiện ở mục 5 và cả ba xác nhận bằng commit, kết quả CI và checklist. Nếu chưa đạt, frontend tiếp tục Blocked.

### BE-T06 — Vòng đời chăm sóc và release/void/refund

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N13–N15 \| **Timebox:** 6 giờ

**Phụ thuộc:** BE-T05; chính sách hoàn tất và hủy đã chốt

**Phạm vi:** M05/M06 — Completion & settlement sandbox

**Branch đề xuất:** codex/4w-be-t06

**Tasks to do:**

☐ Hoàn thiện CONFIRMED → IN\_PROGRESS → AWAITING\_CONFIRMATION.

☐ Owner xác nhận hoàn tất; release thành công mới chuyển COMPLETED; tích hợp void/refund theo chính sách.

☐ Test xác nhận lặp, sai actor, release lỗi và retry; lưu lịch sử chuyển trạng thái.

**Acceptance criteria:** COMPLETED chỉ khi owner xác nhận và release sandbox thành công; lỗi release không bị coi là hoàn tất; retry không release hai lần.

### BE-HA06 — Report, quản trị, audit và tác vụ nền

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N13–N15 \| **Timebox:** 10 giờ

**Phụ thuộc:** BE-HA03, BE-HA04 và review contract từ BE-H05; nghiệm thu moderation sau BE-H05

**Phạm vi:** M09 — Reports & Admin; vận hành backend

**Branch đề xuất:** codex/4w-be-ha06

**Tasks to do:**

☐ Tạo/xem/xử lý report qua OPEN, IN\_REVIEW, RESOLVED hoặc DISMISSED.

☐ Dùng action/policy chung để ẩn listing/review; lưu actor, thời gian, lý do; giới hạn admin đọc chứng cứ riêng tư.

☐ Cài Filament, cấu hình panel auth/Policy (màn hình admin ở tuần 4); cài Pulse và bảo vệ dashboard bằng quyền admin; kiểm request/query/queue, worker/scheduler, failed jobs, log request ID; test sai quyền và lịch sử moderation.

**Acceptance criteria:** Report có lịch sử xử lý; moderation không sửa booking cũ ngoài quy trình; không lộ dữ liệu riêng; worker/scheduler chạy được; Pulse dashboard chỉ admin truy cập.

## Tuần 4 — Frontend

### FE-H01 — Giao diện chung, tài khoản và pet

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N16–N17 \| **Timebox:** 6 giờ

**Phụ thuộc:** Gate BE đạt; BE-H01, BE-H02. Bàn giao Vue shell/router/API client sáng N16 cho FE-T01 và FE-HA01

**Phạm vi:** Layout, auth và pet UI

**Branch đề xuất:** codex/4w-fe-h01

**Tasks to do:**

☐ Dựng Vue SPA shell/router/API client; chốt layout, điều hướng theo vai trò, thành phần form và cách hiển thị lỗi dùng chung sáng N16.

☐ Làm giao diện đăng ký, đăng nhập, đăng xuất, yêu cầu/hoàn tất đặt lại mật khẩu và quản lý pet bằng JSON API đã nghiệm thu; xử lý 401/419 và CSRF cookie.

☐ Kiểm tra mobile, bàn phím, validation, phiên hết hạn/419 và thao tác trái quyền.

**Acceptance criteria:** Owner đăng ký, đăng nhập, đăng xuất, đặt lại mật khẩu và quản lý pet qua Sanctum cookie/CSRF API; 401/419 được xử lý rõ; các owner khác tái sử dụng được shell/API client.

### FE-T01 — Giao diện tạo và quản lý booking

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N17 \| **Timebox:** 6 giờ

**Phụ thuộc:** Gate BE đạt; BE-T02–BE-T04; Vue shell/API client từ FE-H01 bàn giao N16

**Phạm vi:** Booking UI cho owner/caregiver

**Branch đề xuất:** codex/4w-fe-t01

**Tasks to do:**

☐ Form chọn pet, thời gian, hướng dẫn; hiển thị giá và chính sách từ server.

☐ Trang danh sách/chi tiết; caregiver nhận/từ chối; owner hủy theo quyền.

☐ Hiển thị lịch xung đột, hết hạn và bước tiếp theo; chống gửi lặp trên giao diện kết hợp idempotency backend.

**Acceptance criteria:** Owner tạo/theo dõi booking được; caregiver thao tác đúng quyền; tải lại hoặc gửi lại không tạo booking trùng.

### FE-HA01 — Giao diện listing, lịch và duyệt dịch vụ

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N17 \| **Timebox:** 6 giờ

**Phụ thuộc:** Gate BE đạt; BE-HA02, BE-HA03; Vue shell/API client từ FE-H01 bàn giao N16

**Phạm vi:** Caregiver và listing moderation UI

**Branch đề xuất:** codex/4w-fe-ha01

**Tasks to do:**

☐ Trang hồ sơ người chăm, tạo/sửa/gửi duyệt listing và lịch khả dụng.

☐ Dùng Filament đã cài ở BE-HA06 để làm trang duyệt/từ chối/suspend qua service/action và Policy backend, ghi audit; xác nhận panel chỉ admin mở được.

☐ Hiển thị lý do từ chối, trạng thái duyệt lại, lỗi lịch/ảnh và dữ liệu rỗng.

**Acceptance criteria:** Luồng Vue tạo → gửi duyệt → Filament duyệt → công khai chạy được; người không phải admin không mở được panel; giao diện không có đường bỏ qua quyền hoặc quy tắc trạng thái.

### FE-H02 — Giao diện khám phá, Smart Match và review

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N17–N18 \| **Timebox:** 6 giờ

**Phụ thuộc:** Vue shell/API client từ FE-H01 bàn giao N16; BE-H03–BE-H05. Tích hợp sau auth/pet FE-H01

**Phạm vi:** Search, listing detail và review UI

**Branch đề xuất:** codex/4w-fe-h02

**Tasks to do:**

☐ Trang tìm/lọc, chọn pet, phân trang và chi tiết listing.

☐ Hiển thị giá, điều kiện nhận pet, lý do Smart Match và trạng thái không có kết quả.

☐ Form review sau hoàn tất; hiển thị rating và nhãn dữ liệu minh họa đúng backend.

**Acceptance criteria:** Khám phá và review hoạt động bằng dữ liệu thật; listing ẩn không xuất hiện; không hiển thị badge xác minh thật cho dữ liệu demo.

### FE-T02 — Checkout sandbox và hoàn tất dịch vụ

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N18 \| **Timebox:** 6 giờ

**Phụ thuộc:** FE-T01 được tích hợp cuối N17; BE-T05, BE-T06

**Phạm vi:** Payment và lifecycle UI

**Branch đề xuất:** codex/4w-fe-t02

**Tasks to do:**

☐ Checkout với nhánh thành công, thất bại, thử lại và hết hạn.

☐ Giao diện bắt đầu chăm, đánh dấu xong và owner xác nhận hoàn tất.

☐ Hiển thị trạng thái release lỗi và hành động hợp lệ tiếp theo; đồng bộ trạng thái từ server.

**Acceptance criteria:** Luồng booking đến COMPLETED chạy được; luôn ghi “Thanh toán mô phỏng — không trừ tiền thật”; không yêu cầu dữ liệu thẻ hoặc tài khoản thật.

### FE-HA02 — Hội thoại, ảnh cập nhật và report/admin

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N18 \| **Timebox:** 6 giờ

**Phụ thuộc:** FE-HA01 được tích hợp cuối N17; BE-HA04–BE-HA06

**Phạm vi:** Messaging, care updates và report UI

**Branch đề xuất:** codex/4w-fe-ha02

**Tasks to do:**

☐ Giao diện hội thoại, tải thêm tin, gửi và xem ảnh cập nhật; dùng tải lại/poll nhẹ.

☐ Form Vue gửi report và trang Filament admin xử lý, ghi lý do, xem lịch sử; không lộ dữ liệu riêng của booking khác.

☐ Kiểm tra quyền tệp, lỗi upload, trạng thái booking và cập nhật dữ liệu sau thao tác.

**Acceptance criteria:** Hai bên trao đổi được; admin xử lý report được; người ngoài không đọc hội thoại hoặc chứng cứ riêng tư. Cả ba xác nhận Gate FE sau khi đủ sáu issue frontend.

## Tuần 4 — Kiểm thử và bàn giao

### QA-H01 — Kiểm thử hành trình chủ nuôi và giao diện chung

**Owner:** Hoàng \| **Reviewer:** Hào

**Lịch:** N19–N20 \| **Timebox:** 8 giờ

**Phụ thuộc:** Gate FE; bản tích hợp để kiểm thử

**Phạm vi:** Auth → Pet → Search → Review

**Branch đề xuất:** codex/4w-qa-h01

**Tasks to do:**

☐ Kiểm thử hành trình đăng nhập, tạo pet, tìm listing, đi tới booking và đánh giá sau hoàn tất.

☐ Kiểm tra mobile, điều hướng bàn phím, lỗi form, trạng thái rỗng và sai quyền.

☐ Sửa lỗi thuộc phần Hoàng; bàn giao hướng dẫn sử dụng và bằng chứng theo phiên bản kiểm thử.

**Acceptance criteria:** Hành trình đạt trên bản tích hợp; không còn lỗi chặn thuộc phạm vi phụ trách; có kết quả thực tế và giới hạn còn lại.

### QA-T01 — Kiểm thử booking, payment và tính toàn vẹn dữ liệu

**Owner:** Thịnh \| **Reviewer:** Hoàng

**Lịch:** N19–N20 \| **Timebox:** 8 giờ

**Phụ thuộc:** Gate FE; database kiểm thử riêng

**Phạm vi:** Booking/Payment toàn luồng

**Branch đề xuất:** codex/4w-qa-t01

**Tasks to do:**

☐ Chạy thành công, thất bại, timeout, hủy, callback lặp/đến muộn và release lỗi.

☐ Kiểm thử accept đồng thời, sai actor, chuyển sai trạng thái và snapshot sau khi sửa pet/listing.

☐ Sửa lỗi thuộc phần Thịnh; đối chiếu dữ liệu DB, lịch sử trạng thái và tài liệu API.

**Acceptance criteria:** Không trùng lịch được nhận, không xử lý payment hai lần, không sai trạng thái/snapshot; có bằng chứng cho các nhánh quan trọng.

### QA-HA01 — Deploy demo, kiểm thử vận hành và gói bàn giao

**Owner:** Hào \| **Reviewer:** Thịnh

**Lịch:** N19–N20 \| **Timebox:** 8 giờ

**Phụ thuộc:** Gate FE; kết quả QA-H01/QA-T01 trước nghiệm thu cuối

**Phạm vi:** Runtime, demo và bàn giao chung

**Branch đề xuất:** codex/4w-qa-ha01

**Tasks to do:**

☐ Triển khai Vue build + Laravel/Filament ở cùng origin demo; kiểm frontend, backend, database, worker/scheduler, Pulse dashboard theo quyền và private storage.

☐ Thử backup/restore trên môi trường riêng; smoke test bốn workflow và sửa lỗi thuộc phần Hào.

☐ Tổng hợp README, tài khoản mẫu, migrations/seed, kết quả CI/test, kịch bản demo và giới hạn; cả ba kiểm tra trước bàn giao.

**Acceptance criteria:** Bản demo truy cập được; Vue/Filament/API hoạt động qua cùng origin; dựng lại được theo README; có bằng chứng restore và kiểm thử; không còn lỗi chặn luồng chính hoặc rò dữ liệu.

# 7\. Quy tắc trạng thái, review và bàn giao

Trạng thái issue: **Ready → In progress → Review → Done**; dùng **Blocked** khi thiếu quyết định hoặc phụ thuộc.

* BE-H01, BE-T01, BE-HA01 có thể Ready sau kickoff khi đủ đầu vào.  
* Các issue backend còn lại mở Ready khi dữ liệu trao đổi và phụ thuộc cần thiết đã sẵn sàng.  
* Tất cả FE Blocked đến Gate BE.  
* QA chuẩn bị kịch bản sớm; nghiệm thu trên bản tích hợp sau Gate FE.  
* Mỗi issue có một owner; reviewer không làm thay test của owner.  
* Branch trong kế hoạch chỉ là tên đề xuất. PR liên kết issue tracker thật khi được tạo.  
* Mỗi ngày cập nhật trạng thái, việc tiếp theo, blocker/người xử lý và link PR/kết quả kiểm tra.

**Bàn giao chung cho mọi issue:** PR/commit, thay đổi schema/API nếu có, kết quả test, hướng dẫn kiểm tra ngắn và giới hạn còn lại.

# 8\. Checklist đóng issue

☐ Checklist chức năng trong phạm vi đã hoàn thành.

☐ Quyết định nghiệp vụ liên quan đã được chốt và ghi lại.

☐ Quyền, validation, trạng thái và nhánh lỗi quan trọng đã được kiểm tra.

☐ Migration được kiểm tra trên database riêng nếu có thay đổi dữ liệu.

☐ PR được reviewer xem; lỗi chặn đã sửa; thay đổi được tích hợp.

☐ Tài liệu và dữ liệu trao đổi đồng bộ với code.

☐ Bằng chứng ghi phiên bản/commit, môi trường, kịch bản, expected/actual và kết quả.

☐ Không đưa mật khẩu, token hoặc dữ liệu riêng tư vào issue/log/ảnh minh chứng.

# 9\. Trách nhiệm gói bàn giao cuối

**Hoàng:** auth/quyền, pet, tìm kiếm/Smart Match, review, giao diện chung/chủ nuôi và kiểm tra mobile.

**Thịnh:** schema nghiệp vụ, booking, chống trùng lịch, payment sandbox, trạng thái và bằng chứng kiểm thử race/retry.

**Hào:** listing/lịch, messaging/ảnh, report/audit, CI, môi trường demo, backup/restore và tổng hợp bàn giao.

Kết quả cuối phân biệt rõ: **đã có code; test đạt; CI đạt; PR được review/tích hợp; nghiệm thu đạt**. Hạng mục chưa có bằng chứng ghi **Not tested** hoặc **Blocked**, không đánh dấu hoàn thành chỉ vì đã viết code.
