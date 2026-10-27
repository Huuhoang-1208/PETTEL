# Payment Sandbox Integration

PETTEL — Architecture & Technical Design \| Không tích hợp tiền thật.

# Adapter và hợp đồng

PaymentSandboxGateway cung cấp \`createAttempt(bookingId, amountVnd, idempotencyKey)\`, \`simulateResult(attemptId, success/fail)\`, \`release(attemptId)\` và \`refund/void(attemptId)\`. Adapter trả provider\_ref và event ID duy nhất. Có thể dùng provider sandbox thật nếu nhóm đã có tài khoản; mặc định adapter nội bộ để demo deterministic, không nhận dữ liệu thẻ thật.

# Luồng tạo và callback

Booking AWAITING\_PAYMENT → tạo PaymentAttempt PENDING với amount từ price snapshot. Sandbox gửi callback có event ID, booking/attempt reference, amount, status và chữ ký/secret. Server xác thực, ghi event ID unique, khóa attempt/booking trong transaction, từ chối amount khác hoặc state không hợp lệ, chuyển AUTHORIZED và booking CONFIRMED. Phản hồi callback nhanh; side effects đẩy queue sau commit.

# Release, hủy và retry

Khi owner xác nhận sau AWAITING\_CONFIRMATION, release sandbox một lần; chỉ sau xác nhận thành công mới đánh dấu COMPLETED. Nếu release lỗi, giữ trạng thái chờ xử lý, log và retry idempotent. Hủy trước AUTHORIZED → VOIDED; sau AUTHORIZED trước RELEASED → REFUNDED mô phỏng trước khi booking CANCELLED; không hứa hoàn tiền thật. FAILED có thể thử attempt mới trong thời hạn giữ chỗ.

# Test bắt buộc

Success, fail, hết hạn, duplicate/out-of-order callback, amount mismatch, chữ ký sai, retry và race giữa cancel/callback. Sau mỗi test, tổng số RELEASED trên một booking ≤1; không có CONFIRMED nếu không có AUTHORIZED.

# Hiển thị

UI ghi rõ “Thanh toán mô phỏng — không trừ tiền thật”. Các từ “giữ tiền” và “giải ngân” trong proposal chỉ mô tả hành vi mong muốn ở tương lai.

# Chính sách sandbox P0

[P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>) quy định hủy không phí, trạng thái được phép hủy và nhánh admin ngoại lệ. Retry release cùng idempotency key sau 1/5/15 phút; nếu tiếp tục lỗi, giữ AWAITING_CONFIRMATION và đưa hàng admin. Callback authorize sau payment_due_at không confirm, kể cả scheduler chưa chạy; nếu adapter báo đã authorize muộn, tạo tác vụ bù refund/void có audit.
