# PETTEL — Documentation Index

Bộ tài liệu đề xuất cho đồ án \| 26/10/2026 \| 41 Google Docs trong 7 nhóm

# Nguồn và cách diễn giải

Nguồn sản phẩm: Project Overview PETTEL — [Bản sao của Tổng quan dự án PETTEL](<01%20-%20Product%20Document/B%E1%BA%A3n%20sao%20c%E1%BB%A7a%20T%E1%BB%95ng%20quan%20d%E1%BB%B1%20%C3%A1n%20PETTEL.md>) . Nội dung nguồn là bản mẫu sản phẩm; các quy tắc, chỉ tiêu và lựa chọn kỹ thuật trong bộ tài liệu này là đề xuất để thảo luận, chưa phải bằng chứng đã triển khai hay nghiên cứu đã hoàn tất.  
Trong đề bài của thầy, “product/sản phẩm” được quy đổi thành dịch vụ chăm sóc/lưu trú, “seller” thành người chăm/cơ sở, “listing” thành tin dịch vụ, “order” thành booking. Payment sandbox chỉ mô phỏng, không có tiền thật.

# Danh mục tài liệu

01 — Product Document (6 Docs): [01 - Product Document](<01%20-%20Product%20Document/>)  
02 — Domain Documentation (4 Docs): [02 — Domain Documentation](<02%20%E2%80%94%20Domain%20Documentation/>)  
03 — Modules Documentation (10 Docs): [03 — Modules Documentation](<03%20%E2%80%94%20Modules%20Documentation/>)  
04 — Workflows (4 Docs): [04 — Workflows](<04%20%E2%80%94%20Workflows/>)  
05 — Implementation Plan (1 Doc): [05 — Implementation Plan](<05%20%E2%80%94%20Implementation%20Plan/>)  
06 — Non-functional Requirements (6 Docs): [06 — Non-functional Requirements](<06%20%E2%80%94%20Non-functional%20Requirements/>)  
08 — Architecture & Technical Design (10 Docs): [08 — Architecture & Technical Design](<08%20%E2%80%94%20Architecture%20%26%20Technical%20Design/>)

# Quyết định nền tảng

Thí điểm Đà Nẵng; VND dạng số nguyên; lưu UTC, hiển thị Asia/Ho\_Chi\_Minh. P0 một thú cưng/booking và một booking đồng thời/listing. Listing ACTIVE mới công khai; người chăm duyệt yêu cầu; sau accept giữ lịch 15 phút đề xuất. MySQL là nguồn chuẩn; Redis chỉ cache/queue. Video, camera, xác minh thật và thanh toán thật ở ngoài P0.

# Cần chốt trước khi code cuối

Kiểm tra tương thích Laravel 13/Breeze/Vue/Sanctum và các package đã chọn; chính sách hủy/hoàn tiền; thời hạn owner xác nhận hoàn thành; quy trình cấp badge xác minh; khả năng cơ sở nhận nhiều thú cưng cùng lúc; thời hạn lưu ảnh/tin nhắn và hosting demo. Stack đã chốt ở Technology & Tools: Breeze API + Vue 3 SPA + Sanctum; Filament, Scramble và Pulse được sử dụng trong phạm vi nêu tại đó.
