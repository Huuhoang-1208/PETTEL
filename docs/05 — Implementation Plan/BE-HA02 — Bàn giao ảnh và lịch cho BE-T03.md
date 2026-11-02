# BE-HA02 — Bàn giao quan hệ ảnh và lịch cho BE-T03

**Bên bàn giao:** Hào — BE-HA02  
**Bên tiếp tục triển khai:** Thịnh — BE-T03  
**Phạm vi:** Quan hệ dữ liệu và quy ước biểu diễn ảnh/lịch trên schema BE-T01.

## 1. Schema và model

Tái sử dụng các bảng hiện có, không tạo lại migration:

- `media_objects`: metadata của file.
- `listing_media`: liên kết listing với ảnh, có `sort_order`.
- `availability_slots`: khoảng mở nhận chăm, có thời điểm bắt đầu/kết thúc và xóa mềm.

Các quan hệ:

- `Listing::media()` trả ảnh theo `sort_order` tăng dần.
- `MediaObject::listings()` trả các listing liên kết.
- `MediaObject::owner()` trả chủ sở hữu media.
- `Listing::availabilitySlots()` trả slot chưa xóa mềm theo `start_at`, sau đó `id`.
- `AvailabilitySlot::listing()` và `creator()` trả listing và người tạo.

`listing_media` không có timestamps; không dùng `withTimestamps()`.

## 2. Quy ước ảnh có thứ tự

Ảnh được biểu diễn bằng `media_object_id` dạng chuỗi và `sort_order` dạng số nguyên. Thứ tự hiển thị tăng dần; phía quản lý sử dụng các vị trí 1, 2, 3…

Ví dụ:

```json
[
    { "media_object_id": "20", "sort_order": 1 },
    { "media_object_id": "15", "sort_order": 2 }
]
```

Database bảo vệ:

- Không gắn trùng cùng media vào cùng listing.
- Không có hai ảnh cùng `sort_order` trong một listing.

File thật nằm trong private storage. Database lưu `object_key`, không lưu public URL cố định hoặc đường dẫn máy.

Media mặc định `PRIVATE`. `PUBLIC_ELIGIBLE` không tự cấp quyền công khai; việc phục vụ ảnh vẫn phải kiểm tra trạng thái listing, trạng thái media và quyền truy cập.

## 3. Quy ước slot khả dụng

Slot là khoảng mở `[start_at, end_at)`, với `start_at < end_at`.

- Nhận timestamp có múi giờ; service chuẩn hóa UTC trước khi gán vào model.
- Database lưu UTC với độ chính xác microsecond.
- Khi biểu diễn qua API, dùng ISO 8601 có `Z` hoặc offset.
- `listing_id` và `created_by_user_id` do server xác định.
- Slot đã xóa mềm không được dùng để nhận booking.

Ví dụ biểu diễn:

```json
{
    "id": "30",
    "start_at": "2030-01-01T01:00:00.123456Z",
    "end_at": "2030-01-01T03:00:00.654321Z"
}
```

Đây là quy ước dữ liệu bàn giao; endpoint và resource ảnh/lịch sẽ được BE-T03 hoàn thiện.

Theo P0, booking phải nằm trọn trong một slot liên tục. Listing chưa có slot không nhận booking. Công suất bằng 1 trên từng listing.

## 4. BE-T03 tiếp tục triển khai

- API upload, gắn, tháo và sắp xếp ảnh.
- Kiểm tra quyền sở hữu listing/media, trạng thái READY, MIME và dung lượng file.
- Quản lý công khai ảnh theo trạng thái và quy trình duyệt listing.
- API tạo, sửa, xóa slot; kiểm tra quyền sở hữu và thời gian.
- Chuẩn hóa các slot không chồng nhau trên cùng listing trong transaction có khóa listing.
- Kiểm tra ảnh hưởng tới booking khi thay đổi lịch.
- Xử lý thứ tự ảnh trong transaction, tuân thủ khóa unique.
- Kiểm thử tích hợp ảnh/lịch với lifecycle listing và booking.

Quan hệ Eloquent không tự thực hiện các kiểm tra nghiệp vụ trên.

## 5. Giới hạn và kiểm chứng

Task 5 cung cấp model, quan hệ và test cấu trúc; chưa cung cấp API upload hoặc CRUD lịch.

Không tự xóa file vật lý khi xóa mềm listing. Khi triển khai dọn media, phải kiểm tra mọi tham chiếu, kể cả tham chiếu đã xóa mềm, pet, care update và các điều kiện retention/booking/report.

`ListingRelationsTest` kiểm tra:

- Thứ tự ảnh và quan hệ ngược.
- Thời gian UTC giữ microsecond.
- Slot xóa mềm bị loại khỏi truy vấn mặc định.
- Quan hệ tách biệt theo listing.
- Xóa mềm listing không tự xóa các bản ghi liên quan.

Kết quả test quan hệ không xác nhận BE-T03 đã hoàn tất hoặc đã được nghiệm thu.
