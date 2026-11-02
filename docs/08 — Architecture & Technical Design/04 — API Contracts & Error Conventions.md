# API Contracts & Error Conventions

PETTEL — Architecture & Technical Design \| Hợp đồng JSON cho Vue SPA; Filament dùng cùng service/quy tắc nghiệp vụ.

# Endpoint P0

Breeze API scaffold cung cấp route đăng ký/đăng nhập/đăng xuất/khôi phục mật khẩu; Vue lấy `/sanctum/csrf-cookie` trước thao tác auth. Route riêng tư dùng `auth:sanctum`; không phát hành bearer token cho SPA.

GET /api/listings — bộ lọc công khai theo contract M04 bên dưới; `pet_id` thuộc phần mở rộng Smart Match BE-H04. GET /api/listings/{id} — chỉ listing được phép công khai, không có ngoại lệ owner/admin trên route công khai. POST /api/bookings — pet_id, listing_id, start_at, end_at, instructions, Idempotency-Key. POST /api/bookings/{id}/accept — caregiver; POST /api/bookings/{id}/cancel — actor được phép; POST /api/bookings/{id}/sandbox-payments — owner. POST /api/sandbox/callback — chỉ adapter sandbox. POST /api/bookings/{id}/updates và /confirm-completion; POST /api/reports.

## M04 — Public listing search — chốt 02/11/2026

**Trạng thái triển khai BE-H03 — cập nhật 02/11/2026:** Đã có `GET /api/listings` và `GET /api/listings/{id}`. Bộ lọc hiện hỗ trợ `q`, `service_type`, `area_code`, `species`, `size`, `start_at`, `end_at`, `min_price_vnd`, `max_price_vnd`, `page` và `per_page`.

Danh sách và chi tiết dùng chung quy tắc công khai. Search và create/quote dùng chung `ApplyListingAvailability`. Số đơn vị tính tiền lấy từ `CalculateBookingPrice`; tổng giá được tính bằng số chính xác, lọc và sắp xếp trong database trước phân trang. Có ngày sắp tổng giá/ID tăng dần; không có ngày sắp ID tăng dần và tổng giá `null`. Mặc định 20, tối đa 50 kết quả/trang; `page` gây tràn số vị trí phân trang bị từ chối.

Kiểm tra local trên working tree `be-h03` ngày 02/11/2026: Pint PASS (95 file), PHPStan PASS, migration/seed lặp PASS, toàn bộ 199 tests đạt với 2.351 assertions trên database `pettel_test` riêng. Các thay đổi H03 chưa commit tại thời điểm kiểm tra; SHA bàn giao và CI phải được ghi sau khi commit/push.

BE-HA02 đã được đối chiếu qua model/schema/fixture hiện có. BE-T03 và BE-HA03 chưa triển khai; các test hiện kiểm tra cách search đọc slot/trạng thái từ fixture, chưa xác nhận tích hợp API lifecycle, CRUD lịch, accept/reject hoặc luồng expiry thực tế. Giữ BE-H03 ở trạng thái chờ tích hợp và review; chưa đủ điều kiện đóng issue.

### Endpoint và quyền công khai

- `GET /api/listings`: danh sách công khai, không yêu cầu đăng nhập, dùng query bên dưới.
- `GET /api/listings/{id}`: chi tiết công khai, không yêu cầu đăng nhập. Không áp dụng bộ lọc tìm kiếm/tính giá theo ngày trên endpoint này; dùng API quote booking cho báo giá trước khi đặt.
- Cả hai chỉ trả listing `ACTIVE`, chưa xóa mềm, profile chưa xóa mềm, user caregiver `ACTIVE` và còn role `CAREGIVER`. Không nhận `status` do client gửi để mở rộng quyền xem.
- Listing không tồn tại hoặc không đủ điều kiện công khai trả `404 NOT_FOUND` trên route chi tiết, kể cả người gọi là caregiver sở hữu hoặc admin. Caregiver xem DRAFT của mình qua API quản lý `/api/caregiver/listings/{id}` có kiểm tra quyền; quyền quản trị thuộc luồng quản lý riêng.
- Listing bận trong khoảng tìm kiếm chỉ bị loại khỏi kết quả cho khoảng đó, không tự mất quyền công khai ở trang chi tiết. Kết quả tìm kiếm không giữ chỗ; booking vẫn phải kiểm tra lại khi tạo/accept.

### Query của GET /api/listings

Mỗi tham số là một giá trị đơn; không nhận mảng. Các tham số đều tùy chọn trừ các ràng buộc đi kèm ghi dưới đây. Query là chuỗi URL: trường số nhận biểu diễn số nguyên thập phân, không nhận số thực hoặc ký hiệu khoa học.

| Tham số | Kiểu / giá trị | Quy tắc |
| --- | --- | --- |
| `q` | Chuỗi Unicode | Trim, chuẩn hóa NFC; tối đa 100 ký tự sau chuẩn hóa. Rỗng sau trim tương đương không truyền. |
| `service_type` | `DAYCARE`, `BOARDING` | Mã P0, phân biệt hoa/thường khi validation. |
| `area_code` | `DN_CENTER`, `DN_NORTH`, `DN_SOUTH`, `DN_WEST`, `DN_EAST` | Mã vùng nội bộ do caregiver chọn, không nhận tên quận hoặc địa chỉ tự do. |
| `start_at` | Timestamp ISO 8601 | Phải đi cùng `end_at`, có `Z` hoặc offset; lớn hơn server now lúc kiểm tra. |
| `end_at` | Timestamp ISO 8601 | Phải đi cùng `start_at`; lớn hơn `start_at`. |
| `min_price_vnd` | Số nguyên từ 0 đến giới hạn số nguyên backend hỗ trợ | Cận dưới bao gồm của tổng giá; yêu cầu đủ `start_at`/`end_at`. |
| `max_price_vnd` | Số nguyên từ 0 đến giới hạn số nguyên backend hỗ trợ | Cận trên bao gồm của tổng giá; yêu cầu đủ `start_at`/`end_at`. |
| `species` | `DOG`, `CAT`, `OTHER` | Phải có trong `acceptance_rules_json.species` v1 hợp lệ; `OTHER` phải được khai báo rõ. |
| `size` | `SMALL`, `MEDIUM`, `LARGE` | Giữ trong H03 theo M04; phải có trong `acceptance_rules_json.sizes` v1 hợp lệ. |
| `page` | Số nguyên từ 1 | Mặc định `1` khi không truyền. |
| `per_page` | Số nguyên từ 1 đến 50 | Mặc định `20` khi không truyền; vượt giới hạn trả lỗi, không tự cắt về 50. |

