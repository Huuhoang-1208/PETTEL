import { api } from "@/lib/api/client";

export const money = (value: number) => `${Number(value).toLocaleString("vi-VN")} VND`;

export const dateTime = (value: string) =>
  new Date(value).toLocaleString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh" });

export const apiUrl = (value: string) => new URL(value, api.defaults.baseURL).toString();
