# BE-HA05 — Contract payment sandbox và callback

**Ngày chốt:** 07/11/2026 · **Owner:** Hào · **Reviewer:** Hoàng.

**Trạng thái:** Các quyết định task 1 đã được thống nhất. Endpoint tạo attempt, callback có chữ ký và endpoint giả lập success/failure/timeout đã được triển khai. Task 7 đã có tài liệu kiểm tra và evidence bên dưới. Chưa có kết nối payment provider thật; không xử lý tiền thật.

## 1. Booking, attempt và hạn thanh toán

- Caregiver accept booking `REQUESTED` hợp lệ chuyển booking sang `AWAITING_PAYMENT`; `payment_due_at` bằng thời điểm accept cộng 15 phút. Retry không gia hạn hạn này.
- Owner tạo attempt khi booking vẫn `AWAITING_PAYMENT` và chưa tới hạn. Attempt đầu là `PENDING`; amount lấy từ price snapshot của booking, không nhận amount do client quyết định.
- Callback thành công chỉ được authorize attempt hiện hành khi chữ ký/secret, booking reference, attempt reference và amount khớp, booking vẫn `AWAITING_PAYMENT`, attempt đang `PENDING`, và thời gian hiện tại nhỏ hơn `payment_due_at`. Khi hợp lệ, trong cùng transaction chuyển attempt sang `AUTHORIZED`, booking sang `CONFIRMED`, và ghi event trạng thái.
- Callback thất bại hợp lệ chuyển attempt hiện hành `PENDING → FAILED`; không xác nhận booking. Nếu booking vẫn còn hạn, retry tạo attempt `PENDING` mới với attempt number và idempotency key mới. Không đổi attempt `FAILED` về `PENDING`.
- Timeout không thêm trạng thái payment mới. Nếu không có kết quả cuối từ sandbox, attempt vẫn `PENDING`; expiry/cancel xử lý attempt đó theo contract BE-T04.

Nguồn trạng thái/hạn: [BE-T01 §5–6](<BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ.md>) và [BE-HA03](<BE-HA03 — Quy tắc accept-reject và chống trùng lịch.md>).

## 2. Callback đến muộn, hủy và giữ lịch

- Callback authorize đến tại/sau `payment_due_at` không chuyển booking sang `CONFIRMED`, không hồi sinh booking đã `CANCELLED`, và không cấp lại lịch.
- Nếu sandbox xác nhận đã authorize muộn, ghi nhận kết quả thực tế của attempt và tạo tác vụ bù void/refund có audit qua cơ chế bền vững của BE-T04. Tác vụ bù dùng operation key ổn định; retry không tạo hiệu lực bù hai lần.
- Cancel/expiry và callback dùng lock order `booking → payment_attempt`. Dưới khóa, đọc lại deadline, trạng thái booking và attempt hiện hành trước khi quyết định.
- Không gọi mạng/gateway khi đang giữ transaction lock. Ghi trạng thái/event và tác vụ cần xử lý nguyên tử; thực hiện side effect sau commit. Nếu void/refund chưa thành công, giữ booking/lịch theo contract BE-T04.

Nguồn chuẩn hủy/expiry: [BE-T04 — Phân tích issue 20](<BE-T04 — Phân tích issue 20 và tiêu chí hoàn thành.md>) và [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).

## 3. Idempotency và event ID

- Tạo payment attempt bắt buộc `Idempotency-Key`, scope theo booking và hash payload chuẩn hóa. Cùng key/payload trả lại attempt đã tạo; cùng key với payload khác trả HTTP 409.
- Callback chỉ được khử trùng sau khi xác thực chữ ký/secret. Unique key là `(provider, external_event_id)`; lưu `payload_hash` và `processing_status`.
- Cùng provider/event ID và cùng payload hash là replay: trả lại kết quả đã lưu, không đổi attempt/booking và không ghi transition thứ hai.
- Cùng provider/event ID nhưng payload hash khác là xung đột: trả HTTP 409 `PAYMENT_EVENT_CONFLICT`; không thay đổi attempt hoặc booking.
- Event ID mới vẫn phải kiểm tra booking reference, amount, hạn, trạng thái và attempt hiện hành. Attempt cũ hoặc trạng thái không còn hợp lệ không được xác nhận booking.

Nguồn schema/idempotency: [BE-T01 §4–6](<BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ.md>) và [Database Design](<../08 — Architecture & Technical Design/database design.md>).

## 4. Phản hồi callback không hợp lệ

| Tình huống | Phản hồi/quy tắc |
| --- | --- |
| Chữ ký/secret sai | HTTP 401; không ghi nhận là payment event đáng tin cậy và không đổi payment/booking. |
| Payload thiếu/sai cấu trúc | HTTP 422 `VALIDATION_ERROR`; không đổi payment/booking. |
| Chữ ký hợp lệ nhưng reference, amount, deadline hoặc attempt không hợp lệ | Ghi event với `processing_status = REJECTED`, không đổi trạng thái payment/booking; trả HTTP 200 acknowledgment để sandbox không retry một callback đã được xử lý và từ chối. |
| Event ID đã dùng nhưng payload hash khác | HTTP 409 `PAYMENT_EVENT_CONFLICT`; giữ nguyên trạng thái. |
| Callback hợp lệ hoặc replay chính xác | HTTP 200; replay trả kết quả đã lưu mà không chạy lại side effect. |