Ngoài `q`, giá trị rỗng của tham số đã gửi là không hợp lệ. Tham số ngoài danh sách, gồm `pet_id`/`status`/`sort`, trả `422 VALIDATION_ERROR` theo trường để tránh âm thầm bỏ qua bộ lọc. `pet_id` được bàn giao BE-H04 mở rộng contract với auth/owner policy; H03 chỉ lọc thủ công và không đọc pet profile.

### Kết hợp, thời gian, lịch và giá

1. Các bộ lọc kết hợp bằng AND. Riêng `q` tìm chuỗi xuất hiện trong `title` OR `description`, không tìm tên chủ nuôi, hồ sơ pet hoặc nội dung booking. Dùng quy tắc so sánh của collation MySQL dự án, không tự bỏ dấu; `%` và `_` trong từ khóa là ký tự thường, không phải wildcard do người dùng điều khiển.
2. Thiếu cả hai mốc thời gian thì không kiểm tra lịch cho một khoảng cụ thể, không tính tổng giá và không tuyên bố listing còn trống. Nếu gửi một trong hai cận giá thì phải gửi đủ hai mốc thời gian. Cho phép chỉ có giá tối thiểu hoặc chỉ có giá tối đa; nếu có cả hai thì `min_price_vnd <= max_price_vnd`.
3. Timestamp dùng cùng định dạng với booking: `YYYY-MM-DDTHH:mm:ss[.ffffff]Z` hoặc offset như `+07:00`, tối đa 6 chữ số phần lẻ. Chuẩn hóa UTC trước khi so sánh, giữ phần lẻ. Trong query URL, dấu `+` của offset phải được encode thành `%2B`; có thể dùng `Z` để tránh nhầm.
4. Có khoảng thời gian thì phải nằm trọn trong một slot chưa xóa mềm: `slot.start_at <= start_at` và `slot.end_at >= end_at`. Không tự ghép các slot rời thành một slot. Khoảng là `[start_at, end_at)`; giao nhau khi `booking.start_at < end_at` AND `booking.end_at > start_at`. Hai khoảng chỉ chạm đầu/cuối không xung đột.
5. Các trạng thái khóa lịch hiện dùng trong tạo booking: `AWAITING_PAYMENT`, `CONFIRMED`, `IN_PROGRESS`, `AWAITING_CONFIRMATION`. `REQUESTED`, `COMPLETED`, `CANCELLED` không khóa lịch. Hạn thanh toán 15 phút không tự làm search bỏ qua booking vẫn khóa lịch: chỉ thả theo cùng quy tắc chuyển trạng thái/void/refund của booking. BE-HA03/BE-T04 phải tích hợp quy tắc hết hạn nhất quán trước nghiệm thu; nếu đổi cách xét hạn thì cập nhật cả create/accept/search và test hồi quy.
6. Tính tổng giá cùng quy tắc `CalculateBookingPrice`: DAYCARE/HOUR từ 1–12 giờ và cùng ngày `Asia/Ho_Chi_Minh`; BOARDING/NIGHT từ 12 giờ đến 30 ngày. Làm tròn lên theo đơn vị, giữ phần lẻ và kiểm tra tràn số theo P0. Không đặt thêm giới hạn số ngày đặt trước.
7. Nếu đã chọn `service_type` nhưng thời lượng không phù hợp loại đó, trả `422` tại `end_at`. Nếu không chọn loại dịch vụ, xét thời lượng theo từng listing và loại listing không phù hợp; không làm hỏng cả truy vấn vì một loại không phù hợp. Khoảng thời gian không phù hợp bất kỳ loại P0 nào trả `422`. Listing có cấu hình điều kiện/giá sai hoặc tổng giá không thể biểu diễn an toàn không được coi là phù hợp; không trả lỗi toàn bộ truy vấn vì một bản ghi sai.
8. Lọc và sắp xếp phải hoàn tất trước phân trang. Có ngày: tổng giá tăng dần, sau đó ID tăng dần. Không có ngày: ID tăng dần, không so đơn giá HOUR với NIGHT. Thông tin phân trang và các link phải giữ các bộ lọc đã chuẩn hóa.

### Response công khai

- Danh sách: HTTP `200`, cấu trúc phân trang `data`, `links`, `meta` theo chuẩn dự án. `meta` có tối thiểu `current_page`, `last_page`, `per_page`, `total`; `links` có `first`, `last`, `prev`, `next`.
- Chi tiết: HTTP `200`, cấu trúc `{ "data": { ... } }`.
- Allowlist cho mỗi listing ở cả danh sách và chi tiết: `id` (chuỗi), `title`, `description`, `service_type`, `area_code`, `price_vnd` (đơn giá nguyên VND), `price_unit`, `acceptance_rules_json` v1 và `price_total_vnd`.
- `price_total_vnd` là số nguyên server tính khi tìm có ngày; là `null` khi tìm không có ngày và trên endpoint chi tiết không có ngữ cảnh báo giá.
- Không trả pet profile/notes, booking snapshot, thông tin liên hệ riêng, địa chỉ chi tiết, object key hoặc dữ liệu nội bộ bằng cách dump model. Ảnh và aggregate review là phần mở rộng cần contract riêng trước khi thêm vào allowlist.
- Không có kết quả hoặc trang vượt kết quả: vẫn `200`, `data: []`, giữ metadata phân trang đúng. Frontend có thể giải thích kết quả rỗng và gợi ý nới lọc dựa trên các bộ lọc đã chọn; không bịa listing.

Ví dụ request hợp lệ (09:00–12:00 giờ Việt Nam, tổng giá tối đa 200.000 VND):

```http
GET /api/listings?service_type=DAYCARE&area_code=DN_EAST&species=DOG&start_at=2026-11-10T02:00:00Z&end_at=2026-11-10T05:00:00Z&max_price_vnd=200000&page=1&per_page=20
```

### Validation và ca kiểm thử bắt buộc

Lỗi dùng envelope chung `error: { code, message, details, request_id }`; `details` ánh xạ tên trường sang mảng thông báo, không chứa dữ liệu riêng.

