# BE-H05 — Contract review và rating công khai

**Ngày chốt:** 07/11/2026 · **Owner:** Hoàng · **Reviewer:** Hào.

**Trạng thái:** Bốn nhóm quyết định task 1 đã được áp dụng trong API/schema và có test. Task 6 đã kiểm tra luồng tạo review, public aggregate và action ẩn/khôi phục review; toàn bộ test chuẩn đạt ở lần chạy gần nhất. Chưa có endpoint quản trị report/moderation để nghiệm thu BE-T06 từ đầu đến cuối; chưa có xác nhận reviewer Hào.

## 1. Quyền và điều kiện tạo review — H05-D01

- Người gửi phải đăng nhập, account `ACTIVE`, có role `OWNER` và là `bookings.owner_id` của booking được đánh giá.
- Chỉ tạo review khi `bookings.status = COMPLETED`. H05 đọc trạng thái này; workflow hoàn tất booking chịu trách nhiệm bảo đảm release sandbox thành công trước khi chuyển trạng thái. H05 không gọi release hoặc tự chuyển booking sang COMPLETED.
- Backend lấy `reviews.owner_id` và `reviews.listing_id` từ booking. Client không tự đặt `owner_id`, `listing_id`, `status` hoặc `is_demo`.
- `rating` là số nguyên từ 1 đến 5. `body` tùy chọn, tối đa 1.000 ký tự, theo quy tắc chuẩn hóa/trim chung.
- Một review cho mỗi booking, kể cả khi review cũ là `HIDDEN`. Tái sử dụng unique `reviews.booking_id`; tạo trùng trả HTTP 409, bao gồm yêu cầu đồng thời.
- Tái sử dụng schema hiện có; bốn quyết định này chưa đòi hỏi migration mới.

## 2. Công khai, moderation và tổng hợp — H05-D02

- Review mới mặc định `VISIBLE`. Chỉ review `VISIBLE` được trả trong danh sách công khai.
- Gửi report không tự ẩn review. Review chỉ bị loại khỏi hiển thị khi moderation chuyển `reviews.status` thành `HIDDEN`.
- Module report/admin chịu trách nhiệm cập nhật trạng thái review và ghi lịch sử moderation/audit trong cùng transaction. Giữ review và lịch sử khi ẩn.
- Tổng hợp theo từng `listing_id` bằng `AVG(rating)` và `COUNT` của cùng tập review: `status = VISIBLE`, `is_demo = false`, booking liên quan có `status = COMPLETED`.
- Tính trực tiếp khi đọc; chưa lưu rating/count vào cột hoặc bảng tổng hợp, chưa thêm job hay cache. Danh sách listing tổng hợp trong truy vấn chung, tránh một truy vấn cho mỗi listing.
- Sau khi thao tác tạo hoặc moderation commit, lần đọc tiếp theo từ dữ liệu đã commit phản ánh kết quả mới. Nếu BE-H06 bổ sung cache, phải bổ sung invalidation khi review đổi và kiểm thử tương ứng.

## 3. Response rating — H05-D03

| Trường | Quy ước |
| --- | --- |
| `rating_average` | JSON number, làm tròn 2 chữ số thập phân; `null` khi không có review đủ điều kiện. |
| `review_count` | JSON integer, số review đủ điều kiện tính điểm; `0` khi không có. |
| Phạm vi | Theo `listing_id`. |

Khi mọi review đã bị ẩn hoặc chỉ còn review demo, trả `rating_average: null`, `review_count: 0`. Không dùng số 0 để biểu diễn điểm trung bình khi chưa có đánh giá.

`review_count` không phải tổng số item của danh sách review có kèm demo. Nếu danh sách có phân trang, tổng item phân trang vẫn mô tả đúng tập item được trả.

## 4. Seed minh họa — H05-D04

- Review được seed để minh họa luôn có `is_demo = true`; không suy ra cờ này từ tên hoặc email.
- Review demo `VISIBLE` có thể xuất hiện trong danh sách công khai, nhưng response phải trả `is_demo` để frontend gắn nhãn minh họa.
- Loại demo khỏi cả `rating_average` và `review_count`. Không gắn nhãn xác minh hoặc đánh giá từ giao dịch thật cho dữ liệu minh họa.
- API tạo review thông thường do server đặt `is_demo = false`; không cho client thay cờ.
- `backend/database/seeders/DemoSeeder.php` hiện chỉ tạo tài khoản, pet và caregiver profile mẫu; không tạo booking hoặc review. Vì không có review seed nên không cần sửa seeder hoặc thêm một booking `COMPLETED` giả chỉ để gắn review minh họa.
- Nếu sau này bổ sung review seed, phải gắn với booking demo hợp lệ, đặt rõ `is_demo = true` và giữ quy tắc loại khỏi rating/count. Cờ `is_demo` được trả trong từng item review công khai.
- Test fixture có thể đặt booking thành `COMPLETED` để kiểm tra H05, nhưng không chứng minh workflow hoàn tất/release thật đã tích hợp.