Lỗi HTTP có envelope API chung `{error: {code, message, details, request_id}}`. Callback là server-to-server, không yêu cầu session người dùng.

## 5. Điểm tích hợp và kiểm thử bàn giao

- BE-HA03 tạo điều kiện booking `AWAITING_PAYMENT` và hạn giữ lịch 15 phút.
- BE-T04 trong backlog repo đảm nhiệm expiry/cancel và durable void/refund; nếu tracker nhóm gọi phần này là BE-HA04 thì liên kết issue tương ứng. Hào phối hợp để cùng dùng current attempt, operation key và lock order.
- Test bao gồm success/failure/timeout, retry sau FAILED, event lặp và event ID tái sử dụng với payload khác, event khác thứ tự, chữ ký sai, amount/reference sai, attempt cũ, callback muộn, callback chạy đồng thời với cancel, và retry/đối soát void-refund.
- Mọi payment là mô phỏng. Không thu thẻ/CVV/OTP/tài khoản ngân hàng thật; không ghi callback secret hoặc dữ liệu xác thực vào log.

### Runtime đã triển khai

- `POST /api/bookings/{id}/sandbox-payments`: owner đăng nhập gửi `Idempotency-Key`; server tạo attempt theo giá snapshot và deadline hiện có. Body không nhận amount hoặc kết quả thanh toán.
- `POST /api/bookings/{id}/sandbox-payments/{attemptId}/simulate`: chỉ dùng trong local/testing; body `{ "result": "SUCCESS|FAILURE|TIMEOUT" }`. Timeout giữ attempt ở `PENDING`; success/failure chạy qua cùng bộ xử lý callback đã xác thực chữ ký.
- `POST /api/sandbox/payment-callback`: server-to-server; yêu cầu `X-Payment-Signature` là HMAC-SHA256 của payload chuẩn hóa `event_id`, `booking_id`, `attempt_reference`, `amount_vnd`, `result`.
- `PAYMENT_SANDBOX_SECRET` cấu hình secret ở môi trường triển khai. Mặc định chỉ tạo secret và bật endpoint giả lập trong `local`/`testing`; không bật endpoint giả lập ở production.
- Callback authorize muộn ghi nhận attempt, không confirm booking và tạo operation `REFUND` trong outbox, kèm audit. Gateway hoàn tiền mặc định của T04 vẫn fail-closed cho đến khi adapter sandbox cancellation được tích hợp.

## 6. Hướng dẫn kiểm tra và ví dụ API

### Callback thành công

Gửi `POST /api/sandbox/payment-callback` với payload dưới đây và chữ ký HMAC hợp lệ trong header `X-Payment-Signature`:

```json
{
  "event_id": "9fc7d68d-34c8-4c1e-aabd-62f803d80c5d",
  "booking_id": "123",
  "attempt_reference": "sandbox_45cc6c8b-6f17-4e90-9e33-458c6b71bd2f",
  "amount_vnd": 150000,
  "result": "AUTHORIZED"
}
```

Chữ ký là HMAC-SHA256 dạng hex của JSON compact theo đúng thứ tự trường `event_id`, `booking_id`, `attempt_reference`, `amount_vnd`, `result`, dùng `PAYMENT_SANDBOX_SECRET`. Với booking đang `AWAITING_PAYMENT`, attempt hiện hành `PENDING`, còn hạn và amount/reference khớp, phản hồi có dạng:

```json
{
  "data": {
    "event_id": "9fc7d68d-34c8-4c1e-aabd-62f803d80c5d",
    "processing_status": "PROCESSED",
    "rejection_code": null,
    "attempt_status": "AUTHORIZED",
    "booking_status": "CONFIRMED",
    "compensation_operation_id": null,
    "replayed": false
  }
}
```

`event_record_id` cũng được trả về cho callback mới. Callback lặp với cùng provider/event ID và payload trả HTTP 200 với `replayed: true`, không ghi event hay transition lần hai.

### Thất bại và callback đến muộn

Để kiểm tra kết quả thất bại, dùng cùng payload hợp lệ với `result: "FAILED"`. Callback được ghi nhận là `PROCESSED`, attempt thành `FAILED`, booking vẫn `AWAITING_PAYMENT`; owner có thể tạo attempt mới khi booking còn hạn.

