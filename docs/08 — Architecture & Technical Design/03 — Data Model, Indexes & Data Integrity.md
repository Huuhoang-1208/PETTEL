# Data Model, Indexes & Data Integrity

PETTEL — Architecture & Technical Design \| Sơ đồ bảng đề xuất cho MySQL.

# Bảng chính

users(id, email unique, password, status, deletion\_requested\_at); user\_roles(user\_id, role, assigned\_at); media\_objects(id, owner\_user\_id, object\_key unique, mime\_type, byte\_size, sha256, visibility, state); pets(id, user\_id FK, species, age/birth, notes, status ACTIVE/ARCHIVED, photo\_media\_id FK, deleted\_at); caregiver\_profiles(id, user\_id unique FK, type, display\_name, area\_code, deleted\_at); listings(id, caregiver\_profile\_id FK, service\_type, price\_vnd, price\_unit, status, area\_code, capacity=1, version, deleted\_at); listing\_change\_requests(id, listing\_id FK, base\_listing\_version, proposed\_data\_json, status PENDING\_REVIEW/APPROVED/REJECTED/WITHDRAWN); availability\_slots(id, listing\_id FK, start\_at, end\_at).
bookings(id, owner\_id FK, pet\_id FK, listing\_id FK, caregiver\_user\_id FK, start\_at, end\_at, price snapshot, pet/listing/policy snapshots, status, payment\_due\_at, current\_payment\_attempt\_id FK, request idempotency key/hash, version); booking\_status\_events(id, booking\_id FK, from\_status, to\_status, actor\_id, source, reason, created\_at); payment\_attempts(id, booking\_id FK, attempt\_no, amount\_vnd, status, provider\_ref, scoped idempotency\_key/hash); payment\_events(id, provider, external\_event\_id, payment\_attempt\_id FK, payload\_hash, processing\_status).
messages(id, booking\_id FK, sender\_id FK, body, visibility, created\_at); care\_updates(id, booking\_id FK, sender\_id FK, body, media\_object\_id FK nullable, visibility, created\_at); reviews(id, booking\_id unique FK, owner\_id FK, listing\_id FK, rating, body, status, is\_demo); reports(id, reporter\_user\_id FK, exactly one FK target among listing/review/message/booking, reason, status); moderation\_actions(id, report\_id FK, admin\_user\_id FK, action, reason, created\_at). Audit uses the shared audit\_logs table.

# Index và ràng buộc

Index listings(status, service\_type, area\_code, price\_vnd, id), bookings(listing\_id, status, start\_at, end\_at), bookings(owner\_id, created\_at, id), availability\_slots(listing\_id, start\_at, end\_at), messages(booking\_id, created\_at, id), reports(status, created\_at, id). Unique email, caregiver\_profiles.user\_id, reviews.booking\_id, payment event `(provider, external\_event\_id)` and scoped idempotency keys. MySQL 8.4 enforces VARCHAR status/code values, price/range/rating and one-report-target CHECK constraints.

# Chống lịch trùng

MySQL không tự bảo đảm exclusion theo khoảng: khi accept, transaction \`SELECT ... FOR UPDATE\` trên row listing để serialize thao tác của cùng listing, sau đó query booking blocking states với điều kiện \`existing.start\_at \< new.end\_at AND existing.end\_at \> new.start\_at\`; insert/update rồi commit. Test hai request đồng thời. Không dựa vào Redis lock duy nhất.

# Toàn vẹn lịch sử

Không cascade xóa booking/payment/review khi pet/listing được xóa mềm. Snapshot giá/hướng dẫn/chính sách bảo vệ lịch sử. Migration thay schema phải có kế hoạch rollback/backup.
