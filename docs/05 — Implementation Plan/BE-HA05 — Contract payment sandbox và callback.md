# BE-HA05 — Contract payment sandbox và callback

**Ngày chốt:** 07/11/2026 · **Owner:** Hào · **Reviewer:** Hoàng.

**Trạng thái:** Các quyết định task 1 đã được thống nhất. Đây là contract triển khai cho sandbox/payment callback; chưa xác nhận adapter, endpoint hoặc kiểm thử tích hợp đã hoàn thành.

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

## 6. Nguồn đối chiếu

- [M06 — Payment Sandbox](<../03 — Modules Documentation/M06 — Payment Sandbox.md>).
- [Payment Sandbox Integration](<../08 — Architecture & Technical Design/07 — Payment Sandbox Integration.md>).
- [BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ](<BE-T01 — Lộ trình schema, trạng thái và hợp đồng nghiệp vụ.md>).
- [BE-T04 — Phân tích issue 20 và tiêu chí hoàn thành](<BE-T04 — Phân tích issue 20 và tiêu chí hoàn thành.md>).
- [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
