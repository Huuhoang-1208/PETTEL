# Data Model, Indexes & Data Integrity

PETTEL — Architecture & Technical Design \| Sơ đồ bảng đề xuất cho MySQL.

# Bảng chính

users(id, email unique, password\_hash, roles, status); pets(id, user\_id FK, species, age/birth, diet\_notes, vaccination\_note, special\_needs, deleted\_at); caregiver\_profiles(id, user\_id unique FK, type, display\_name, area); listings(id, caregiver\_profile\_id FK, service\_type, price\_vnd, status, area, capacity=1, deleted\_at); availability\_slots(id, listing\_id FK, start\_at, end\_at).  
bookings(id, owner\_id FK, pet\_id FK, listing\_id FK, start\_at, end\_at, price\_snapshot\_vnd, pet\_snapshot\_json, policy\_snapshot\_json, status, payment\_due\_at, version); booking\_status\_events(id, booking\_id FK, from\_state, to\_state, actor\_id, reason, created\_at); payment\_attempts(id, booking\_id FK, amount\_vnd, status, provider\_ref unique nullable, idempotency\_key unique); payment\_events(id, external\_event\_id unique, payment\_attempt\_id FK, payload\_hash, status).  
messages(id, booking\_id FK, sender\_id FK, body, created\_at); care\_updates(id, booking\_id FK, sender\_id FK, body, media\_id nullable); media\_objects(id, owner\_id, object\_key unique, mime, bytes, visibility); reviews(id, booking\_id unique FK, rating, body, status); reports(id, reporter\_id, target\_type, target\_id, reason, status); moderation\_actions(id, report\_id, admin\_id, action, reason, created\_at).

# Index và ràng buộc

Index listings(status, service\_type, area, price\_vnd), bookings(listing\_id, status, start\_at, end\_at), bookings(owner\_id, created\_at), availability\_slots(listing\_id, start\_at, end\_at), messages(booking\_id, created\_at), reports(status, created\_at). Unique email, caregiver\_profiles.user\_id, reviews.booking\_id, payment event ID và idempotency key. CHECK giá ≥0, start\_at \< end\_at, rating 1..5 nếu DB hỗ trợ đúng.

# Chống lịch trùng

MySQL không tự bảo đảm exclusion theo khoảng: khi accept, transaction \`SELECT ... FOR UPDATE\` trên row listing để serialize thao tác của cùng listing, sau đó query booking blocking states với điều kiện \`existing.start\_at \< new.end\_at AND existing.end\_at \> new.start\_at\`; insert/update rồi commit. Test hai request đồng thời. Không dựa vào Redis lock duy nhất.

# Toàn vẹn lịch sử

Không cascade xóa booking/payment/review khi pet/listing được xóa mềm. Snapshot giá/hướng dẫn/chính sách bảo vệ lịch sử. Migration thay schema phải có kế hoạch rollback/backup.  
