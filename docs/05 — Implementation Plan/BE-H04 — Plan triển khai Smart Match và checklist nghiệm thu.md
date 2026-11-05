# BE-H04 — Plan triển khai Smart Match và checklist nghiệm thu

Issue: [#19](https://github.com/Huuhoang-1208/PETTEL/issues/19). Owner: **Hoàng**. Reviewer: **Hào**. Phạm vi: **M04 — Smart Match**. Phụ thuộc: **BE-T02, BE-H03**. Nhánh đề xuất: `codex/4w-be-h04`.

## 1. Mục tiêu và ranh giới

Mở rộng tìm kiếm public listing hiện có để owner có thể dùng pet của mình làm đầu vào Smart Match. Backend kiểm tra quyền pet, đối chiếu điều kiện nhận pet v1, lịch và tổng giá theo các contract đã có; trả giải thích dựa trên những điều kiện thực sự được kiểm tra. Người chưa chọn pet vẫn được lọc thủ công. Kết quả không tiết lộ hồ sơ pet hoặc ghi chú riêng.

Tái sử dụng schema và API pet của BE-T02; route và query search, điều kiện public listing, lịch, giá và phân trang của BE-H03; `ApplyListingAvailability` và `CalculateBookingPrice` đã dùng cho search/booking. Không tạo migration nếu không phát hiện thiếu dữ liệu nền có căn cứ. Không triển khai UI: `frontend/` chưa được scaffold theo README hiện tại.

Theo quyết định P0 ngày 02/11/2026, tự động đối chiếu chỉ gồm `species` và `size` theo `acceptance_rules_json` v1. Chưa lọc theo tuổi/nhu cầu đặc biệt và không tuyên bố đã kiểm tra khả năng hỗ trợ. Không tạo điểm tin cậy, khẳng định AI, hoặc badge xác minh. Pet notes vẫn thuộc snapshot booking và luồng caregiver có quyền; không thêm chúng vào kết quả tìm kiếm.

## 2. Nguồn chuẩn và nguyên tắc đóng task

- [Issue #19](https://github.com/Huuhoang-1208/PETTEL/issues/19) là nguồn scope, phụ thuộc và owner/reviewer.
- [M04 — Discovery, Search & Smart Match](<../03 — Modules Documentation/M04 — Discovery, Search & Smart Match.md>) mô tả logic sản phẩm và tiêu chí gợi ý.
- [P0 Decision Contract §8](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) là nguồn chuẩn cho điều kiện nhận pet v1.
- [API Contracts — M04](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) cùng mã nguồn H03 là nguồn chuẩn cho search công khai, lỗi, lịch, giá và phân trang.
- [BE-T02 — Pet Profiles](<M02 — Lộ trình hồ sơ thú cưng và quyền dữ liệu (issue 6).md>) là nguồn chuẩn cho owner scope, trạng thái pet và dữ liệu riêng.

Chỉ đánh `[x]` khi mọi tiêu chí task đạt và có bằng chứng trong code/test hoặc quyết định/contract tương ứng. Quyết định API mới được ghi rõ là quyết định H04, không diễn giải đề xuất trong plan thành contract đã chốt. Test phải chạy trên database test riêng; ghi lệnh, kết quả, commit bàn giao và CI. Không suy ra reviewer sign-off từ việc test pass.

## 3. Baseline hiện tại

- `GET /api/listings` đã hỗ trợ lọc thủ công theo từ khóa, dịch vụ, khu vực, loài, kích cỡ, thời gian và cận tổng giá; lọc/sắp xếp trước phân trang. `pet_id` hiện là tham số không hỗ trợ và trả 422.
- Search chỉ dùng listing ACTIVE và caregiver/profile hợp lệ; response public dùng resource allowlist.
- `ApplyListingAvailability` kiểm tra slot mở bao trọn khoảng yêu cầu và booking đang khóa lịch. `CalculateBookingPrice` là công thức giá được booking dùng.
- Pet routes yêu cầu owner đăng nhập, tài khoản active, role OWNER; truy vấn pet nằm trong phạm vi owner. Pet có trạng thái ACTIVE/ARCHIVED và soft delete. `StoreBookingRequest` đã kiểm tra quyền pet, trạng thái, điều kiện listing và cấm tự đặt.
- Chưa có hợp đồng H04 cụ thể cho query `pet_id`, xung đột giữa pet với bộ lọc thủ công, pet thiếu size trong search, mã lỗi của nhánh H04, schema lý do phù hợp và thông điệp kết quả rỗng. Các mục này phải chốt ở Task 01.
- README ghi Vue SPA chưa scaffold; issue #19 là backend. Không biến scope H04 thành công việc frontend.

## 4. Thứ tự thực hiện

| Thứ tự | Task | Đầu ra |
| ---: | --- | --- |
| 01 | Xác nhận baseline và chốt contract H04 | Decision log/API behavior rõ ràng |
| 02 | Đọc pet đúng quyền và xử lý đầu vào `pet_id` | Nguồn pet an toàn cho request search |
| 03 | Đối chiếu điều kiện nhận pet v1 dùng chung | Quy tắc species/size/schema nhất quán |
| 04 | Tích hợp Smart Match vào search | Kết quả dùng pet và bộ lọc thủ công |
| 05 | Lý do phù hợp và kết quả rỗng | Giải thích có căn cứ, không suy diễn |
| 06 | Rà quyền riêng tư và allowlist | Không rò pet/snapshot/PII |
| 07 | Kiểm thử hành vi và tích hợp | Bộ kiểm thử H04 trên contract cuối |
| 08 | Tài liệu, hồi quy và bàn giao | Báo cáo có bằng chứng trên commit cuối |

## A. Contract và nguồn pet

- [x] **01 — Xác nhận baseline và chốt contract H04.**

  **Việc làm:** kiểm tra lại branch/HEAD/working tree; đối chiếu API pet BE-T02 và search H03 trong code hiện tại. Ghi request/response Smart Match, quy tắc lọc thủ công kết hợp pet, nhánh lỗi, trạng thái pet được dùng, hành vi khi pet thiếu size, lý do phù hợp và kết quả rỗng. Chốt rõ lựa chọn: endpoint search hiện tại có mở rộng auth tùy chọn theo `pet_id` hay dùng endpoint riêng; quy ước lỗi cho guest/owner/pet không hợp lệ; xử lý `species`/`size` thủ công mâu thuẫn pet. Xác nhận có/không loại listing của chính owner khỏi kết quả match để tương thích điều kiện booking cấm self-booking.

  **Tiêu chí hoàn thành:** contract có ví dụ request/response và mã HTTP/error cho các nhánh; chỉ rõ guest vẫn tìm thủ công; pet chỉ được dùng sau khi xác thực owner; mọi điểm chưa chốt ở §3 có quyết định hoặc được ghi là ngoài scope có lý do. Không đổi contract BE-T02/H03/P0 ngoài phạm vi H04 nếu chưa ghi nhận tác động và quyết định tương ứng.

  **Bằng chứng:** decision log H04 và cập nhật API Contract/M04 khi cần. **Phụ thuộc:** không.

- [x] **02 — Đọc pet đúng quyền và xử lý đầu vào `pet_id`.**

  **Việc làm:** mở rộng request validation để nhận dạng `pet_id` theo contract. Truy vấn pet gắn với owner đang đăng nhập, đồng thời áp dụng điều kiện account active/role OWNER và trạng thái pet đã chốt. Giữ bộ lọc thủ công hợp lệ khi không có `pet_id`. Không nhận `species`/`size` từ client như dữ liệu của pet đã chọn.

  **Tiêu chí hoàn thành:** pet không tồn tại/không thuộc caller/đã soft-delete không làm lộ sự tồn tại; pet archive và thiếu size đi đúng hành vi đã chốt; request không có pet tiếp tục dùng H03; query sai/field ngoài allowlist trả envelope 422 theo convention. Không thêm caregiver route đọc live pet.

  **Bằng chứng:** feature tests cho guest, role/account, pet cùng chủ/khác chủ, soft-delete, archive và thiếu size. **Phụ thuộc:** 01, BE-T02.

## B. Matching, lý do và quyền riêng tư

- [x] **03 — Đối chiếu điều kiện nhận pet v1 dùng chung.**

  **Việc làm:** tạo/reuse một quy tắc nghiệp vụ để xác nhận `acceptance_rules_json` hợp lệ (schema version integer 1; species/sizes là list không rỗng với mã P0), rồi đối chiếu pet phải thỏa cả species lẫn size. Dùng cùng semantics với luồng booking; listing cấu hình hỏng/không hỗ trợ không được xem như nhận mọi pet. Tránh sao chép một bộ validator khác với search hoặc booking.

  **Tiêu chí hoàn thành:** match đúng khi cả hai điều kiện khớp; loại khi lệch một điều kiện, pet thiếu size, rules thiếu key/sai kiểu/sai mã/version; `OTHER` chỉ khớp khi khai báo rõ. Không lọc hoặc đưa ra kết luận hỗ trợ theo tuổi/nhu cầu đặc biệt. Không để record JSON sai làm lỗi toàn bộ search ngoài hành vi đã định ở Task 01.

  **Bằng chứng:** unit/feature cases cho mỗi điều kiện và so khớp với booking validator. **Phụ thuộc:** 01, 02.

- [x] **04 — Tích hợp Smart Match vào search.**

  **Việc làm:** gắn điều kiện pet vào query pipeline hiện có, giữ quy tắc public listing, kết hợp AND với bộ lọc thủ công đã thống nhất. Với thời gian, tái sử dụng `ApplyListingAvailability`; với giá, tái sử dụng `CalculateBookingPrice` và thứ tự giá tổng/ID H03. Áp dụng lọc trước phân trang. Bảo đảm listing self-owned được xử lý theo quyết định Task 01.

  **Tiêu chí hoàn thành:** kết quả chỉ gồm listing public thỏa mọi điều kiện đã chọn; pet filter không làm rò listing ẩn; không tính giá hoặc tuyên bố còn lịch khi request thiếu khoảng thời gian; tổng giá và thứ tự giống quy tắc quote/search hiện có; phân trang giữ query; kết quả không tạo giữ chỗ và booking tiếp tục kiểm tra lại dữ liệu.

  **Bằng chứng:** integration tests đối chiếu pet, bộ lọc thủ công, giá/lịch, public states, self-booking và pagination. **Phụ thuộc:** 02, 03; BE-H03.

- [x] **05 — Trả lý do phù hợp và giải thích kết quả rỗng.**

  **Việc làm:** theo schema response đã chốt, trả mã/giải thích ngắn cho các điều kiện thực sự đã kiểm: loài, kích cỡ, lịch và tổng giá. Phân biệt “đạt điều kiện” với “chưa có dữ liệu để kiểm tra” khi caller chưa chọn thời gian/ngân sách. Quy định nội dung trạng thái không có kết quả và gợi ý nới bộ lọc chỉ dựa trên input, không bịa listing hoặc lý do loại trừ chưa được truy vấn xác nhận.

  **Tiêu chí hoàn thành:** mỗi lý do có thể truy về phép kiểm tương ứng; lý do lịch/giá chỉ xuất hiện khi ngữ cảnh đủ; kết quả rỗng và trang rỗng có metadata đúng; không tuyên bố AI, tuổi/nhu cầu đặc biệt đã được hỗ trợ hoặc listing đã xác minh.

  **Bằng chứng:** resource/API tests cho từng tổ hợp có/thiếu ngày, giá, pet và kết quả rỗng. **Phụ thuộc:** 01, 04.

- [x] **06 — Rà quyền riêng tư và allowlist.**

  **Việc làm:** kiểm tra resources, error envelope, log/response và query mới sau khi dùng pet; giới hạn kết quả theo allowlist listing + lý do. Không serialize Pet model, PetResource, snapshot booking, caregiver contact/address hoặc nội dung nội bộ. Kiểm tra response public không chứa pet notes.

  **Tiêu chí hoàn thành:** không có tên pet hoặc `birth_date`, `age_note`, `diet_notes`, `vaccination_note`, `temperament`, `special_needs` trong search response/error; không đọc pet của user khác; không cấp badge/claim xác minh; caregiver chỉ tiếp tục nhận pet care data trong snapshot booking theo quyền và retention hiện hành.

  **Bằng chứng:** test so sánh key allowlist và sentinel private notes trong response 200/4xx; rà soát log không ghi nội dung pet. **Phụ thuộc:** 02, 04, 05.

## C. Nghiệm thu và bàn giao

- [x] **07 — Kiểm thử hành vi và tích hợp H04.**

  **Việc làm:** viết/chạy test theo contract cuối, tập trung ma trận owner × pet state × listing condition × lịch/giá; giữ hồi quy H03 và booking. Chạy trên DB test riêng, migration/seed đúng runner dự án; không dùng DB demo/production cho test phá dữ liệu.

  **Tiêu chí hoàn thành:** tất cả ca ở Task 02–06 có test; thêm ca khớp species/size, lệch một điều kiện, JSON không hợp lệ, thiếu size, không kết quả, lọc thủ công khi không pet, và tương quan Smart Match với quote/booking. Pint, PHPStan và PHPUnit pass trên working tree bàn giao; `git diff --check` sạch. Lưu lệnh và kết quả thực tế, không sao chép bằng chứng từ commit khác.

  **Bằng chứng:** `SmartMatchTest` (10 ca, 59 assertions) cùng hồi quy PHPUnit; full isolated Docker test runner: Pint 128 files PASS, PHPStan 87 files không lỗi, migrations + DemoSeeder PASS, PHPUnit **306 passed (3,591 assertions)**; `git diff --check` sạch. Chưa có commit SHA vì thay đổi đang ở working tree. **Phụ thuộc:** 02–06.

- [ ] **08 — Đồng bộ tài liệu, review và bàn giao.**

  **Việc làm:** cập nhật API Contract, M04 và/hoặc P0 traceability nếu hành vi được chốt/thay đổi; ghi implementation, giới hạn, CI, commit/PR và kết quả reviewer. Đối chiếu các mô tả trạng thái cũ với code hiện tại, chỉ cập nhật phần liên quan H04 và không coi tài liệu cũ là bằng chứng runtime.

  **Tiêu chí hoàn thành:** issue checklist, API contract, tài liệu module và code nhất quán; PR liên kết #19; CI thành công trên head SHA cuối; Hào review xác nhận quyền, lý do, riêng tư và tích hợp; mọi checkbox chỉ đánh hoàn thành khi kèm bằng chứng. Ghi rõ phần frontend không thuộc issue backend nếu UI chưa được scaffold.

  **Bằng chứng:** link PR/CI, SHA cuối, reviewer sign-off, decision log và bảng test trong báo cáo H04. **Phụ thuộc:** 01–07.

## 5. Ma trận nghiệm thu issue #19

| Tiêu chí issue | Bằng chứng cần có |
| --- | --- |
| Gợi ý theo species/size của pet hoặc lọc thủ công khi chưa chọn pet | API/feature tests; pet phải thuộc owner và match đủ hai điều kiện |
| Dùng lịch và tổng giá | H04 gọi logic lịch/giá hiện có; kết quả khớp search/quote; lọc trước phân trang |
| Trả lý do phù hợp | Response có lý do theo phép kiểm thực; không suy diễn khi thiếu dữ liệu |
| Kiểm tra quyền pet | Ma trận guest/owner khác chủ/account/role/pet state, không rò tài nguyên |
| Có ca không tìm được kết quả | HTTP 200 và `data: []` theo contract; metadata/trạng thái không đánh đồng trang rỗng |
| Không tuyên bố AI/Verified | API/resource/UI-facing copy không phát sinh claim hoặc badge |
| Không trả ghi chú pet công khai | Allowlist và sentinel test chứng minh không có pet notes/snapshot trong response hoặc error |
| Hoàn thành engineering handoff | Pint/PHPStan/PHPUnit/CI trên SHA cuối, PR và reviewer sign-off được ghi nhận |

## 6. Decision log H04

| Mục cần chốt trước khi code | Trạng thái | Ghi chú |
| --- | --- | --- |
| Dùng optional-auth trên `GET /api/listings` có `pet_id` hay endpoint riêng | Đã chốt và triển khai | Dùng endpoint hiện có; không có `pet_id` vẫn public, có `pet_id` yêu cầu owner hợp lệ |
| HTTP/error cho guest, account/role sai, pet không tồn tại/không thuộc owner, pet archived | Đã chốt và triển khai | Guest 401; account/role không đạt 403; pet không tồn tại/khác chủ/xóa mềm 404 đồng nhất; archived 409; dữ liệu thiếu/xung đột 422 |
| Pet thiếu size | Đã chốt và triển khai | Không auto-match; trả 422 để owner cập nhật hồ sơ |
| Pet filter và species/size filter thủ công mâu thuẫn | Đã chốt và triển khai | Bộ lọc trùng giá trị được chấp nhận; mâu thuẫn trả 422 ở trường tương ứng |
| Listing của owner trong Smart Match | Đã chốt và triển khai | Loại listing do owner hiện tại quản lý khỏi kết quả pet match để phù hợp cấm self-booking |
| Shape của lý do và trạng thái empty | Đã chốt và triển khai | Search item có reason codes; rỗng trả 200/data rỗng và `match_summary`; không suy diễn listing nào bị loại vì sao |
| Tuổi/nhu cầu đặc biệt/Verified/AI/private notes | Đã chốt giới hạn | Không auto-filter, không claim hỗ trợ, không badge, không public pet notes |

Các quyết định trên là lựa chọn contract BE-H04 được ghi cùng API Contract và kiểm thử. Task 08 vẫn mở cho PR/CI trên head cuối và reviewer sign-off; các bước đó cần được thực hiện ở quy trình review của nhóm.

## 7. Trạng thái bàn giao working tree

- Hồ sơ kết quả và bảng link bằng chứng: [BE-H04 — Evidence nghiệm thu](<BE-H04 — Evidence nghiệm thu.md>); log runner đã chuẩn hóa để bỏ mã màu/whitespace: [BE-H04 — Evidence nghiệm thu.log](<BE-H04 — Evidence nghiệm thu.log>).
- Task 01–07 đã hoàn tất và được lưu trong commit local; staged diff đã qua `git diff --cached --check`.
- Kiểm tra cuối được chạy bằng `docker compose -p pettel-be-h04-evidence -f compose.test.yaml run --build --rm test` trên DB test riêng; output được lưu trong file log liên kết ở trên: Pint 128 files PASS; PHPStan 87 files không lỗi; migrations và DemoSeeder PASS; PHPUnit **306 passed, 3,591 assertions**.
- Feature suite Smart Match có 10 ca, 59 assertions, bao gồm quyền owner/pet state, xung đột filter, matching, reason codes, kết quả rỗng và privacy regression.
- Commit triển khai local `807785ebf2560aff6362133eb71af74be75b963a` được tạo trên nhánh `be-t03` và ghi trong [hồ sơ evidence](<BE-H04 — Evidence nghiệm thu.md>). Không push theo chỉ dẫn người dùng. Chưa có PR, CI từ GitHub hoặc reviewer sign-off; do đó Task 08 vẫn mở cho đến khi các bước review nhóm này được thực hiện.