Nếu callback `AUTHORIZED` đến sau deadline, hoặc đang có yêu cầu `VOID` chưa hoàn tất, hệ thống không chuyển booking sang `CONFIRMED`. Attempt được ghi nhận `AUTHORIZED` và response có `compensation_operation_id`; tác vụ `REFUND` được đưa vào outbox. Nếu `VOID` còn `PENDING`/`PROCESSING`, nó được chuyển sang `MANUAL_REVIEW` với `PAYMENT_CALLBACK_RACE`. Khi booking đã `CANCELLED`, trạng thái đó được giữ nguyên và refund vẫn được xếp hàng.

| Trường hợp | Kết quả cần thấy |
| --- | --- |
| `result: FAILED` hợp lệ | HTTP 200; attempt `FAILED`; booking không `CONFIRMED`. |
| Callback authorize quá hạn hoặc có VOID đang chờ | HTTP 200; không confirm; attempt `AUTHORIZED`; có operation refund bù. |
| Chữ ký sai/thiếu | HTTP 401 `INVALID_CALLBACK_SIGNATURE`; không thay đổi payment/booking. |
| Payload sai hoặc có trường ngoài hợp đồng | HTTP 422 `VALIDATION_ERROR`. |
| Booking/attempt reference không khớp | HTTP 200, `processing_status: REJECTED`, mã `PAYMENT_REFERENCE_MISMATCH`. |
| Amount không khớp | HTTP 200, `processing_status: REJECTED`, mã `PAYMENT_AMOUNT_MISMATCH`. |
| Event ID đã dùng với payload khác | HTTP 409 `PAYMENT_EVENT_CONFLICT`. |
| Attempt/trạng thái không còn hợp lệ | HTTP 200, `processing_status: REJECTED`, mã `PAYMENT_STATE_MISMATCH`. |

Ví dụ lỗi chữ ký (HTTP 401):

```json
{
  "error": {
    "code": "INVALID_CALLBACK_SIGNATURE",
    "message": "Callback signature is invalid.",
    "details": [],
    "request_id": "<request-uuid>"
  }
}
```

Ví dụ callback có chữ ký hợp lệ nhưng amount sai (HTTP 200 acknowledgement; không phải authorize):

```json
{
  "data": {
    "event_id": "<event-uuid>",
    "event_record_id": "<event-row-id>",
    "processing_status": "REJECTED",
    "rejection_code": "PAYMENT_AMOUNT_MISMATCH",
    "attempt_status": "PENDING",
    "booking_status": "AWAITING_PAYMENT",
    "compensation_operation_id": null,
    "replayed": false
  }
}
```

### Lệnh kiểm tra và kết quả

Chạy toàn bộ pipeline từ thư mục gốc repository:

```powershell
docker compose -p pettel-test -f compose.test.yaml run --build --rm test
```

Kết quả lần kiểm tra ngày 07/11/2026: Pint đạt trên 171 file; PHPStan báo `[OK] No errors`; migration và seed thành công; **375 tests passed (4.252 assertions)**. Test liên quan trực tiếp: `backend/tests/Feature/SandboxPaymentSimulationTest.php` và `backend/tests/Feature/PaymentCallbackCancellationConcurrencyTest.php` (callback và cancel chạy trên hai tiến trình, cùng database MySQL test).

## 7. Bàn giao reviewer

**Reviewer:** Hoàng. Khi PR được tạo, yêu cầu Hoàng review phần `ProcessSandboxPaymentCallback`, hai test payment/cancellation nói trên và tài liệu này. Các điểm kiểm tra: chữ ký và response lỗi; callback replay không tạo side effect lần hai; callback muộn không xác nhận/hồi sinh booking; operation refund bù được ghi bền vững; test race không tạo transition trùng.

**Phụ thuộc:** BE-HA03 cung cấp trạng thái `AWAITING_PAYMENT`, attempt hiện hành và hạn giữ chỗ. Theo mã/tài liệu trong repository, phần hết hạn/hủy/outbox tương ứng nằm ở BE-T04; liên kết với BE-HA04 trong tracker nhóm khi nghiệm thu. Hành vi tích hợp được kiểm tra qua `BookingCancellationTest`, `SandboxPaymentSimulationTest` và `PaymentCallbackCancellationConcurrencyTest`.

Nội dung bàn giao PR: “Đã bổ sung hợp đồng callback sandbox, callback muộn và xử lý cạnh tranh với hủy. Đã chạy pipeline `docker compose -p pettel-test -f compose.test.yaml run --build --rm test`: Pint đạt, PHPStan đạt, 375 tests/4.252 assertions đạt. Nhờ Hoàng review transaction/lock order, idempotency callback và test race.”

## 8. Nguồn đối chiếu

- [M06 — Payment Sandbox](<../03 — Modules Documentation/M06 — Payment Sandbox.md>).
- [Payment Sandbox Integration](<../08 — Architecture & Technical Design/07 — Payment Sandbox Integration.md>).
- [BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ](<BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ.md>).
- [BE-T04 — Phân tích issue 20 và tiêu chí hoàn thành](<BE-T04 — Phân tích issue 20 và tiêu chí hoàn thành.md>).
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
