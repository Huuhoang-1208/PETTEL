# M04 — Discovery, Search & Smart Match

PETTEL — Modules Documentation \| Bản thảo 26/10/2026

# Trách nhiệm

Tìm listing ACTIVE theo từ khóa, khu vực Đà Nẵng, loại dịch vụ, ngày, khoảng giá, điều kiện nhận thú cưng và tín hiệu đánh giá. Smart Match dùng pet được chọn để đề xuất bộ lọc/tiêu chí; người dùng vẫn sửa được.

# Logic P0

Quy tắc có thể giải thích: loài và kích cỡ được nhận theo `acceptance_rules_json` v1 (`schema_version=1`, `species`, `sizes`), lịch có thể nhận và tổng giá cho khoảng chọn. Theo quyết định 02/11/2026, phiên bản đầu chưa tự lọc theo tuổi/nhu cầu đặc biệt hoặc tuyên bố đã kiểm tra hỗ trợ; thông tin này vẫn được chia sẻ trong snapshot booking theo quyền. Pet thiếu size không tự match listing giới hạn size; JSON điều kiện sai/không hỗ trợ không được coi là nhận tất cả. Không tính “điểm tin cậy” tổng hợp hoặc khẳng định AI. Nếu không có pet, dùng lọc thủ công. Xem P0 Contract §8.

# Phụ thuộc và dữ liệu

Đọc bản công khai của Listing, Availability và aggregate review đủ điều kiện; pet profile chỉ khi owner cho phép qua phiên đăng nhập. Kết quả không chứa ghi chú riêng tư của pet. Thứ tự mặc định P0: listing đạt điều kiện cứng trước, sau đó giá tổng tăng dần và ID tăng dần để ổn định phân trang.

# Tiêu chí chấp nhận

Listing không ACTIVE không hiện; listing nhận booking còn cần profile chưa xóa mềm và user caregiver ACTIVE/còn CAREGIVER. Bộ lọc ngày loại bỏ lịch bị khóa. Kết quả cho biết vì sao phù hợp hoặc thiếu điều kiện theo loài/kích cỡ/lịch/giá, không suy diễn tuổi/nhu cầu đặc biệt là đã phù hợp; không gắn nhãn Verified cho seed. Tìm trống có lời giải thích và gợi ý nới lọc, không tự bịa listing.

# Danh mục P0

Filter `service_type` nhận DAYCARE/BOARDING, `species` nhận DOG/CAT/OTHER, `size` nhận SMALL/MEDIUM/LARGE và `area_code` nhận mã vùng nội bộ. Giá so sánh theo tổng tiền server tính cho khoảng chọn, không so đơn giá khác đơn vị. Vùng là lựa chọn do caregiver khai báo, không suy ra từ địa chỉ chi tiết. Xem [P0 Decision Contract](<../02 — Domain Documentation/05 — P0 Decision Contract.md>).
