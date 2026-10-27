# Redis Cache Design

PETTEL — Architecture & Technical Design \| Thiết kế đề xuất.

# Vai trò

Redis dùng cache dữ liệu đọc công khai và backend queue cho tác vụ phụ. MySQL vẫn là nguồn chuẩn cho giá snapshot, booking, availability, payment và quyền. Không coi cache là bằng chứng còn chỗ hoặc payment đã thành công.

# Key và TTL đề xuất

\`listing:public:{id}:v{version}\` TTL 5 phút cho trang chi tiết ACTIVE; \`search:{hash(filters)}:v{search\_version}\` TTL 1–2 phút cho trang kết quả; \`review:summary:{listing\_id}\` TTL 5 phút. Key có prefix môi trường. Không cache pet notes, chat, payment hoặc token vào cache công khai. Danh sách search có thể chứa ID, sau đó kiểm tra status ACTIVE từ DB khi mở chi tiết.

# Invalidation

Listing ACTIVE/SUSPENDED, giá, lịch hoặc review thay đổi → tăng version/xóa key liên quan sau DB commit. Booking accepted/cancelled làm mới chỉ mục/lịch công khai; chấp nhận booking luôn kiểm tra DB, kể cả cache chưa xóa. Có TTL để tự phục hồi khi một sự kiện invalidation bị lỡ.

# Queue và khóa

Redis queue xử lý thumbnail/thông báo; job idempotent và có failed-job tracking. Có thể dùng Redis lock để giảm trùng job, nhưng transaction với \`FOR UPDATE\` trên row listing mới là bảo vệ cuối cùng cho lịch trùng. Nếu Redis lỗi, trả dữ liệu trực tiếp từ DB khi khả thi; không tự chuyển booking/payment.

# Đo lường

Theo dõi hit ratio, latency, memory, eviction, queue depth. Chỉ tối ưu TTL/keys sau khi đo; cache không làm sai quyền truy cập.  
