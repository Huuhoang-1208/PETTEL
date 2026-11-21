# Checklist và kịch bản demo PETTEL

Ngày cập nhật: 21/11/2026, múi giờ Asia/Saigon. Dùng cùng rubric CSB35050.
Giảng viên đã chấp thuận Next.js/React cho giao diện theo xác nhận của nhóm.

## Trạng thái bằng chứng

Không đánh dấu hoàn thành chỉ vì có mã nguồn hoặc cấu hình. Mỗi bằng chứng phải thể hiện kết quả thực tế và phiên bản được trình bày.

| Hạng mục | Đã chuẩn bị | Cần nhóm bổ sung trước khi nộp |
| --- | --- | --- |
| Source code và quy trình | Repository, branches, PR, CI và ví dụ Issue–PR trong README | Link CI thành công của commit cuối; review và liên kết issue cho PR mới |
| Database | ERD, migrations, factories, seeders, test migration upgrade/rollback | Quay dữ liệu mẫu và thao tác lưu/đọc dữ liệu |
| UI và chức năng | Screenshot trang chủ, bộ lọc; mô tả phạm vi demo | Quay đăng ký, đăng nhập, pet, đặt lịch và các vai trò |
| API | Sanctum token/abilities, Scramble, tests | Quay đọc API hợp lệ, lỗi validation và từ chối quyền |
| Google OAuth | Có code, cấu hình và test provider giả lập | Quay đăng nhập với Google thật và trở về PETTEL thành công |
| Slack | Có logging channel và cảnh báo slow query/server | Chụp tin nhắn thử nghiệm thực nhận trên Slack, kèm thời gian và loại cảnh báo |
| Monitoring | Telescope/Pulse và ngưỡng cảnh báo | Quay dashboard có dữ liệu; xác định metric đang đo container hay máy chủ |
| Hiệu năng | Queue/retry, cache, WebP thumbnails, benchmark API | Quay worker xử lý và minh họa cache; không dùng số đo cũ như số đo của commit cuối |
| Video | Kịch bản dưới 10 phút bên dưới | Quay MP4 tối thiểu 720p, tải lên nơi giảng viên xem được, thêm link vào README |
| Cá nhân | Checklist 5 sprint ở cuối tài liệu | Từng thành viên nộp đủ Self-Report và mô tả đóng góp |

## Chuẩn bị trước khi quay

- [ ] Chốt commit cần nộp; lưu SHA từ `git rev-parse HEAD` và link CI của chính SHA đó.
- [ ] Khởi động Docker; xác nhận frontend, API, MySQL, Redis, worker, scheduler và Reverb hoạt động.
- [ ] Dùng tài khoản demo riêng cho OWNER, CAREGIVER, ADMIN; không dùng dữ liệu cá nhân thật trong video.
- [ ] Chuẩn bị một pet, listing ACTIVE có lịch trống và một listing DRAFT để trình diễn duyệt.
- [ ] Chuẩn bị các trạng thái đơn cần quay trước để video không phải chờ scheduler hoặc thời gian nhận chăm.
- [ ] Chạy backend test trên `compose.test.yaml`; không chạy `migrate:fresh` trên database demo.
- [ ] Chạy `npm test`, lint, typecheck và production build trong `frontend/`.
- [ ] Kiểm tra mobile 390px và desktop; bật kích thước chữ đủ đọc, tắt thông báo riêng tư.
- [ ] Mở sẵn các tab ứng dụng, Filament, Scramble, Telescope/Pulse, GitHub CI và Slack.
- [ ] Che token, webhook, client secret, mật khẩu và thông tin tài khoản Google cá nhân khi quay.

## Kịch bản quay 9 phút 40 giây

Video chính tập trung vào ứng dụng đang hoạt động. Không dành thời gian đọc mã nguồn; link repo, báo cáo test và benchmark đặt ở mô tả video/README.

