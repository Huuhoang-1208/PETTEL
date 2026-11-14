# PETTEL — Next.js frontend

Giao diện HTML/CSS/JavaScript đã được chuyển sang Next.js App Router và kết nối
với Laravel/Sanctum. Các trang chính dùng dữ liệu từ API, không dùng bộ sitter
mẫu hoặc hồ sơ thú cưng trong localStorage.

## Chạy local

Từ thư mục gốc repo, bật Docker backend và các dịch vụ phụ thuộc:

```powershell
docker compose up -d db redis backend frontend worker scheduler reverb
cd frontend
Copy-Item .env.example .env.local # Chỉ khi chưa có .env.local
npm install
npm run dev
```

Mở http://localhost:3000. API dùng http://localhost:8080 và Reverb cổng 8081.
Điền `NEXT_PUBLIC_REVERB_APP_KEY` bằng public app key của backend để bật socket;
nếu chưa cấu hình socket, chat vẫn cập nhật qua polling. `.env` gốc phải có
`FRONTEND_URL=http://localhost:3000` và cho phép `localhost:3000` trong
`SANCTUM_STATEFUL_DOMAINS`. Cấu hình hiện tại dùng cookie Sanctum và CSRF;
không lưu mật khẩu hoặc bearer token trong localStorage.

Nếu thay backend, chạy `docker compose build backend` rồi khởi động lại dịch vụ.
Nếu gặp lỗi quyền ghi storage, kiểm tra volume có cho `www-data` ghi hay không.

## Các trang

| Trang                                 | Chức năng                                                                                    |
| ------------------------------------- | -------------------------------------------------------------------------------------------- |
| `/`                                   | Homepage giới thiệu PETTEL, thanh tìm sitter và điều hướng                                   |
| `/services`                           | Tìm kiếm, lọc, lịch trống, tổng giá và Smart Match                                           |
| `/login`                              | Đăng ký, đăng nhập, yêu cầu đặt lại mật khẩu                                                 |
| `/reset-password?token=...&email=...` | Đặt lại mật khẩu theo liên kết email                                                         |
| `/pet-profile`                        | Thêm/sửa/xóa, lưu trữ/kích hoạt thú cưng theo tài khoản                                      |
| `/detail?id={listingId}`              | Chi tiết dịch vụ, đánh giá, báo giá và tạo đơn thật                                          |
| `/bookings`                           | Đơn của chủ/người chăm, trạng thái, thanh toán sandbox, cập nhật chăm sóc, đánh giá, báo cáo |
| `/chat?booking={bookingId}`           | Chat theo đơn, gửi ảnh, tải lịch sử, Reverb và polling dự phòng                              |
| `/caregiver`                          | Hồ sơ người chăm, dịch vụ, ảnh, lịch trống, gửi duyệt và đề xuất sửa                         |
| `/admin`                              | Duyệt dịch vụ/đề xuất sửa, tạm ngưng/khôi phục và liên kết công cụ báo cáo                   |

`/videocall` và `/livecam` vẫn là video mô phỏng và có thông báo rõ trên trang.
Backend chưa có signaling hoặc luồng camera thật. Thanh toán chỉ dùng sandbox,
không thu tiền thật. Mail local dùng log; cần cấu hình mail để gửi email thật.
Bản đồ là ảnh minh họa. Các route sitter theo tên trong bản HTML cũ chuyển về
tìm kiếm để tránh nhầm ID mẫu với listing thật.

## Mã nguồn và kiểm tra

- `src/app`: route, metadata, layout và entry CSS của Next.js.
- `src/features`: mã nguồn theo chức năng; mỗi chức năng có màn hình, component,
  hook, kiểu dữ liệu và style riêng khi cần.
- `src/components/layout`: header và điều hướng dùng chung.
- `src/components/ui`: icon, thông báo lỗi, bộ lọc select, hộp xác nhận.
- `src/providers`: quản lý phiên đăng nhập dùng chung.
- `src/hooks`: hook tải API dùng chung.
- `src/lib/api`: HTTP client, kiểu dữ liệu API, CSRF và xử lý lỗi.
- `src/lib/constants.ts`, `date.ts`, `format.ts`: nhãn, chuyển múi giờ và định dạng.
- `src/styles`: CSS toàn cục, được import theo thứ tự tại `src/app/globals.css`.
- `src/legacy`: giao diện, script và dữ liệu demo từ bản HTML ban đầu.
- `public`: 59 ảnh/video gốc giữ nguyên nội dung.
- Component/logic prototype còn được giữ để tham khảo; dữ liệu demo không được
  dùng cho các trang nghiệp vụ chính.

```powershell
npm run lint
npm run typecheck
npm run format:check
npm run build
npm start
```

Chạy `npm run format` để định dạng mã nguồn. Xem [ARCHITECTURE.md](./ARCHITECTURE.md)
để biết vị trí thêm màn hình, component hoặc hook mới.

Danh mục API, giới hạn và thay đổi backend:
[frontend-backend-integration.md](../docs/frontend-backend-integration.md).
