# BE-T05 — Ảnh cập nhật chăm sóc và bảo vệ dữ liệu

Plan triển khai cho [issue #27](https://github.com/Huuhoang-1208/PETTEL/issues/27), thuộc module M07 — Care Updates.

## Mục tiêu

Cho phép caregiver tạo cập nhật chăm sóc gắn với booking, có nội dung và/hoặc ảnh. Owner và caregiver của booking xem được đúng lịch sử; người ngoài bị chặn. Chỉ tạo cập nhật trong trạng thái booking được phép. Ảnh cần được lưu riêng tư, truy cập có kiểm quyền và tuân thủ chính sách lưu trữ hiện hành.

## Hiện trạng project

- Bảng `care_updates` đã có `booking_id`, `sender_id`, `body`, `media_object_id`, `visibility` và `created_at`; có ràng buộc yêu cầu nội dung hoặc ảnh.
- Backend hiện chưa có model, controller hoặc API route chuyên trách cho care update.
- Luồng media hiện có tập trung vào listing và pet; ảnh care update cần được phục vụ theo quyền booking, không dùng route ảnh public của listing.
- M07 và P0 Decision Contract quy định caregiver tạo care update khi booking ở `IN_PROGRESS`; owner đọc; không tạo mới khi booking chuyển sang `AWAITING_CONFIRMATION`.
- Nội dung riêng và ảnh theo retention 90 ngày; report đang mở giữ dữ liệu đến 90 ngày sau khi report đóng.

## Phụ thuộc và hợp đồng cần thống nhất

- **BE-HA04:** xác nhận đầu vào và cách phối hợp với phần việc phụ thuộc.
- **BE-T01:** dùng trạng thái booking và hợp đồng nghiệp vụ đã chốt; không định nghĩa trạng thái riêng trong M07.
- **BE-HA06:** thống nhất tích hợp và quyền đọc booking/care update.
- **Quyết định triển khai:** liên kết ảnh được ký với TTL 5 phút; endpoint vẫn yêu cầu đăng nhập và kiểm tra lại quyền participant của booking. Client lấy liên kết mới qua API sau khi liên kết cũ hết hạn.
- Giới hạn MIME/kích thước ảnh cần dùng lại cấu hình hoặc hợp đồng upload đã có; không tạo giới hạn mâu thuẫn với storage design.

## Task 1 — Tạo cập nhật chăm sóc có nội dung, ảnh, người gửi và thời điểm

### Checklist thực hiện

- [x] Thêm model `CareUpdate` và quan hệ tới booking, user gửi và media object.
- [x] Thêm API tạo care update trong phạm vi booking.
- [x] Gán `sender_id` từ người dùng đã xác thực và `created_at` từ server; không tin giá trị sender/thời gian do client gửi.
- [x] Cho phép nội dung văn bản, ảnh hoặc cả hai; từ chối payload không có nội dung lẫn ảnh.
- [x] Thêm API đọc danh sách care update theo booking, có phân trang.
- [x] Sắp xếp lịch sử ổn định theo `created_at`, sau đó theo `id`.
- [x] Trả resource theo allowlist, gồm nội dung, thông tin người gửi cần hiển thị, thời điểm và link ảnh có hạn dùng; không trả object key, hash hay đường dẫn storage nội bộ.

### Tiêu chí hoàn thành

- Care update được lưu đúng booking và gắn đúng người dùng đang đăng nhập.
- Tạo được cập nhật có nội dung, có ảnh hoặc có cả hai; cập nhật rỗng bị từ chối.
- Thời điểm do server ghi nhận; client không thể sửa người gửi hoặc thời điểm lịch sử.
- Danh sách trả theo thứ tự thời gian ổn định.
- Response có đủ dữ liệu cho giao diện và không lộ thông tin storage nội bộ.
- Nếu upload ảnh lỗi, không tạo care update giả hoặc bản ghi không có media hợp lệ.

### Vì sao

Schema `care_updates` đã có các trường và ràng buộc nền. Tái sử dụng schema tránh tạo dữ liệu trùng; sender và timestamp do server quản lý giúp lịch sử đáng tin cậy.

## Task 2 — Chỉ cho gửi ở trạng thái được phép; hiển thị giờ Việt Nam

### Checklist thực hiện

- [x] Xác thực caregiver thuộc booking trước khi cho tạo cập nhật.
- [x] Chỉ cho tạo care update khi booking đang `IN_PROGRESS`.
- [x] Từ chối tạo mới ở trạng thái trước `IN_PROGRESS`, `AWAITING_CONFIRMATION` và các trạng thái không được phép khác.
- [x] Cho owner và caregiver thuộc booking đọc lịch sử care update.
- [x] Chặn người ngoài booking khỏi danh sách và ảnh; không làm lộ dữ liệu booking khác qua phản hồi lỗi.
- [x] Trả timestamp UTC có timezone rõ ràng để client hiển thị theo `Asia/Ho_Chi_Minh`.
- [x] Dùng mã lỗi nhất quán với quy ước API hiện hành cho lỗi quyền và sai trạng thái.

### Tiêu chí hoàn thành

- Caregiver đúng booking tạo được cập nhật khi trạng thái là `IN_PROGRESS`.
- Owner, caregiver không thuộc booking và user ngoài không tạo được cập nhật.
- Owner và caregiver của booking đọc được lịch sử đúng booking; người ngoài bị chặn.
- Không thể tạo cập nhật trước `IN_PROGRESS` hoặc sau khi booking rời trạng thái được phép.
- Timestamp có timezone rõ ràng và hiển thị đúng giờ Việt Nam; quy tắc sắp xếp lịch sử vẫn dựa trên timestamp chuẩn.

### Vì sao

M07 và P0 Decision Contract đã chốt quyền theo vai trò và trạng thái. Kiểm tra quyền trên booking cụ thể cho mỗi request ngăn việc suy rộng quyền chỉ từ role `CAREGIVER` hoặc `OWNER`.

## Task 3 — Bảo vệ ảnh, xử lý link hết hạn/upload lỗi và giữ lịch sử

### Checklist thực hiện

- [x] Lưu ảnh care update trên private storage; không đưa ảnh lên public disk.
- [x] Xác thực MIME từ nội dung file, giới hạn 10 MiB và tối đa 20 megapixel; chấp nhận JPEG/PNG/WebP.
- [x] Chỉ gắn media vào care update sau khi upload thành công.
- [x] Khi upload hoặc ghi DB thất bại, dọn file vừa tạo nếu có thể; log lỗi cleanup cần thiết mà không lộ thông tin cho client.
- [x] Cấp URL/link ảnh ký có TTL 5 phút.
- [x] Endpoint đọc ảnh yêu cầu xác thực, kiểm tra participant của booking và xác nhận ảnh thuộc care update tương ứng.
- [x] Từ chối link hết hạn; client có thể lấy link mới từ API danh sách.
- [x] Tác vụ retention kiểm tra tham chiếu trước khi xóa ảnh; report đang giữ dữ liệu ngăn purge.
- [x] Tác vụ scheduler hàng ngày redacts nội dung/ảnh sau 90 ngày; report đang mở hoặc đóng chưa đủ 90 ngày giữ dữ liệu.

### Tiêu chí hoàn thành

- Ảnh hợp lệ được lưu private và gắn đúng care update/booking.
- Ảnh sai MIME hoặc quá giới hạn bị từ chối.
- Upload thất bại không để lại care update giả, media reference sai hoặc file mồ côi không có phương án cleanup.
- Owner/caregiver thuộc booking đọc được ảnh; người ngoài bị chặn kể cả khi biết ID hoặc URL.
- Link hết hạn không truy cập được; link mới chỉ cấp sau khi kiểm tra quyền lại.
- Dữ liệu được giữ/xóa theo retention và ngoại lệ report; không xóa file còn được tham chiếu hoặc đang bị giữ.
- Response không chứa URL public vĩnh viễn, object key hoặc hash.

### Vì sao

Ảnh care update chứa thông tin riêng về quá trình chăm sóc. Private storage, quyền theo booking và link hết hạn giảm khả năng truy cập ngoài phạm vi. Tách TTL của link khỏi retention dữ liệu giúp link cũ hết hiệu lực mà không làm mất lịch sử còn phải lưu.

## Trình tự triển khai

1. Dùng trạng thái BE-T01 hiện có và kiểm tra quyền theo participant trực tiếp trên booking; API không phụ thuộc middleware chỉ kiểm tra role.
2. Đã thêm model/quan hệ, validation, controller và resource cho care update.
3. Đã triển khai kiểm tra participant, role caregiver và booking state; API tạo/đọc lịch sử có phân trang.
4. Đã triển khai ảnh private, link ký có hạn dùng và endpoint ảnh kiểm quyền mỗi request.
5. Đã xử lý cleanup upload lỗi và thêm tác vụ retention/report hold chạy hàng ngày.
6. Đã bổ sung feature test cho quyền, state, upload, signed link và retention.

## Checklist kiểm tra nghiệm thu

- [x] Caregiver đúng booking tạo được cập nhật trong `IN_PROGRESS`.
- [x] Không cho owner, caregiver sai booking hoặc user ngoài tạo cập nhật.
- [x] Không cho tạo ở trạng thái trước `IN_PROGRESS`, `AWAITING_CONFIRMATION` hoặc trạng thái không được phép.
- [x] Owner và caregiver đúng booking đọc được lịch sử; người ngoài bị chặn.
- [x] Ảnh hợp lệ gắn đúng care update; MIME sai/kích thước vượt giới hạn bị từ chối.
- [x] Upload lỗi không tạo care update giả hoặc media reference sai.
- [x] Người ngoài không đọc được ảnh ngay cả khi biết ID/URL.
- [x] Link hết hạn bị từ chối; link mới chỉ cấp sau khi kiểm quyền lại.
- [x] Thứ tự lịch sử ổn định; timestamp trả UTC ISO-8601 để client hiển thị theo `Asia/Ho_Chi_Minh`.
- [x] Retention 90 ngày và ngoại lệ report đang mở/đã đóng chưa đủ 90 ngày được tuân thủ.
- [x] Không lộ object key, hash hoặc URL public lâu dài trong API response.

## Điều kiện hoàn thành issue

Issue đạt khi toàn bộ checklist của ba task được hoàn thành và có bằng chứng kiểm tra; tiêu chí tổng thể được chứng minh: hai bên xem đúng cập nhật của booking, người ngoài bị chặn, không thể gửi care update trước trạng thái cho phép, và ảnh tuân thủ quyền truy cập cùng retention policy.

## Kết quả kiểm tra

- Bộ CI cô lập: Pint và PHPStan đạt; migrate/seed và PHPUnit đạt.
- BE-T05 feature tests: 6 test, 72 assertions đạt, gồm quyền participant, trạng thái booking, upload storage lỗi, link hết hạn, MIME/validation và retention với report mở/đóng gần đây.

## Tài liệu liên quan

- [M07 — Messaging & Care Updates](<../03 — Modules Documentation/M07 — Messaging & Care Updates.md>)
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>)
- [File Storage Design](<../08 — Architecture & Technical Design/08 — File Storage Design.md>)
- [API Contracts & Error Conventions](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>)
