# 02 — Legacy PETTEL UI Audit

**Trạng thái audit:** INCOMPLETE — nguồn legacy chưa truy cập được.  
**Repository được yêu cầu:** <https://github.com/quangnguyentan/pettel>  
**Ngày thử truy cập:** 11/11/2026 (Asia/Ho_Chi_Minh).

## 1. Kết quả tiếp cận nguồn

| Cách tiếp cận | Kết quả |
|---|---|
| Mở repository GitHub qua trình duyệt tra cứu | Bị chặn bởi môi trường (`DisabledError`) |
| Mở raw README ở nhánh `main` và `master` | Cả hai không fetch được (`DisabledError`) |
| `git ls-remote --heads https://github.com/quangnguyentan/pettel.git` | Thất bại: không phân giải được `github.com` trong môi trường shell |
| Tìm checkout legacy trong workspace | Không thấy; workspace hiện là repository PETTEL-Web, remote `Huuhoang-1208/PETTEL` |

Vì không có HTML/CSS/JS/assets hoặc browser preview của website tiền thân trong nguồn truy cập được, không thể xác nhận cấu trúc trang, màu/font, logo, component, interactions hay responsive. Audit này không suy diễn giao diện từ tên repository hoặc các screenshot/ảnh không được xác nhận là nguồn legacy.

## 2. Matrix đánh giá kế thừa

`UNKNOWN` ở bảng dưới nghĩa là chưa có bằng chứng để phân loại, không phải đánh giá tiêu cực về thiết kế cũ.

| Hạng mục legacy cần kiểm tra | Phân loại | Bằng chứng hiện có | Việc cần làm khi có source |
|---|---|---|---|
| Logo/brand, palette, typography | UNKNOWN | Không đọc được repo/assets | Kiểm tra CSS variables, font files, logo và screenshot; ghi màu/font đo được, không phỏng đoán |
| Header, navigation, footer, page layout | UNKNOWN | Không có HTML/page capture | Lập page inventory và role/route mapping |
| Listing/service cards và detail | UNKNOWN | Không có source hoặc browser preview | So với P0 listing fields, availability, price, rules, review evidence |
| Pet profile và caregiver profile | UNKNOWN | Không có source | Kiểm tra field, privacy, form patterns và khả năng tiếp cận |
| Forms, buttons, tabs, badges, modal, toast, table | UNKNOWN | Không có source | Kiểm tra states, keyboard behavior, error/help copy, responsive |
| Search/filter/booking/payment interactions | UNKNOWN | Không có JS source | Chỉ so sánh visual; không kế thừa logic nếu khác current domain/API |
| Messaging/care update/review/report/admin | UNKNOWN | Không có source | So với P0 workflow; Admin hiện thuộc Filament boundary |
| Images, icons, illustration, media treatment | UNKNOWN | Không có asset inventory | Kiểm tra license/provenance, privacy, crop/alt text |
| Responsive behavior/mobile navigation | UNKNOWN | Không chạy được desktop/mobile | Kiểm tra viewport thực tế, tối thiểu 320 px và keyboard/zoom |
| Accessibility | UNKNOWN | Không thể kiểm tra browser/DOM | Audit keyboard, focus, labels, contrast, screen reader và semantics |

## 3. Quyết định thiết kế hiện thời

- Không ghi nhận `REUSE`, `ADAPT`, `REDESIGN` hoặc `REMOVE` cho bất kỳ visual component legacy nào cho đến khi có source/evidence.
- `NEW` chỉ áp dụng có điều kiện cho nhu cầu hiện được P0 yêu cầu nhưng chưa có bằng chứng legacy hỗ trợ: booking lifecycle đầy đủ, payment sandbox rõ nhãn, care updates riêng tư, report workflow/audit và role-specific states. So sánh cuối cùng với legacy vẫn **UNKNOWN**.
- Dùng current product/domain docs làm nguồn xác định nội dung và tương tác; website tiền thân chỉ là visual reference.
- Chưa đề xuất giá trị màu, font, spacing hoặc logo cho legacy. Design system mới có thể dùng tokens **PROPOSED**, nhưng phải phân biệt chúng với tokens legacy đã đo được.

## 4. Nguồn cần bổ sung để hoàn tất audit

Một trong các phương án sau sẽ mở khóa audit:

1. Cho phép mạng/GitHub truy cập repo `quangnguyentan/pettel`, hoặc cung cấp zip/checkout read-only; hoặc
2. Cung cấp screenshot desktop/mobile của các trang chính cùng assets/CSS source cần phân tích.

Khi nhận được, khảo sát DOM/CSS/JS và chạy trang trong browser nếu khả thi; cập nhật bảng Reuse/Adapt/Redesign/Remove/New có dẫn chứng file/page và ghi rõ các thành phần không thể chuyển tiếp do domain mới.

## 5. Giới hạn ảnh hưởng đến prototype package

Design direction trong các tài liệu tiếp theo hiện có thể chốt về UX/domain consistency, accessibility targets và responsive behavior; chưa thể chốt tính kế thừa thương hiệu từ legacy. `07-design-system.md` phải gắn token thị giác mới là `PROPOSED` cho đến khi có audit nguồn cũ hoặc quyết định brand riêng.

