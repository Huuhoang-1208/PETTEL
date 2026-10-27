# Kế hoạch PETTEL — 4 tuần, backend trước, frontend sau

3 người: Hoàng, Thịnh, Hào · 26/10–22/11/2026 (Asia/Ho_Chi_Minh) · 20 ngày làm việc từ thứ Hai 26/10 đến thứ Sáu 20/11; 21–22/11 là khoảng bàn giao, không mặc định làm cuối tuần.

## 1. Mục tiêu và giới hạn

Bàn giao bản demo P0 khép kín bốn hành trình: khám phá dịch vụ; đăng listing và admin duyệt; đặt lịch/thanh toán sandbox; gửi report và admin xử lý. P0 còn có hồ sơ pet, Smart Match theo quy tắc, tin nhắn/cập nhật ảnh và review sau hoàn tất. Không làm tiền thật, xác minh lý lịch thật, video/camera, AI matching hoặc P1. Các quy tắc nghiệp vụ P0 đã chốt trong [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>); chỉ chính sách ngoài sandbox và lựa chọn môi trường còn cần duyệt riêng.

Stack đích: Laravel 13/PHP 8.3, Breeze API stack, Vue 3 SPA, Sanctum stateful cookie/CSRF, MySQL, Redis và Docker Compose. Filament dùng cho admin, Scramble cho OpenAPI, Pulse cho quan sát, Telescope chỉ ở local/dev. **Hiện trạng sau đợt khởi tạo lại ngày 27/10:** Laravel API shell; `frontend/` mới có `.gitkeep`; Breeze/Sanctum, Vue SPA, Filament, Scramble và Pulse chưa được cài/scaffold. Migration passkeys/2FA ngoài phạm vi đã được gỡ; baseline users/password reset/session của Laravel vẫn còn. Không có dữ liệu user từ repo cũ cần chuyển. Kế hoạch này mô tả việc cần làm trên baseline mới, không phải báo cáo đã hoàn tất. Ngày N1–N20 là mốc kế hoạch; nếu khởi động muộn, dịch toàn bộ mốc và cập nhật ngày nộp với nhóm.

## 2. Năng lực và cách chia việc

Mỗi người dành 20 giờ/tuần, tổng 80 giờ/người hoặc 240 giờ toàn nhóm. Backlog có 27 issue: 18 backend (144 giờ), 6 frontend (36 giờ), 3 QA/bàn giao (24 giờ). Tổng timebox 204 người-giờ đã gồm triển khai, test và review thường lệ (ít nhất 1 giờ review/issue). 36 giờ còn lại, 12 giờ/người, dành cho tích hợp và lỗi phát sinh. Đây là ước lượng có điều kiện; cuối mỗi tuần so sánh giờ thực tế với giờ còn lại và thu hẹp giao diện/phần trình bày nếu cần, nhưng giữ đủ bốn hành trình P0 và các kiểm soát quyền, lịch trùng, idempotency.

| Người | Backend tuần 1–3 | Frontend tuần 4 | QA/bàn giao tuần 4 | Reviewer chính |
| --- | --- | --- | --- | --- |
| Hoàng | Khởi tạo Breeze/Sanctum, role/pet, search/Smart Match, review, Scramble và API contract | Vue nền, auth/pet, khám phá/review | Hành trình owner, truy cập và mobile | Hào |
| Thịnh | Schema/state, booking/availability, payment sandbox, idempotency | Vue booking, checkout và hoàn tất | Race/retry, payment và dữ liệu | Hoàng |
| Hào | Docker/CI, listing, messaging/storage, report/audit, Pulse | Vue caregiver/messaging; Filament admin | Deploy, restore, README và demo | Thịnh |

Mỗi issue có một owner và một reviewer. Owner chịu trách nhiệm code, test và bằng chứng; reviewer kiểm tra quyền, trạng thái, dữ liệu và khả năng tích hợp. Quyết định schema/API chung được chốt trước khi các owner triển khai song song. Không hai người sửa cùng file dùng chung cùng lúc.

## 3. Mốc ngày và bàn giao giữa các issue

