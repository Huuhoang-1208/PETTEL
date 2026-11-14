import { api } from "@/lib/api/client";

export async function write<T = unknown>(
  method: "post" | "patch" | "put" | "delete",
  path: string,
  data?: unknown,
  key?: string,
) {
  await api.get("/sanctum/csrf-cookie");
  const response = await api.request<T>({
    method,
    url: path,
    data,
    headers: key ? { "Idempotency-Key": key } : undefined,
  });
  return response.data;
}
