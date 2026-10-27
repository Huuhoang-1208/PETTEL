# Khám phá & tìm sản phẩm

PETTEL — Workflows \| “Sản phẩm” \= dịch vụ chăm sóc/lưu trú, không phải thú cưng để bán.

# Điều kiện đầu vào

Khách hoặc chủ nuôi truy cập trang tìm kiếm. Chỉ listing ACTIVE tại Đà Nẵng được hiển thị. Chủ nuôi có thể chọn một pet profile của mình; khách chưa đăng nhập dùng bộ lọc thủ công.

# Luồng chính

1\. Người dùng chọn loại dịch vụ (chăm sóc ban ngày/lưu trú), khu vực, ngày/giờ và khoảng giá; nếu đăng nhập, có thể chọn pet để Smart Match gợi ý tiêu chí.  
2\. Hệ thống chuẩn hóa bộ lọc, lấy listing ACTIVE và loại kết quả không đáp ứng điều kiện cứng: loài được nhận, lịch và giá. Smart Match chỉ là quy tắc có thể giải thích; người dùng có thể sửa lọc.  
3\. Trang kết quả cho thấy giá, khu vực, loại dịch vụ, tín hiệu review từ booking hoàn tất, nhãn demo/xác minh đúng trạng thái và lý do phù hợp. Người dùng mở chi tiết để xem lịch, điều kiện, chính sách và ảnh.  
4\. Người dùng chọn dịch vụ và chuyển sang tạo booking. Hệ thống kiểm tra lại availability khi người chăm accept; kết quả tìm kiếm không phải cam kết giữ chỗ.

# Nhánh lỗi và ngoại lệ

Không có kết quả: giải thích bộ lọc nào quá hẹp và cho nới lọc. Listing vừa bị SUSPENDED: không cho tạo yêu cầu, trả thông báo cập nhật. Pet không thuộc người dùng: 403\. Kết quả cache cũ: bước tạo booking kiểm tra DB mới nhất.

# Điểm kiểm thử

Không lộ DRAFT/SUSPENDED; không lộ ghi chú riêng tư của pet; lọc ngày không trả listing đã khóa lịch; thứ tự và lý do phù hợp ổn định với cùng dữ liệu.

# Danh mục và giá P0

Chỉ có DAYCARE (giá theo giờ) và BOARDING (giá theo block 24 giờ lưu trú). Bộ lọc giá dùng tổng giá server tính cho khoảng đã chọn, không so đơn giá khác đơn vị. Vùng Đà Nẵng là mã nội bộ do caregiver chọn; địa chỉ chi tiết không công khai. Xem [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
