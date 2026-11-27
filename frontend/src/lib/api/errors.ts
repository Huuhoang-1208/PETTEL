import axios from "axios";

export const unauthorized = (error: unknown) =>
  axios.isAxiosError(error) && error.response?.status === 401;

const codeMessages: Record<string, string> = {
  AVAILABILITY_CONFLICT: "Khung giờ này vừa không còn trống. Bạn hãy chọn thời gian khác nhé.",
  REQUEST_EXPIRED:
    "Yêu cầu đặt lịch này đã hết hạn. Hãy tạo yêu cầu mới nếu bạn vẫn muốn đặt dịch vụ.",
  PAYMENT_EXPIRED: "Thời hạn thanh toán đã hết. Hãy mở lại đơn để xem cách tiếp tục.",
  PAYMENT_ATTEMPT_IN_PROGRESS:
    "Thanh toán đang được xử lý. Vui lòng đợi một chút rồi kiểm tra lại đơn.",
  PAYMENT_STATE_MISMATCH:
    "Đơn đang có thay đổi về thanh toán. Hãy tải lại trang để xem trạng thái mới nhất.",
  PAYMENT_AMOUNT_MISMATCH:
    "Số tiền thanh toán chưa khớp với đơn. Vui lòng kiểm tra lại hoặc liên hệ PETTEL.",
  PAYMENT_REFERENCE_MISMATCH:
    "Thông tin thanh toán chưa khớp với đơn. Vui lòng liên hệ PETTEL để được hỗ trợ.",
  VERSION_CONFLICT: "Thông tin vừa được cập nhật ở nơi khác. Hãy tải lại trang rồi thử lại nhé.",
  REVIEW_ALREADY_EXISTS: "Bạn đã gửi đánh giá cho đơn này rồi.",
  CANCELLATION_IN_PROGRESS: "Yêu cầu hủy đơn đang được xử lý. Vui lòng kiểm tra lại sau ít phút.",
  MEDIA_UPLOAD_FAILED: "Ảnh chưa tải lên được. Hãy thử lại hoặc chọn ảnh khác nhé.",
  RELEASE_OPERATION_CONFLICT:
    "Khoản thanh toán đang được kiểm tra. PETTEL sẽ cập nhật khi xử lý xong.",
  REPORT_IN_REVIEW: "Đơn đang được PETTEL xem xét nên chưa thể thực hiện thao tác này.",
  FORBIDDEN:
    "Tài khoản của bạn chưa thể thực hiện thao tác này. Nếu cần, hãy liên hệ PETTEL để được hỗ trợ.",
  UNAUTHENTICATED: "Phiên đăng nhập đã kết thúc. Vui lòng đăng nhập lại để tiếp tục.",
  NOT_FOUND:
    "Không tìm thấy thông tin này. Nội dung có thể đã bị gỡ hoặc thay đổi; hãy tải lại trang nhé.",
};

