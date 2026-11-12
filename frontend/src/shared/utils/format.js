export function formatVnd(value) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(Number(value ?? 0));
}

export function labelService(type) {
  return type === 'BOARDING' ? 'Lưu trú qua đêm' : type === 'DAYCARE' ? 'Chăm sóc ban ngày' : type;
}

export function labelArea(area) {
  return ({ DN_CENTER: 'Hải Châu, Đà Nẵng', DN_NORTH: 'Phía Bắc Đà Nẵng', DN_SOUTH: 'Phía Nam Đà Nẵng', DN_WEST: 'Thanh Khê, Đà Nẵng', DN_EAST: 'Sơn Trà, Đà Nẵng' })[area] ?? area;
}

export function labelBookingStatus(status) {
  return ({ REQUESTED: 'Chờ người chăm phản hồi', AWAITING_PAYMENT: 'Chờ thanh toán mô phỏng', CONFIRMED: 'Đã xác nhận', IN_PROGRESS: 'Đang chăm sóc', AWAITING_CONFIRMATION: 'Chờ bạn xác nhận hoàn tất', COMPLETED: 'Đã hoàn tất', CANCELLED: 'Đã hủy' })[status] ?? status;
}

export function labelWorkflowStatus(status) {
  return ({ PENDING: 'Đang chờ xử lý', PROCESSING: 'Đang xử lý', MANUAL_REVIEW: 'Cần nhân viên xem xét', CANCELLATION_PENDING: 'Đang xử lý yêu cầu hủy', AUTHORIZED: 'Đã xác thực thanh toán mô phỏng', FAILED: 'Thất bại', VOIDED: 'Đã hủy khoản thanh toán mô phỏng', REFUNDED: 'Đã hoàn khoản thanh toán mô phỏng', RELEASED: 'Đã hoàn tất khoản thanh toán mô phỏng', VISIBLE: 'Đang hiển thị', HIDDEN: 'Đã ẩn', OPEN: 'Đang mở', IN_REVIEW: 'Đang xem xét', RESOLVED: 'Đã xử lý', DISMISSED: 'Đã đóng' })[status] ?? status;
}

export function dateTimeWithOffset(value) {
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) return `${value}:00+07:00`;
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/.test(value)) return `${value}+07:00`;
  throw new TypeError('Thời gian phải ở định dạng ngày giờ Việt Nam.');
}

export function parseVnDateTime(value) {
  return new Date(dateTimeWithOffset(value));
}

export function currentVnLocalInput(now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(now);
  const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
  return `${values.year}-${values.month}-${values.day}T${values.hour}:${values.minute}`;
}

export function addHoursToVnLocalInput(value, hours) {
  const [date, time] = value.split('T');
  const [year, month, day] = date.split('-').map(Number);
  const [hour, minute] = time.split(':').map(Number);
  return new Date(Date.UTC(year, month - 1, day, hour, minute) + hours * 3_600_000).toISOString().slice(0, 16);
}
