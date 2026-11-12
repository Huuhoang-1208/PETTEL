# Phân tích tham chiếu Petty (Community)

**Ngày khảo sát:** 12/11/2026
**Nguồn:** Figma `Petty (Community)`, file `iGPOoHttMEB4wOlwPHzXyd`
**Phạm vi đọc:** các page `Style Guide`, `Design`, `Icon`, `Component`.

## 1. Style Guide

| Nhóm | Giá trị quan sát được | Cách dùng trong PETTEL |
|---|---|---|
| Neutrals (`neu`) | `#263948`, `#ABDCFC`, `#FBD97D`, `#010911`, `#FFFFFF`, `#E2E2E2`, `#D42323`, `#9A9A9A` | Mực chữ đậm, nền/điểm nhấn nhẹ, trắng bề mặt, xám viền và đỏ trạng thái lỗi. |
| Primary (`prime`) | `#C48590`, `#474672` | Hồng mauve cho điểm nhấn và indigo cho hành động chính. |
| Background (`BG`) | `#FBEDEC` | Nền hồng phấn ấm của các màn hình mobile. |
| Secondary (`sec`) | `#B1E0D8`, `#D4A7C7`, `#BBB7EA`, `#FDD2A4`, `#FBAE9E` | Nhãn phân loại, nền ảnh/card phụ và các điểm nhấn nhỏ. |
| Tertiary (`thir`) | `#BED0C5`, `#8FC8EE`, `#FA9D8B`, `#918BDB`, `#D691C2` | Bảng màu bổ sung; chỉ dùng điểm xuyết, không thay màu trạng thái nghiệp vụ. |

Typography dùng **SF Pro**. Các mẫu đọc được gồm 14 px Bold/Semibold/Medium và 12 px Semibold; line-height 20 px, letter spacing 0%, paragraph spacing 0. Trong trình duyệt và máy không có SF Pro, prototype dùng system font tương thích.

## 2. Design

- Các artboard mobile quan sát có chiều rộng 390 px. Bố cục thường có thanh tiêu đề, nội dung cuộn, thẻ ảnh lớn và thanh điều hướng dưới.
- Trang Home dùng logo script, nền pastel, ảnh thú cưng, các nhóm nội dung theo hàng và thẻ trắng bo tròn.
- Trang Service tách rõ danh sách dịch vụ, chi tiết dịch vụ và form yêu cầu. Chi tiết ghép bảng giá, ảnh, mô tả, hồ sơ người chăm và nút reservation ở cuối.
- Trang Pet đặt hồ sơ thú cưng thành card ngang có ảnh/avatar tròn và thông tin ngắn; nội dung đơn hàng được chia theo nhóm trạng thái.
- Mẫu dùng nhiều khoảng trắng và ảnh, ưu tiên cấu trúc card; màu bão hòa tập trung ở CTA, badge và icon.

**Áp dụng cho PETTEL:** giữ nguyên cấu trúc desktop/web và toàn bộ luồng Owner/Caregiver hiện tại; dùng palette, hình dạng, typography, icon line và phân cấp card của Petty. Không chuyển prototype thành ứng dụng mua sắm hoặc sao chép các luồng shopping/cart của mẫu.

## 3. Component

| Component quan sát được | Đặc điểm | Áp dụng |
|---|---|---|
| Primary button | Indigo `#474672`, dáng pill, mẫu 248×56 px, radius 83 px, có bóng trong nhẹ. | CTA PETTEL dạng pill, co giãn theo breakpoint và nội dung. |
| Text fields | Nền trắng, góc bo nhẹ, bóng mờ; có biến thể search, select, suffix đơn vị và nhiều dòng. | Giữ input/select/textarea hiện có; thống nhất radius, viền, focus ring và shadow. |
| Service card | Ảnh lớn phía trên, tiêu đề trên nền trắng; ảnh có thể được gom thành carousel/list. | Giữ ảnh mock PETTEL và dữ liệu dịch vụ; tạo cảm giác ảnh/card sáng, bo mềm. |
| Pet profile | Card ngang nền trắng, ảnh tròn, tên và thuộc tính; mẫu 350×144 px, radius 50 px, padding 16 px. | Tạo card hồ sơ dễ quét trong trang thú cưng và booking. |
| Bottom nav | Thanh indigo, icon/nhãn trắng, tab đang chọn nổi thành vòng tròn mauve. | Chỉ tham khảo cách phối màu và icon; web PETTEL vẫn dùng điều hướng hiện hữu phù hợp viewport. |
| Review/feedback | Avatar, tên, sao, đoạn nhận xét và ảnh đính kèm. | Giữ dữ liệu và hành vi đánh giá; làm mềm bề mặt và chuyển sao sang màu vàng ấm. |

## 4. Icon

Page Icon phân nhóm icon line theo Essentials, Interactions, Navigation & Transportation, Animals, Food, Messages và các nhóm khác. Nhóm Animals có dog/cat/paw; Navigation có pin, map, route, directions và my location. Nét icon đơn sắc, tương phản cao, đầu nét bo tròn. Prototype dùng SVG line-size nhỏ cho điều hướng, tìm kiếm và vị trí; màu icon kế thừa màu chữ/brand, trạng thái hoạt động dùng mauve/indigo.

## 5. Ranh giới và giới hạn tham chiếu

- Tài khoản hiện chỉ xem được file trong trình duyệt; Figma MCP báo không có quyền edit, vì vậy không tạo/sửa nội dung Figma và không tải riêng asset qua MCP.
- Ảnh screenshot chỉ được dùng làm tham chiếu thị giác. Prototype tiếp tục dùng SVG mock nội bộ; màu SVG được điều chỉnh để hợp palette mới.
- Page `Component` có viền nét đứt tím để phân nhóm trong thư viện; đó là khung tổ chức của file Figma, không đưa viền đó vào giao diện PETTEL.
- File prompt được yêu cầu (`docs/agent-prompts/petty-visual-redesign.md`) không có trong checkout. Người dùng đã chọn tiếp tục theo mô tả trực tiếp trong tin nhắn; phần triển khai bám phạm vi đó và ghi giới hạn này vào bàn giao.

## 6. Checkpoint triển khai

1. **Khảo sát Figma:** hoàn tất; đã đọc Style Guide, Home/Service/Pet trên Design, nhóm Animals/Navigation trên Icon và các component button/field/service/profile/navigation/review.
2. **Đối chiếu prototype:** giữ nguyên routes, mock data, rules và handler nghiệp vụ; thay lớp tokens, stylesheet trình bày và icon tĩnh.
3. **Redesign:** áp palette Petty lên luồng user-facing; giữ Admin reference trong panel màu trung tính kiểu Filament.
4. **Kiểm chứng:** chạy kiểm tra syntax và smoke checks; browser walkthrough bị chặn bởi chính sách truy cập local preview đã nêu trong `TEST-RESULTS.md`.
