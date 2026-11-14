"use client";

import { useEffect, useState } from "react";
import axios from "axios";
import { api } from "./client";

export type User = { id: number | string; name: string; email: string; roles?: { role: string }[] };
export type Pet = { id: string; name: string; species: string; sex: string | null; size: string | null; birth_date: string | null; age_note: string | null; diet_notes: string | null; vaccination_note: string | null; temperament: string | null; special_needs: string | null; status: string };
export type Listing = { id: string; title: string; description: string; service_type: string; area_code: string; price_vnd: number; price_unit: string; price_total_vnd: number | null; rating_average: number | null; review_count: number; acceptance_rules_json: { species: string[]; sizes: string[] }; media?: { image_url: string; thumbnail_url: string }[]; match_reasons?: string[]; status?: string };
export type Booking = { id: string; status: string; pet_id: string; listing_id: string; start_at: string; end_at: string; price_snapshot_vnd: number; payment_due_at: string | null; participant_role: "OWNER" | "CAREGIVER"; listing_title: string | null; pet_name: string | null };
export type Message = { id: string; sender_id: string; body: string | null; created_at: string; attachments: { id: string; url: string; mime_type: string }[] };
export type Page<T> = { data: T[]; meta?: { current_page: number; last_page: number; total: number }; links?: { next: string | null } };
export type CareUpdate = { id: string; body: string | null; created_at: string; sender: { name: string }; image_url: string | null; redacted: boolean };

export const areas: Record<string, string> = { DN_CENTER: "Trung tâm Đà Nẵng", DN_NORTH: "Bắc Đà Nẵng", DN_SOUTH: "Nam Đà Nẵng", DN_WEST: "Tây Đà Nẵng", DN_EAST: "Đông Đà Nẵng" };
export const species: Record<string, string> = { CAT: "Mèo", DOG: "Chó", OTHER: "Khác" };
export const sizes: Record<string, string> = { SMALL: "Nhỏ", MEDIUM: "Vừa", LARGE: "Lớn" };
export const services: Record<string, string> = { DAYCARE: "Chăm ban ngày", BOARDING: "Lưu trú qua đêm" };
export const statuses: Record<string, string> = { REQUESTED: "Chờ người chăm nhận", AWAITING_PAYMENT: "Chờ thanh toán", CONFIRMED: "Đã xác nhận", IN_PROGRESS: "Đang chăm sóc", AWAITING_CONFIRMATION: "Chờ chủ xác nhận hoàn thành", COMPLETED: "Hoàn thành", CANCELLED: "Đã hủy", REJECTED: "Đã từ chối", EXPIRED: "Đã hết hạn" };
export const money = (value: number) => `${Number(value).toLocaleString("vi-VN")} VND`;
export const dateTime = (value: string) => new Date(value).toLocaleString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh" });
export const apiUrl = (value: string) => new URL(value, api.defaults.baseURL).toString();
export const unauthorized = (error: unknown) => axios.isAxiosError(error) && error.response?.status === 401;

export function errorText(error: unknown) {
  if (axios.isAxiosError(error)) {
    const status = error.response?.status;
    if (status === 401) return "Vui lòng đăng nhập để tiếp tục.";
    if (status === 419) return "Phiên làm việc đã hết hạn. Vui lòng thử lại.";
    if (!error.response) return "Không kết nối được máy chủ. Vui lòng thử lại.";
    const body = error.response.data;
    const details = body?.errors ?? body?.error?.details;
    const lines = details && typeof details === "object" ? Object.values(details).flat().filter((value) => typeof value === "string") : [];
    return lines.join(" ") || body?.error?.message || body?.message || "Yêu cầu chưa thực hiện được.";
  }
  return error instanceof Error ? error.message : "Đã xảy ra lỗi. Vui lòng thử lại.";
}

/** The API keeps authority over totals, availability and access permissions. */
export async function write<T = unknown>(method: "post" | "patch" | "put" | "delete", path: string, data?: unknown, key?: string) {
  await api.get("/sanctum/csrf-cookie");
  const response = await api.request<T>({ method, url: path, data, headers: key ? { "Idempotency-Key": key } : undefined });
  return response.data;
}

export function useApi<T>(path: string | null, pollMs = 0) {
  const [version, setVersion] = useState(0);
  const [state, setState] = useState<{ path: string | null; version: number; data?: T; error?: unknown }>({ path: null, version: -1 });
  useEffect(() => {
    if (!path) return;
    const controller = new AbortController();
    let request = 0;
    async function load() {
      const current = ++request;
      try {
        const { data } = await api.get<T>(path!, { signal: controller.signal });
        if (!controller.signal.aborted && current === request) setState({ path, version, data });
      } catch (error) {
        if (!controller.signal.aborted && current === request) setState({ path, version, error });
      }
    }
    void load();
    const timer = pollMs ? window.setInterval(() => { if (!document.hidden) void load(); }, pollMs) : undefined;
    return () => { controller.abort(); window.clearInterval(timer); };
  }, [path, version, pollMs]);
  return { data: state.path === path ? state.data : undefined, error: state.path === path ? state.error : undefined, loading: !!path && (state.path !== path || state.version !== version), reload: () => setVersion((value) => value + 1) };
}
