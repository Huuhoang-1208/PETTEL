# Performance & Capacity

PETTEL — Non-functional Requirements \| Mục tiêu đề xuất cho demo, cần đo lại với môi trường thực tế.

# Chỉ tiêu

Ở bộ dữ liệu thử 5.000 listing ACTIVE, 10.000 booking và 50 người dùng đồng thời: p95 trang tìm kiếm có lọc ≤2 giây; p95 API ghi booking ≤800 ms khi không tranh chấp; p95 trang chi tiết ≤1 giây, không tính tải ảnh qua mạng chậm. Tỷ lệ lỗi server trong bài test \<1%. Các ngưỡng này chỉ có nghĩa khi ghi cấu hình máy, dữ liệu seed và kịch bản test.

# Cách đạt

Phân trang kết quả, eager loading tránh N+1, index theo status/service/area/time, cache dữ liệu listing công khai ít thay đổi. Booking và payment luôn đọc/ghi DB chuẩn dưới transaction; không dùng cache để quyết định chỗ trống. Ảnh thu nhỏ trước hiển thị, giới hạn 10 MB/ảnh đề xuất.

# Kiểm chứng

Dùng kịch bản tải gồm tìm/lọc, mở listing và tạo booking; báo cáo p50/p95, throughput, tỷ lệ 4xx/5xx, query chậm và CPU/RAM. Test riêng 2 request accept trùng; yêu cầu 1 thành công, 1 conflict. Nếu chưa có số đo, ghi “chưa kiểm chứng” thay vì đạt.  