| Tình huống | Kết quả |
| --- | --- |
| Không truyền bộ lọc | `200`, danh sách công khai, trang 1, 20 kết quả/trang, ID tăng dần. |
| Kết hợp từ khóa + loại + vùng + loài/kích cỡ | Chỉ listing thỏa tất cả điều kiện; `q` vẫn áp dụng title OR description. |
| Sai mã, sai kiểu/mảng, số âm/số thực, query ngoài danh sách | `422 VALIDATION_ERROR`, chỉ rõ trường. |
| Chỉ có một mốc, thiếu múi giờ, start không ở tương lai, end <= start | `422 VALIDATION_ERROR`, chỉ rõ trường thời gian. |
| Lọc giá thiếu ngày hoặc min > max | `422 VALIDATION_ERROR`, chỉ rõ trường cần sửa. |
| Chỉ có min hoặc max với đủ ngày | Hợp lệ; lọc cận bao gồm theo tổng giá. |
| Thời lượng không hợp loại đã chọn | `422` tại `end_at`; chưa chọn loại thì loại từng listing không phù hợp. |
| Không có slot bao trọn hoặc giao booking khóa lịch | Loại listing khỏi danh sách; không trả `409` cho toàn bộ search. |
| REQUESTED giao nhau hoặc booking chỉ chạm đầu/cuối | Không loại vì xung đột lịch. |
| DRAFT, trạng thái ẩn, soft delete, profile/user/role caregiver không hợp lệ | Không có trong danh sách và chi tiết trả `404 NOT_FOUND`. |
| Không kết quả hoặc page vượt kết quả | `200`, `data: []`, metadata đúng. |
| page < 1, per_page < 1 hoặc > 50 | `422 VALIDATION_ERROR`. |
| So response public với dữ liệu pet/booking riêng | Không có trường ngoài allowlist; quyền công khai danh sách/chi tiết thống nhất. |

### Bàn giao và giới hạn task 1

- BE-HA02: đã đối chiếu các field/model/fixture listing và mã validation hiện có; không cần schema mới để chốt contract.
- BE-T03: tái sử dụng slot UTC có microsecond, `[start_at,end_at)`, xóa mềm và một slot bao trọn từ tài liệu bàn giao. Lifecycle và API lịch vẫn phải tích hợp/kiểm thử khi triển khai.
- BE-H02/BE-HA03/BE-T04: tái sử dụng công thức giá và trạng thái khóa lịch; thống nhất xử lý hết hạn trước nghiệm thu.
- Task 1 hoàn tất phần quyết định/tài liệu. Chưa đánh dấu API, kiểm thử hoặc toàn bộ BE-H03 hoàn thành; reviewer sign-off ghi riêng khi có bằng chứng.

## M02 — Pet Profiles

Các route pet yêu cầu `auth:sanctum`, account `ACTIVE` và role `OWNER`; mọi truy vấn chỉ trả pet chưa xóa mềm của owner hiện tại. ID không thuộc owner trả 404. Caregiver không có route đọc pet live; dữ liệu chăm sóc chỉ được cung cấp trong snapshot của booking liên quan.

| Method / route                               | Dùng để                                           | Request / response                                                            |
| -------------------------------------------- | ------------------------------------------------- | ----------------------------------------------------------------------------- |
| `GET /api/pets`                              | Liệt kê pet của owner, gồm `ACTIVE` và `ARCHIVED` | Pagination chuẩn `data`, `links`, `meta`.                                     |
| `POST /api/pets`                             | Tạo pet                                           | `name`, `species` bắt buộc; các trường hồ sơ khác tùy chọn; trả resource 201. |
| `GET /api/pets/{pet}`                        | Xem hồ sơ                                         | `PetResource`; chỉ owner của pet.                                             |
| `PATCH /api/pets/{pet}`                      | Cập nhật hồ sơ                                    | Chỉ field hồ sơ được gửi; `user_id` và `status` do server quản lý.            |
| `PATCH /api/pets/{pet}/archive` / `activate` | Lưu trữ hoặc kích hoạt lại hồ sơ                  | Cập nhật `status`; chỉ pet ACTIVE được dùng tạo booking.                      |
| `DELETE /api/pets/{pet}`                     | Xóa mềm                                           | Đặt `deleted_at`; luôn giữ row khi booking tham chiếu. Trả 204.               |

Mã `species`: `DOG`, `CAT`, `OTHER`; `size`: `SMALL`, `MEDIUM`, `LARGE`; `sex`: `MALE`, `FEMALE`, `UNKNOWN`. Tên tối đa 100, `age_note` tối đa 100 và mỗi trường ghi chú tối đa 2.000 ký tự Unicode sau trim/NFC. Ngày sinh không được ở tương lai. Ảnh chỉ nhận `photo_media_id` thuộc owner hiện tại và đang `READY`; API này không upload ảnh hay trả URL ảnh.

`Pet::snapshotForBooking()` tạo allowlist version 1 gồm ID, tên, loài, ngày sinh/ghi chú tuổi, giới tính, kích cỡ và ghi chú chăm sóc; không gồm owner ID hoặc media ID. BE-H02 chịu trách nhiệm kiểm tra owner, chỉ nhận pet ACTIVE chưa xóa mềm và lưu kết quả này vào `pet_snapshot_json` lúc tạo booking. Thay đổi hồ sơ về sau không cập nhật snapshot đã lưu.

## M03 — Caregiver profile & draft listings

**Trạng thái triển khai BE-HA02:** đã có tám API quản lý caregiver profile và listing nháp trong `backend/routes/api.php`. Các API yêu cầu đăng nhập, account ACTIVE và role CAREGIVER; tài nguyên được giới hạn theo chủ sở hữu. Listing mới là DRAFT; chỉ DRAFT được sửa/xóa qua API nháp.

Code tại commit `6eb0e3a` đã được kiểm tra local: định dạng và phân tích kiểu đạt; migration/seed đạt; toàn bộ 119 tests đạt với 1.922 assertions. Kết quả này không thay thế CI hoặc xác nhận reviewer trên commit cuối của PR.

API công khai `/api/listings` và `/api/listings/{id}` đã được triển khai trong BE-H03 task 2; kiểm thử HTTP loại DRAFT và các điều kiện công khai đạt theo cập nhật M04 bên trên. Bộ lọc/lịch/giá đầy đủ và nghiệm thu tích hợp toàn BE-H03 vẫn thuộc các task tiếp theo.

### Schema tái sử dụng và kết quả kiểm tra

Tái sử dụng migration `2026_10_28_000002_create_profiles_and_listing_schema` từ BE-T01; không cần migration mới cho contract này.

| Bảng                 | Cột / quan hệ chính                                                                                                                                                                                                              | Ràng buộc đã đối chiếu                                                                                                                                              |
| -------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `caregiver_profiles` | `id`, `user_id`, `type`, `display_name`, `bio`, `area_code`, `deleted_at`, `created_at`, `updated_at`                                                                                                                            | Unique `user_id`, FK tới user với `RESTRICT`; loại `INDIVIDUAL`/`FACILITY`; mã khu vực P0. Không có status riêng.                                                   |
| `listings`           | `id`, `caregiver_profile_id`, `title`, `description`, `service_type`, `area_code`, `price_vnd`, `price_unit`, `capacity`, `acceptance_rules_json`, `status`, `published_at`, `version`, `deleted_at`, `created_at`, `updated_at` | FK tới profile với `RESTRICT`; giá nguyên dương; `capacity=1`; cặp `DAYCARE/HOUR` hoặc `BOARDING/NIGHT`; mặc định `status=DRAFT`, `version=1`, `published_at=null`. |
| `listing_media`      | `listing_id`, `media_object_id`, `sort_order`                                                                                                                                                                                    | PK `(listing_id,media_object_id)`; unique `(listing_id,sort_order)`; FK tới listing/media với `RESTRICT`. Không có cột `id` hoặc timestamps riêng.                  |
| `availability_slots` | `id`, `listing_id`, `start_at`, `end_at`, `created_by_user_id`, `deleted_at`, `created_at`, `updated_at`                                                                                                                         | FK tới listing/user với `RESTRICT`; thời gian `DATETIME(6)`; CHECK `start_at < end_at`.                                                                             |

