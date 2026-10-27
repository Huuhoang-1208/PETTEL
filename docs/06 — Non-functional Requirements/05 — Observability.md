# Observability

PETTEL — Non-functional Requirements \| Bản thảo 26/10/2026

# Log có cấu trúc

Mỗi request có request\_id/correlation\_id. Log gồm thời điểm, môi trường, route, status, thời gian xử lý, actor ID nội bộ (nếu có), booking/payment ID khi liên quan; không ghi tên, địa chỉ, pet notes, tin nhắn, token hoặc nội dung ảnh. Audit riêng cho thay trạng thái và admin action.

# Metric và cảnh báo

Theo dõi request rate, p95 latency, 5xx, query chậm, queue depth/failed jobs, callback payment lỗi, booking AWAITING\_PAYMENT quá hạn, release sandbox thất bại, upload lỗi. Cảnh báo tối thiểu: 5xx tăng liên tục, worker dừng, DB/Redis không healthy, failed job không được xử lý.

# Debug và bảo vệ

Telescope chỉ ở local/dev, không mở công khai. Pulse được dùng để xem xu hướng hiệu năng request/query/queue; không thay thế log/audit. Filament admin và Pulse dashboard chỉ mở sau xác thực và phân quyền. Lưu log theo thời hạn đề xuất 30 ngày cho demo, điều chỉnh theo chính sách dữ liệu.

# Kiểm chứng

Từ một booking demo phải truy được chuỗi request → status event → payment attempt → callback → release qua ID, không cần xem PII. Tạo lỗi có kiểm soát để chứng minh log và cảnh báo hoạt động.  
