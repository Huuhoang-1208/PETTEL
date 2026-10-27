# User Research & Success Metrics

PETTEL — Product Document \| Bản thảo 26/10/2026

# Kế hoạch nghiên cứu

Phỏng vấn khám phá: 5–8 chủ nuôi và 3–5 người chăm ở Đà Nẵng; hỏi cách họ tìm/chọn dịch vụ, điều gì tạo niềm tin và dữ liệu thú cưng nào cần chuyển giao. Ghi chủ đề và trích dẫn có đồng ý sử dụng.  
Kiểm thử bản mẫu: 5 chủ nuôi và 3 người chăm thực hiện các tác vụ tìm listing, đặt lịch, xử lý lịch trùng, gửi cập nhật và xác nhận hoàn thành; ghi lỗi và thời gian. Có thể thêm 1–2 người đóng vai admin để rà luồng báo cáo.

# Metric và cách tính

Task completion \= số người hoàn thành tác vụ không cần trợ giúp / tổng người thử. Mục tiêu đề xuất cho bài demo: ≥80% ở tác vụ tìm và tạo booking.  
Time to suitable listing \= thời gian từ bắt đầu tìm đến khi chọn listing đáp ứng kịch bản; dùng median và so với baseline, không đặt mức giảm tùy ý trước khi đo.  
Booking conflict rate \= số lần hệ thống cho hai booking khóa cùng listing/khoảng thời gian / tổng booking được chấp nhận; mục tiêu 0\.  
Price/policy comprehension \= tỷ lệ người thử giải thích đúng giá cuối và quy tắc hủy trước thanh toán; mục tiêu đề xuất ≥80%. Sandbox completion \= tỷ lệ test kịch bản thành công đi đủ REQUESTED→COMPLETED; mục tiêu 100%.

# Sự kiện đo

Ghi \`search\_submitted\`, \`listing\_viewed\`, \`booking\_requested\`, \`booking\_accepted\`, \`sandbox\_payment\_authorized\`, \`booking\_completed\`, \`report\_submitted\`. Analytics chỉ chứa ID nội bộ và thời gian, không chứa tên, địa chỉ hoặc hướng dẫn chăm sóc. Mẫu nhỏ chỉ hỗ trợ phát hiện vấn đề UX, không dùng để suy rộng thống kê.  
