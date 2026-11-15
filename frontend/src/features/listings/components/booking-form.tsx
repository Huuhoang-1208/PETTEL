"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { Draft, Quote } from "@/features/listings/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Listing, type Page, type Pet } from "@/lib/api/types";
import { sizes, species, services } from "@/lib/constants";
import { localDate, vietnamISO } from "@/lib/date";
import { dateTime, money } from "@/lib/format";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

export function BookingForm({ listing }: { listing: Listing }) {
  const router = useRouter();
  const pets = useApi<Page<Pet>>("/api/pets");
  const offerings = useApi<Page<Listing>>(`/api/listings/${listing.id}/services`);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [estimatedQuantity, setEstimatedQuantity] = useState(1);
  const [selectedListingId, setSelectedListingId] = useState(listing.id);
  const availableOfferings = offerings.data?.data ?? [listing];
  const selectedListing = availableOfferings.find((item) => item.id === selectedListingId) ?? listing;
  const quantityLabel = selectedListing.price_unit === "HOUR" ? "giờ" : "đêm";
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
        listing_id: String(form.get("listing_id") ?? listing.id),
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

  function updateEstimate(event: FormEvent<HTMLFormElement>) {
    setDraft(null);
    const form = new FormData(event.currentTarget);
    const selectedId = String(form.get("listing_id") ?? listing.id);
    setSelectedListingId(selectedId);
    const offer = availableOfferings.find((item) => item.id === selectedId) ?? listing;
    const startInput = event.currentTarget.elements.namedItem("start_at") as HTMLInputElement;
    const endInput = event.currentTarget.elements.namedItem("end_at") as HTMLInputElement;
    const start = new Date(String(form.get("start_at")));
    if ((event.target as HTMLSelectElement).name === "listing_id" && Number.isFinite(start.getTime())) {
      const finish = new Date(start.getTime() + (offer.price_unit === "HOUR" ? 2 : 24) * 3600000);
      endInput.value = localDate(finish);
      form.set("end_at", endInput.value);
    }
    const finish = new Date(String(form.get("end_at")));
    const duration = finish.getTime() - start.getTime();
    if (!Number.isFinite(duration) || duration <= 0) {
      setEstimatedQuantity(0);
      return;
    }
    setEstimatedQuantity(
      Math.ceil(duration / (offer.price_unit === "HOUR" ? 3600000 : 86400000)),
    );
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
  const estimatedTotal = draft?.quote.price_total_vnd ?? selectedListing.price_vnd * estimatedQuantity;
  const formatDong = (value: number) => `${Number(value).toLocaleString("vi-VN")}đ`;

  return (
    <>
      <form className="booking-form" onSubmit={quote} onChange={updateEstimate}>
        <ApiError error={error ?? pets.error ?? offerings.error} />
        <div className="booking-card-header">
          <div className="booking-card-price">
            {selectedListing.price_vnd.toLocaleString("vi-VN")}đ{" "}
            <small>/ {selectedListing.price_unit === "HOUR" ? "giờ" : "ngày"}</small>
          </div>
          <div className="booking-card-rating">
            <PettelIcon name="star" />
            <strong>{selectedListing.rating_average?.toFixed(1) ?? "Mới"}</strong>
            <span>({selectedListing.review_count})</span>
          </div>
        </div>
        <div className="booking-field">
          <span>DỊCH VỤ BẠN MUỐN CHỌN</span>
          <select name="listing_id" value={selectedListingId} aria-label="Dịch vụ muốn chọn">
            {availableOfferings.map((offer) => (
              <option key={offer.id} value={offer.id}>
                {services[offer.service_type] ?? offer.title} · {offer.price_vnd.toLocaleString("vi-VN")}đ / {offer.price_unit === "HOUR" ? "giờ" : "đêm"}
              </option>
            ))}
          </select>
        </div>

        <div className="booking-dates">
          <label className="booking-field">
            <span>NGÀY GỬI</span>
            <input
              type="datetime-local"
              name="start_at"
              required
              defaultValue={localDate(tomorrow)}
            />
          </label>
          <label className="booking-field">
            <span>NGÀY ĐÓN</span>
            <input
              type="datetime-local"
              name="end_at"
              required
              defaultValue={localDate(end)}
            />
          </label>
        </div>
        <p className="booking-duration">Ước tính: {estimatedQuantity} {quantityLabel}</p>

        <label className="booking-field">
          <span>BÉ CƯNG CẦN GỬI</span>
          <select name="pet_id" required defaultValue="">
            <option value="" disabled>Chọn thú cưng</option>
            {availablePets.map((pet) => (
              <option key={pet.id} value={pet.id}>
                {pet.name} ({species[pet.species]} · {sizes[pet.size ?? ""] ?? "chưa chọn kích thước"})
              </option>
            ))}
          </select>
        </label>
        {!pets.loading && availablePets.length === 0 && (
          <Link className="booking-add-pet" href="/pet-profile">
            Thêm hồ sơ thú cưng trước khi đặt lịch
          </Link>
        )}
        <label className="booking-field">
          <span>GHI CHÚ RIÊNG (KHÔNG BẮT BUỘC)</span>
          <textarea
            name="instructions"
            maxLength={2000}
            placeholder="Ví dụ: Bé sợ tiếng sấm, ăn hạt ngâm..."
          />
        </label>

        <div className="booking-summary">
          <div className="booking-summary-row">
            <span>{formatDong(selectedListing.price_vnd)} × {estimatedQuantity} {quantityLabel}</span>
            <strong>{formatDong(estimatedTotal)}</strong>
          </div>
          <div className="booking-summary-row">
            <span>Bảo hiểm PETTEL Care &amp; Y tế 24/7</span>
            <strong className="booking-free">Miễn phí</strong>
          </div>
          <div className="booking-summary-row">
            <span>Phí dịch vụ nền tảng</span>
            <strong className="booking-free">0đ</strong>
          </div>
          <div className="booking-summary-total">
            <strong>Tổng cộng tạm tính:</strong>
            <strong>{formatDong(estimatedTotal)}</strong>
          </div>
        </div>

        <button
          className="booking-submit app-button"
          disabled={busy || pets.loading || availablePets.length === 0 || estimatedQuantity <= 0}
        >
          <PettelIcon name="calendar" />
          {busy ? "Đang kiểm tra lịch..." : "Đặt lịch gửi bé ngay"}
        </button>
      </form>
      <Link className="booking-message" href="/chat">
        <PettelIcon name="mail" /> Nhắn tin trao đổi trước với Sitter
      </Link>
      <div className="booking-policy">
        <strong><PettelIcon name="shield" /> Chưa trả tiền ngay</strong>
        <p>Người chăm xác nhận lịch trước. Bạn sẽ kiểm tra lại báo giá trước khi gửi yêu cầu.</p>
      </div>
      <p className="booking-cancel-note">
        <PettelIcon name="clock" /> Chi tiết lịch và giá sẽ được xác nhận trước khi đặt.
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
            <p>{availableOfferings.find((offer) => offer.id === draft.payload.listing_id)?.title ?? listing.title}</p>
            <p>{dateTime(draft.payload.start_at)} → {dateTime(draft.payload.end_at)}</p>
            <p>{draft.quote.quantity} {draft.quote.price_unit === "HOUR" ? "giờ" : "đêm"}</p>
            <h3>Tổng: {money(draft.quote.price_total_vnd)}</h3>
            <ApiError error={error} />
            <div className="app-actions">
              <button disabled={busy} onClick={() => setDraft(null)}>Quay lại</button>
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
