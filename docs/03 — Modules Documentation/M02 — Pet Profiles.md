# M02 — Pet Profiles

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Chủ nuôi tạo nhiều hồ sơ thú cưng: tên, loài, tuổi/ngày sinh ước tính, giới tính, chế độ ăn, tình trạng tiêm phòng do chủ khai, tính cách và lưu ý đặc biệt. Hồ sơ được dùng để lọc/gợi ý và chụp snapshot hướng dẫn trong booking.

# Quy tắc

Chỉ owner sửa/xóa mềm hồ sơ. Không xóa cứng nếu đã có booking; booking giữ snapshot tại thời điểm đặt. Trường tiêm phòng là thông tin tự khai, không là chứng nhận y tế. Giới hạn độ dài ghi chú và không bắt buộc cung cấp dữ liệu không cần thiết.

# Dữ liệu và quyền

pets.user\_id, species, birth\_date\_or\_age, sex, diet\_notes, vaccination\_note, temperament, special\_needs, status. Chỉ chủ nuôi xem toàn bộ. Người chăm của booking được xem dữ liệu cần thiết cho booking đó; pet không xuất hiện công khai trong search.

# Tiêu chí chấp nhận

Một owner tạo được nhiều pet và chọn đúng pet khi tìm/đặt. Người chăm không mở pet profile của khách không có booking với mình. Sửa pet sau khi đặt không âm thầm thay đổi hướng dẫn booking đã gửi.  