const exactMessages: Record<string, string> = {
  "time range is unavailable.":
    "Khung giờ này vừa không còn trống. Bạn hãy chọn thời gian khác nhé.",
  "listing conditions changed. create a new booking.":
    "Điều kiện của dịch vụ vừa thay đổi. Vui lòng tạo yêu cầu đặt lịch mới.",
  "this booking request has expired.":
    "Yêu cầu đặt lịch này đã hết hạn. Hãy tạo yêu cầu mới nếu bạn vẫn muốn đặt dịch vụ.",
  "the payment deadline has passed.":
    "Thời hạn thanh toán đã hết. Hãy mở lại đơn để xem cách tiếp tục.",
  "a payment attempt is already pending.":
    "Thanh toán đang được xử lý. Vui lòng đợi một chút rồi kiểm tra lại đơn.",
  "these credentials do not match our records.":
    "Email hoặc mật khẩu chưa đúng. Bạn hãy kiểm tra lại thông tin. Nếu chưa có tài khoản PETTEL, chọn “Đăng ký tài khoản” để tạo mới.",
  "the provided credentials are incorrect.":
    "Email hoặc mật khẩu chưa đúng. Bạn hãy kiểm tra lại thông tin. Nếu chưa có tài khoản PETTEL, chọn “Đăng ký tài khoản” để tạo mới.",
  "pet species or size is not accepted.":
    "Bé cưng bạn chọn chưa phù hợp với dịch vụ này. Người chăm có giới hạn về loài hoặc kích thước thú cưng. Hãy kiểm tra hồ sơ của bé hoặc chọn dịch vụ khác.",
  "pet does not match listing conditions.":
    "Bé cưng bạn chọn chưa phù hợp với dịch vụ này. Người chăm có giới hạn về loài hoặc kích thước thú cưng. Hãy kiểm tra hồ sơ của bé hoặc chọn dịch vụ khác.",
  "the given data was invalid.": "Thông tin chưa hợp lệ. Vui lòng kiểm tra lại các mục đã nhập.",
  "invalid request data.": "Thông tin chưa hợp lệ. Vui lòng kiểm tra lại các mục đã nhập.",
  "invalid timestamp.": "Ngày hoặc giờ chưa đúng. Vui lòng chọn lại thời gian.",
  "the end must be in the future and after the start.":
    "Thời gian kết thúc cần sau thời gian bắt đầu và nằm trong tương lai.",
  "the change would invalidate a held booking.":
    "Không thể đổi khung giờ này vì đã có khách giữ lịch. Hãy chọn khung giờ khác.",
  "listing cannot receive bookings.":
    "Dịch vụ này hiện chưa thể nhận đặt lịch. Hãy chọn dịch vụ khác hoặc thử lại sau.",
  "listing is not active.": "Dịch vụ này hiện chưa mở nhận khách. Hãy chọn dịch vụ khác nhé.",
  "listing not found.": "Không tìm thấy dịch vụ này. Có thể dịch vụ đã được gỡ hoặc thay đổi.",
  "booking not found.": "Không tìm thấy đơn chăm sóc này. Đơn có thể đã bị gỡ hoặc thay đổi.",
  "caregiver cannot publish this listing.":
    "Hồ sơ Sitter chưa đủ điều kiện để đăng dịch vụ. Vui lòng hoàn thiện hồ sơ rồi thử lại.",
  "listing requires administrator review.":
    "Dịch vụ đang chờ PETTEL xem xét nên hiện chưa thể thực hiện thao tác này.",
  "listing has active bookings.":
    "Dịch vụ đang có lịch chăm sóc. Hãy xử lý các lịch hiện tại trước khi thay đổi dịch vụ.",
  "listing is in an invalid state.":
    "Dịch vụ vừa thay đổi trạng thái. Tải lại trang để kiểm tra rồi thử lại nhé.",
  "a change request is already pending.":
    "Đề xuất chỉnh sửa dịch vụ này đang chờ duyệt. Bạn có thể tiếp tục sau khi PETTEL phản hồi.",
  "listing has changed. refresh and retry.":
    "Dịch vụ vừa được cập nhật. Tải lại trang để xem thông tin mới nhất rồi thử lại.",
  "at least one field must change.":
    "Bạn chưa thay đổi thông tin nào. Hãy chỉnh ít nhất một mục trước khi gửi.",
  "only pending requests can be withdrawn.":
    "Chỉ có thể rút đề xuất khi đề xuất vẫn đang chờ duyệt.",
  "only requested bookings can be accepted.":
    "Yêu cầu này không còn chờ xác nhận. Hãy tải lại danh sách đơn để xem trạng thái mới nhất.",
  "only requested bookings can be rejected.":
    "Yêu cầu này không còn chờ phản hồi. Hãy tải lại danh sách đơn để xem trạng thái mới nhất.",
  "only completed bookings can be reviewed.":
    "Bạn chỉ có thể đánh giá sau khi đơn chăm sóc hoàn tất.",
  "this booking chat is read-only.": "Đơn này hiện không nhận thêm tin nhắn.",
  "this account cannot send booking messages.":
    "Tài khoản này chưa thể gửi tin nhắn trong đơn chăm sóc.",
  "this account cannot access booking messages.":
    "Tài khoản này chưa thể xem tin nhắn của đơn chăm sóc.",
  "the combined size must not exceed 10 mb.":
    "Tổng dung lượng ảnh đính kèm tối đa là 10 MB. Hãy chọn ít ảnh hơn hoặc ảnh nhẹ hơn nhé.",
  "provide text, an image, or both.":
    "Hãy nhập nội dung tin chăm sóc, chọn ảnh, hoặc cung cấp cả hai.",
  "unable to read image.": "Ảnh này không mở được. Hãy chọn ảnh khác nhé.",
  "image upload failed.": "Ảnh chưa tải lên được. Hãy thử lại hoặc chọn ảnh khác nhé.",
  "image could not be processed.": "Ảnh chưa xử lý được. Hãy chọn ảnh khác hoặc thử lại sau.",
  "upload a valid jpeg, png or webp image.": "Vui lòng chọn ảnh định dạng JPG, PNG hoặc WEBP.",
  "choose a readable image.": "Ảnh này không đọc được. Hãy chọn ảnh khác nhé.",
  "image dimensions exceed the allowed limit.":
    "Ảnh có kích thước quá lớn. Hãy chọn ảnh nhỏ hơn nhé.",
  "reason is required.": "Vui lòng ghi lý do trước khi gửi yêu cầu này.",
};

const fieldLabels: Record<string, string> = {
  name: "họ tên",
  display_name: "tên hiển thị",
  email: "email",
  password: "mật khẩu",
  password_confirmation: "mật khẩu xác nhận",
  phone: "số điện thoại",
  contact_phone: "số điện thoại liên hệ",
  title: "tên dịch vụ",
  description: "phần giới thiệu",
  area_code: "khu vực hoạt động",
  service_type: "loại dịch vụ",
  price_vnd: "giá dịch vụ",
  price_unit: "đơn vị tính giá",
  start_at: "thời gian bắt đầu",
  end_at: "thời gian kết thúc",
  pet_id: "thú cưng",
  listing_id: "dịch vụ",
  body: "nội dung",
  reason: "lý do",
  image: "ảnh",
  attachments: "ảnh đính kèm",
};

