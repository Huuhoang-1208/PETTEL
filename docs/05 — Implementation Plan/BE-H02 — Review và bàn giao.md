# BE-H02 — Review và bàn giao

Owner: Hoàng. Reviewer: Thịnh. Phạm vi: POST /api/bookings/quote và POST /api/bookings.

Trạng thái bàn giao: chờ người triển khai áp dụng bộ file, chạy lại kiểm tra và Thịnh xác nhận review. Chưa đủ điều kiện đóng issue chỉ dựa trên bộ file mẫu đã kiểm chứng.

## Contract và ví dụ

- [API Contracts & Error Conventions — M05 và ví dụ BE-H02](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>).
- [M05 — Booking & Availability](<../03 — Modules Documentation/M05 — Booking & Availability.md>).
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).

Request nhận pet_id, listing_id, start_at, end_at và instructions; Idempotency-Key lấy từ header. Owner, caregiver, status và giá do server xác định. Quote trả 200; create mới 201; replay 200 cùng ID/trạng thái hiện tại; cùng owner/key khác payload trả 409 IDEMPOTENCY_CONFLICT.

## Kiểm chứng tự động

Kết quả bộ file mẫu kiểm chứng ngày 02/11/2026: Pint PASS (69 files), PHPStan không có lỗi, migration/seed và seed lặp đạt, 51 tests passed (485 assertions), MySQL 8.4 pettel_test. Kết quả này áp dụng cho bộ file mẫu task 7; chưa thay thế kết quả CI trên commit thực tế bàn giao.

Kết quả trên commit thực tế bàn giao: CHƯA GHI. Điền sau khi áp dụng file và chạy lại:

- Commit được kiểm tra: ghi SHA trong PR/CI sau khi commit bộ file; không tự tham chiếu SHA trong tài liệu này.
- Thời điểm kiểm tra: CHƯA GHI.
- Pint / PHPStan / migration / seed / toàn bộ test: CHƯA GHI.
- Link CI hoặc log đính kèm PR: CHƯA GHI.

Chạy tại thư mục gốc repo. Chỉnh format trước:

```powershell
docker compose -p pettel-test -f compose.test.yaml run --rm --no-deps --volume "D:/Dev/Projects/PETTEL/backend/tests:/var/www/html/tests" test vendor/bin/pint tests
```

Sau đó chạy toàn bộ kiểm tra trên MySQL riêng:

```powershell
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Runner thực hiện Pint, PHPStan, migrate:fresh --seed, seed lại và PHPUnit. Database là pettel_test, không dùng database demo. Tests/TestCase kiểm tra môi trường/driver/database/user trước các trait migration; test đồng thời cũng kiểm tra cấu hình database trong từng tiến trình.

## Phạm vi test

| Nội dung                                                        | Bằng chứng                              |
| --------------------------------------------------------------- | --------------------------------------- |
| Session/role, owner, dữ liệu giả từ client                      | BookingCreationTest                     |
| Pet trái quyền/archived/xóa mềm, listing/caregiver không hợp lệ | BookingCreationTest                     |
| Giá DAYCARE/BOARDING, microsecond, UTC, giới hạn, overflow      | BookingPriceTest và BookingCreationTest |
| Slot, khoảng [start,end), REQUESTED không khóa lịch             | BookingCreationTest                     |
| Ghi booking/snapshot/event cùng transaction; lỗi event rollback | BookingCreationTest                     |
| Hash chuẩn hóa, replay/conflict, key độc lập theo owner         | BookingCreationTest                     |
| Hai tiến trình cùng key/payload và cùng key/khác listing        | BookingIdempotencyConcurrencyTest       |
| Sửa nguồn/xóa mềm pet giữ snapshot, booking và lịch sử          | BookingCreationTest                     |
| Fixture REQUESTED tạo qua API có thể bàn giao                   | BookingHandoffFixtureTest               |

## Fixture cho task accept booking

File: backend/tests/Support/BookingFixture.php, namespace Tests\Support.

- BookingFixture::make(): owner, caregiver, pet ACTIVE, pet của owner khác, listing ACTIVE/DRAFT, slot mở và payload hợp lệ; chưa tạo booking.
- BookingFixture::requested($this): cùng dữ liệu trên, thêm key booking chứa model Booking đã được tạo qua API POST /api/bookings.
- Giá mặc định: DAYCARE/HOUR 50.000 VND, thời lượng 130 phút => 3 giờ => 150.000 VND.
- start_at là 08:00 Việt Nam sau hiện tại hai ngày, end_at là 10:10 cùng ngày; giá trị lưu UTC.
- booking.status = REQUESTED; payment_due_at = null; có snapshot và một event USER từ null sang REQUESTED.
- Fixture không cần admin duyệt listing. Mỗi lần gọi tạo graph dữ liệu mới; không dựa vào seed demo hay ID cố định.

Dùng trong test accept kế thừa Tests\TestCase và có RefreshDatabase:

```php
use Tests\Support\BookingFixture;

