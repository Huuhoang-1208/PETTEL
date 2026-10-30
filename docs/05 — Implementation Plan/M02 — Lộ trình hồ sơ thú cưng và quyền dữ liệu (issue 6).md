# M02 — Lộ trình hồ sơ thú cưng và quyền dữ liệu (issue #6)

**Nguồn:** [GitHub issue #6](https://github.com/Huuhoang-1208/PETTEL/issues/6) · **Mã kế hoạch:** BE-T02 · **Owner:** Thịnh · **Reviewer:** Hào · **Phạm vi:** M02 — Pet Profiles · **Lịch:** N3–N5 · **Timebox:** 8 người-giờ.

**Trạng thái cập nhật 31/10/2026:** model, owner relation, policy, validation, resource và owner-scoped CRUD routes đã được thêm trên schema BE-T01. 5/5 feature test riêng M02 đạt (49 assertions); toàn bộ feature test đạt 20/20 (99 assertions) trên SQLite in-memory. Năm migration ứng dụng BE-T01 đã apply vào MySQL 8.4 trong Docker (batch 2), `migrate:status` xác nhận tất cả đã `Ran`. Chưa review Hào hoặc tích hợp booking writer của BE-H02. M02 không tạo migration mới vì dùng schema hiện có.

## 1. Hợp đồng cần áp dụng

- **Dữ liệu:** một user có nhiều pet. Dùng schema đã có: `pets.user_id`, `name`, `species`, `birth_date` hoặc `age_note` (đều có thể thiếu), `sex`, `size`, `diet_notes`, `vaccination_note`, `temperament`, `special_needs`, `status`, `photo_media_id`, `deleted_at`. `vaccination_note` chỉ là lời khai của owner, không phải chứng nhận y tế. `species` dùng `DOG/CAT/OTHER`; `size` dùng `SMALL/MEDIUM/LARGE` hoặc để trống theo [P0 Decision Contract §7](<../02 — Domain Documentation/05 — P0 Decision Contract.md>). Tên tối đa 100 ký tự Unicode; từng trường pet notes tối đa 2.000 ký tự Unicode, kiểm tra sau chuẩn hóa và trim. Không tự đặt danh mục giới tính hoặc tuổi tối đa khi tài liệu chưa chốt.
- **Vòng đời:** `ACTIVE`/`ARCHIVED`; chỉ pet `ACTIVE` và chưa xóa mềm được dùng cho booking mới. Archive là trạng thái nghiệp vụ; `deleted_at` là xóa mềm/privacy. Không xóa cứng pet có booking; FK booking `RESTRICT`, lịch sử và snapshot không cascade mất theo pet.
- **Quyền:** owner đã xác thực và account `ACTIVE` quản lý, xem đầy đủ pet của mình. User khác không được đọc qua list, ID trực tiếp hoặc `pet_id` trong search/quote/booking. Caregiver chỉ nhận trường cần chăm sóc của **booking liên quan** qua quyền booking và snapshot; không được mở hồ sơ pet hiện tại bằng ID. Admin xem dữ liệu riêng chỉ khi có lý do xử lý và audit theo policy chung. `DELETION_PENDING` không tạo/sửa pet mới; quyền tối thiểu cho booking/report đang mở theo P0 Contract.
- **Ranh giới snapshot:** M02 cung cấp dữ liệu pet theo allowlist cho BE-H02. **Booking** xác thực owner/pet và ghi `pet_snapshot_json` cùng `instructions_snapshot` khi tạo `REQUESTED`; M02 không sửa snapshot khi pet đổi. Snapshot có `schema_version`, chỉ chứa dữ liệu cần phục vụ booking, không dump toàn bộ model. Pet notes trong snapshot được redact theo retention P0: 90 ngày sau booking kết thúc, có ngoại lệ report đang mở; ID/thời gian/giá/trạng thái cốt lõi giữ theo mốc riêng.
- **Ảnh:** ảnh pet là private; `photo_media_id` phải thuộc user sở hữu pet. URL/stream chỉ sau policy, không lưu URL public vĩnh viễn. Upload và purge file tuân theo thiết kế storage; phần upload/thumbnail/purge nền chỉ tích hợp khi hợp đồng media dùng chung sẵn sàng, không mở rộng issue này thành xây toàn bộ M08/media pipeline.
- **API:** JSON UTF-8; ID response là chuỗi; create trả 201; lỗi theo contract chung (401 chưa đăng nhập, 403 sai quyền, 404 không tồn tại/không được lộ, 419 CSRF, 422 validation). Route pet CRUD hiện được liệt kê trong [API Contract](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>); reviewer cần chốt trước khi FE-H01 tích hợp.

## 3. Thứ tự thực hiện trong 8 giờ

| Mốc | Ngân sách | Việc làm | Đầu ra kiểm được |
| --- | ---: | --- | --- |
| N3 — xác nhận đầu vào | 1 giờ | Rà migration BE-T01, auth `account.active`, role OWNER, media contract và dữ liệu cần bàn giao cho booking. | Không có migration trùng; danh sách field cho API owner và snapshot. |
| N3–N4 — model và validation | 2 giờ | Tạo model/service M02 trên schema sẵn có; validate tên, mã loài/kích cỡ, ngày sinh hoặc ghi chú tuổi, giới hạn từng ghi chú; chuẩn hóa/trim đầu vào. | Owner tạo nhiều pet; dữ liệu sai trả 422 theo field; `user_id/status` do server quản lý. |
| N4 — quyền và CRUD | 2 giờ | Thêm PetPolicy và route/controller/resource cho list/detail/create/update/archive/soft delete; scope truy vấn theo owner, chặn IDOR, account state và hard delete. | Owner chỉ đọc/sửa pet của mình; người ngoài không lấy được dữ liệu riêng; pet đã archive/xóa mềm không được chọn đặt mới. |
| N4–N5 — hợp đồng với booking | 2 giờ | Tạo cách lấy dữ liệu snapshot theo allowlist/version cho BE-H02, xác định response caregiver từ booking snapshot; phối hợp fixture pet ACTIVE và case đổi pet sau đặt. | BE-H02 có contract/fixture ổn định; sửa pet không thay hướng dẫn/snapshot booking đã tạo. |
| N5 — kiểm thử, review, bàn giao | 1 giờ | Chạy feature tests cho actor × resource × state, kiểm tra 401/403/404/419/422, archive/soft delete và FK lịch sử; Hào review API, quyền và dữ liệu snapshot. | Ghi lệnh/kết quả test, commit/PR, giới hạn chưa tích hợp; bàn giao cho BE-H02, BE-H03 và FE-H01. |
| **Tổng** | **8 giờ** | | |

Nếu API pet phụ thuộc media chưa sẵn, hoàn thiện CRUD dữ liệu không ảnh và ghi blocker ảnh riêng; không coi ảnh công khai là giải pháp tạm. Nghiệm thu snapshot tích hợp cần BE-H02 thực sự ghi booking, không chỉ unit test dữ liệu trả từ M02.

## 4. Ma trận kiểm chứng tối thiểu

| Tình huống | Kết quả mong đợi |
| --- | --- |
| Guest gọi API pet riêng; owner đổi pet ID trong URL; caregiver dùng pet ID của khách không có booking | Guest 401; ID không thuộc quyền bị chặn 403/404 theo route policy, không lộ nội dung pet. |
| Owner tạo nhiều pet, sửa một pet, archive hoặc xóa mềm | Danh sách đúng owner; trường server quản lý không bị payload ghi đè; pet không ACTIVE/đã xóa mềm không dùng cho booking mới. |
| Species/size sai mã, tên hoặc từng ghi chú quá giới hạn, ngày sinh tương lai | 422 theo field; không lưu dữ liệu không hợp lệ. |
| Pet đã được booking tham chiếu bị xóa mềm | Không hard delete; booking/FK và snapshot vẫn đọc theo quyền booking. |
| Pet hoặc ghi chú được sửa sau khi tạo booking | Bản pet hiện tại đổi; snapshot/hướng dẫn đã gửi không đổi, trừ redaction theo retention. |
| Caregiver của booking A thử mở pet hiện tại hoặc booking B không liên quan | Chỉ thấy snapshot/field cần thiết của booking A theo policy; không có đường đọc hồ sơ live hoặc dữ liệu booking B. |
| `photo_media_id` của user khác, tài khoản `DELETION_PENDING` tạo pet | Bị từ chối; không gán ảnh sai owner hoặc mở dữ liệu mới khi yêu cầu xóa đang xử lý. |

## 5. Bàn giao và giới hạn phạm vi

- **BE-H02 (booking):** nhận fixture pet `ACTIVE`, quy tắc pet thuộc owner, contract snapshot allowlist/version và test sửa pet sau đặt. BE-H02 sở hữu transaction tạo booking, `pet_snapshot_json` và `instructions_snapshot`; không đọc trực tiếp toàn bộ pet để trả cho caregiver.
- **BE-H03 (search/Smart Match):** chỉ dùng pet của owner đang đăng nhập cho filter; không đưa pet notes vào kết quả public. Pet thiếu size không tự match listing giới hạn size.
- **FE-H01:** nhận endpoint/resource pet và lỗi đã review ở Gate BE; UI không quyết định quyền hay trạng thái thay server.
- **Ngoài issue này:** tạo booking/giá/idempotency, listing/search, quy trình xóa tài khoản và retention job, upload/media pipeline hoàn chỉnh. Chỉ ghi điểm tích hợp và chủ sở hữu, không đánh dấu các phần này đã hoàn thành theo M02.

## Tài liệu đối chiếu

- [M02 — Pet Profiles](<../03 — Modules Documentation/M02 — Pet Profiles.md>), [Module Map & Dependencies](<../03 — Modules Documentation/00 — Module Map & Dependencies.md>) và [State Lifecycles](<../02 — Domain Documentation/04 — State Lifecycles — Listing, Order, Payment.md>).
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>), [Database Design §4 M02/M05](<../08 — Architecture & Technical Design/database design.md>) và [BE-T01 schema handoff](<BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ.md>).
- [Authentication & Authorization Design](<../08 — Architecture & Technical Design/05 — Authentication & Authorization Design.md>), [API Contracts](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>), [File Storage Design](<../08 — Architecture & Technical Design/08 — File Storage Design.md>) và [Security & Privacy](<../06 — Non-functional Requirements/01 — Security & Privacy.md>).
- [Backlog 4 tuần](<../PETTEL — Task 4 tuần (A, B, C)/Chia task.md>) và [Kế hoạch 4 tuần](<05 — Kế hoạch 4 tuần — Backend trước, frontend sau.md>).