Quan hệ: user có tối đa một profile; profile có nhiều listing; listing có nhiều slot và nhiều media qua `listing_media`. Profile, listing và slot có xóa mềm. Một profile đã xóa mềm vẫn chịu unique `user_id`; contract này không thêm API tự khôi phục hoặc tạo profile thứ hai.

**Bằng chứng ngày 02/11/2026:** kiểm tra database local `pettel` trên MySQL **8.4.13**; migration trên ở trạng thái **Ran**. Đã đọc `SHOW CREATE TABLE` của đủ bốn bảng và chạy **37 kiểm tra tự động, tất cả PASS**, gồm danh sách cột, mặc định, kiểu/độ dài, xóa mềm, khóa duy nhất, FK `RESTRICT`, bảy CHECK đang `ENFORCED=YES` và migration đã áp dụng. Việc kiểm tra chỉ đọc metadata, không insert/update/delete hoặc chạy lại migration trên database local.

Phạm vi bằng chứng là cấu trúc database. Chưa thử ghi dữ liệu sai, chưa test CRUD, quyền sở hữu hoặc khả năng ẩn DRAFT qua HTTP. Giới hạn nội dung, cấu trúc JSON nhận pet, quyền sở hữu và điều kiện trạng thái vẫn phải được BE-HA02 kiểm tra ở lớp ứng dụng; có CHECK trong DB không thay thế các kiểm tra này.

Lệnh đối chiếu lại, chạy từ thư mục gốc dự án khi backend đang hoạt động:

```powershell
docker compose exec -T backend php artisan migrate:status
docker compose exec -T backend php artisan tinker --execute="dump(DB::select('SHOW CREATE TABLE caregiver_profiles')); dump(DB::select('SHOW CREATE TABLE listings')); dump(DB::select('SHOW CREATE TABLE listing_media')); dump(DB::select('SHOW CREATE TABLE availability_slots'));"
```

### API quản lý riêng của caregiver

Tất cả route dùng `auth:sanctum`, `account.active`, `role:CAREGIVER`; Vue sử dụng cookie/CSRF theo BE-H01. Nhóm `/api/caregiver` dành cho quản lý của chính caregiver, tách với API tìm kiếm công khai `/api/listings`. Role ADMIN không tự thay thế quyền sở hữu trong nhóm route này; moderation thuộc workflow riêng.

| Method / route                        | Mục đích                                                                         | Response thành công                      |
| ------------------------------------- | -------------------------------------------------------------------------------- | ---------------------------------------- |
| `POST /api/caregiver/profile`         | Tạo profile của tài khoản đăng nhập                                              | 201 + profile resource.                  |
| `GET /api/caregiver/profile`          | Xem profile của tài khoản đăng nhập                                              | 200 + profile resource.                  |
| `PATCH /api/caregiver/profile`        | Cập nhật profile của tài khoản đăng nhập                                         | 200 + profile resource.                  |
| `POST /api/caregiver/listings`        | Tạo listing DRAFT thuộc profile của tài khoản đăng nhập                          | 201 + listing resource.                  |
| `GET /api/caregiver/listings`         | Liệt kê listing DRAFT chưa xóa mềm thuộc tài khoản đăng nhập                     | 200; pagination `data`, `links`, `meta`. |
| `GET /api/caregiver/listings/{id}`    | Xem listing chưa xóa mềm thuộc tài khoản đăng nhập, kể cả trạng thái ngoài DRAFT | 200 + listing resource.                  |
| `PATCH /api/caregiver/listings/{id}`  | Sửa listing DRAFT thuộc tài khoản đăng nhập                                      | 200 + listing resource.                  |
| `DELETE /api/caregiver/listings/{id}` | Xóa mềm listing DRAFT thuộc tài khoản đăng nhập                                  | 204, không response body.                |

Chỉ truy vấn profile chưa xóa mềm và listing chưa xóa mềm trong quan hệ của user hiện tại. Listing không tồn tại hoặc thuộc caregiver khác trả 404; không trả chi tiết trạng thái của listing ngoài quyền sở hữu. Kiểm tra quyền sở hữu trước khi trả lỗi trạng thái. Listing thuộc mình nhưng không còn DRAFT trả 409 khi PATCH/DELETE; không dùng API này sửa listing ACTIVE hoặc xử lý change request.

API công khai vẫn chỉ trả listing ACTIVE đủ điều kiện hiển thị theo P0; DRAFT không được xuất hiện trong kết quả công khai hoặc chi tiết cho khách/người không có quyền. Chủ sở hữu đọc DRAFT qua nhóm API quản lý trên.

### Request profile

```json
{
    "type": "INDIVIDUAL",
    "display_name": "Nhà chăm pet của Hào",
    "bio": "Nhận chăm chó và mèo.",
    "area_code": "DN_CENTER"
}
```

| Trường client được sửa | POST                    | Validation                                                  |
| ---------------------- | ----------------------- | ----------------------------------------------------------- |
| `type`                 | Bắt buộc                | `INDIVIDUAL` hoặc `FACILITY`.                               |
| `display_name`         | Bắt buộc                | Chuỗi không rỗng, tối đa 100 ký tự Unicode sau NFC + trim.  |
| `bio`                  | Tùy chọn, mặc định null | Chuỗi hoặc null, tối đa 2.000 ký tự Unicode sau NFC + trim. |
| `area_code`            | Bắt buộc                | `DN_CENTER`, `DN_NORTH`, `DN_SOUTH`, `DN_WEST`, `DN_EAST`.  |

PATCH chỉ sửa các trường được gửi; trường bỏ qua giữ nguyên, chỉ `bio` cho phép null. Không nhận `user_id` từ client để xác định profile. Profile không có trường status. POST khi đã tồn tại profile, kể cả row đã xóa mềm, trả 409 `INVALID_STATE`; không tự cập nhật hoặc khôi phục profile qua POST.

### Request listing DRAFT

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

