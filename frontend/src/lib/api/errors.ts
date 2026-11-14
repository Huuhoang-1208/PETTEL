import axios from "axios";

export const unauthorized = (error: unknown) =>
  axios.isAxiosError(error) && error.response?.status === 401;

export function errorText(error: unknown) {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    if (status === 401) return "Vui lòng đăng nhập để tiếp tục.";
    if (status === 419) return "Phiên làm việc đã hết hạn. Vui lòng thử lại.";
    if (!error.response) return "Không kết nối được máy chủ. Vui lòng thử lại.";
    const body = error.response.data;
    const details = body?.errors ?? body?.error?.details;
    const lines =
      details && typeof details === "object"
        ? Object.values(details)
            .flat()
            .filter((value) => typeof value === "string")
        : [];
    return (
      lines.join(" ") || body?.error?.message || body?.message || "Yêu cầu chưa thực hiện được."
    );
  }
  return error instanceof Error ? error.message : "Đã xảy ra lỗi. Vui lòng thử lại.";
}
