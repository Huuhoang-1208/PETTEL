# M06 — Payment Sandbox

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Mô phỏng payment cho booking: tạo attempt, nhận thành công/thất bại, authorize khoản tiền giả, void/refund khi hủy và release khi owner xác nhận hoàn tất. Không tích hợp tiền hoặc dữ liệu thẻ thật.

# Quy tắc

Amount VND khớp booking snapshot. Callback có event ID duy nhất, secret/chữ ký và booking reference hợp lệ; xử lý idempotent, transaction bảo vệ chuyển trạng thái. Không RELEASED hai lần. Retry tạo attempt mới; chỉ attempt hiện hành được đổi booking. PENDING thất bại không tự xác nhận booking.

# Dữ liệu và phụ thuộc

payment\_attempts, payment\_events, booking\_id, amount\_vnd, provider\_ref, status, idempotency\_key, timestamps. Booking phát lệnh bắt đầu và release; Payment trả sự kiện AUTHORIZED/FAILED/REFUNDED/RELEASED; audit ghi actor và kết quả.

# Tiêu chí chấp nhận

Demo được success, failure, duplicate callback, timeout và cancel. UI luôn ghi “Thanh toán mô phỏng — không trừ tiền thật”. Không yêu cầu số thẻ, CVV, OTP hay tài khoản ngân hàng thật.

# Cần chốt

Chính sách phí hủy và quy trình xử lý nếu mô phỏng release lỗi; P0 không đưa ra cam kết giữ tiền hoặc hoàn tiền thật.  