Tất cả bảy trường cấp ngoài trong ví dụ listing đều bắt buộc khi POST, không nhận null. PATCH chỉ sửa trường được gửi và giữ nguyên trường bỏ qua; nếu gửi `acceptance_rules_json` thì gửi nguyên object v1 hợp lệ, thay thế object cũ, không ngầm merge mảng hoặc key thiếu.

| Trường client được sửa  | Validation                                                                                                                                                                |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `title`                 | Chuỗi không rỗng, tối đa 120 ký tự Unicode sau NFC + trim.                                                                                                                |
| `description`           | Chuỗi không rỗng, tối đa 2.000 ký tự Unicode sau NFC + trim.                                                                                                              |
| `service_type`          | `DAYCARE` hoặc `BOARDING`.                                                                                                                                                |
| `area_code`             | Một trong năm mã khu vực P0 như profile.                                                                                                                                  |
| `price_vnd`             | Số nguyên VND lớn hơn 0, trong phạm vi số nguyên backend hỗ trợ; không nhận số thập phân hoặc tính giá bằng số thực.                                                      |
| `price_unit`            | Chỉ cặp `DAYCARE/HOUR` hoặc `BOARDING/NIGHT`; khi PATCH kiểm tra cặp sau khi áp dụng thay đổi lên dữ liệu hiện tại.                                                       |
| `acceptance_rules_json` | Object v1 theo P0 Contract §8: `schema_version` là integer 1; `species` và `sizes` là mảng không rỗng; mã loài `DOG`, `CAT`, `OTHER`; kích cỡ `SMALL`, `MEDIUM`, `LARGE`. |

Không tự lọc theo tuổi hoặc nhu cầu đặc biệt trong v1. Không nhận ảnh hoặc slot lồng trong request tạo/sửa BE-HA02. BE-T03 tiếp tục quản lý ảnh/lịch trên `listing_media`/`availability_slots` hiện có; không thêm cột `availability` boolean hoặc lưu danh sách ảnh vào JSON listing.

### Trường do server quản lý

| Trường                                         | Nguồn / giá trị                                                                |
| ---------------------------------------------- | ------------------------------------------------------------------------------ |
| Profile `user_id`                              | User đăng nhập.                                                                |
| Listing `caregiver_profile_id`                 | Profile chưa xóa mềm của user đăng nhập.                                       |
| Listing `status`                               | Khi tạo luôn là `DRAFT`; client không được tự đổi trạng thái.                  |
| Listing `capacity`                             | Cố định 1 tại P0.                                                              |
| Listing `version`                              | Khi tạo là 1; server quản lý, client không được sửa.                           |
| Listing `published_at`                         | Khi tạo là null; workflow duyệt quản lý về sau.                                |
| `id`, `created_at`, `updated_at`, `deleted_at` | Hệ thống quản lý; DELETE thực hiện xóa mềm, không nhận `deleted_at` từ client. |

Nếu request chứa các trường hệ thống trên thì trả 422 `VALIDATION_ERROR` tại trường tương ứng, kể cả gửi null hoặc gửi đúng giá trị mặc định. Chỉ ghi các trường thuộc danh sách được phép đã qua validation; không dùng toàn bộ request để tạo/cập nhật model. Quy tắc từ chối trường hệ thống ở M03 không thay quy tắc bỏ qua trường client của endpoint booking.

### Response resource

Profile và listing đơn bọc trong `data`; ID dạng chuỗi, giá VND dạng số nguyên, timestamp ISO 8601 UTC. Ví dụ POST profile trả HTTP 201:

```json
{
    "data": {
        "id": "11",
        "type": "INDIVIDUAL",
        "display_name": "Nhà chăm pet của Hào",
        "bio": "Nhận chăm chó và mèo.",
        "area_code": "DN_CENTER",
        "created_at": "2026-11-02T03:00:00Z",
        "updated_at": "2026-11-02T03:00:00Z"
    }
}
```

Ví dụ POST listing trả HTTP 201:

```json
{
    "data": {
        "id": "101",
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
        },
        "capacity": 1,
        "status": "DRAFT",
        "version": 1,
        "published_at": null,
        "created_at": "2026-11-02T03:00:00Z",
        "updated_at": "2026-11-02T03:00:00Z"
    }
}
```

GET/PATCH trả cùng cấu trúc resource với dữ liệu hiện tại và HTTP 200. Các ID và timestamp trên là minh họa. Không trả user/profile ownership ID, `deleted_at`, media object key hoặc URL storage trong các resource này. Danh sách rỗng trả HTTP 200 với `data: []` và metadata phân trang.

### Bảng lỗi M03

| Tình huống                                              | HTTP | Mã lỗi / chi tiết                                                |
| ------------------------------------------------------- | ---- | ---------------------------------------------------------------- |
| Chưa đăng nhập                                          | 401  | Theo BE-H01.                                                     |
| Account không ACTIVE hoặc thiếu role CAREGIVER          | 403  | Theo BE-H01.                                                     |
| Thiếu/sai/hết hạn CSRF                                  | 419  | Theo BE-H01.                                                     |
| GET/PATCH profile không tồn tại hoặc đã xóa mềm         | 404  | Không trả dữ liệu profile.                                       |
| Listing không tồn tại, đã xóa mềm hoặc thuộc người khác | 404  | Không tiết lộ trạng thái hoặc dữ liệu ngoài quyền sở hữu.        |
| Tạo profile khi đã có profile                           | 409  | `INVALID_STATE`; bao gồm profile đã xóa mềm vì unique theo user. |
| Tạo listing khi chưa có profile chưa xóa mềm            | 409  | `INVALID_STATE`.                                                 |
| PATCH/DELETE listing thuộc mình nhưng không còn DRAFT   | 409  | `INVALID_STATE`.                                                 |
| Dữ liệu không hợp lệ hoặc có trường hệ thống bị cấm     | 422  | `VALIDATION_ERROR`; `details` chỉ ra trường tương ứng.           |

Lỗi nghiệp vụ dùng envelope `error: { code, message, details?, request_id }` chung. Auth/CSRF giữ response của BE-H01 hiện hành, không giả định lớp auth đã có envelope giống lỗi nghiệp vụ. Test HTTP cho các tình huống trên thuộc bước triển khai BE-HA02 tiếp theo; mục này chỉ chốt contract.

# Quy ước request/response

