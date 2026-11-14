"use client";

import { ApiError } from "@/components/ui/api-error";
import { Draft, Quote } from "@/features/listings/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Listing, type Page, type Pet } from "@/lib/api/types";
import { sizes, species } from "@/lib/constants";
import { localDate, vietnamISO } from "@/lib/date";
import { dateTime, money } from "@/lib/format";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

export function BookingForm({ listing }: { listing: Listing }) {
  const router = useRouter();
  const pets = useApi<Page<Pet>>("/api/pets");
  const [draft, setDraft] = useState<Draft | null>(null);
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const tomorrow = new Date();
  tomorrow.setUTCDate(tomorrow.getUTCDate() + 1);
  tomorrow.setUTCHours(2, 0, 0, 0);
  const end = new Date(
    tomorrow.getTime() + (listing.service_type === "DAYCARE" ? 2 : 24) * 3600000,
  );
  async function quote(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setBusy(true);
    setError(undefined);
    try {
      const payload = {
        listing_id: listing.id,
        pet_id: String(form.get("pet_id")),
        start_at: vietnamISO(String(form.get("start_at"))),
        end_at: vietnamISO(String(form.get("end_at"))),
        instructions: String(form.get("instructions") ?? ""),
      };
      const data = await write<Quote>("post", "/api/bookings/quote", payload);
      setDraft({ payload, quote: data, key: crypto.randomUUID() });
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  async function book() {
    if (!draft || busy) return;
    setBusy(true);
    setError(undefined);
    try {
      const result = await write<{ id: string }>("post", "/api/bookings", draft.payload, draft.key);
      router.push(`/bookings?open=${result.id}`);
    } catch (failure) {
      setError(failure);
      setBusy(false);
    }
  }
  const availablePets = pets.data?.data.filter((pet) => pet.status === "ACTIVE") ?? [];
  return (
    <>
      <ApiError error={error ?? pets.error} />
      <form className="app-form" onSubmit={quote} onChange={() => setDraft(null)}>
        <label>
          Thú cưng
          <select name="pet_id" required defaultValue="">
            <option value="" disabled>
              Chọn thú cưng
            </option>
            {availablePets.map((pet) => (
              <option key={pet.id} value={pet.id}>
                {pet.name} ({species[pet.species]} ·{" "}
                {sizes[pet.size ?? ""] ?? "chưa chọn kích thước"})
              </option>
            ))}
          </select>
        </label>
        {!pets.loading && availablePets.length === 0 && (
          <Link href="/pet-profile">Thêm hồ sơ thú cưng trước khi đặt lịch</Link>
        )}
        <label>
          Bắt đầu (giờ Việt Nam)
          <input
            type="datetime-local"
            name="start_at"
            required
            defaultValue={localDate(tomorrow)}
          />
        </label>
        <label>
          Kết thúc (giờ Việt Nam)
          <input type="datetime-local" name="end_at" required defaultValue={localDate(end)} />
        </label>
        <p className="app-muted">
          {listing.service_type === "DAYCARE"
            ? "Chăm ban ngày: 1–12 giờ trong cùng ngày."
            : "Lưu trú: từ 12 giờ đến 30 ngày."}
        </p>
        <label>
          Hướng dẫn chăm sóc
          <textarea name="instructions" maxLength={2000} />
        </label>
        <button
          className="app-button"
          disabled={busy || pets.loading || availablePets.length === 0}
        >
          {busy ? "Đang kiểm tra..." : "Kiểm tra giá & lịch trống"}
        </button>
      </form>
      <p>
        Đơn cần người chăm chấp nhận trước khi thanh toán. Thanh toán hiện ở môi trường sandbox.
      </p>
      {draft && (
        <div className="pet-modal" style={{ display: "flex" }}>
          <section
            className="pet-modal-content"
            role="dialog"
            aria-modal="true"
            aria-labelledby="booking-dialog"
          >
            <h2 id="booking-dialog">Xác nhận yêu cầu đặt lịch</h2>
            <p>{listing.title}</p>
            <p>
              {dateTime(draft.payload.start_at)} → {dateTime(draft.payload.end_at)}
            </p>
            <p>
              {draft.quote.quantity} {draft.quote.price_unit === "HOUR" ? "giờ" : "đêm"}
            </p>
            <h3>Tổng: {money(draft.quote.price_total_vnd)}</h3>
            <ApiError error={error} />
            <div className="app-actions">
              <button disabled={busy} onClick={() => setDraft(null)}>
                Quay lại
              </button>
              <button className="app-button" disabled={busy} onClick={() => void book()}>
                {busy ? "Đang gửi..." : "Gửi yêu cầu đặt lịch"}
              </button>
            </div>
          </section>
        </div>
      )}
    </>
  );
}
