# Domain Overview & Glossary

PETTEL — Domain Documentation \| Bản thảo 26/10/2026

# Bối cảnh nghiệp vụ

PETTEL kết nối chủ nuôi cần dịch vụ chăm sóc với người chăm/cơ sở tại Đà Nẵng. Listing mô tả dịch vụ và lịch; Booking là yêu cầu dùng một listing trong một khoảng thời gian cho một thú cưng; Payment là bản ghi sandbox; Conversation/Update hỗ trợ theo dõi; Review/Report tạo phản hồi và quản trị.

# Thuật ngữ chuẩn

Pet profile: hồ sơ do chủ nuôi quản lý, ghi nhu cầu chăm sóc; không phải hồ sơ y tế được xác minh.  
Caregiver profile: thông tin cá nhân/cơ sở người chăm; không mặc nhiên là xác minh danh tính hoặc lý lịch.  
Listing: tin dịch vụ có loại chăm sóc, giá, điều kiện và lịch. “Product” trong đề bài được hiểu là dịch vụ này, không phải thú cưng để bán.  
Availability: khoảng thời gian người chăm khai báo có thể nhận; chưa đảm bảo còn chỗ cho đến khi booking được chấp nhận và khóa lịch.  
Booking / Order: lượt đặt lịch cho một listing và một thú cưng. Booking khác Payment; một booking có thể có nhiều payment attempt khi thử lại.  
Authorized / Released / Refunded: trạng thái giao dịch mô phỏng trong sandbox; không có tiền thật hay escrow hợp pháp.  
Care update: bản cập nhật chữ/ảnh gắn với booking; không phải camera trực tiếp. Verified review: đánh giá gắn với booking hoàn tất, không đồng nghĩa xác minh người chăm.  
Report: báo cáo nội dung, người dùng hoặc booking cần admin xem; chưa phải quy trình tranh chấp tài chính.

# Quy ước chung

Lưu thời gian bằng UTC, hiển thị theo Asia/Ho\_Chi\_Minh. Khoảng thời gian là \[start\_at, end\_at): đầu gồm, cuối không gồm. Tiền VND lưu số nguyên. Dữ liệu thú cưng và địa chỉ không đưa vào tìm kiếm công khai.  
