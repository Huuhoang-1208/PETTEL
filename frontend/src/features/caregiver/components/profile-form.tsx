"use client";

import { ApiError } from "@/components/ui/api-error";
import { Profile } from "@/features/caregiver/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { areas } from "@/lib/constants";
import axios from "axios";
import { useState } from "react";

export function ProfileForm() {
  const profile = useApi<{ data: Profile }>("/api/caregiver/profile");
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(false);
  const missing = axios.isAxiosError(profile.error) && profile.error.response?.status === 404;
  const value = profile.data?.data;
  if (profile.loading) return <p>Đang tải hồ sơ người chăm...</p>;
  return (
    <details className="app-card" open={!value}>
      <summary>{value ? `Hồ sơ: ${value.display_name}` : "Tạo hồ sơ người chăm"}</summary>
      <ApiError error={error ?? (missing ? null : profile.error)} />
      {saved && <p className="app-success">Đã lưu hồ sơ.</p>}
      <form
        className="app-form"
        key={JSON.stringify(value)}
        onSubmit={async (event) => {
          event.preventDefault();
          const data = Object.fromEntries(new FormData(event.currentTarget));
          setBusy(true);
          setError(undefined);
          try {
            await write(value ? "patch" : "post", "/api/caregiver/profile", data);
            profile.reload();
            setSaved(true);
          } catch (failure) {
            setError(failure);
          } finally {
            setBusy(false);
          }
        }}
      >
        <label>
          Tên hiển thị
          <input name="display_name" defaultValue={value?.display_name} required maxLength={100} />
        </label>
        <label>
          Loại hình
          <select name="type" defaultValue={value?.type ?? "INDIVIDUAL"}>
            <option value="INDIVIDUAL">Cá nhân</option>
            <option value="FACILITY">Cơ sở chăm sóc</option>
          </select>
        </label>
        <label>
          Khu vực
          <select name="area_code" defaultValue={value?.area_code ?? "DN_CENTER"}>
            {Object.entries(areas).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Giới thiệu
          <textarea name="bio" defaultValue={value?.bio ?? ""} maxLength={2000} />
        </label>
        <button className="app-button" disabled={busy}>
          Lưu hồ sơ
        </button>
      </form>
    </details>
  );
}
