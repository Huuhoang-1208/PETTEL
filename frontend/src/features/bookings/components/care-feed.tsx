"use client";

/* eslint-disable @next/next/no-img-element -- Preserve existing API and demo image rendering. */
import { ApiError } from "@/components/ui/api-error";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Booking, type CareUpdate, type Page } from "@/lib/api/types";
import { apiUrl, dateTime } from "@/lib/format";
import { useState, type FormEvent } from "react";

export function CareFeed({ booking }: { booking: Booking }) {
  const [page, setPage] = useState(1);
  const feed = useApi<Page<CareUpdate>>(
    `/api/bookings/${booking.id}/care-updates?page=${page}&per_page=20`,
    30000,
  );
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    const file = data.get("image");
    if (file instanceof File && !file.size) data.delete("image");
    setBusy(true);
    setError(undefined);
    try {
      await write("post", `/api/bookings/${booking.id}/care-updates`, data);
      form.reset();
      feed.reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  return (
    <section>
      <h3>Cập nhật chăm sóc</h3>
      <ApiError error={feed.error ?? error} />
      {feed.data?.data.map((update) => (
        <article className="app-update" key={update.id}>
          <p>
            <strong>{update.sender.name}</strong> · {dateTime(update.created_at)}
          </p>
          <p>{update.redacted ? "Nội dung đã được ẩn." : update.body}</p>
          {update.image_url && <img src={apiUrl(update.image_url)} alt="Ảnh cập nhật chăm sóc" />}
        </article>
      ))}
      {feed.data?.data.length === 0 && <p>Chưa có cập nhật chăm sóc.</p>}
      <div className="app-actions">
        <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
          Trước
        </button>
        <span>Trang {page}</span>
        <button
          disabled={page >= (feed.data?.meta?.last_page ?? 1)}
          onClick={() => setPage((value) => value + 1)}
        >
          Sau
        </button>
      </div>
      {booking.participant_role === "CAREGIVER" && booking.status === "IN_PROGRESS" && (
        <form className="app-form" onSubmit={submit}>
          <label>
            Nội dung cập nhật
            <textarea name="body" maxLength={2000} />
          </label>
          <label>
            Ảnh (JPEG/PNG/WebP, tối đa 10 MB)
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" />
          </label>
          <button disabled={busy} className="app-button">
            Gửi cập nhật
          </button>
        </form>
      )}
    </section>
  );
}