$fixture = BookingFixture::requested($this);
$booking = $fixture['booking'];
$this->actingAs($fixture['caregiver']);
// Task accept bổ sung request/assertion cho endpoint accept của mình.
```

requested() đăng nhập owner khi gọi create; chuyển sang caregiver trước request accept. Fixture dành cho test và được dọn theo vòng đời test; không phải seeder giữ dữ liệu demo. Không thay RefreshDatabase trong test đồng thời: test đó dùng DatabaseMigrations để hai kết nối đọc được dữ liệu đã commit.

Task accept phải tự kiểm tra lại quyền caregiver, trạng thái/expiry, slot và booking đang giữ chỗ dưới transaction; REQUESTED hiện tại chưa giữ lịch. Khi accept thành công theo contract, chuyển sang AWAITING_PAYMENT và đặt hạn thanh toán 15 phút. Tạo REQUESTED thành công không chứng minh luồng accept/payment/expiry đã triển khai.

## Checklist review của Thịnh

Giữ các ô bên dưới chưa chọn cho đến khi Thịnh review trên commit bàn giao.

- [ ] Quyền: middleware ACTIVE/OWNER; pet thuộc owner; caregiver hợp lệ; không tự đặt listing của mình; không tin owner_id/status/amount client.
- [ ] Giá: quote/create dùng CalculateBookingPrice; VND nguyên; UTC/microsecond; ngày Việt Nam; giới hạn và overflow.
- [ ] Transaction: khóa owner trước listing; dữ liệu nguồn được kiểm tra trong transaction; booking/event rollback cùng nhau; REQUESTED không khóa lịch.
- [ ] Snapshot: allowlist pet/listing/policy; instructions riêng; sửa nguồn/xóa mềm pet không đổi lịch sử.
- [ ] Idempotency: key theo owner, hash chuẩn hóa; replay trước source validation; unique constraint; xử lý đồng thời và unique violation; không thêm event khi replay.
- [ ] Fixture dùng lại và tài liệu request/response/lỗi khớp code; test và CI trên commit bàn giao đạt.

Xác nhận reviewer: CHƯA XÁC NHẬN.

Commit reviewer đã kiểm tra: CHƯA GHI.

Link review/comment/PR: CHƯA GHI.

Các yêu cầu sửa còn mở: CHƯA GHI.

## Điều kiện đóng issue

- [ ] Các file đã được áp dụng và commit đầy đủ.
- [ ] Kiểm tra định dạng, PHPStan, migration/seed và toàn bộ test đạt trên commit bàn giao; ghi lại kết quả.
- [ ] Fixture REQUESTED và hướng dẫn đã bàn giao cho người làm accept booking.
- [ ] Thịnh xác nhận review trên commit cuối cùng; không còn yêu cầu sửa cần xử lý.
