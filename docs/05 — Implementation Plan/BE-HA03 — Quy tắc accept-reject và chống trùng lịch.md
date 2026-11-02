# BE-HA03 — Quy tắc accept/reject và chống trùng lịch

Owner: Hào. Reviewer: Hoàng. Phạm vi: M05 — Booking acceptance. Lịch N6–N9, timebox 10 giờ. Phụ thuộc BE-H02 và lịch/trạng thái listing từ BE-T03.

## Trạng thái quyết định

Ngày 02/11/2026, người dùng đồng ý các quy tắc cho task 1. Nguồn chuẩn: [P0 §12](<../02 — Domain Documentation/05 — P0 Decision Contract.md>); chi tiết HTTP/quyền/replay tại [API M05 accept/reject](<../08 — Architecture & Technical Design/04 — API Contracts & Error Conventions.md>). Đây là bản chốt nghiệp vụ để triển khai và viết test; chưa xác nhận Hoàng đã review, endpoint đã triển khai hoặc test đã đạt.

| Nội dung task 1 | Quyết định |
| --- | --- |
| Trạng thái/bước chuyển | Accept REQUESTED chưa hết hiệu lực → AWAITING_PAYMENT, hạn 15 phút. Reject → CANCELLED, event CAREGIVER_REJECTED. Không thêm ACCEPTED/REJECTED. |
| Đối tượng chống trùng | Cùng listing_id, capacity=1; chưa chặn overlap giữa hai listing cùng caregiver. |
| Trạng thái giữ lịch | AWAITING_PAYMENT, CONFIRMED, IN_PROGRESS, AWAITING_CONFIRMATION. REQUESTED/CANCELLED/COMPLETED không giữ lịch. |
| Ranh giới | UTC/microsecond, khoảng [start_at,end_at); end = next start không xung đột. Một slot mở chưa xóa mềm phải bao trọn. |
| Sai caregiver | 404 NOT_FOUND sau auth; kiểm tra quyền trước chi tiết trạng thái/lịch. |
| Sai trạng thái/listing ngừng nhận | 409 INVALID_STATE; listing ngừng nhận vẫn cho reject yêu cầu đang chờ hợp lệ. |
| Thiếu slot/trùng lịch | 409 AVAILABILITY_CONFLICT. |
| Hết hiệu lực REQUESTED | Tại/sau min(created_at + 24 giờ, start_at), accept trả 409 REQUEST_EXPIRED dù job chưa chạy. |
| Thao tác lặp | Accept lặp trả INVALID_STATE, không gia hạn. Replay chính lần reject trước của cùng caregiver trả 200, không thêm event; hủy vì lý do khác trả INVALID_STATE. |
| Thay đổi listing | Chỉ đổi giá: giữ snapshot. Đổi dịch vụ/điều kiện nhận pet trọng yếu: accept trả INVALID_STATE, yêu cầu booking mới. |
| Hold quá hạn | Không bỏ qua booking vẫn giữ lịch chỉ vì payment_due_at đã qua; phối hợp BE-T04 chuyển trạng thái/void/refund trước khi thả lịch. |
| Tính toàn vẹn | DB transaction/khóa chung listing; booking, payment_due_at và event ghi nguyên tử; không dùng cache quyết định. |

## Bàn giao triển khai

- Dùng [BookingFixture của BE-H02](<BE-H02 — Review và bàn giao.md>) để tạo REQUESTED qua API; chuyển phiên sang caregiver trước accept/reject.
- Thống nhất thứ tự khóa giữa accept, sửa slot/lifecycle BE-T03 và expiry/cancel/payment BE-T04; không chỉ khóa từng booking riêng lẻ. Kiểm tra lại nguồn sau khi lấy khóa, bảo vệ cả khi chưa có booking giữ lịch.
- Accept/reject ghi event trong cùng transaction; event có actor/source đúng, rollback toàn bộ nếu ghi event lỗi. Replay reject chỉ đọc kết quả đã lưu, không ghi lại event/snapshot.
- Hủy sau accept dùng luồng cancel/payment, không dùng reject để bỏ qua void/refund. Giữ quy tắc auth và bảo vệ tài nguyên riêng hiện có.

## Checklist kiểm thử nghiệm thu — chưa thực hiện

- [ ] Accept hợp lệ: AWAITING_PAYMENT, hạn đúng 15 phút, một event mới; snapshot không đổi.
- [ ] Reject hợp lệ: CANCELLED, event USER với caregiver và CAREGIVER_REJECTED.
- [ ] Sai caregiver trả 404, không lộ trạng thái/lịch và không thay dữ liệu; auth/role/CSRF theo contract hiện có.
- [ ] Listing ngừng ACTIVE: accept trả INVALID_STATE, reject hợp lệ vẫn thành công.
- [ ] Accept/reject sai trạng thái trả INVALID_STATE; reject sau hủy vì nguyên nhân khác không được coi là replay.
- [ ] Accept lặp không gia hạn/event trùng; hai reject đồng thời của cùng caregiver chỉ ghi một event, request replay trả 200.
- [ ] REQUESTED trước mốc hết hiệu lực có thể accept khi các điều kiện khác hợp lệ; tại/sau mốc trả REQUEST_EXPIRED dù job chưa chạy.
- [ ] Hai REQUESTED overlap cùng tồn tại; hai accept đồng thời cùng listing đúng một thắng, còn lại AVAILABILITY_CONFLICT và vẫn REQUESTED, không event accept.
- [ ] Hai booking overlap khác listing của cùng caregiver không bị chặn bởi quy tắc công suất.
- [ ] Cả bốn trạng thái giữ lịch chặn overlap; REQUESTED/CANCELLED/COMPLETED không chặn.
- [ ] Hai khoảng tiếp giáp không xung đột; overlap thực sự bị chặn; giữ độ chính xác UTC/microsecond.
- [ ] Thiếu slot hoặc không có một slot bao trọn trả AVAILABILITY_CONFLICT, không tự ghép slot rời.
- [ ] Chỉ đổi giá vẫn giữ giá/snapshot cũ; đổi dịch vụ/điều kiện trọng yếu trả INVALID_STATE, không sửa snapshot.
- [ ] AWAITING_PAYMENT quá hạn nhưng chưa chuyển trạng thái vẫn chặn lịch; sau quy trình expiry thả lịch đúng một lần, thống nhất create/search/accept.
- [ ] Lỗi ghi event rollback booking/hạn thanh toán; không có cập nhật một phần.

Test đồng thời dùng DB thực và dữ liệu đã commit để các kết nối cùng đọc được; không dùng mock khóa thay cho kiểm chứng cạnh tranh. Chưa chạy các test trên trong bước cập nhật tài liệu này.

## Xác nhận review

- Quy tắc: người dùng đã đồng ý ngày 02/11/2026.
- Hoàng xác nhận review: CHƯA XÁC NHẬN.
- Commit triển khai / link PR / kết quả CI: CHƯA GHI.
- Các việc tích hợp còn lại: BE-T03 lịch/lifecycle; BE-T04 expiry/cancel/payment; kiểm thử đồng thời và rollback của BE-HA03.
