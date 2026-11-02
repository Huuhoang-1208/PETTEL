# BE-HA02 — Review và bàn giao

**Owner:** Hào  
**Reviewer:** Hoàng  
**Nhánh:** `be-ha02`  
**Trạng thái:** Chờ PR/CI và Hoàng xác nhận review trên commit cuối.

## 1. Kết quả triển khai

Caregiver quản lý profile của mình và CRUD listing nháp theo quyền sở hữu.

- Mỗi user tối đa một caregiver profile.
- Listing mới có status DRAFT, capacity 1 và version 1.
- Danh sách quản lý trả DRAFT của tài khoản đăng nhập.
- Chi tiết cho phép đọc listing thuộc mình.
- PATCH/DELETE chỉ áp dụng cho DRAFT thuộc mình.
- Listing ngoài quyền sở hữu hoặc đã xóa mềm trả 404.
- Client không được đổi chủ sở hữu, status hoặc các trường hệ thống.
- Dữ liệu không hợp lệ trả 422 VALIDATION_ERROR theo trường.

## 2. API bàn giao

Tất cả route dùng `auth:sanctum`, `account.active`, `role:CAREGIVER`.

| Method | Route                          | Thành công           |
| ------ | ------------------------------ | -------------------- |
| POST   | `/api/caregiver/profile`       | 201 + resource       |
| GET    | `/api/caregiver/profile`       | 200 + resource       |
| PATCH  | `/api/caregiver/profile`       | 200 + resource       |
| POST   | `/api/caregiver/listings`      | 201 + resource DRAFT |
| GET    | `/api/caregiver/listings`      | 200 + pagination     |
| GET    | `/api/caregiver/listings/{id}` | 200 + resource       |
| PATCH  | `/api/caregiver/listings/{id}` | 200 + resource       |
| DELETE | `/api/caregiver/listings/{id}` | 204, xóa mềm         |

Request/response và bảng lỗi chi tiết nằm tại API Contracts, mục M03.

Ví dụ tạo profile:

```json
{
    "type": "INDIVIDUAL",
    "display_name": "Nhà chăm pet của Hào",
    "bio": "Nhận chăm chó và mèo.",
    "area_code": "DN_CENTER"
}
```

Ví dụ tạo listing:

```json
{
    "title": "Trông mèo qua đêm",
    "description": "Không gian chăm sóc trong nhà.",
    "service_type": "BOARDING",
    "area_code": "DN_CENTER",
    "price_vnd": 150000,
    "price_unit": "NIGHT",
    "acceptance_rules_json": {
        "schema_version": 1,
        "species": ["CAT"],
        "sizes": ["SMALL", "MEDIUM"]
    }
}
```

Resource đơn bọc trong `data`; ID dạng chuỗi. PATCH chỉ cập nhật trường được gửi. Nếu gửi JSON điều kiện, phải gửi nguyên object v1 hợp lệ.

## 3. Bằng chứng kiểm tra

Đã chạy kiểm tra local trên commit `6eb0e3a`:

- Pint: PASS.
- PHPStan: PASS.
- Migration, seed và seed lặp: PASS.
- Toàn bộ: 119 tests đạt, 1.922 assertions.
- Database: MySQL `pettel_test` riêng.

Lệnh kiểm tra:

```powershell
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Khi cập nhật code để sửa review, chạy lại kiểm tra và ghi commit/kết quả mới trong PR. CI phải đạt trên commit cuối.

## 4. Checklist review của Hoàng

Các ô này chỉ đánh dấu sau khi Hoàng kiểm tra và xác nhận:

- [ ] Profile được xác định từ tài khoản đăng nhập; không sửa được hồ sơ người khác.
- [ ] Listing được giới hạn theo chủ sở hữu; không lộ bản nháp của người khác.
- [ ] Unique profile/user được bảo vệ; tạo trùng trả 409.
- [ ] Giá, độ dài nội dung, khu vực, cặp dịch vụ/đơn vị giá và JSON điều kiện đúng contract.
- [ ] Các trường hệ thống bị chặn, kể cả client gửi null hoặc giá trị mặc định.
- [ ] Không tự công khai listing; không sửa/xóa ngoài DRAFT qua API nháp.
- [ ] Sửa/xóa kiểm tra trạng thái trong transaction có khóa listing.
- [ ] Test, API contract và ví dụ dữ liệu khớp code.
- [ ] CI đạt trên commit cuối và không còn yêu cầu sửa cần xử lý.
- [ ] Các bên nhận biết rõ phạm vi đã có và phần cần tiếp tục.

## 5. Bàn giao downstream

| Bên nhận                 | Đầu vào bàn giao                                                  | Phần tiếp tục                                                          |
| ------------------------ | ----------------------------------------------------------------- | ---------------------------------------------------------------------- |
| Thịnh — BE-T03           | Profile/listing, quan hệ media/slot và tài liệu bàn giao ảnh/lịch | Lifecycle listing, upload/quản lý ảnh, CRUD lịch và kiểm tra nghiệp vụ |
| Booking — BE-H02/BE-HA03 | Listing ACTIVE/DRAFT, điều kiện nhận pet v1, fixture có slot mở   | Tích hợp booking/accept và kiểm tra lại điều kiện theo contract        |
| Hoàng — BE-H03           | API contract, fixture và biên bản kiểm thử                        | Search công khai và test loại DRAFT khỏi danh sách/chi tiết            |

Fixture dùng chung: `Tests\Support\ListingFixture::make()`.

Fixture có hai caregiver, profile riêng, listing ACTIVE/DRAFT, slot mở, owner/pet và payload booking. Dùng trong test kế thừa `Tests\TestCase` và có `RefreshDatabase`.

Tài liệu liên quan:

- [Bàn giao ảnh và lịch cho BE-T03](<BE-HA02 — Bàn giao ảnh và lịch cho BE-T03.md>).
- [Kiểm thử nghiệm thu và bàn giao BE-H03](<BE-HA02 — Kiểm thử nghiệm thu và bàn giao BE-H03.md>).

## 6. Giới hạn và điều kiện hoàn thành

Chưa triển khai trong BE-HA02:

- API upload ảnh và CRUD lịch đầy đủ.
- Gửi duyệt, admin duyệt và các chuyển trạng thái listing.
- Search công khai và kiểm thử tích hợp ẩn DRAFT trên API công khai.

Xác nhận reviewer: **CHƯA XÁC NHẬN**.

Task 8 chỉ hoàn thành khi Hoàng xác nhận acceptance criteria trên commit cuối, các yêu cầu sửa đã được giải quyết và phần bàn giao rõ ràng. Ghi link review/PR và commit được duyệt khi có xác nhận.