function translateValidationMessage(message: string) {
  const normalized = message.trim().toLowerCase();
  if (exactMessages[normalized]) return exactMessages[normalized];

  let match = message.match(/^The ([\w.]+) field is required\.?$/i);
  if (match) return `Vui lòng nhập ${fieldLabels[match[1]] ?? "thông tin bắt buộc"}.`;

  match = message.match(/^The ([\w.]+) field must be a valid email address\.?$/i);
  if (match) return `Địa chỉ ${fieldLabels[match[1]] ?? "email"} chưa đúng. Vui lòng kiểm tra lại.`;

  match = message.match(/^The ([\w.]+) field has already been taken\.?$/i);
  if (match)
    return `${fieldLabels[match[1]] ?? "Thông tin này"} đã được sử dụng. Hãy thử thông tin khác.`;

  match = message.match(/^The ([\w.]+) field must be at least (\d+) characters?\.?$/i);
  if (match) return `${fieldLabels[match[1]] ?? "Nội dung này"} cần có ít nhất ${match[2]} ký tự.`;

  match = message.match(/^The ([\w.]+) field may not be greater than (\d+) characters?\.?$/i);
  if (match)
    return `${fieldLabels[match[1]] ?? "Nội dung này"} không được dài quá ${match[2]} ký tự.`;

  match = message.match(/^The selected ([\w.]+) is invalid\.?$/i);
  if (match)
    return `Lựa chọn ở mục ${fieldLabels[match[1]] ?? "này"} không hợp lệ. Vui lòng chọn lại.`;

  match = message.match(/^The ([\w.]+) field must be a number\.?$/i);
  if (match) return `Vui lòng nhập số hợp lệ cho mục ${fieldLabels[match[1]] ?? "này"}.`;

  return null;
}

function translateMessage(message: unknown): string | null {
  if (typeof message !== "string" || !message.trim()) return null;
  const normalized = message.trim().toLowerCase();
  const validationMessage = translateValidationMessage(message);
  if (validationMessage) return validationMessage;
  if (exactMessages[normalized]) return exactMessages[normalized];
  // Keep messages that are already written in Vietnamese.
  if (/[À-ỹĐđ]/.test(message)) return message;
  return null;
}

function statusMessage(status?: number) {
  if (status === 400 || status === 422)
    return "Thông tin chưa hợp lệ hoặc thao tác chưa thể thực hiện. Vui lòng kiểm tra lại rồi thử nhé.";
  if (status === 403)
    return "Tài khoản của bạn chưa thể thực hiện thao tác này. Nếu cần, hãy liên hệ PETTEL để được hỗ trợ.";
  if (status === 404)
    return "Không tìm thấy thông tin này. Nội dung có thể đã bị gỡ hoặc thay đổi; hãy tải lại trang nhé.";
  if (status === 409)
    return "Thông tin vừa thay đổi nên thao tác chưa thực hiện được. Hãy tải lại trang rồi thử lại nhé.";
  if (status === 413) return "Tệp bạn chọn quá lớn. Hãy chọn tệp nhẹ hơn nhé.";
  if (status === 429) return "Bạn thao tác hơi nhanh. Hãy đợi một chút rồi thử lại nhé.";
  if (status !== undefined && status >= 500)
    return "PETTEL đang gặp trục trặc. Thông tin của bạn vẫn an toàn; hãy thử lại sau ít phút nhé.";
  return "Rất tiếc, hiện chưa thể thực hiện thao tác này. Bạn thử lại sau ít phút nhé.";
}

export function errorText(error: unknown) {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    if (status === 401) return "Phiên đăng nhập đã kết thúc. Vui lòng đăng nhập lại để tiếp tục.";
    if (status === 419) return "Phiên làm việc đã hết hạn. Vui lòng tải lại trang rồi thử lại.";
    if (!error.response) return "Không kết nối được PETTEL. Hãy kiểm tra mạng rồi thử lại nhé.";

    const body = error.response.data;
    const apiError = body?.error;
    const code = typeof apiError?.code === "string" ? apiError.code.toUpperCase() : "";
    if (code && codeMessages[code]) return codeMessages[code];

    const message = apiError?.message ?? body?.message;
    const details = body?.errors ?? apiError?.details;
    const lines =
      details && typeof details === "object"
        ? Object.values(details)
            .flat()
            .filter((value): value is string => typeof value === "string")
        : [];

    const translatedLines = lines
      .map(translateMessage)
      .filter((value): value is string => Boolean(value));
    if (translatedLines.length) return translatedLines.join(" ");

    const translatedMessage = translateMessage(message);
    if (translatedMessage) return translatedMessage;

    // Never show raw English server/framework messages to customers.
    return statusMessage(status);
  }

  if (error instanceof Error) {
    return (
      translateMessage(error.message) ??
      "Rất tiếc, hiện chưa thể thực hiện thao tác này. Bạn thử lại nhé."
    );
  }
  return "Rất tiếc, hiện chưa thể thực hiện thao tác này. Bạn thử lại nhé.";
}