JSON UTF-8, timestamp ISO 8601 có offset/UTC; tiền số nguyên VND. ID dạng chuỗi trong response. Pagination \`data\`, \`links\`, \`meta\`. Thành công tạo tài nguyên trả 201 \+ resource/status hiện tại. Idempotency-Key bắt buộc cho booking và payment create; cùng key+payload trả cùng kết quả, khác payload trả 409\.

Riêng create booking theo quyết định 02/11/2026: lần tạo mới trả **201**; replay cùng owner/key/payload trả **200** với cùng booking và trạng thái hiện tại, giữ nguyên giá/snapshot đã lưu. Đây là contract BE-H02, không tự thay response replay của payment.

# Lỗi thống nhất

Lỗi JSON: `error: { code, message, details?, request_id }`. 400 request lỗi cấu trúc; 401 chưa đăng nhập; 403 không có quyền; 404 không tồn tại/không được lộ; 409 `AVAILABILITY_CONFLICT`, `INVALID_STATE`, `IDEMPOTENCY_CONFLICT`, `REQUEST_EXPIRED` hoặc `PAYMENT_EXPIRED`; 422 `VALIDATION_ERROR` theo field; 429 quá nhiều request; 500 lỗi server. CSRF sai/hết hạn trả 419 theo auth contract. Message dành cho người dùng không chứa stack trace hay dữ liệu riêng.

# Ví dụ hợp đồng booking

POST body: `{pet_id, listing_id, start_at, end_at, instructions}` + header `Idempotency-Key`. Response: `{id, status:"REQUESTED", pet_id, listing_id, start_at, end_at, price_snapshot_vnd, payment_due_at:null}`. Server tự lấy owner từ session Sanctum stateful, giá từ listing; bỏ qua mọi owner_id, status hoặc amount do client gửi. Key unique theo owner, hash payload chuẩn hóa; cùng key/payload trả kết quả cũ, khác payload trả 409 `IDEMPOTENCY_CONFLICT`.

## M05 — Chi tiết create booking đã chốt 02/11/2026

Các quyết định được người dùng đồng ý và ghi trong [P0 Contract §8–11](<../02 — Domain Documentation/05 — P0 Decision Contract.md>). Contract này là đầu vào triển khai/test; không đánh dấu endpoint đã hoạt động chỉ vì tài liệu đã cập nhật.

### Quyền, nguồn dữ liệu và thời gian

- `POST /api/bookings` dùng `auth:sanctum`, `account.active`, `role:OWNER`. Pet phải thuộc owner, `ACTIVE` và chưa xóa mềm. Listing phải `ACTIVE`, chưa xóa mềm và có điều kiện nhận pet hợp lệ theo P0 §8.
- Caregiver profile chưa xóa mềm; user của profile `ACTIVE`, còn role `CAREGIVER`. Server lấy `caregiver_user_id` qua listing/profile. Owner tự đặt listing của mình bị từ chối tại `listing_id`.
- Timestamp bắt buộc có `Z` hoặc offset; lưu UTC, nhận giây lẻ và không cắt giây khi tính thời lượng. Khi tạo mới, `start_at > thời điểm server kiểm tra request` và `start_at < end_at`. DAYCARE 1–12 giờ, cùng ngày Việt Nam; BOARDING 12 giờ–30 ngày. Không thêm giới hạn số ngày được đặt trước.
- DAYCARE tính `ceil(duration_seconds / 3600)`; BOARDING tính `ceil(duration_seconds / 86400)` theo thời lượng thực. Ví dụ 1 giờ 1 giây DAYCARE tính 2 giờ. Quote và create dùng chung công thức, create tính lại trong transaction.
- Khoảng đặt phải nằm trọn trong một slot mở và không giao booking đang khóa lịch. Các `REQUESTED` khác không khóa lịch. Hướng dẫn riêng tối đa 2.000 ký tự sau NFC + trim, lưu riêng với pet notes; allowlist snapshot tại P0 §9 và Database Design §4 M05.

### Chuẩn hóa idempotency payload

1. Kiểm tra header `Idempotency-Key` khớp `\A[a-z0-9._:-]{1,128}\z`: chỉ ASCII chữ thường/số và bốn dấu đã nêu. Không trim, lowercase hoặc sửa key giúp client; sai định dạng trả 422, `details` chỉ ra header `Idempotency-Key`. Key có phạm vi theo owner; client có thể dùng UUID chữ thường.
2. Chỉ lấy năm trường theo thứ tự cố định: `pet_id`, `listing_id`, `start_at`, `end_at`, `instructions`. ID là chuỗi số nguyên dương dạng thập phân chuẩn, không có số 0 dư ở đầu; không chuyển qua số thực làm mất độ chính xác.
3. Timestamp chuẩn hóa về UTC, định dạng cố định `YYYY-MM-DDTHH:mm:ss.ffffffZ` (sáu chữ số microsecond, bổ sung số 0 nếu input không có phần lẻ). Hai offset biểu diễn cùng thời điểm phải có cùng giá trị chuẩn hóa. Không cắt giây/phần lẻ trong phạm vi `DATETIME(6)` của schema.
4. Instructions chuẩn hóa Unicode NFC rồi trim. Trường thiếu, `null` hoặc chuỗi rỗng sau trim cùng biểu diễn bằng JSON `null`. Dữ liệu sai kiểu/định dạng trả 422 trước khi tính hash.
5. Encode object năm trường theo đúng thứ tự trên thành JSON UTF-8 gọn, không escape Unicode hoặc dấu `/`, không chèn khoảng trắng định dạng; SHA-256 của chuỗi JSON là `request_payload_hash`. Giá listing, snapshots và các trường client không có quyền quyết định như `owner_id`, `status`, `amount` không tham gia hash.

Sau auth/quyền của actor và chuẩn hóa payload, tra booking theo owner/key. Nếu đã có booking: khác hash trả 409; cùng hash trả 200 với cùng booking/status hiện tại và dữ liệu đã lưu, không kiểm tra lại giá/pet/listing/thời điểm bắt đầu như một lần tạo mới. Thay đổi dữ liệu nguồn không làm replay thất bại hoặc sửa snapshot. Quyền truy cập và retention/redaction hiện hành vẫn áp dụng; không khôi phục nội dung đã redact.

Chỉ khi chưa có booking mới kiểm tra nguồn dữ liệu/thời gian/slot để tạo `REQUESTED`. Unique `(owner_id,request_idempotency_key)` bảo vệ request đồng thời; nếu request khác vừa tạo cùng key, xử lý lại như replay/conflict. Booking và event tạo trạng thái ghi trong cùng transaction, không tạo event thứ hai khi replay.

### Bảng lỗi tạo booking

| Tình huống                                                                                     | HTTP | Mã lỗi / chi tiết                                                                     |
| ---------------------------------------------------------------------------------------------- | ---- | ------------------------------------------------------------------------------------- |
| Chưa đăng nhập                                                                                 | 401  | Theo auth contract.                                                                   |
| Thiếu role OWNER hoặc account actor không ACTIVE                                               | 403  | Theo auth/role contract.                                                              |
| CSRF thiếu, sai hoặc hết hạn                                                                   | 419  | Theo auth contract.                                                                   |
| Pet không tồn tại, xóa mềm hoặc thuộc owner khác                                               | 404  | Không tiết lộ tài nguyên riêng.                                                       |
| Pet thuộc owner nhưng ARCHIVED                                                                 | 409  | `INVALID_STATE`.                                                                      |
| Listing không tồn tại, xóa mềm hoặc actor không được phép xem                                  | 404  | Không tiết lộ listing ngoài quyền xem.                                                |
| Listing actor được phép xem nhưng không ACTIVE                                                 | 409  | `INVALID_STATE`.                                                                      |
| Listing thấy được nhưng điều kiện JSON sai/không hỗ trợ, hoặc caregiver không còn đủ điều kiện | 409  | `INVALID_STATE`; thông báo chung về listing, không lộ trạng thái tài khoản caregiver. |
| Pet không khớp điều kiện loài/kích cỡ                                                          | 422  | `VALIDATION_ERROR`, tại `pet_id`.                                                     |
| Owner tự đặt listing của mình                                                                  | 422  | `VALIDATION_ERROR`, tại `listing_id`.                                                 |
| Sai định dạng/thời lượng hoặc bắt đầu trong quá khứ/bằng hiện tại                              | 422  | `VALIDATION_ERROR`, tại trường thời gian tương ứng.                                   |
| Không có slot bao trọn hoặc giao booking đang khóa lịch                                        | 409  | `AVAILABILITY_CONFLICT`; không tính các REQUESTED khác là khóa lịch.                  |
| Thiếu/sai định dạng idempotency key                                                            | 422  | `VALIDATION_ERROR`, chi tiết tại header `Idempotency-Key`.                            |
| Cùng owner/key nhưng khác payload chuẩn hóa                                                    | 409  | `IDEMPOTENCY_CONFLICT`.                                                               |

404 về quyền nhìn thấy tài nguyên được xét trước khi trả chi tiết trạng thái/điều kiện của tài nguyên đó. Bảng này bổ sung tình huống cho envelope `error: { code, message, details?, request_id }` chung; không thay các mã lỗi auth đã bàn giao.

## M05 — Accept/reject booking — chốt BE-HA03 ngày 02/11/2026

**Trạng thái:** contract được người dùng đồng ý để triển khai; chưa xác nhận endpoint accept/reject đã tồn tại, test đã đạt hoặc Hoàng đã review. Nguồn chuẩn: [P0 Decision Contract §12](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).

### Quyền, kết quả và thao tác lặp

Contract cho `POST /api/bookings/{id}/accept` và `POST /api/bookings/{id}/reject`: xác thực theo auth contract, actor có quyền caregiver và booking có `caregiver_user_id` trùng user đang đăng nhập. Không lấy caregiver/status/giá/hạn thanh toán từ client. Thành công trả HTTP 200 với object booking theo BookingResource hiện có, không bọc `data`.

| Thao tác / trạng thái | Kết quả |
| --- | --- |
| Accept REQUESTED trước `min(created_at + 24 giờ, start_at)`; listing, slot và lịch hợp lệ | AWAITING_PAYMENT; `payment_due_at = thời điểm accept + 15 phút`; thêm một event USER của caregiver. |
| Accept REQUESTED tại hoặc sau mốc hết hiệu lực, job chưa chuyển trạng thái | 409 REQUEST_EXPIRED; không được nhận booking. |
| Accept từ trạng thái khác, kể cả đã AWAITING_PAYMENT | 409 INVALID_STATE; không gia hạn, không thêm event. |
| Reject REQUESTED hợp lệ | CANCELLED; thêm một event `REQUESTED → CANCELLED`, `reason_code=CAREGIVER_REJECTED`, `source=USER`, actor là caregiver. |
| Reject lặp khi CANCELLED do chính caregiver đó từ chối trước đó | 200 với booking đã lưu; không thêm event, không sửa snapshot. |
| Reject khi đã hủy vì lý do khác hoặc từ trạng thái khác | 409 INVALID_STATE; hủy booking sau accept thuộc luồng cancel/payment. |

Listing ngừng ACTIVE chặn accept nhưng không chặn reject yêu cầu đang chờ hợp lệ. Reject lặp phải được nhận diện bằng trạng thái và event của chính caregiver, không chỉ bằng `status=CANCELLED`; vẫn kiểm tra auth/quyền khi replay. Không thêm booking status ACCEPTED/REJECTED. Accept/reject không thay giá hoặc snapshot đã lưu. Nếu chỉ giá listing đổi, giữ giá cũ; nếu loại dịch vụ hoặc điều kiện nhận pet trọng yếu đổi so với snapshot thì accept trả INVALID_STATE và yêu cầu owner tạo booking mới. Không chỉ dùng thay đổi `listing_version` để kết luận điều kiện trọng yếu đã đổi, vì thay đổi giá đơn thuần vẫn được giữ giá cũ.

### Lịch và tính toàn vẹn

Chỉ xét booking cùng `listing_id`, capacity=1; nhiều listing cùng caregiver chưa chia sẻ công suất. Slot mở chưa xóa mềm phải bao trọn booking, không tự ghép các slot rời. Khoảng `[start_at,end_at)` dùng UTC/microsecond; overlap khi `existing.start_at < incoming.end_at AND existing.end_at > incoming.start_at`; hai khoảng chỉ chạm đầu/cuối không xung đột.

Các trạng thái giữ lịch là AWAITING_PAYMENT, CONFIRMED, IN_PROGRESS, AWAITING_CONFIRMATION; REQUESTED, CANCELLED, COMPLETED không giữ lịch. Không bỏ qua AWAITING_PAYMENT chỉ vì `payment_due_at` đã qua: BE-T04 phải chuyển trạng thái theo cùng quy trình expiry/void/refund trước khi lịch được thả. Quy tắc này phải nhất quán với quote/create/search.

Accept kiểm tra lại quyền, trạng thái/expiry, điều kiện listing/caregiver, slot và overlap trong transaction với khóa chung listing; không chỉ khóa từng booking và không dùng cache làm nguồn quyết định. Booking, hạn thanh toán và event được ghi cùng transaction; reject cũng ghi booking/event cùng transaction. Hai accept đồng thời trên hai REQUESTED xung đột chỉ một thành công, thao tác còn lại trả 409 AVAILABILITY_CONFLICT, booking thua vẫn REQUESTED và không có event accept. Lỗi ghi event phải rollback thay đổi booking.

### Bảng lỗi accept/reject

| Tình huống | HTTP | Mã lỗi / quy tắc |
| --- | --- | --- |
| Chưa đăng nhập | 401 | Theo auth contract hiện có. |
| Account/role không được phép thao tác | 403 | Theo auth contract hiện có. |
| CSRF thiếu, sai hoặc hết hạn | 419 | Theo auth contract hiện có. |
| Booking không tồn tại hoặc thuộc caregiver khác | 404 | NOT_FOUND; không tiết lộ booking ngoài quyền. |
| Chuyển trạng thái không hợp lệ hoặc accept lặp | 409 | INVALID_STATE. |
| Listing ngừng ACTIVE hoặc không còn đủ điều kiện nhận booking khi accept | 409 | INVALID_STATE; không áp điều kiện nhận mới này cho reject hợp lệ. |
| Loại dịch vụ/điều kiện nhận pet trọng yếu thay đổi so với snapshot | 409 | INVALID_STATE; thông báo yêu cầu tạo booking mới, giữ snapshot cũ. |
| Không có slot mở bao trọn hoặc giao booking đang giữ lịch | 409 | AVAILABILITY_CONFLICT. |
| REQUESTED đã hết hiệu lực tại thời điểm accept | 409 | REQUEST_EXPIRED; không phụ thuộc job đã chạy hay chưa. |

Thứ tự xử lý: auth/quyền nhìn thấy booking trước; sau đó nhận diện reject replay và kiểm tra trạng thái; với accept kiểm tra expiry trước listing/điều kiện/slot/overlap. Không trả thông tin trạng thái/lịch hoặc ID của booking xung đột cho người ngoài quyền. Lỗi nghiệp vụ dùng envelope `error: { code, message, details?, request_id }` chung; message phân biệt listing ngừng nhận, trạng thái sai và lịch xung đột. Auth/CSRF giữ response hiện hành.

Các test bắt buộc gồm hai accept đồng thời cùng listing chỉ một thắng, khác listing cùng caregiver không bị chặn vì overlap, sai caregiver trả 404, listing ngừng nhận chặn accept nhưng cho reject hợp lệ, thao tác lặp, ranh giới expiry, slot bao trọn/tiếp giáp, thay đổi giá/điều kiện và rollback booking/event. Checklist: [BE-HA03 — Quy tắc accept/reject và chống trùng lịch](<../05 — Implementation Plan/BE-HA03 — Quy tắc accept-reject và chống trùng lịch.md>).

# Tài liệu API

Dùng Scramble sinh OpenAPI từ routes/FormRequest/Resource phục vụ Vue; review thủ công ví dụ lỗi, quyền, state và idempotency. Giới hạn truy cập tài liệu API ở demo nếu có endpoint nội bộ.

# Contract P0 bổ sung

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) định nghĩa mã trạng thái và giá. `POST /api/bookings/quote` nhận pet_id, listing_id, start_at, end_at; trả price_total_vnd, price_unit, quantity, pricing_version và cancellation_policy_version, không tạo booking. POST booking tính lại cùng công thức trong transaction; không tin amount client. `POST /api/bookings/{id}/cancel` kiểm tra actor/state, chỉ trả CANCELLED sau void/refund sandbox tương ứng. `POST /api/bookings/{id}/messages` chỉ cho hai bên gửi khi booking từ REQUESTED đến AWAITING_CONFIRMATION. Care update chỉ caregiver trong IN_PROGRESS. Endpoint bật role CAREGIVER yêu cầu user đăng nhập, ghi audit; admin role không có route tự cấp. Các lỗi hết hạn dùng HTTP 409 với mã `REQUEST_EXPIRED` hoặc `PAYMENT_EXPIRED`; conflict, invalid state và idempotency conflict cũng trả 409.

## BE-H02 — Ví dụ request/response triển khai

Các ID dưới đây là minh họa; thay bằng ID của fixture thực tế. Timestamp mẫu thuộc tương lai tại thời điểm viết; khi chạy thủ công phải chọn thời gian tương lai nằm trong slot. Phiên đăng nhập là owner ACTIVE có role OWNER, dùng cookie Sanctum và CSRF theo auth contract.

### Quote

```http
POST /api/bookings/quote
Accept: application/json
Content-Type: application/json
```

```json
{
    "pet_id": "101",
    "listing_id": "201",
    "start_at": "2030-01-01T08:00:00+07:00",
    "end_at": "2030-01-01T10:10:00+07:00"
}
```

Listing DAYCARE/HOUR có đơn giá 50.000 VND; thời lượng 130 phút làm tròn thành 3 giờ. Response HTTP 200:

```json
{
    "price_total_vnd": 150000,
    "price_unit": "HOUR",
    "quantity": 3,
    "pricing_version": 1,
    "cancellation_policy_version": 1
}
```

### Create và replay

```http
POST /api/bookings
Accept: application/json
Content-Type: application/json
Idempotency-Key: be-h02-example-001
```

```json
{
    "pet_id": "101",
    "listing_id": "201",
    "start_at": "2030-01-01T08:00:00+07:00",
    "end_at": "2030-01-01T10:10:00+07:00",
    "instructions": "Feed at 09:00 Vietnam time"
}
```

Response HTTP 201 khi tạo mới, object ở gốc, không bọc `data`:

```json
{
    "id": "301",
    "status": "REQUESTED",
    "pet_id": "101",
    "listing_id": "201",
    "start_at": "2030-01-01T01:00:00.000000Z",
    "end_at": "2030-01-01T03:10:00.000000Z",
    "price_snapshot_vnd": 150000,
    "payment_due_at": null
}
```

Gửi lại cùng owner/key/payload chuẩn hóa trả HTTP 200 với cùng ID, trạng thái hiện tại và dữ liệu đã lưu. Không tính lại giá, thay snapshot hoặc thêm event; thay đổi dữ liệu nguồn và thời gian đã qua không biến replay thành lần tạo mới.

### Conflict và validation

Giữ nguyên key nhưng đổi instructions hoặc bất kỳ trường nghiệp vụ nào trong payload chuẩn hóa: HTTP 409.

```json
{
    "error": {
        "code": "IDEMPOTENCY_CONFLICT",
        "message": "This booking request key was used with a different payload.",
        "details": [],
        "request_id": "f39e3629-d503-49d7-93c7-81d74004e495"
    }
}
```

Với key mới, nếu bắt đầu trong quá khứ: HTTP 422.

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Start must be in the future.",
        "details": {
            "start_at": ["Choose a future start time."]
        },
        "request_id": "f39e3629-d503-49d7-93c7-81d74004e495"
    }
}
```

`request_id` trong ví dụ là giá trị minh họa. Các trường hợp 401/403/419/404/409/422 khác theo bảng lỗi M05 ở trên. Auth/CSRF sử dụng response của lớp auth hiện hành, không giả định mọi lỗi auth có cùng envelope với lỗi booking.

Fixture dùng lại, kết quả kiểm tra và xác nhận reviewer: [BE-H02 — Review và bàn giao](<../05 — Implementation Plan/BE-H02 — Review và bàn giao.md>).