## 5. Điểm tích hợp và hai bản mã kế hoạch

Danh sách người dùng cung cấp ngày 07/11/2026 và backlog trên `main` tại commit `d088f17` đang dùng mã khác nhau. Bốn quyết định trên được áp dụng theo chức năng, không tự đổi mã issue, owner hoặc stack frontend.

| Chức năng | Mã trong danh sách người dùng vừa cung cấp | Mã trên main tại baseline |
| --- | --- | --- |
| Giữ chỗ, hết hạn, hủy | BE-HA04 — Thịnh | BE-T04 — Thịnh |
| Messaging/private storage | BE-T04 — Hào | BE-HA04 — Hào |
| Hoàn tất booking và release | BE-T06 — Thịnh | BE-HA06 — Hào |
| Report/admin/moderation | BE-HA06 — Hào | BE-T06 — Thịnh |

H05 phụ thuộc BE-T01 về schema/state, fixture COMPLETED để phát triển/test, workflow hoàn tất booking để nghiệm thu end-to-end và module report/admin để nghiệm thu moderation. Theo danh sách vừa cung cấp, hai phần tích hợp sau lần lượt là BE-T06 và BE-HA06; theo backlog main là BE-HA06 và BE-T06.

Danh sách vừa cung cấp ghi H05 N11–N13, trong khi completion/moderation N13–N15; backlog main ghi H05 N11–N15. Đề xuất rà soát lịch: phần review độc lập có thể làm bằng fixture N11–N13, còn nghiệm thu tích hợp trước Gate BE N15. Đây là đề xuất lịch, không phải quyết định lịch mới đã được duyệt.

Task 1 đã chốt bốn nhóm quy tắc; mã phân công/lịch và các điểm lệch trong kế hoạch toàn dự án được rà soát riêng, không coi là đã đổi theo việc duyệt quy tắc H05.

## 6. Nguồn đối chiếu

- [M08 — Reviews & Trust Signals](<../03 — Modules Documentation/M08 — Reviews & Trust Signals.md>).
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
- [BE-T01 — Schema và hợp đồng nghiệp vụ](<BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ.md>).
- [Database Design — M08](<../08 — Architecture & Technical Design/database design.md>).
- [API Contracts](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>).
- [Backlog trong repo](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>).
- Migration `backend/database/migrations/2026_10_28_000004_create_booking_content_and_moderation_schema.php`, `backend/database/seeders/DemoSeeder.php`, `backend/tests/Support/BookingFixture.php` và API listing tại baseline.

## 7. Bàn giao task 7

- Hợp đồng endpoint, ví dụ thành công, lỗi chính, phân trang và quy tắc aggregate được ghi tại [API Contracts — M08](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>).
- Seed: hiện chưa có review seed trong `DemoSeeder`; không có dữ liệu review nào cần đổi thành demo. Nếu bổ sung về sau, bắt buộc `is_demo = true`; API tạo review thật luôn do server đặt `is_demo = false`.
- Lệnh kiểm tra chuẩn: `docker compose -p pettel-test -f compose.test.yaml run --build --rm test`.
- Kết quả lần chạy gần nhất trên nhánh `be-h05`: Pint đạt, PHPStan không lỗi, migration/seed đạt, 355 tests và 4105 assertions đạt.
- Phụ thuộc: BE-T01 cung cấp schema/state; moderation action hiện được kiểm thử cùng API đọc công khai, nhưng endpoint report/admin của BE-T06 chưa có trong nhánh này nên chưa nghiệm thu end-to-end. Workflow hoàn tất booking/release cũng cần kiểm tra trên luồng thật; H05 hiện dùng fixture `COMPLETED`.
- Reviewer: Hào. Khi tạo PR, thêm Hào làm reviewer và liên kết issue H05 cùng các phụ thuộc đang chờ tích hợp.
