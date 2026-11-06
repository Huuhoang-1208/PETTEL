# BE-T04 — Phân tích issue #20 và tiêu chí hoàn thành

Nguồn: [issue #20 — Giữ chỗ, hết hạn và hủy booking](https://github.com/Huuhoang-1208/PETTEL/issues/20). Owner: **Thịnh**. Reviewer: **Hoàng**.

Baseline đọc code: `2facfc6a6d2e4869b2a03a788d0c6e4b34b2ac23` (`origin/main`, đã gồm BE-T03). Nhánh làm việc: **`be-t04`**, tạo từ baseline này vì chưa có nhánh cùng tên ở local/remote. BE-H04 chưa nằm trong baseline main; không lấy các commit Smart Match vào phạm vi T04.

Tài liệu này ghi contract, implementation và bằng chứng T04. T04 được chuẩn bị để bàn giao review; adapter/callback runtime vẫn thuộc BE-HA05 và phải có bằng chứng tích hợp riêng. Không thêm ngân sách thời gian.

## Checklist theo dõi và mức độ hoàn thành

**Trạng thái hiện tại:** phạm vi implementation T04 hoàn tất (**100%**); bộ kiểm tra và toàn bộ backend tests đã pass. Bàn giao chính thức còn chờ Hoàng review. Tích hợp payment sandbox/callback là đầu việc BE-HA05, không tính vào phần trăm T04.

### T04 — việc thuộc phạm vi hiện tại

- [x] Chốt và ghi contract cancel/expiry, reason codes, idempotency, error/status, retry, quyền và lock order.
- [x] Thêm cancel API cho owner/caregiver đúng booking; kiểm tra trạng thái, quyền, key và replay.
- [x] Dùng chung cancellation coordinator cho cancel và expiry; giữ reject REQUESTED theo luồng riêng.
- [x] Thêm expiry cho REQUESTED và AWAITING_PAYMENT; chạy theo lịch mỗi phút, batch tối đa 100.
- [x] Lưu cancellation operation/outbox bền vững; retry tối đa 5 lần với backoff 1/2/4/8/16 phút, sau đó đưa vào manual review.
- [x] Thêm admin queue/retry có audit và giữ cùng gateway operation key.
- [x] Giữ `payment_due_at`, current attempt và snapshot lịch sử; không thả lịch trước khi điều kiện payment đạt.
- [x] Bổ sung feature tests cho cancel, expiry, replay, quyền, lỗi payment và retry.
- [x] Cập nhật P0/API contract và kế hoạch T04; kiểm tra `git diff --check`.
- [x] Chạy `composer ci:check` và full backend suite: **308 passed, 3,627 assertions**.
- [x] Tạo bằng chứng local trong [`docs/evidence/t04-handoff.md`](../evidence/t04-handoff.md); thư mục được ignore trong `.gitignore`.
- [ ] Hoàng review code/contract và ghi nhận kết quả review. **Đang chờ reviewer.**

### BE-HA05 — phần phụ thuộc, chưa hoàn thành trong T04

- [ ] Bind gateway adapter thật vào `PaymentCancellationGateway`; không dùng test double làm adapter runtime.
- [ ] Tích hợp sandbox void/refund và callback theo cùng lock order `booking → payment_attempt`.
- [ ] Kiểm chứng timeout, callback đến trễ/trùng, retry sau gateway thành công nhưng DB lỗi, và đối soát trạng thái attempt.
- [ ] Lưu bằng chứng end-to-end sandbox riêng trước khi đánh dấu payment integration hoàn tất.

**Cách đọc trạng thái:** các ô đã đánh dấu là T04 đã làm và có thể kiểm tra trong code/tests/docs; ô review là điều kiện để đóng bàn giao review; các ô BE-HA05 là phần còn lại của dự án, không phải thiếu sót implementation T04.

## 1. Phạm vi đã xác nhận

- Xử lý hết hạn và hủy booking trong M05, cho owner/caregiver liên quan và tác vụ hệ thống.
- Giữ hạn thanh toán 15 phút đã có từ accept; retry không gia hạn.
- Tự xử lý booking chờ thanh toán quá hạn; P0 còn yêu cầu xử lý REQUESTED hết hiệu lực.
- Chỉ thả lịch khi hoàn tất chuyển trạng thái hợp lệ; payment cần void/refund thì phải thành công trước.
- Hủy/job chạy lặp hoặc chạy đồng thời không được tạo thêm chuyển trạng thái, event hoặc tác động payment.
- Luồng thường không hủy khi đang chăm; CONFIRMED chỉ hủy trước `start_at`.
- Chuẩn bị tích hợp payment BE-HA05; không tự mở rộng sang toàn bộ callback/checkout/completion, tiền thật hoặc frontend.

**Người dùng đã xác nhận ngày 06/11/2026:** giữ phạm vi theo issue #20. T04 sở hữu expiry/cancel và hợp đồng phối hợp void/refund; BE-HA05 sở hữu implementation payment sandbox/callback. Test double có thể chứng minh cách T04 xử lý thành công/thất bại, nhưng không chứng minh tích hợp payment thực tế đã hoàn tất.

## 2. Ba to-do trong issue so với dự án hiện tại

| To-do issue | Đã có trong code | Khoảng trống thực tế |
|---|---|---|
| Ghi thời điểm hết hạn khi accept; scheduler hủy chờ thanh toán quá hạn | [AcceptBooking](../../backend/app/Domain/Booking/Actions/AcceptBooking.php) đặt `payment_due_at = acceptedAt + 15 phút`, tăng version và ghi event cùng transaction. [BookingAcceptanceTest](../../backend/tests/Feature/BookingAcceptanceTest.php) có kiểm tra hạn, accept lặp và rollback. | Chưa có tác vụ expiry booking; [console.php](../../backend/routes/console.php) chỉ schedule heartbeat ở local. Chưa có xử lý REQUESTED → CANCELLED khi hết hiệu lực. |
| Hủy theo actor/trạng thái/chính sách; chuẩn bị void/refund | [RejectBooking](../../backend/app/Domain/Booking/Actions/RejectBooking.php) chỉ xử lý caregiver từ chối REQUESTED và replay đúng lần từ chối. Schema đã có attempts/events/current attempt. | Chưa có `POST /api/bookings/{id}/cancel` trong [api.php](../../backend/routes/api.php). Chưa có action hủy owner/AWAITING_PAYMENT/CONFIRMED. Chưa có Payment domain/gateway runtime. |
| Test job chạy lại, hủy lặp, sai trạng thái và thả lịch | Có tests accept/reject/concurrency. [PublicListingAvailabilityTest](../../backend/tests/Feature/PublicListingAvailabilityTest.php) chứng minh hold quá hạn vẫn chặn cho đến CANCELLED. | Test availability hiện tự sửa status thành CANCELLED, chưa gọi expiry/cancel thực tế. Chưa có tests scheduler, cancel replay, gateway lỗi hay race callback/cancel/expiry của T04. |

**Không cần viết lại phần accept đã có.** Phải giữ hồi quy và bổ sung luồng còn thiếu. Các test hiện có là bằng chứng về nội dung coverage đang tồn tại; lượt phân tích này chưa chạy lại chúng và không khẳng định chúng đã pass trên baseline mới.

## 3. Contract bắt buộc từ tài liệu P0

Nguồn chuẩn: [P0 Decision Contract §1–3, §11](<../02 — Domain Documentation/05 — P0 Decision Contract.md>), [M05](<../03 — Modules Documentation/M05 — Booking & Availability.md>), [API Contract](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>) và [Payment Sandbox Integration](<../08 — Architecture & Technical Design/07 — Payment Sandbox Integration.md>).

| Trạng thái/điều kiện | Ai/hành vi hợp lệ | Payment và lịch phải ra sao |
|---|---|---|
| REQUESTED còn hiệu lực | Owner hủy; caregiver từ chối qua contract reject hiện có | Chưa có attempt/hold; một lần chuyển CANCELLED và một event tương ứng. |
| REQUESTED tại hoặc sau `min(created_at + 24 giờ, start_at)` | Hệ thống hết hạn; accept phải tiếp tục trả REQUEST_EXPIRED dù job chưa chạy | CANCELLED với reason REQUEST_EXPIRED; không có hold để thả. Đây là yêu cầu suy ra từ P0, không được bỏ chỉ vì issue nhấn mạnh chờ thanh toán. |
| AWAITING_PAYMENT trước hạn | Owner hoặc caregiver liên quan hủy | Không có attempt hoặc current attempt FAILED: không cần void; current attempt PENDING: chỉ CANCELLED sau VOIDED thành công. |
| AWAITING_PAYMENT tại hoặc sau `payment_due_at` | Hệ thống hết hạn | Cùng điều kiện payment an toàn như cancel; retry không đổi deadline. Chưa hoàn tất void thì giữ trạng thái cũ và tiếp tục chặn lịch. |
| CONFIRMED và `now < start_at` | Owner hoặc caregiver liên quan hủy | AUTHORIZED → REFUNDED thành công rồi mới CANCELLED/thả lịch. |
| CONFIRMED tại hoặc sau start_at | Không hủy bằng luồng thường | Không được dựa riêng vào status CONFIRMED để cho hủy khi tác vụ bắt đầu chăm chưa cập nhật status. |
| IN_PROGRESS / AWAITING_CONFIRMATION | Không hủy bằng luồng thường | Giữ trạng thái/payment/lịch; ngoại lệ admin thuộc contract riêng. |
| COMPLETED / CANCELLED | Không tạo chuyển trạng thái hủy mới | Replay hợp lệ phải không tạo thêm event/version/payment operation. Quy tắc nhận diện replay cần chốt rõ; không suy ra mọi CANCELLED đều là replay cùng yêu cầu. |

Các trạng thái giữ lịch hiện có là AWAITING_PAYMENT, CONFIRMED, IN_PROGRESS, AWAITING_CONFIRMATION. [ApplyListingAvailability](../../backend/app/Domain/Booking/Actions/ApplyListingAvailability.php) dùng cùng tập trạng thái cho search/accept; create/quote kiểm tra cùng nghiệp vụ. Không thêm trạng thái EXPIRED nếu chưa có quyết định thay schema/contract.

## 4. Từng task cần làm và tiêu chí hoàn thành

Các mục dưới đây là tiêu chí nghiệm thu chi tiết; trạng thái hiện tại được đánh dấu trong checklist đầu tài liệu.

### T04-01 — Chốt contract expiry/cancel và phần giao payment

**Việc cần làm:** ghi actor/state matrix, request/response cancel, mã lỗi, replay, reason code, mốc thời gian, kết quả gateway và trách nhiệm T04/HA05. Giữ reject hiện có, không dùng reject sau accept để né void/refund.

**Hoàn thành khi:** có ví dụ API và bảng nhánh 200/401/403/404/409/422 cùng lỗi adapter đã thống nhất; xác định hủy lặp nào trả 200, hủy từ lý do khác xử lý thế nào; quy định SYSTEM event không giả actor user; người ngoài quyền không thấy booking. Những điểm chưa chốt ở §7 có quyết định. API/M05/P0 traceability khớp nhau.

### T04-02 — Hết hạn REQUESTED và AWAITING_PAYMENT

**Việc cần làm:** dùng dữ liệu hạn hiện có; quét booking cần xử lý, đọc lại state/deadline dưới khóa trước khi ghi; áp dụng quy tắc payment ở §3. Không sửa snapshots hoặc gia hạn khi job retry.

**Hoàn thành khi:** trước deadline không đổi; đúng deadline và sau deadline xử lý hợp lệ; REQUESTED dùng mốc sớm hơn trong hai mốc P0; job không hủy booking đã sang CONFIRMED/terminal sau khi được chọn để xử lý. Mỗi chuyển trạng thái chỉ tăng version một lần và ghi một event; chạy lại không thêm gì. Lỗi một booking không làm mất khả năng xử lý các booking còn lại.

### T04-03 — Hủy từ owner/caregiver đúng quyền

**Việc cần làm:** bổ sung cancel API và nghiệp vụ theo actor/state; xác thực booking participant qua IDs đã lưu, không lấy quyền từ body. Hủy nghĩa vụ cũ không được phụ thuộc listing vẫn ACTIVE hoặc pet vẫn đang dùng.

**Hoàn thành khi:** owner/caregiver đúng booking mới có quyền; guest bị chặn; người ngoài nhận lỗi không tiết lộ tài nguyên; có tests account/role theo contract cuối. CONFIRMED kiểm `now < start_at` sau khi lấy khóa. IN_PROGRESS/AWAITING_CONFIRMATION/COMPLETED không đổi. Replay hợp lệ không đổi event/version. Suspend/delete nguồn không vô tình chặn thao tác giải quyết booking hợp lệ.

### T04-04 — Phối hợp void/refund và xử lý lỗi

**Việc cần làm:** thống nhất giao diện với BE-HA05 cho current attempt, kết quả thành công/thất bại/không chắc chắn, idempotency key ổn định và retry/reconciliation. Bao phủ không có attempt, FAILED, PENDING, AUTHORIZED, VOIDED/REFUNDED replay và RELEASED/bất nhất dữ liệu.

**Hoàn thành khi:** PENDING chưa void thành công thì không CANCELLED; CONFIRMED chưa refund thành công thì không CANCELLED; gateway lỗi không thả lịch và có dấu vết retry/xử lý. Retry dùng cùng operation key, không tạo thêm hiệu lực void/refund. Nếu gateway thành công nhưng DB/event ghi lỗi, lần chạy lại phải đối soát/tái sử dụng kết quả an toàn: transaction DB không thể tự rollback tác động ngoài DB. Test double chỉ được báo là bằng chứng contract; tích hợp HA05 phải có bằng chứng riêng.

### T04-05 — Tích hợp scheduler và vận hành

**Việc cần làm:** đăng ký expiry trong scheduler, có cách chạy thủ công để kiểm tra; quét theo batch có giới hạn và thống kê số xử lý/bỏ qua/lỗi. Tái sử dụng worker/scheduler hiện có ở [compose.yaml](../../compose.yaml); không đặt expiry chỉ trong điều kiện local như heartbeat nếu môi trường triển khai cũng cần chạy.

**Hoàn thành khi:** `schedule:list` hiển thị tác vụ đúng môi trường; chứng minh scheduler thực sự gọi luồng expiry, không chỉ tồn tại method. Scheduler/job chạy lặp hoặc chồng nhau không làm sai dữ liệu. Khi scheduler/Redis chậm, hạn thanh toán không được gia hạn và callback không được confirm quá hạn. Có hướng dẫn retry và xem lỗi; log chứa IDs/request correlation, không chứa pet notes/token/payload riêng tư.

### T04-06 — Giải phóng lịch và chống race

**Việc cần làm:** kiểm tra đồng thời expiry/cancel/accept, nhiều cancel và hai job cùng booking; thống nhất thứ tự khóa với code accept/listing/payment. P0 yêu cầu booking trước attempt; nếu dùng khóa listing/actor phải rà cả các flow khác để không tạo thứ tự ngược nhau.

**Hoàn thành khi:** trước hủy hợp lệ search/quote/accept vẫn thấy hold; sau hủy thành công cùng khoảng được dùng lại nếu listing/slot vẫn hợp lệ và không có booking khác chặn. Không sửa/xóa availability slot để “thả lịch”. Chỉ có một transition CANCELLED và một event cho lần chuyển đó; snapshots/giá/hạn cũ không bị viết lại. Khi cancel cạnh tranh callback, chỉ một kết quả hợp lệ thắng; callback muộn không hồi sinh booking hoặc cấp lại lịch.

**Giới hạn phụ thuộc:** callback chưa có ở baseline. T04 có thể kiểm thử cạnh tranh qua contract seam, nhưng chỉ đánh dấu race callback tích hợp hoàn tất sau khi chạy với implementation HA05.

### T04-07 — Kiểm thử và lưu evidence

**Việc cần làm:** tests deadline bằng clock cố định; API quyền/state/replay; adapter success/error/retry; rollback event; DB concurrency; scheduler wiring; hồi quy search/quote/create/accept/reject/listing lifecycle.

**Hoàn thành khi:** chạy trên MySQL test riêng bằng [compose.test.yaml](../../compose.test.yaml), Pint/PHPStan/PHPUnit đạt; có log lệnh/exit code/kết quả và SHA code được kiểm tra. Không dùng số tests của H04 hoặc lượt chạy cũ làm evidence T04. Có bảng từng acceptance criterion → test cụ thể → output. Phần chỉ dùng mock/fake và phần tích hợp thực tế được ghi rõ.

### T04-08 — Tài liệu và bàn giao

**Việc cần làm:** đồng bộ API Contract/M05/payment contract; ghi giới hạn, hướng dẫn chạy scheduler/retry, migration/index nếu có, SHA và reviewer.

**Hoàn thành khi:** local evidence tái hiện được và liên kết đúng code/test/log. PR/CI/reviewer Hoàng có bằng chứng nếu quy trình bàn giao yêu cầu; không suy ra review đạt từ test pass. Theo chỉ dẫn đang có trong phiên làm việc, không push; các bước cần push phải để chờ cho đến khi người dùng cho phép.

## 5. Diễn giải ba acceptance criteria của issue thành bằng chứng

| Acceptance của issue | Cách chứng minh phải có |
|---|---|
| Hết hạn/hủy giải phóng lịch đúng một lần | Tạo booking giữ lịch qua accept thật; search/quote bị chặn. Gọi expiry/cancel thật; kiểm CANCELLED, event/version delta một lần và khoảng lịch dùng lại. Chạy lại và chạy đồng thời: không thêm hiệu lực payment/event/version; booking khác vẫn chặn bình thường. |
| Chạy lại job không làm sai trạng thái | Gọi job 2 lần và 2 worker cạnh tranh; bao phủ booking chưa đến hạn, đã hủy, đã confirm, đã hoàn tất, gateway lỗi và gateway đã thành công nhưng lần ghi DB trước bị lỗi. Deadline/snapshots không đổi; không có hủy nhầm trạng thái mới. |
| Không cho hủy thường sau khi bắt đầu chăm | Owner và caregiver gọi cancel khi IN_PROGRESS/AWAITING_CONFIRMATION, và CONFIRMED tại/sau start_at: bị từ chối, toàn bộ booking/payment/event giữ nguyên. Người ngoài quyền vẫn nhận lỗi quyền/404 trước khi biết state. |

“Đúng một lần” ở đây là **hiệu lực nghiệp vụ**: scheduler có thể gọi nhiều lần nhưng chỉ một transition/payment effect hợp lệ được lưu. Dự án không có bộ đếm hold riêng để trừ; trạng thái booking quyết định lịch bị chặn.

## 6. Phi chức năng và dữ liệu cần bảo toàn

- **An toàn dữ liệu:** MySQL là nguồn chuẩn, không dùng cache/Redis lock làm bảo đảm duy nhất. Ghi status/version/event nguyên tử; side effect ngoài DB có idempotency/reconciliation.
- **Riêng tư:** response/error/log không dump snapshot pet, instructions hoặc secrets payment. Audit chứa actor/source/reason và IDs cần truy vết.
- **Độ tin cậy:** lỗi gateway phải giữ hold; job retry được; callback kiểm deadline trực tiếp dù scheduler chưa chạy. Tần suất scheduler và độ trễ giải phóng cần được chốt, không hứa giải phóng tức thì đúng giây deadline.
- **Hiệu năng/quy mô:** giả định phạm vi demo P0, chưa có mục tiêu tải mới. Quét theo batch; đánh giá truy vấn/index trước khi thêm migration. Index hiện có bắt đầu bằng `listing_id`, không tự chứng minh quét toàn cục theo deadline là hiệu quả.
- **Bảo trì:** Thịnh chịu trách nhiệm T04, Hoàng review; hợp đồng payment phối hợp Hào/BE-HA05. Giữ schema hiện có nếu không có khoảng trống đã chứng minh.

## 7. Điểm cần chốt trước thiết kế và implementation

1. **Đã chốt:** T04 chuẩn bị contract/gateway seam; adapter payment/callback runtime thuộc BE-HA05. Không coi fake trong tests là implementation sandbox đã hoàn thành.
2. **Đã chốt:** Cancel bắt buộc Idempotency-Key; replay theo actor/action/reason; `CANCELLED` do expiry/reject hoặc actor/lý do khác không được tính là replay mới.
3. **Đã chốt:** HTTP/status khi lỗi payment, lưu retry MySQL operation/outbox, worker T04 tiếp tục, 5 lần retry tự động với backoff 1/2/4/8/16 phút rồi chuyển admin.
4. **Đã chốt:** Reason codes do server gán; reason note tùy chọn; giữ `payment_due_at`, current attempt và snapshots để audit.
5. **Đã chốt:** Worker expiry mỗi phút, batch 100, mục tiêu độ trễ tối đa 2 phút khi worker/DB khả dụng; `REQUESTED` cùng lịch xử lý với `AWAITING_PAYMENT`.
6. **Đã chốt:** Actor thường là owner/caregiver liên quan; admin chỉ qua workflow ngoại lệ M09; lock order `booking → payment_attempt`, không khóa listing/user.

Trước implementation cần phối hợp HA03/T03/HA05 để xác nhận các luồng callback thật dùng đúng lock order, operation key và dữ liệu attempt như contract này.

### Các quyết định hủy được người dùng chấp nhận ngày 06/11/2026

- `POST /api/bookings/{id}/cancel` bắt buộc `Idempotency-Key`, cùng quy tắc định dạng 1–128 ký tự ASCII như API tạo booking. Phạm vi key là actor + booking; hash payload chuẩn hóa gồm booking, action, reason code và reason note. Cùng key/cùng payload replay trạng thái operation mới nhất, không thêm event/payment operation; cùng key/khác payload trả `409 IDEMPOTENCY_CONFLICT`; thiếu/sai key trả `422 VALIDATION_ERROR`.
- Gửi lại bằng key mới chỉ được xem là replay thành công (`200`) nếu event `CANCELLED` cho thấy đúng actor đã hủy cùng booking với cùng action/reason. Nếu đã `CANCELLED` do expiry/reject, actor khác hoặc reason khác thì trả `409 INVALID_STATE`; không tạo event mới.
- Reason mô tả tự do là tùy chọn. Client không tự đặt reason code; server gán `OWNER_CANCELLED` hoặc `CAREGIVER_CANCELLED` cho yêu cầu hủy người dùng; worker dùng `REQUEST_EXPIRED` hoặc `PAYMENT_EXPIRED`; reject hiện có tiếp tục dùng `CAREGIVER_REJECTED`. `reason_note` chỉ để giải thích ngắn lý do hủy, tối đa 500 ký tự sau NFC + trim; không yêu cầu ghi dữ liệu sức khỏe chi tiết, thông tin liên hệ/tài chính hoặc bí mật. Note được lưu trong audit/event có kiểm soát truy cập, không đưa vào log thông thường.
- Khi void/refund timeout hoặc lỗi có thể thử lại, API trả `202 CANCELLATION_PENDING`; booking vẫn giữ status hiện tại và lịch vẫn bị khóa. Khi gateway trả lỗi từ chối cuối cùng, API trả `502 PAYMENT_CANCELLATION_FAILED`; trạng thái booking/lịch vẫn giữ nguyên và thao tác được đưa vào hàng admin. Attempt không khớp nhánh nghiệp vụ trả `409 PAYMENT_STATE_MISMATCH`, giữ nguyên dữ liệu và cần đối soát.
- Retry được lưu bền vững dưới dạng cancellation operation/outbox trong MySQL cùng stable gateway operation key, actor/source/reason, attempt hiện tại, thời điểm retry kế tiếp và mã lỗi gần nhất. Worker T04 tiếp tục; không phụ thuộc request client còn mở. Tối đa 5 lần retry tự động sau lần gọi ban đầu, backoff 1, 2, 4, 8, 16 phút; sau đó chuyển hàng admin, không tự thả lịch. Mỗi lần dùng lại cùng gateway operation key; admin retry cũng dùng key này. Đây là lịch retry riêng của cancel.
- Worker expiry chạy mỗi phút, xử lý theo batch tối đa 100 booking với lỗi được cô lập theo từng booking. Mục tiêu là xử lý trong vòng 2 phút sau deadline khi worker/DB khả dụng; đây không phải đảm bảo khi gateway chậm. `REQUESTED` expiry nằm trong cùng lịch/bàn giao với `AWAITING_PAYMENT`; booking `REQUESTED` không có payment nên chuyển `CANCELLED` trực tiếp với `REQUEST_EXPIRED`. Nếu payment void/refund chưa hoàn tất, booking giữ hold cho tới khi thành công.
- Quyền hủy thường chỉ thuộc owner hoặc caregiver gắn với booking, xác định từ quan hệ đã lưu và role/account còn hợp lệ; admin không dùng endpoint cancel thường. Admin chỉ xử lý ngoại lệ theo workflow M09, cần quyền admin, lý do bắt buộc và audit riêng.
- Khóa cancel/expiry theo thứ tự `booking → payment_attempt`; không khóa listing hoặc user vì hủy booking cũ không sửa listing/availability và không nên bị chặn bởi listing/user đã ngừng hoạt động. Callback HA05 phải dùng cùng thứ tự `booking → payment_attempt`. Nếu một luồng thật sự cần thêm khóa, phải thống nhất lại toàn bộ thứ tự trước khi code.
- Giữ nguyên `payment_due_at`, current attempt và snapshots làm dấu vết lịch sử sau cancel/expiry; chỉ cập nhật trạng thái attempt theo kết quả void/refund, không xóa hoặc đặt null liên kết attempt. Mã reason do server gán: `OWNER_CANCELLED`, `CAREGIVER_CANCELLED`, `REQUEST_EXPIRED`, `PAYMENT_EXPIRED`; reject hiện có là `CAREGIVER_REJECTED`.

Các quyết định trên đóng câu hỏi cancel idempotency, xử lý lỗi/retry, reason/audit, nhịp expiry, phạm vi REQUESTED, quyền và thứ tự khóa. Cần đồng bộ contract với HA03/T03/HA05 trước implementation.

Phạm vi và quyết định nghiệp vụ đã được chốt; implementation và contract seam đã hoàn thành trong T04. Cần đồng bộ callback/payment runtime với HA03/T03/HA05 khi tích hợp BE-HA05.

## 8. Tổ chức nghiệp vụ đã chọn

Thiết kế giữ matrix quyền/trạng thái P0, dùng MySQL làm nguồn quyết định, có replay an toàn và chuẩn bị giao tiếp payment cho BE-HA05.

| Phương án | Tổ chức xử lý | Ưu điểm | Đánh đổi |
|---|---|---|---|
| **A — Một luồng chuyển trạng thái hủy dùng chung (đã chọn)** | API cancel và expiry truyền ngữ cảnh actor/source/reason vào cùng phần điều phối chuyển trạng thái/payment/event. Scheduler chỉ chọn ứng viên; từng booking được kiểm tra lại trong luồng chung. Reject hiện có giữ contract riêng. | Một nơi bảo vệ idempotency, payment prerequisites và ghi event; dễ giữ kết quả nhất quán giữa API và job; ít lặp code trong phạm vi P0. | Guard phải phân biệt rõ USER và SYSTEM; luồng dùng chung không cấp quyền người dùng cho SYSTEM hoặc ngược lại. |

**Phương án A được người dùng chọn ngày 06/11/2026.** API cancel và expiry dùng chung bộ điều phối; reject REQUESTED giữ contract/action hiện có. Luồng cần void/refund chỉ chuyển booking sang `CANCELLED` và thả lịch sau khi payment đạt điều kiện. Khi chưa có adapter HA05, không giả lập thành công trong runtime; phần độc lập payment có thể triển khai và kiểm chứng qua contract seam, còn tích hợp payment thật cần evidence riêng.

## 9. Decision log

| Mã | Quyết định | Trạng thái | Căn cứ |
|---|---|---|---|
| T04-D01 | Giữ phạm vi expiry/cancel, chuẩn bị hợp đồng void/refund; payment sandbox/callback runtime thuộc HA05. | Người dùng xác nhận 06/11/2026 | Phản hồi trực tiếp: “Đúng, giữ phạm vi theo issue #20”. |
| T04-D02 | API cancel và expiry dùng chung một bộ điều phối chuyển trạng thái/payment/event; reject REQUESTED giữ contract riêng. | Người dùng chọn A ngày 06/11/2026 | Dùng chung các bảo đảm payment/event/replay giữa API và scheduler. |
| T04-D03 | Cancel bắt buộc idempotency key; replay chỉ theo actor + booking + action + reason; CANCELLED do nguồn khác không được coi là replay của cancel mới. | Người dùng chấp nhận 06/11/2026 | Phản hồi: chấp nhận năm đề xuất về hành vi gửi hủy. |
| T04-D04 | Reason note tùy chọn, tối đa 500 ký tự; dùng mã reason cố định do server gán. Lỗi payment có kết quả pending/failed/inconsistent riêng; retry lưu bền vững qua MySQL operation/outbox, worker tiếp tục tối đa 5 lần theo 1/2/4/8/16 phút rồi chuyển admin. | Người dùng chốt 06/11/2026 | Người dùng đồng ý phương án lỗi/retry được đề xuất. |
| T04-D05 | Expiry chạy mỗi phút, batch 100, mục tiêu xử lý trong 2 phút khi worker/DB khả dụng; REQUESTED và AWAITING_PAYMENT cùng lịch xử lý; payment chưa void/refund thì giữ lịch. | Người dùng chốt 06/11/2026 | Người dùng đồng ý bộ đề xuất chốt các mục expiry. |
| T04-D06 | Hủy thường chỉ dành cho owner/caregiver gắn với booking; admin chỉ xử lý ngoại lệ M09 có lý do/audit. Lock order `booking → payment_attempt`; không khóa listing/user. Giữ `payment_due_at`, attempt hiện hành và snapshots để audit. | Người dùng chốt 06/11/2026 | Người dùng đồng ý bộ đề xuất chốt quyền, khóa và lịch sử. |

## 10. Implementation handoff status

| Hạng mục | Kết quả | Bằng chứng |
|---|---|---|
| Cancel API, idempotency, quyền và state matrix | Implemented; owner/caregiver participant only; required key, replay/conflict behavior, optional note. | `backend/tests/Feature/BookingCancellationTest.php` |
| Expiry REQUESTED/AWAITING_PAYMENT | Implemented; every-minute scheduler, max batch 100, rechecks state/deadline under booking lock. | `backend/tests/Feature/BookingExpiryTest.php` |
| Void/refund outbox and retry | Implemented durable MySQL operation, leases, stable gateway operation key, 5 retries with backoff then manual review. Default gateway is fail-closed and awaits HA05 binding. | `backend/tests/Feature/BookingCancellationTest.php`; `backend/app/Domain/Booking/Payment/` |
| Admin handoff | Implemented manual review queue and audited retry route; retry keeps the gateway operation key. | `backend/tests/Feature/BookingCancellationTest.php` |
| Formatting/static analysis/regression | After merging BE-H04, `composer ci:check` passed; full backend suite passed 317 tests / 3,686 assertions on MySQL test DB. | Ignored local evidence: `docs/evidence/t04-handoff.md` |
| Real sandbox/callback integration | Not part of T04 runtime; requires BE-HA05 adapter and callback race evidence. | Contract seam only; no fake success in runtime. |

**Handoff:** T04 implementation is complete and ready for Hoàng’s review; reviewer sign-off is still pending. Do not mark payment sandbox/callback integration complete until BE-HA05 supplies the adapter and end-to-end evidence.
