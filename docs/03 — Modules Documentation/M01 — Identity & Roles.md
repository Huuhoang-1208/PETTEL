# M01 — Identity & Roles

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Đăng ký, đăng nhập/đăng xuất, xác nhận email nếu dùng, đặt lại mật khẩu, hồ sơ tài khoản và gán vai trò owner/caregiver/admin. Một người có thể có hai vai trò owner và caregiver. Admin phải được cấp quyền ngoài luồng tự đăng ký.

# Quy tắc

Breeze API scaffold cho auth; Vue SPA dùng Sanctum stateful session cookie an toàn và CSRF trên thao tác ghi. Policy kiểm tra role \+ owner ID \+ trạng thái ở server, không dựa vào nút ẩn trên giao diện. Throttle đăng nhập, đặt lại mật khẩu và API nhạy cảm. Không tiết lộ tài khoản tồn tại qua thông báo quên mật khẩu.

# Dữ liệu và phụ thuộc

Bảng users, role assignments hoặc role enum đơn giản, email\_verified\_at, audit auth. Cấp user ID cho Pet, Caregiver và Booking. Dữ liệu mật khẩu chỉ dạng hash của framework; không log mật khẩu, token hoặc session ID.

# Tiêu chí chấp nhận

Người chưa đăng nhập chỉ xem listing ACTIVE. Owner không sửa pet khác. Caregiver không sửa listing khác. Admin không đăng nhập bằng role tự khai. Thao tác bị từ chối trả 403; API chưa đăng nhập trả 401 để Vue chuyển về login, CSRF hết hạn trả 419.
