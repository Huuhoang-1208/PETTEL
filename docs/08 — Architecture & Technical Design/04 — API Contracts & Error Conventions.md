# API Contracts & Error Conventions

PETTEL — Architecture & Technical Design \| Hợp đồng JSON cho Vue SPA; Filament dùng cùng service/quy tắc nghiệp vụ.

# Endpoint P0

Breeze API scaffold cung cấp route đăng ký/đăng nhập/đăng xuất/khôi phục mật khẩu; Vue lấy `/sanctum/csrf-cookie` trước thao tác auth. Route riêng tư dùng `auth:sanctum`; không phát hành bearer token cho SPA.

GET /api/listings?service_type=\&area_code=\&start_at=\&end_at=\&pet_id= — public filters; pet_id chỉ khi owner đăng nhập. GET /api/listings/{id} — chỉ ACTIVE hoặc owner/admin có quyền. POST /api/bookings — pet_id, listing_id, start_at, end_at, instructions, Idempotency-Key. POST /api/bookings/{id}/accept — caregiver; POST /api/bookings/{id}/cancel — actor được phép; POST /api/bookings/{id}/sandbox-payments — owner. POST /api/sandbox/callback — chỉ adapter sandbox. POST /api/bookings/{id}/updates và /confirm-completion; POST /api/reports.

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

API công khai `/api/listings` chưa được triển khai. Kiểm thử tích hợp loại DRAFT khỏi danh sách/chi tiết công khai được bàn giao BE-H03.

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
