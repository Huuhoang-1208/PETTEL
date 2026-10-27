# FE & BE Structure

PETTEL — Architecture & Technical Design \| Thiết kế đề xuất.

# Frontend

Dùng Vue 3 SPA ở `frontend/` cho giao diện responsive; Laravel 13 ở `backend/` cung cấp JSON API. Laravel Breeze API stack scaffold auth; Sanctum xác thực SPA bằng session cookie. Filament dựng admin panel riêng. Trang Vue: public search/listing detail; owner pets/bookings/conversation; caregiver profile/listings/calendar/bookings. Trang Filament: admin review/reports. Các component dùng chung: search filters, listing card, status badge, price summary, confirmation dialog, form errors. Không nhúng quy tắc giá/quyền vào JS; server trả dữ liệu đã kiểm tra.

# Backend

`routes/api.php` cho dữ liệu nghiệp vụ JSON và `auth:sanctum` trên endpoint riêng tư; `routes/web.php` giữ auth/session/csrf của Breeze và route phục vụ SPA. Vue dùng API client gửi cookie/CSRF, xử lý 401/419. app/Domain/{Identity,Pet,Listing,Booking,Payment,...} hoặc cấu trúc module tương đương gồm Models, Services, Policies, DTO/Events; app/Http/{Controllers,Requests,Resources}. FormRequest validate cú pháp; Policy kiểm tra quyền; Service thực thi quy tắc và transaction; Model giữ quan hệ. Không viết nghiệp vụ trọng yếu trực tiếp trong controller/Vue/Filament Resource.

# Xử lý bất đồng bộ

Queue worker cho ảnh thumbnail/thông báo và job retry; scheduler kiểm tra booking AWAITING\_PAYMENT quá hạn. Job idempotent, dispatch after commit. Mọi job có timeout/retry hữu hạn và failed-job inspection.

# Kiểm thử theo lớp

Feature tests cho Breeze/Sanctum, permission/state/API, transaction test cho accept trùng và callback lặp, browser test cho bốn workflow, unit test cho Smart Match rules. Seed demo tách test data, không dùng PII thật. CI chạy lint/test/migration trên DB thử.
