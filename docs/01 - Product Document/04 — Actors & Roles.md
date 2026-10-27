# Actors & Roles

PETTEL — Product Document \| Bản thảo 26/10/2026

# Khách chưa đăng nhập

Được xem listing ACTIVE, tìm và lọc. Không xem hồ sơ thú cưng, nhắn tin, đặt lịch hoặc đánh giá.

# Chủ nuôi

Quản lý hồ sơ thú cưng của mình; tạo và theo dõi booking; thanh toán sandbox; nhắn tin và xem cập nhật trong booking; xác nhận hoàn tất; đánh giá từ booking đã hoàn thành; báo cáo nội dung. Không sửa listing hay dữ liệu của người khác.

# Người chăm hoặc cơ sở

Tạo và sửa listing của mình; quản lý lịch; chấp nhận hoặc từ chối booking; xem hướng dẫn cần thiết về thú cưng; nhắn tin, gửi cập nhật và đánh dấu xong. Không tự cấp badge, sửa đánh giá hoặc thay trạng thái payment tùy ý.

# Quản trị

Duyệt, ẩn hoặc khôi phục listing; xem và xử lý báo cáo; can thiệp booking theo quy trình có lý do và audit. Quyền đọc dữ liệu riêng tư chỉ dùng khi xử lý trường hợp liên quan.

# Hệ thống sandbox

Adapter thanh toán gửi kết quả mô phỏng. Chỉ sự kiện có mã tham chiếu, số tiền và chữ ký/secret hợp lệ mới được xử lý; callback lặp phải idempotent.

# Nguyên tắc quyền

Một tài khoản có thể là chủ nuôi và người chăm. Mọi thao tác kiểm tra vai trò, quyền sở hữu tài nguyên và trạng thái hiện tại ở phía server. Quản trị dùng tài khoản riêng; quyền quản trị không suy ra từ giao diện.  