| Mốc | Ngày | Việc phải sẵn sàng |
| --- | --- | --- |
| N1–N5, tuần 1 | 26–30/10 | N1 xác nhận Laravel/Docker/CI baseline và chốt contract. N2 có schema/state và auth contract. N3 có pet/listing contract và fixture ACTIVE cho booking. N5 chạy được Breeze/Sanctum auth, pet, listing DRAFT và booking REQUESTED qua API trên database mới. |
| N6–N10, tuần 2 | 02–06/11 | N6 có listing lifecycle/availability contract để booking/search tích hợp; N9 test accept cạnh tranh. N10 demo listing được admin duyệt → search → booking → accept/expire/cancel qua API. |
| N11–N15, tuần 3 | 09–13/11 | N11 chốt callback/payment contract; N13 có sandbox và care update; N14 tích hợp contract review/report; N15 nghiệm thu trên code hoàn chỉnh và chạy bốn workflow qua API, OpenAPI được review, CI xanh và đạt Gate BE. |
| N16–N20, tuần 4 | 16–20/11 | Sáng N16 chốt Vue shell/API client/auth flow; N17–N18 tích hợp Vue và Filament, Gate FE cuối N18; N19–N20 kiểm thử, sửa lỗi, deploy và bàn giao. |

Không tính cuối tuần là ngày phát triển. Nếu một mốc phụ thuộc trượt, issue downstream chỉ làm phần độc lập hoặc dùng fixture/contract đã chốt; không đánh dấu tích hợp hoàn tất bằng mock. Các issue và timebox chi tiết ở [Chia task](<../PETTEL%20%E2%80%94%20Task%204%20tu%E1%BA%A7n%20(A%2C%20B%2C%20C)/Chia%20task.md>).

## 4. Tuần 1 — Khởi tạo backend từ Laravel API shell

**Hoàng:** khởi tạo Breeze API + Sanctum stateful trên Laravel API shell; rà User/auth config/routes/migrations để xác nhận baseline sạch và phù hợp M01. Xác định role contract, admin provisioning, session/CSRF/throttle, đăng ký/đăng nhập/đăng xuất/reset password; kiểm thử 401/403/419. Không chuyển user từ repo cũ và không xây Vue UI ở tuần này. Policy truy cập pet/listing cụ thể thuộc các issue module tương ứng.

**Thịnh:** thiết kế schema mới, chốt trạng thái Listing/Booking/Payment, đơn vị VND nguyên, UTC, khoảng `[start,end)`, lỗi API và idempotency. Sau khi pet/listing contract và fixture sẵn sàng, tạo booking REQUESTED và snapshot giá/chính sách/hướng dẫn.

**Hào:** hoàn thiện Compose/CI/test DB/seed; tạo caregiver profile và listing DRAFT API, fixture listing ACTIVE riêng cho test. Công bố listing/availability contract cho Hoàng và Thịnh. Kiểm tra `composer` tương thích với Breeze, Sanctum, Filament, Scramble, Pulse; pin phiên bản khi cài.

**Gate tuần 1 (N5):** clone/build/chạy theo hướng dẫn trên database mới; migration và seed lặp được; auth/pet/listing DRAFT/booking REQUESTED chạy qua API; người ngoài không sửa dữ liệu; ba owner thống nhất contract, state và lỗi. Booking được kiểm bằng fixture ACTIVE, chưa yêu cầu màn hình admin.

## 5. Tuần 2 — Nghiệp vụ lõi

**Hoàng:** GET listing/search với lọc và phân trang; chỉ ACTIVE công khai. Smart Match trả lý do dựa trên quy tắc; không lộ pet notes hoặc tuyên bố AI.

**Thịnh:** accept/reject với transaction và khóa DB để chống trùng lịch; giữ chỗ chờ payment, timeout và hủy. Test hai accept đồng thời, job chạy lại, sai actor và lịch xung đột.

**Hào:** lifecycle listing, lịch khả dụng, upload private và admin action/service/policy; messaging theo booking. Admin duyệt qua API/service trong Gate BE, Filament UI làm ở tuần 4. Thay đổi listing phải làm mới search/cache phù hợp.

