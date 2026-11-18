# Tổ chức frontend PETTEL

```text
src/
├── app/                    Route Next.js, metadata, layout
├── features/
│   ├── home/               Hero, tìm sitter, giới thiệu dịch vụ, nội dung homepage
│   ├── auth/               Đăng nhập, đăng ký, đặt lại mật khẩu, yêu cầu phiên
│   ├── listings/           Tìm kiếm, chi tiết dịch vụ, báo giá và tạo đơn
│   ├── pets/               Danh sách, thẻ và biên tập hồ sơ thú cưng
│   ├── bookings/           Danh sách đơn, trạng thái, chăm sóc và đánh giá
│   ├── caregiver/          Hồ sơ người chăm, dịch vụ, ảnh và lịch trống
│   ├── chat/               Hội thoại, lịch sử, ảnh và hook Reverb
│   ├── admin/              Hàng đợi kiểm duyệt và thao tác duyệt dịch vụ
│   ├── reports/            Form báo cáo dùng ở dịch vụ và đơn chăm sóc
│   └── demo/               Trang video call và live cam mô phỏng
├── components/
│   ├── layout/             SiteHeader dùng chung
│   └── ui/                 Icon, lỗi API, select, hộp xác nhận
├── providers/              SessionProvider và useSession
├── hooks/                  useApi: tải dữ liệu, hủy request, polling
├── lib/
│   ├── api/                client.ts, types.ts, errors.ts, request.ts
│   ├── constants.ts        Nhãn khu vực, loài, dịch vụ, trạng thái
│   ├── date.ts             Chuyển giờ Việt Nam sang ISO và ngược lại
│   └── format.ts           Định dạng tiền, ngày giờ và URL ảnh API
├── styles/                 CSS chung; legacy/ giữ style từ giao diện ban đầu
└── legacy/                 Component, script, fixture cũ để tham khảo
```

## Thêm và sửa chức năng

- File `app/**/page.tsx` chỉ khai báo route, metadata, Suspense khi cần và render
  màn hình từ `features`.
- Mỗi màn hình có file `*-page.tsx`. Các phần giao diện có trách nhiệm riêng đặt
  trong `components/` của chức năng đó, ví dụ `pet-card.tsx`, `pet-editor.tsx`.
- Hook chỉ phục vụ một chức năng đặt trong `features/<chức năng>/hooks/`.
  Hook phục vụ nhiều chức năng đặt trong `src/hooks/`.
- Component dùng chung đặt trong `src/components`. Chỉ tách thành component
  khi nó có trách nhiệm rõ ràng, tránh tạo file chỉ để giảm số dòng.
- Khai báo kiểu dữ liệu trả về từ backend trong `lib/api/types.ts`. Kiểu dữ liệu
  dành riêng cho form hoặc màn hình đặt trong `types.ts` của chức năng.
- Dùng `lib/api/client.ts` cho HTTP, `lib/api/request.ts` cho request ghi có CSRF,
  `hooks/use-api.ts` cho đọc dữ liệu. Tổng giá và quyền truy cập vẫn do backend quyết định.
- Import giữa các thư mục bằng alias `@/`. Đặt `"use client"` trước import trong
  các entry có state, effect hoặc API trình duyệt.

## CSS

Homepage dùng CSS Modules tại `features/home/styles`. CSS chung nằm trong
`src/styles`; `app/globals.css` chỉ chứa danh sách import. Thứ tự import giữ nguyên
thứ tự quy tắc của stylesheet ban đầu, tránh thay đổi giao diện do cascade.
Một số style trong `styles/legacy` vẫn được màn hình thật sử dụng, ví dụ thẻ dịch vụ,
hồ sơ thú cưng và tin nhắn; cần kiểm tra nơi dùng trước khi chỉnh sửa.

## Mã demo cũ

`legacy/components` và `legacy/scripts` lưu giao diện chuyển đổi từ HTML.
Những script cũ dài và phụ thuộc DOM được giữ lại làm tài liệu tham khảo.
Không thêm nghiệp vụ thật ở đây. Các route nghiệp vụ sử dụng `features` và API.
Hai trang `features/demo` còn dùng tiện ích vòng đời và fixture từ `legacy`;
fixture sitter đã được chia thành các nhóm nhỏ trong `legacy/data/sitters`.

## Kiểm tra

```sh
npm run format
npm run format:check
npm run lint
npm run typecheck
npm run build
```

Prettier là công cụ phát triển; không được đưa vào bundle chạy trên trình duyệt.
