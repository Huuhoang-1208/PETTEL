# Kết nối Next.js với Laravel

## Phạm vi đã kết nối

| Nhóm | API Laravel | Giao diện Next.js |
| --- | --- | --- |
| Tài khoản | CSRF cookie, register, login, logout, forgot/reset-password, GET /api/user | /login, /reset-password, menu tài khoản |
| Thú cưng | GET/POST/PATCH/DELETE /api/pets; archive, activate | /pet-profile; lưu dữ liệu trong database theo tài khoản |
| Tìm dịch vụ | GET /api/listings; q, service_type, area_code, species, size, pet_id, thời gian và tổng giá | /; Smart Match dùng pet thuộc tài khoản |
| Chi tiết, đánh giá công khai | GET /api/listings/{id}; /reviews | /detail?id={listingId} |
| Báo giá, tạo đơn | POST /api/bookings/quote; POST /api/bookings | Form chọn thú cưng/thời gian; xác nhận giá từ server; khóa idempotency |
| Xem đơn | GET /api/bookings; GET /api/bookings/{id} | /bookings; chỉ các bên tham gia được xem |
| Vòng đời đơn | accept, reject, cancel, start, request-completion, confirm-completion | /bookings; thao tác theo vai trò và trạng thái, backend kiểm tra lại |
| Thanh toán sandbox | sandbox-payments, simulate | /bookings; ghi rõ không thu tiền thật |
| Chat | GET/POST messages, ảnh đính kèm, private Reverb channel | /chat?booking={bookingId}; polling dự phòng và tải tin nhắn cũ |
| Cập nhật chăm sóc | GET/POST care-updates, URL ảnh ký tạm thời | /bookings; người chăm gửi trong IN_PROGRESS |
| Đánh giá, báo cáo | POST bookings/{id}/reviews; POST /api/reports | /bookings và /detail |
| Người chăm | bật vai trò, hồ sơ, listing nháp, ảnh, lịch trống, gửi duyệt, tạm ngưng/mở lại, đề xuất sửa và rút đề xuất | /caregiver |
| Quản trị dịch vụ | listing queue; approve/reject/suspend/restore; change request queue và approve/reject | /admin; quyền ADMIN vẫn kiểm tra ở API |
| Kiểm duyệt báo cáo | Filament có sẵn trong backend | Liên kết sang công cụ báo cáo backend từ /admin |

Hai endpoint GET xem đơn được bổ sung vì backend trước đó chưa có API để frontend
khôi phục danh sách đơn sau khi tải lại trang hoặc đăng nhập trên thiết bị khác.
Response không trả khóa idempotency, hash request hoặc toàn bộ snapshot riêng tư.
Snapshot đã redacted không trả tên thú cưng/tiêu đề lưu trước đó.

Các API quản trị nâng cao (hàng đợi hủy/giải ngân cần xử lý thủ công, lịch sử và
quyết định báo cáo chi tiết) vẫn còn ở backend; chưa có màn hình Next.js riêng
trong lần tích hợp này. Một số tác vụ báo cáo dùng Filament sẵn có.

## Cấu hình local

- Next.js: http://localhost:3000.
- API Laravel qua Nginx: http://localhost:8080.
- Reverb: localhost:8081.
- `frontend/.env.local`: `NEXT_PUBLIC_API_URL=http://localhost:8080`;
  `NEXT_PUBLIC_REVERB_APP_KEY` phải khớp public app key của Reverb.
- `.env` gốc: `FRONTEND_URL=http://localhost:3000`;
  `SANCTUM_STATEFUL_DOMAINS` phải gồm `localhost:3000`.
- CORS cho phép credentials và header `Idempotency-Key` để tạo đơn/hủy đơn/
  khởi tạo thanh toán từ frontend khác cổng.
- Compose truyền `BCRYPT_ROUNDS` (mặc định 12) vào PHP-FPM. Nếu thiếu biến này,
  pool FPM gán chuỗi trống làm bcrypt không tạo được hash khi đăng nhập/đăng ký.
- Thư mục `storage` của volume Laravel phải cho `www-data` ghi; tránh chạy lệnh
  tạo log bằng root rồi để PHP-FPM không ghi được file đó.

Không cần thay APP_KEY, tạo lại database hoặc reset mật khẩu tài khoản hiện có.
Mail local hiện dùng driver log; muốn nhận email đặt lại mật khẩu thật cần cấu
hình dịch vụ gửi mail. URL đặt lại mật khẩu trỏ về Next.js `/reset-password`.

## Các phần còn mô phỏng

Video call và live cam chưa có API signaling, WebRTC hoặc luồng camera thật.
Các trang /videocall và /livecam được gắn nhãn mô phỏng, không được quảng bá
như tính năng gọi trực tiếp trong chat thật. Thanh toán dùng sandbox có sẵn
của backend, chưa tích hợp cổng thu tiền thật. Bản đồ hiện là ảnh minh họa.

Các route chi tiết dùng tên sitter trong bản HTML cũ chuyển về tìm kiếm vì
ID của dữ liệu mẫu không được phép tự gán thành ID listing trong database.
Các component gốc vẫn được giữ để tham khảo thiết kế.