**Gate tuần 2 (N10):** qua API/service, caregiver tạo và gửi listing → admin duyệt → owner tìm kiếm, tạo booking → caregiver accept. Chứng minh một trong hai booking trùng lịch bị chặn; timeout/hủy giải phóng lịch; tin nhắn không lộ cho người ngoài.

## 6. Tuần 3 — Khóa backend

**Hoàng:** review một lần sau booking COMPLETED, rating từ review công khai, kiểm thử search/cache. Dùng Scramble sinh OpenAPI; review thủ công quyền, state, lỗi và idempotency với Thịnh/Hào.

**Thịnh:** payment sandbox success/failure/timeout, callback xác thực và idempotent; release/void/refund mô phỏng, hoàn tất booking. Test callback lặp/đến muộn, race hủy/callback và lỗi release.

**Hào:** care update/ảnh riêng tư, report/moderation/audit, worker/scheduler và log request ID. Cài Pulse ở môi trường demo có kiểm soát; giới hạn dashboard theo quyền admin. Cài Filament, cấu hình panel auth/Policy và chuẩn bị service; màn hình admin dựng ở tuần 4.

**Gate BE (cuối N15):** bốn workflow chạy bằng API/service và seed demo; kiểm thử quyền, trạng thái, lịch trùng, callback lặp và file riêng tư đạt; CI xanh; OpenAPI khớp API đã test; worker/scheduler chạy; không còn lỗi P0 chặn luồng hoặc rò dữ liệu. Cả ba xác nhận bằng commit, kết quả test và danh sách giới hạn. Nếu chưa đạt, sửa backend trước khi mở issue FE.

## 7. Tuần 4 — Vue, Filament, QA và bàn giao

**N16:** Hoàng tạo Vue SPA shell, router, API client gửi cookie/CSRF và xử lý 401/419; cung cấp component và contract dùng chung sớm trong ngày. Thịnh/Hào chuẩn bị màn hình bằng API đã khóa, không phụ thuộc vào component chưa bàn giao.

**N17–N18:** Hoàng hoàn thiện auth/pet/search/review; Thịnh làm booking/checkout/completion; Hào làm caregiver listing/lịch, messaging/care update và Filament panel duyệt listing/report. Tất cả giao diện lấy trạng thái/giá/quyền từ server. **Gate FE cuối N18:** bốn workflow chạy trên bản tích hợp bằng dữ liệu thật; không còn route hoặc form trống chặn hành trình.

**N19–N20:** Hoàng kiểm tra hành trình owner, mobile, keyboard và lỗi form; Thịnh kiểm tra booking/payment/race/retry; Hào deploy demo, kiểm tra Pulse, worker/scheduler, backup/restore và README. Cả ba smoke test bốn workflow; ghi expected/actual, commit và môi trường. Hai ngày này ưu tiên sửa lỗi, không thêm P1.

**Gate bàn giao cuối N20:** demo truy cập được, CI xanh, seed không có PII thật, tài khoản mẫu, kịch bản thành công/thất bại, hướng dẫn dựng lại và giới hạn còn lại. Không mô tả thanh toán sandbox hoặc badge minh họa như dữ liệu thật.

## 8. Phụ thuộc, thay đổi và quyết định còn mở

Đường găng: BE-T01 + BE-H01 + BE-HA02 fixture/contract → BE-T02 → BE-T03/BE-HA03 → BE-T04/BE-T05 → BE-T06 → BE-H05 → Gate BE → Vue/Filament → Gate FE → QA/deploy. Hoàng và Hào có thể phát triển search, messaging, report song song khi contract đã ổn định. Mọi thay đổi API/schema sau N15 cần có lý do lỗi chặn, reviewer của các owner liên quan và test hồi quy.

Quy tắc P0 về công suất, giá, giữ chỗ, hủy/void/refund sandbox, owner xác nhận, chat, role caregiver và badge đã chốt trong [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>). N1–N2 kiểm tra package, hosting/storage và phân công triển khai theo contract; thay đổi quy tắc phải cập nhật Domain Docs, API và database design cùng lúc.
