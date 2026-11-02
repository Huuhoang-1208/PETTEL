# BE-HA02 — Kiểm thử nghiệm thu và bàn giao BE-H03

**Owner:** Hào  
**Reviewer:** Hoàng  
**Phạm vi:** Caregiver profile và CRUD listing DRAFT theo quyền sở hữu.

## 1. Ma trận kiểm thử

| Yêu cầu                                                                                 | Bằng chứng                                                          |
| --------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| Tạo, xem, cập nhật profile; chặn tạo trùng, kể cả profile đã xóa mềm                    | `CaregiverProfileTest`                                              |
| CRUD nháp thành công với chủ sở hữu; xóa mềm                                            | `CaregiverListingTest`                                              |
| Caregiver khác không xem/sửa/xóa được listing qua API quản lý                           | `CaregiverListingTest`, `ListingFixtureTest`                        |
| Chặn chưa đăng nhập, thiếu role CAREGIVER và account không ACTIVE trên toàn bộ tám API  | `CaregiverAccessTest`                                               |
| Chặn đổi chủ sở hữu, tự công khai hoặc ghi trường hệ thống                              | `CaregiverListingTest`, `CaregiverAccessTest`, `ListingFixtureTest` |
| Chặn sửa/xóa listing ngoài DRAFT                                                        | `CaregiverListingTest`                                              |
| Từ chối giá, cặp dịch vụ/đơn vị giá và JSON điều kiện không hợp lệ; trả lỗi theo trường | `ListingValidationTest`                                             |
| Quan hệ ảnh/lịch và dữ liệu fixture hợp lệ                                              | `ListingRelationsTest`, `ListingFixtureTest`                        |

Các test kiểm tra request bị từ chối không tạo thêm hoặc sửa dữ liệu.

## 2. Cách chạy

Chạy tại thư mục gốc dự án:

```powershell
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Sử dụng database MySQL `pettel_test` riêng theo cấu hình dự án.

Bản mẫu task 7 đã được kiểm tra:

- Định dạng: PASS.
- Phân tích kiểu: PASS.
- Toàn bộ: 119 tests đạt, 1.922 assertions.

Trước khi gửi PR, chạy lại trên code thực tế, ghi commit đã kiểm tra và đính kèm kết quả. Kết quả bản mẫu không thay thế kết quả CI hoặc xác nhận reviewer.

## 3. Kiểm thử công khai còn chờ BE-H03

Tại thời điểm đối chiếu, chưa có API công khai:

- `GET /api/listings`
- `GET /api/listings/{id}`

Chưa xác nhận việc lọc DRAFT trong luồng truy vấn công khai hoặc qua HTTP. Không dùng phản hồi 404 do route chưa tồn tại làm bằng chứng kiểm thử ẩn DRAFT.

BE-H03 cần bổ sung:

1. Dùng `ListingFixture::make()` để lấy listing ACTIVE hợp lệ và các listing DRAFT.
2. Kiểm tra danh sách công khai có ACTIVE hợp lệ và không chứa DRAFT.
3. Kiểm tra khách chưa đăng nhập hoặc người không có quyền nhận 404 khi truy cập trực tiếp DRAFT.
4. Kiểm tra chi tiết ACTIVE hợp lệ trả dữ liệu công khai đúng contract.
5. Nếu chi tiết hỗ trợ ngoại lệ cho caregiver sở hữu listing hoặc admin theo contract, viết testcase riêng cho quyền đó.
6. Mở rộng kiểm tra các trạng thái không công khai và điều kiện caregiver theo P0/BE-T03.
7. Chạy lại toàn bộ tests sau khi tích hợp.

API `/api/caregiver/listings` là API quản lý riêng. Việc API này trả DRAFT của chủ sở hữu không thay thế kiểm thử danh sách công khai.

## 4. Giới hạn nghiệm thu

- Test hiện tại xác nhận profile, CRUD nháp, quyền sở hữu và validation trong phạm vi BE-HA02.
- Kiểm thử công khai được bàn giao BE-H03, chưa đánh dấu PASS.
- Kiểm thử HTTP feature không thay thế bằng chứng CSRF/cookie thực tế của BE-H01.
- Xác nhận review của Hoàng được ghi riêng; test xanh không tự đồng nghĩa PR đã được duyệt.
