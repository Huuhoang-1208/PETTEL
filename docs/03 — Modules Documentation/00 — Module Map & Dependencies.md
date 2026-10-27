# Module Map & Dependencies

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Module P0

M01 Identity & Roles: tài khoản, đăng nhập, quyền. M02 Pet Profiles: dữ liệu từng thú cưng. M03 Caregiver & Listings: người chăm, tin dịch vụ, lịch. M04 Discovery & Smart Match: tìm/lọc/gợi ý quy tắc. M05 Booking & Availability: yêu cầu, giữ lịch, trạng thái. M06 Payment Sandbox: mô phỏng authorize/release/refund. M07 Messaging & Care Updates: tin nhắn và ảnh trong booking. M08 Reviews & Trust Signals: đánh giá có nguồn. M09 Reports & Admin: tiếp nhận báo cáo và quản trị.

# Phụ thuộc theo chiều nghiệp vụ

Identity → Pet Profiles và Caregiver & Listings. Pet Profiles \+ Listings → Discovery. Pet Profiles \+ Listings \+ Availability → Booking. Booking → Payment Sandbox, Messaging, Updates, Reviews và Reports. Admin đọc các module qua policy và audit; không sở hữu trực tiếp dữ liệu nghiệp vụ.

# Ranh giới dữ liệu

Mỗi module sở hữu validation, service và bảng dữ liệu của mình. Module khác tham chiếu ID hoặc gọi service công khai; không sửa status trực tiếp trong bảng của module khác. Booking là nguồn chuẩn cho khoảng thời gian và giá snapshot; Payment là nguồn chuẩn cho trạng thái sandbox; Listing là nguồn chuẩn cho giá/lịch đang công khai.

# Sự kiện quan trọng

ListingActivated làm mới kết quả tìm kiếm. BookingAccepted giữ lịch. SandboxPaymentAuthorized xác nhận booking. CareMarkedDone yêu cầu chủ nuôi xác nhận. BookingCompleted cho phép review và mô phỏng release. ReportResolved ghi audit.

# Nguyên tắc triển khai

Laravel monolith có module logic theo thư mục/namespace; không tách microservice cho bản mẫu. Giao dịch DB bao các chuyển trạng thái quan trọng; side effects như email hoặc thumbnail chạy sau commit.  