| Thời gian | Thao tác và hình ảnh | Lời dẫn gợi ý / kết quả cần thấy | Rubric |
| --- | --- | --- | --- |
| 00:00–00:30 | Trang chủ, trang dịch vụ | “PETTEL kết nối người nuôi với người chăm tại Đà Nẵng. Hiện có lưu trú qua đêm và chăm ban ngày; thanh toán là mô phỏng.” | Tổng quan, Task 3 |
| 00:30–01:00 | GitHub repository, một Issue, PR liên quan và CI | Chỉ rõ mô tả issue, acceptance criteria, topic branch, PR và kết quả checks. Dùng Issue #16 / PR #17 làm ví dụ lịch sử nếu vẫn truy cập được. | Task 1 |
| 01:00–01:30 | ERD trong README và dữ liệu mẫu ở ứng dụng/admin | Chỉ quan hệ owner–pet–booking–listing; nói migrations/factories/seeders giúp dựng dữ liệu thử. | Task 2 |
| 01:30–02:20 | Đăng ký/đăng nhập, nhập một form không hợp lệ, sửa lại và lưu pet | Thấy lỗi validation có ích, dữ liệu lưu thành công, dashboard chỉ hiện tài nguyên của tài khoản. Có thể dùng Network để minh họa CSRF mà không lộ cookie. | Task 3(b,c) |
| 02:20–03:00 | Chọn loại dịch vụ, khu vực, thời gian; thao tác bộ lọc đang có; thử màn hình nhỏ | Minh họa các bộ lọc đang hỗ trợ và kết quả hiện tại. | Task 3(a,e) |
| 03:00–04:20 | Owner đặt lịch; caregiver nhận; owner thanh toán sandbox; xem nhật ký/chat | Giá và lịch nhất quán; trạng thái đơn thay đổi; tin nhắn/ảnh xuất hiện đúng đơn. Nêu rõ không chuyển tiền thật. | Task 3, Task 6(a) |
| 04:20–05:00 | Admin duyệt listing/báo cáo, sau đó thử tài khoản thường | Cho thấy phân quyền và dữ liệu công khai cập nhật sau thao tác. | Task 3, Task 4 |
| 05:00–05:40 | Google sign-in thật; quay về PETTEL | Thấy người dùng đã đăng nhập trong ứng dụng. Cắt phần nhập mật khẩu/2FA; không coi trang chọn tài khoản Google là đăng nhập thành công. | Task 3(f) |
| 05:40–06:40 | Scramble và các request API đọc | Minh họa `200`, `422`, token đúng ability và token thiếu ability nhận `403`. Không quay nguyên token; hiển thị tên scope và response. | Task 4 |
| 06:40–07:40 | Telescope/Pulse và Slack | Xem request/query; đối chiếu timestamp cảnh báo thử nghiệm với tin nhắn thực nhận. Phân biệt error alert, slow-query alert và server-resource alert. | Task 5 |
| 07:40–08:40 | Worker/scheduler, báo cáo cache/benchmark và ảnh tải lazy | Chỉ tác vụ nền/retry, cache hit/miss và kết quả benchmark đã lưu. Nêu dataset, môi trường, ngày đo; không trình bày như cam kết tải production. | Task 6 |
| 08:40–09:20 | Báo cáo backend và frontend tests | Hiển thị số test, assertions, line coverage, lint/typecheck/CI. Nói rõ test frontend dùng DOM mô phỏng, không thay thế demo với backend thật. | Task 3(g), Task 4(d) |
| 09:20–09:40 | Trang cuối/README | Nhắc link repo, video, phạm vi demo và đóng góp từng thành viên. | Bàn giao |

## Thu thập bằng chứng Slack và Google

### Slack

1. Dùng kênh thử nghiệm được nhóm cho phép; kiểm tra `LOG_STACK=single,slack` và webhook ở cấu hình riêng.
2. Tạo một lỗi thử nghiệm có nhãn `PETTEL DEMO` theo hướng dẫn README. Không đưa webhook vào terminal được ghi hình.
3. Ghi lại thời gian phát sinh, môi trường, loại lỗi và tin nhắn thực nhận. Chụp riêng vùng thông báo, tránh tin nhắn khác.
4. Nếu quay cảnh báo slow query hoặc ngưỡng tài nguyên, chỉ hạ ngưỡng tạm trên môi trường demo và phục hồi sau thử nghiệm. Không tạo tải gây gián đoạn máy đang dùng.
5. Lưu ảnh đã kiểm tra quyền riêng tư vào `docs/screenshots/`; cập nhật README với loại cảnh báo đã chứng minh. Có cấu hình chưa đồng nghĩa gửi thành công.

### Google OAuth

1. Kiểm tra redirect URI khớp cấu hình Google; dùng tài khoản thử nghiệm được phép.
2. Bấm Google sign-in trong PETTEL, hoàn tất đăng nhập, xác nhận quay về ứng dụng và hiển thị tài khoản.
3. Chụp trạng thái sau đăng nhập và thử logout. Không lưu URL callback chứa authorization code, cookie hoặc token.
4. Ghi timestamp, commit và kết quả; nếu thất bại, giữ trạng thái “chưa xác minh”, không dùng test mock làm bằng chứng kết nối thật.

## Video và Self-Report

- [ ] MP4 tối thiểu 1280×720, âm thanh rõ; tổng thời lượng không quá 10 phút.
- [ ] Video đặt unlisted/public hoặc dịch vụ khác có quyền xem phù hợp; kiểm tra link bằng phiên chưa đăng nhập.
- [ ] Thêm link video thật và timestamp từng phần vào README sau khi upload.
- [ ] Mỗi thành viên nộp Self-Report sprint 1, 2, 3, 4, 5; dẫn chứng issue, commit, PR và công việc thực hiện.
- [ ] Kiểm tra hạn nộp và quyền truy cập repository. Theo brief, thiếu/nộp muộn Self-Report có thể bị trừ 10% điểm nhóm; không tự đánh dấu hoàn thành khi chưa có xác nhận.
