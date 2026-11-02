# BE-H03 — Review và bàn giao

**Owner:** Hoàng  
**Reviewer:** Hào  
**Nhánh:** `be-h03`  
**Issue:** https://github.com/Huuhoang-1208/PETTEL/issues/12  
**Trạng thái:** Chuẩn bị Draft PR; chờ tích hợp BE-T03/BE-HA03, CI và Hào review.

## Kết quả triển khai

- `GET /api/listings`: tìm kiếm công khai theo từ khóa, loại dịch vụ, khu vực, loài, kích cỡ, khoảng thời gian và khoảng tổng giá.
- `GET /api/listings/{id}`: chi tiết listing được phép công khai.
- Danh sách và chi tiết dùng chung điều kiện ACTIVE, chưa xóa mềm, profile hợp lệ, tài khoản caregiver ACTIVE và còn role CAREGIVER.
- Response dùng allowlist; không trả hồ sơ pet, ghi chú chăm sóc hoặc snapshot booking riêng tư.
- Các bộ lọc kết hợp bằng AND; từ khóa tìm trong title OR description.
- Khoảng tìm phải nằm trọn trong một slot chưa xóa mềm.
- Khoảng giao nhau dùng `[start_at, end_at)`, UTC và microsecond.
- REQUESTED không khóa lịch.
- AWAITING_PAYMENT, CONFIRMED, IN_PROGRESS và AWAITING_CONFIRMATION khóa lịch khi giao nhau.
- Giữ chỗ quá hạn vẫn khóa lịch cho đến khi luồng booking thực sự thả lịch theo contract.
- Tổng giá dùng số đơn vị từ CalculateBookingPrice, nhân bằng số chính xác và kiểm tra giới hạn.
- Có ngày: sắp tổng giá tăng dần, rồi ID tăng dần. Không có ngày: ID tăng dần, tổng giá null.
- Lọc và sắp xếp trước phân trang. Mặc định 20, tối đa 50 kết quả/trang.
- Không có kết quả hoặc trang vượt kết quả trả 200 với data rỗng và metadata đúng.

## Đối chiếu phụ thuộc

| Phụ thuộc           | Bằng chứng hiện có                                                                            | Phần còn chờ                                             |
| ------------------- | --------------------------------------------------------------------------------------------- | -------------------------------------------------------- |
| BE-HA02             | Tái sử dụng schema/model, ListingFixture và BookingFixture; không thêm migration              | Đối chiếu lại nếu contract nguồn thay đổi                |
| BE-H02              | Search và create/quote dùng chung ApplyListingAvailability; tái sử dụng CalculateBookingPrice | Chạy hồi quy sau khi cập nhật booking                    |
| BE-T03              | Tuân theo contract trạng thái công khai, slot UTC, xóa mềm và một slot bao trọn               | Tích hợp API lifecycle và CRUD lịch thực tế              |
| BE-HA03             | Test search với các trạng thái booking và kiểm tra nhất quán với quote                        | Tích hợp accept/reject và kiểm tra cạnh tranh khi accept |
| Luồng expiry/cancel | Test giữ chỗ quá hạn vẫn chặn; CANCELLED thả lịch                                             | Kiểm thử job/transaction hết hạn và void/refund thực tế  |

Các trạng thái được tạo trực tiếp trong fixture không thay thế kiểm thử các API chuyển trạng thái.

## Bằng chứng kiểm thử local

Đã chạy ngày 02/11/2026 trên working tree nhánh be-h03:

- HEAD nền: d528f7a4b9d698ba450a601a1cfd662c23f87ad1.
- Các thay đổi H03 tại thời điểm kiểm tra chưa commit.
- Pint: PASS, 95 file.
- PHPStan: PASS.
- Migration và seed lặp: PASS.
- Toàn bộ: 199 tests đạt, 2.351 assertions.
- Database: MySQL pettel_test riêng.
- git diff --check: PASS.

Không dùng SHA nền ở trên làm SHA của phần triển khai H03. Sau khi commit, ghi SHA bàn giao và link CI trong PR.

| Bộ kiểm thử công khai         | Số trường hợp |
| ----------------------------- | ------------: |
| PublicListingTest             |            13 |
| PublicListingFilterTest       |            34 |
| PublicListingAvailabilityTest |            20 |
| PublicListingPricingTest      |             9 |
| PublicListingAcceptanceTest   |             4 |
| Tổng                          |            80 |

## Cách kiểm tra lại

Chạy tại thư mục gốc dự án:

```cmd
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Runner kiểm tra định dạng trước khi phân tích kiểu, chuẩn bị database và chạy PHPUnit.

## Checklist review của Hào

- [ ] Danh sách và chi tiết dùng cùng quyền công khai; truy cập trực tiếp listing bị ẩn trả 404.
- [ ] Bộ lọc, chuẩn hóa từ khóa và nhóm OR không vượt qua các điều kiện AND.
- [ ] Slot bao trọn, giao nhau, múi giờ và trạng thái khóa lịch đúng contract.
- [ ] Tổng giá khớp quote booking; không so trực tiếp đơn giá HOUR với NIGHT.
- [ ] Giá bằng nhau được sắp tiếp theo ID; phân trang sau lọc.
- [ ] Không lộ dữ liệu pet hoặc booking riêng tư.
- [ ] CI đạt trên commit cuối cùng của PR.
- [ ] BE-T03 đã tích hợp và kiểm thử qua API thực tế.
- [ ] BE-HA03 và luồng hết hạn giữ chỗ đã tích hợp, có test hồi quy.

**Reviewer sign-off:** CHƯA XÁC NHẬN.

**Điều kiện đóng issue:** PR, CI, review và các phụ thuộc tích hợp đều hoàn tất. Giữ task 7 chưa chọn trong thời gian chờ BE-T03/BE-HA03.
