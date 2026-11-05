# BE-H04 — Hồ sơ bằng chứng nghiệm thu

Ngày chạy: **2026-11-06** (Asia/Ho_Chi_Minh)
Phạm vi: **A–B, tasks 01–07** của Smart Match backend.
Trạng thái bàn giao: commit triển khai local **`807785ebf2560aff6362133eb71af74be75b963a`** trên nhánh `be-t03`; không push theo yêu cầu.

## Kết quả có thể kiểm tra

| Kiểm tra | Kết quả | Bằng chứng trực tiếp |
|---|---|---|
| Pint | PASS — 128 files | [Log đầy đủ, dòng 92](<BE-H04 — Evidence nghiệm thu.log#L92>) |
| PHPStan | PASS — 87 files, không lỗi | [Log đầy đủ, dòng 98](<BE-H04 — Evidence nghiệm thu.log#L98>) |
| Database setup | Migrations và DemoSeeder chạy thành công trên service `db-test` của test compose | [Log đầy đủ, dòng 105–124](<BE-H04 — Evidence nghiệm thu.log#L105>); cấu hình [compose.test.yaml](../../compose.test.yaml) |
| PHPUnit | **306 passed, 3,591 assertions** | [Log đầy đủ, dòng 506–507](<BE-H04 — Evidence nghiệm thu.log#L506>) |
| Test riêng Smart Match | 10 ca PASS trong full suite | [SmartMatchTest.php](../../backend/tests/Feature/SmartMatchTest.php) và [log, dòng 494–505](<BE-H04 — Evidence nghiệm thu.log#L494>) |
| Kiểm tra whitespace/conflict markers | `git diff --check` không báo lỗi | Đã chạy sau lần cập nhật cuối; working tree vẫn chưa commit |

Log trên được lưu trực tiếp từ output của lần chạy lệnh dưới đây. Tôi chỉ bỏ mã màu ANSI, khoảng trắng đệm cuối dòng và dòng trống cuối file để diff dễ đọc; nội dung kết quả và thứ tự output được giữ nguyên:

```sh
docker compose -p pettel-be-h04-evidence -f compose.test.yaml run --build --rm test > 'docs/05 — Implementation Plan/BE-H04 — Evidence nghiệm thu.log' 2>&1
```

Docker Compose command trả exit code **0**. File log ghi output Pint, PHPStan, migration/seed và toàn bộ kết quả PHPUnit. Test dùng compose riêng của project `pettel-be-h04-evidence`; không chạy trên database demo/production.

SHA-256 của log đã chuẩn hóa: `c921276e4d6d0627f1c30319720cd21aac0aa95a93ddb71a7cbecc9bf667b923`. Có thể đối chiếu bằng `sha256sum` trên file log.

## Tiêu chí và đường dẫn tới bằng chứng

| Tiêu chí | Phần triển khai | Test/contract để đối chiếu |
|---|---|---|
| `pet_id` tùy chọn; lọc thủ công vẫn dùng được; kiểm tra owner, account/role và trạng thái pet | [SearchListingRequest.php](../../backend/app/Http/Requests/SearchListingRequest.php), [ResolveSmartMatchPet.php](../../backend/app/Domain/Discovery/Actions/ResolveSmartMatchPet.php), [PublicListingController.php](../../backend/app/Http/Controllers/PublicListingController.php) | [SmartMatchTest.php](../../backend/tests/Feature/SmartMatchTest.php): guest/manual, auth/role, foreign/soft-deleted, inactive, archived, missing size, conflicting filters, invalid ID |
| Acceptance rules v1 dùng thống nhất giữa search và booking | [AcceptanceRules.php](../../backend/app/Domain/Listing/AcceptanceRules.php), [StoreBookingRequest.php](../../backend/app/Http/Requests/StoreBookingRequest.php) | [SmartMatchTest.php](../../backend/tests/Feature/SmartMatchTest.php): species/size mismatch, invalid rules; booking regression nằm trong full suite log |
| Search tái sử dụng quy tắc lịch/tổng giá; lọc trước phân trang; loại listing của chính owner | [PublicListingController.php](../../backend/app/Http/Controllers/PublicListingController.php) | [SmartMatchTest.php](../../backend/tests/Feature/SmartMatchTest.php); [PublicListingAcceptanceTest.php](../../backend/tests/Feature/PublicListingAcceptanceTest.php); [PublicListingPricingTest.php](../../backend/tests/Feature/PublicListingPricingTest.php) |
| Trả reason codes và trạng thái không có kết quả | [SearchListingResource.php](../../backend/app/Http/Resources/SearchListingResource.php), [PublicListingController.php](../../backend/app/Http/Controllers/PublicListingController.php) | [SmartMatchTest.php](../../backend/tests/Feature/SmartMatchTest.php); contract có ví dụ response trong [API Contracts](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) |
| Không thêm pet details/notes vào response công khai; detail response giữ allowlist hiện có | [SearchListingResource.php](../../backend/app/Http/Resources/SearchListingResource.php) | [PublicListingAcceptanceTest.php](../../backend/tests/Feature/PublicListingAcceptanceTest.php) kiểm tra keys/private data và detail không có `match_reasons` |
| Tài liệu module mô tả đúng contract triển khai | [M04 — Discovery, Search & Smart Match](<../03 — Modules Documentation/M04 — Discovery, Search & Smart Match.md>), [API Contracts & Error Conventions](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) | Decision log, ví dụ request/response và mã lỗi được ghi trong các tài liệu liên kết |

## Ca Smart Match trong test suite

`SmartMatchTest` xác nhận: owner pet lọc kết quả và trả từng lý do; guest vẫn lọc thủ công; yêu cầu đăng nhập owner khi gửi pet; pet khác chủ/xóa mềm bị ẩn đồng nhất; account không active, pet archived, thiếu size; xung đột species/size thủ công; empty result có summary; rules sai/không match bị loại; listing của owner bị loại; và `pet_id` sai định dạng trả validation error.

## Phạm vi chưa được xác nhận bởi hồ sơ này

- Đây là kết quả local trên code/test được commit; chưa phải GitHub Actions CI trên PR.
- Commit được tạo trên nhánh hiện tại `be-t03`; không tạo/push PR theo chỉ dẫn người dùng.
- Chưa có PR và chưa có reviewer sign-off của Hào. Vì vậy tasks A–B/01–07 có bằng chứng local; Task 08 (PR, CI trên head cuối và review) vẫn mở.
- Chưa có kiểm thử UI vì frontend chưa thuộc scope triển khai backend H04.
