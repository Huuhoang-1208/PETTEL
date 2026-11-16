"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { Draft, Quote } from "@/features/listings/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Listing, type Page, type Pet } from "@/lib/api/types";
import { services, sizes, species } from "@/lib/constants";
import { localDate } from "@/lib/date";
import { apiUrl } from "@/lib/format";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import styles from "./booking-form.module.css";
import { SitterMessageModal, type SitterMessageContext } from "./sitter-message-modal";

type BookingPayload = Draft["payload"];
type AvailabilityWindow = { start_at: string; end_at: string };
type AvailabilityDay = {
  date: string;
  status: "AVAILABLE" | "FULL";
  time_slots: AvailabilityWindow[];
};
type AvailabilityResponse = { data: AvailabilityDay[]; month: string };
const bookingAddons = [
  {
    id: "PET_TAXI",
    title: "Đưa đón tận nơi (Pet Taxi khứ hồi)",
    description: "Tài xế chuyển đón bé tại nhà & trao tận tay Sitter",
    price: 120000,
  },
  {
    id: "BATH_BRUSH",
    title: "Tắm sấy thảo mộc & chải lông trước khi về",
    description: "Sử dụng sữa tắm hữu cơ dịu nhẹ, sấy chải mềm mượt thơm mát",
    price: 150000,
  },
] as const;

const VIETNAM_OFFSET_MS = 7 * 60 * 60 * 1000;

function currentMonthInVietnam() {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Ho_Chi_Minh",
    year: "numeric",
    month: "2-digit",
  }).formatToParts(new Date());
  return `${parts.find((part) => part.type === "year")?.value ?? "2026"}-${parts.find((part) => part.type === "month")?.value ?? "01"}`;
}

function dateKeyInVietnam(timestamp: number) {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Ho_Chi_Minh",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(new Date(timestamp));
  const value = (type: string) => parts.find((part) => part.type === type)?.value ?? "00";
  return `${value("year")}-${value("month")}-${value("day")}`;
}

function vietnamMidnightAfter(timestamp: number) {
  const [year, month, day] = dateKeyInVietnam(timestamp).split("-").map(Number);
  return Date.UTC(year, month - 1, day + 1) - VIETNAM_OFFSET_MS;
}

function buildSelectableWindows(windows: AvailabilityWindow[], serviceType: string) {
  const minDuration = (serviceType === "DAYCARE" ? 1 : 12) * 60 * 60 * 1000;
  const maxDuration = (serviceType === "DAYCARE" ? 12 : 30 * 24) * 60 * 60 * 1000;
  const unique = new Map<string, AvailabilityWindow>();
  for (const window of windows) {
    const start = Date.parse(window.start_at);
    const end = Math.min(
      Date.parse(window.end_at),
      start + maxDuration,
      serviceType === "DAYCARE" ? vietnamMidnightAfter(start) : Number.MAX_SAFE_INTEGER,
    );
    if (end - start < minDuration) continue;
    const option = { start_at: new Date(start).toISOString(), end_at: new Date(end).toISOString() };
    unique.set(`${option.start_at}|${option.end_at}`, option);
  }
  return [...unique.values()].sort((a, b) => a.start_at.localeCompare(b.start_at));
}

function bookingWindowOption(window: AvailabilityWindow) {
  const start = bookingDate(window.start_at);
  const end = bookingDate(window.end_at);
  return start.day === end.day
    ? `${start.day} · ${start.time} – ${end.time}`
    : `${start.day} · ${start.time} → ${end.day} · ${end.time}`;
}

function shiftMonth(month: string, offset: number) {
  const [year, monthNumber] = month.split("-").map(Number);
  const shifted = new Date(year, monthNumber - 1 + offset, 1);
  return `${shifted.getFullYear()}-${String(shifted.getMonth() + 1).padStart(2, "0")}`;
}

function bookingDate(value: string) {
  const date = new Date(value);
  const options = { timeZone: "Asia/Ho_Chi_Minh" };
  const time = date.toLocaleTimeString("vi-VN", {
    ...options,
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });
  const weekday = date.toLocaleDateString("vi-VN", { ...options, weekday: "long" });
  return {
    time: `${time}, ${weekday.charAt(0).toUpperCase()}${weekday.slice(1)}`,
    day: date.toLocaleDateString("vi-VN", {
      ...options,
      day: "numeric",
      month: "long",
      year: "numeric",
    }),
  };
}

export function BookingForm({ listing }: { listing: Listing }) {
  const router = useRouter();
  const formRef = useRef<HTMLFormElement>(null);
  const pets = useApi<Page<Pet>>("/api/pets");
  const offerings = useApi<Page<Listing>>(`/api/listings/${listing.id}/services`);
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [pending, setPending] = useState<BookingPayload | null>(null);
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [selectedAddons, setSelectedAddons] = useState<string[]>([]);
  const [selectedListingId, setSelectedListingId] = useState(listing.id);
  const [availabilityMonth, setAvailabilityMonth] = useState(currentMonthInVietnam);
  const [selectedWindowPreference, setSelectedWindowPreference] = useState("");
  const [selectedPetId, setSelectedPetId] = useState("");
  const [changingPet, setChangingPet] = useState(false);
  const [messageContext, setMessageContext] = useState<SitterMessageContext | null>(null);
  const availableOfferings = offerings.data?.data ?? [listing];
  const selectedListing =
    availableOfferings.find((item) => item.id === selectedListingId) ?? listing;
  const availability = useApi<AvailabilityResponse>(
    `/api/listings/${selectedListing.id}/availability?month=${availabilityMonth}`,
  );
  const availabilityWindows = useMemo(
    () =>
      availability.data?.data
        .filter((day) => day.status === "AVAILABLE")
        .flatMap((day) => day.time_slots) ?? [],
    [availability.data],
  );
  const availabilityOptions = useMemo(
    () => buildSelectableWindows(availabilityWindows, selectedListing.service_type),
    [availabilityWindows, selectedListing.service_type],
  );
  const selectedWindowKey = availability.loading
    ? selectedWindowPreference
    : availabilityOptions.some(
          (window) => `${window.start_at}|${window.end_at}` === selectedWindowPreference,
        )
      ? selectedWindowPreference
      : availabilityOptions[0]
        ? `${availabilityOptions[0].start_at}|${availabilityOptions[0].end_at}`
        : "";
  const selectedWindow = availabilityOptions.find(
    (window) => `${window.start_at}|${window.end_at}` === selectedWindowKey,
  );
  const startAt = selectedWindow?.start_at ?? "";
  const endAt = selectedWindow?.end_at ?? "";
  const activePets = pets.data?.data.filter((pet) => pet.status === "ACTIVE") ?? [];
  const quantityLabel = selectedListing.price_unit === "HOUR" ? "giờ" : "đêm";
  const durationMs = Date.parse(endAt) - Date.parse(startAt);
  const estimatedQuantity =
    Number.isFinite(durationMs) && durationMs > 0
      ? Math.ceil(durationMs / (selectedListing.price_unit === "HOUR" ? 3600000 : 86400000))
      : 0;
  const estimatedTotal =
    draft?.quote.price_total_vnd ?? selectedListing.price_vnd * estimatedQuantity;
  const formatDong = (value: number) => `${Number(value).toLocaleString("vi-VN")}đ`;
  const pendingOffer =
    availableOfferings.find((offer) => offer.id === pending?.listing_id) ?? selectedListing;
  const pendingPet = pets.data?.data.find((pet) => pet.id === pending?.pet_id);
  const checkIn = pending ? bookingDate(pending.start_at) : null;
  const checkOut = pending ? bookingDate(pending.end_at) : null;
  const pendingTotal =
    draft?.quote.price_total_vnd ??
    pendingOffer.price_vnd * estimatedQuantity +
      bookingAddons
        .filter((addon) => selectedAddons.includes(addon.id))
        .reduce((sum, addon) => sum + addon.price, 0);

  function closeBookingReview() {
    setOpen(false);
    setDraft(null);
    setPending(null);
  }

  useEffect(() => {
    if (!open) return;
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape" && !busy) closeBookingReview();
    };
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [open, busy]);

  function updateEstimate(event: FormEvent<HTMLFormElement>) {
    setDraft(null);
    setError(undefined);
    if ((event.target as HTMLInputElement | HTMLSelectElement).name === "listing_id") {
      const form = new FormData(event.currentTarget);
      setSelectedListingId(String(form.get("listing_id") ?? listing.id));
    } else if ((event.target as HTMLInputElement | HTMLSelectElement).name === "pet_id") {
      const form = new FormData(event.currentTarget);
      setSelectedPetId(String(form.get("pet_id") ?? ""));
    }
  }

  function openBookingReview() {
    const form = formRef.current;
    if (!form) return;
    if (!form.reportValidity()) return;
    const values = new FormData(form);
    const payload: BookingPayload = {
      listing_id: String(values.get("listing_id") ?? listing.id),
      pet_id: String(values.get("pet_id")),
      start_at: String(values.get("start_at")),
      end_at: String(values.get("end_at")),
      instructions: String(values.get("instructions") ?? ""),
      addons: [],
    };
    setPending(payload);
    setDraft(null);
    setSelectedAddons([]);
    setChangingPet(false);
    setError(undefined);
    setOpen(true);
  }

  function openSitterMessage() {
    const values = formRef.current ? new FormData(formRef.current) : null;
    const start = String(values?.get("start_at") ?? "");
    const finish = String(values?.get("end_at") ?? "");
    setMessageContext({
      petId: String(values?.get("pet_id") ?? ""),
      start: start ? localDate(new Date(start)) : "",
      end: finish ? localDate(new Date(finish)) : "",
    });
  }

  async function quote() {
    if (!pending || busy) return;
    setBusy(true);
    setError(undefined);
    try {
      const payload = { ...pending, addons: selectedAddons };
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

  return (
    <>
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

      <form ref={formRef} className="booking-form" onChange={updateEstimate}>
        <ApiError error={error ?? pets.error ?? offerings.error ?? availability.error} />
        <label className="booking-field">
          <span>DỊCH VỤ BẠN MUỐN CHỌN</span>
          <select name="listing_id" value={selectedListingId} aria-label="Dịch vụ muốn chọn">
            {availableOfferings.map((offer) => (
              <option key={offer.id} value={offer.id}>
                {services[offer.service_type] ?? offer.title} ·{" "}
                {offer.price_vnd.toLocaleString("vi-VN")}đ/
                {offer.price_unit === "HOUR" ? "giờ" : "ngày"}
              </option>
            ))}
          </select>
        </label>

        <div className={styles.availabilityMonthControls}>
          <span>
            {new Date(`${availabilityMonth}-01T00:00:00`).toLocaleDateString("vi-VN", {
              month: "long",
              year: "numeric",
            })}
          </span>
          <div>
            <button
              className={styles.availabilityMonthButton}
              type="button"
              aria-label="Xem tháng trước"
              onClick={() => setAvailabilityMonth((month) => shiftMonth(month, -1))}
            >
              ‹
            </button>
            <button
              className={styles.availabilityMonthButton}
              type="button"
              aria-label="Xem tháng sau"
              onClick={() => setAvailabilityMonth((month) => shiftMonth(month, 1))}
            >
              ›
            </button>
          </div>
        </div>
        <label className="booking-field">
          <span>CHỌN KHUNG GIỜ CỦA SITTER</span>
          <select
            name="availability_window"
            required
            value={selectedWindowKey}
            disabled={availability.loading || availabilityOptions.length === 0}
            onChange={(event) => setSelectedWindowPreference(event.target.value)}
          >
            <option value="" disabled>
              {availability.loading ? "Đang tải lịch Sitter..." : "Chọn khung giờ phù hợp"}
            </option>
            {availabilityOptions.map((window) => {
              const key = `${window.start_at}|${window.end_at}`;
              return (
                <option key={key} value={key}>
                  {bookingWindowOption(window)}
                </option>
              );
            })}
          </select>
        </label>
        <input type="hidden" name="start_at" value={startAt} />
        <input type="hidden" name="end_at" value={endAt} />
        <p className={styles.availabilityHint}>
          Chọn một khung còn trống. Giờ gửi và giờ đón sẽ theo khung đã chọn.
        </p>
        {!availability.loading && !availability.error && availabilityOptions.length === 0 && (
          <p className={styles.noAvailability} role="status">
            Sitter chưa mở khung giờ phù hợp trong tháng này. Hãy chọn tháng khác để xem lịch.
          </p>
        )}
        <p className="booking-duration">
          Ước tính: {estimatedQuantity} {quantityLabel}
        </p>

        <label className="booking-field">
          <span>BÉ CƯNG CẦN GỬI</span>
          <select name="pet_id" required value={selectedPetId}>
            <option value="" disabled>
              Chọn thú cưng
            </option>
            {activePets.map((pet) => (
              <option key={pet.id} value={pet.id}>
                {pet.name} ({species[pet.species]} ·{" "}
                {sizes[pet.size ?? ""] ?? "chưa chọn kích thước"})
              </option>
            ))}
          </select>
        </label>
        {!pets.loading && activePets.length === 0 && (
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
            <span>
              {formatDong(selectedListing.price_vnd)} × {estimatedQuantity} {quantityLabel}
            </span>
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
          type="button"
          disabled={
            pets.loading ||
            activePets.length === 0 ||
            estimatedQuantity <= 0 ||
            offerings.loading ||
            availability.loading ||
            !selectedWindow
          }
          onClick={openBookingReview}
        >
          <PettelIcon name="calendar" /> Đặt lịch gửi bé ngay
        </button>
      </form>

      <button
        className={`booking-message ${styles.preMessageButton}`}
        type="button"
        onClick={openSitterMessage}
      >
        <PettelIcon name="mail" /> Nhắn tin trao đổi trước với Sitter
      </button>
      {messageContext && (
        <SitterMessageModal
          listing={selectedListing}
          pets={activePets}
          initial={messageContext}
          onClose={() => setMessageContext(null)}
        />
      )}
      <div className="booking-policy">
        <strong>
          <PettelIcon name="shield" /> Chưa trả tiền ngay
        </strong>
        <p>Người chăm xác nhận lịch trước. Bạn sẽ kiểm tra lại báo giá trước khi gửi yêu cầu.</p>
      </div>
      <p className="booking-cancel-note">
        <PettelIcon name="clock" /> Chi tiết lịch và giá được xác nhận trước khi đặt.
      </p>

      {open && pending && (
        <div
          className={styles.backdrop}
          onMouseDown={(event) => {
            if (event.target === event.currentTarget && !busy) closeBookingReview();
          }}
        >
          <section
            className={styles.dialog}
            role="dialog"
            aria-modal="true"
            aria-labelledby="booking-dialog-title"
          >
            <div className={styles.safetyBar}>
              <PettelIcon name="shield" /> Bảo trợ an toàn PETTEL Care <span>•</span> Chưa trừ tiền{" "}
              <i /> Sitter phản hồi trong 15 phút
            </div>
            <header className={styles.header}>
              <span className={styles.headerIcon}>
                <PettelIcon name="calendar" />
              </span>
              <div>
                <h2 id="booking-dialog-title">
                  Xác nhận đặt lịch chăm sóc bé <PettelIcon name="paw" />
                </h2>
                <p>
                  Sitter {listing.caregiver?.display_name ?? "PETTEL"} · {listing.title}
                </p>
              </div>
              <button
                type="button"
                className={styles.close}
                aria-label="Đóng cửa sổ"
                disabled={busy}
                onClick={closeBookingReview}
              >
                ×
              </button>
            </header>

            <div className={styles.modalBody}>
              <section className={styles.bookingPreview}>
                <div className={styles.previewHeading}>
                  <span>
                    <PettelIcon name={pendingOffer.service_type === "DAYCARE" ? "clock" : "home"} />
                  </span>
                  <div>
                    <small>GÓI CHĂM SÓC</small>
                    <strong>{services[pendingOffer.service_type] ?? pendingOffer.title}</strong>
                  </div>
                  <b>
                    {formatDong(pendingOffer.price_vnd)} /{" "}
                    {pendingOffer.price_unit === "HOUR" ? "giờ" : "ngày"}
                  </b>
                </div>
                <div className={styles.previewDates}>
                  <div className={styles.dateBlock}>
                    <span className={styles.checkInIcon}>
                      <PettelIcon name="arrow" />
                    </span>
                    <div>
                      <small>Nhận bé (Check-in)</small>
                      <strong>{checkIn?.time}</strong>
                      <span>{checkIn?.day}</span>
                    </div>
                  </div>
                  <div className={styles.dateBlock}>
                    <span className={styles.checkOutIcon}>
                      <PettelIcon name="arrow" />
                    </span>
                    <div>
                      <small>Đón bé (Check-out)</small>
                      <strong>{checkOut?.time}</strong>
                      <span>
                        {checkOut?.day} ({estimatedQuantity} {quantityLabel})
                      </span>
                    </div>
                  </div>
                </div>
                <div className={styles.previewPet}>
                  <span className={styles.petAvatar}>
                    {pendingPet?.photo_media_id ? (
                      // eslint-disable-next-line @next/next/no-img-element -- Private pet photo uses the authenticated API.
                      <img
                        src={apiUrl(`/api/pets/media/${pendingPet.photo_media_id}/content`)}
                        alt={`Ảnh ${pendingPet.name}`}
                      />
                    ) : (
                      <PettelIcon
                        name={
                          pendingPet?.species === "DOG"
                            ? "dog"
                            : pendingPet?.species === "CAT"
                              ? "cat"
                              : "paw"
                        }
                      />
                    )}
                  </span>
                  <div className={styles.petDetails}>
                    <div className={styles.petName}>
                      <strong>{pendingPet?.name ?? "Thú cưng"}</strong>
                      <span>
                        {pendingPet?.breed ||
                          (pendingPet ? species[pendingPet.species] : "Hồ sơ thú cưng")}
                        {pendingPet?.weight_kg
                          ? ` · ${Number(pendingPet.weight_kg).toLocaleString("vi-VN")}kg`
                          : ""}
                      </span>
                    </div>
                    <small>
                      <PettelIcon name="shield" />{" "}
                      {pendingPet?.health_flags?.core_vaccines
                        ? "Đã tiêm vaccine cơ bản"
                        : "Chưa xác nhận tiêm chủng"}
                      {pendingPet?.is_neutered && (
                        <>
                          <i>•</i>
                          <span>Đã triệt sản</span>
                        </>
                      )}
                    </small>
                  </div>
                  <button
                    className={styles.changePet}
                    type="button"
                    disabled={busy}
                    aria-expanded={changingPet}
                    onClick={() => setChangingPet(!changingPet)}
                  >
                    Đổi bé
                  </button>
                </div>
                {changingPet && (
                  <select
                    className={styles.petSelect}
                    aria-label="Chọn bé cần gửi"
                    value={pending.pet_id}
                    disabled={busy}
                    onChange={(event) => {
                      setPending({ ...pending, pet_id: event.target.value });
                      setDraft(null);
                      setError(undefined);
                      setChangingPet(false);
                    }}
                  >
                    {activePets.map((pet) => (
                      <option key={pet.id} value={pet.id}>
                        {pet.name} · {species[pet.species]}
                      </option>
                    ))}
                  </select>
                )}
              </section>
              <label className={styles.instructions}>
                <strong>Dặn dò cho Sitter {listing.caregiver?.display_name ?? ""}</strong>
                <span>(Thói quen &amp; lưu ý) · Không bắt buộc</span>
                <textarea
                  value={pending.instructions}
                  onChange={(event) => {
                    setPending({ ...pending, instructions: event.target.value });
                    setDraft(null);
                  }}
                  placeholder="Ví dụ: Bé ăn hạt ngâm lúc 18h, sợ tiếng sấm, thích chơi bóng màu đỏ sau khi đi dạo cùng Sitter..."
                  maxLength={2000}
                />
              </label>

              <section className={styles.addons} aria-label="Dịch vụ bổ sung tiện lợi">
                <h3>DỊCH VỤ BỔ SUNG TIỆN LỢI:</h3>
                {bookingAddons.map((addon) => (
                  <label className={styles.addon} key={addon.id}>
                    <input
                      type="checkbox"
                      checked={selectedAddons.includes(addon.id)}
                      disabled={busy}
                      onChange={(event) => {
                        setSelectedAddons((current) =>
                          event.target.checked
                            ? [...current, addon.id]
                            : current.filter((id) => id !== addon.id),
                        );
                        setDraft(null);
                      }}
                    />
                    <span>
                      <strong>{addon.title}</strong>
                      <small>{addon.description}</small>
                    </span>
                    <b>+{formatDong(addon.price)}</b>
                  </label>
                ))}
              </section>

              <div className={`booking-summary ${styles.modalSummary}`}>
                <h3>CHI TIẾT CHI PHÍ LƯU TRÚ</h3>
                <div className="booking-summary-row">
                  <span>
                    Tiền {pendingOffer.service_type === "DAYCARE" ? "chăm sóc" : "phòng nghỉ"} (
                    {formatDong(pendingOffer.price_vnd)} ×{" "}
                    {draft?.quote.quantity ?? estimatedQuantity}{" "}
                    {draft?.quote.price_unit === "HOUR" ? "giờ" : quantityLabel})
                  </span>
                  <strong>
                    {formatDong(
                      draft?.quote.base_price_total_vnd ??
                        pendingOffer.price_vnd * estimatedQuantity,
                    )}
                  </strong>
                </div>
                <div className="booking-summary-row">
                  <span>Bảo hiểm PETTEL Care &amp; Y tế 24/7</span>
                  <strong className="booking-free">Miễn phí</strong>
                </div>
                {bookingAddons
                  .filter((addon) => selectedAddons.includes(addon.id))
                  .map((addon) => (
                    <div className="booking-summary-row" key={addon.id}>
                      <span>{addon.title}</span>
                      <strong>{formatDong(addon.price)}</strong>
                    </div>
                  ))}
                <div className="booking-summary-total">
                  <strong>Tổng cộng tạm tính:</strong>
                  <strong>
                    {formatDong(
                      draft?.quote.price_total_vnd ??
                        pendingOffer.price_vnd * estimatedQuantity +
                          bookingAddons
                            .filter((addon) => selectedAddons.includes(addon.id))
                            .reduce((sum, addon) => sum + addon.price, 0),
                    )}
                  </strong>
                </div>
              </div>
              <ApiError error={error ?? pets.error ?? offerings.error ?? availability.error} />
            </div>

            <footer className={styles.footer}>
              <button
                className={styles.messageButton}
                type="button"
                disabled={busy}
                onClick={() => router.push("/chat")}
              >
                <PettelIcon name="mail" /> Nhắn tin với Sitter
              </button>
              <button
                className={styles.confirmButton}
                type="button"
                disabled={busy}
                onClick={() => (draft ? void book() : void quote())}
              >
                <PettelIcon name="calendar" />{" "}
                <span>
                  {busy
                    ? draft
                      ? "Đang gửi yêu cầu..."
                      : "Đang kiểm tra giá..."
                    : draft
                      ? `Xác nhận & Giữ chỗ ngay (${formatDong(pendingTotal)})`
                      : `Kiểm tra giá & tiếp tục (${formatDong(pendingTotal)})`}
                </span>
              </button>
              <p>
                <span>
                  <PettelIcon name="shield" /> Hoàn tiền 100% nếu hủy trước 24h
                </span>
                <i>•</i>
                <span>
                  Hotline hỗ trợ 24/7: <strong>1900 6868</strong>
                </span>
              </p>
            </footer>
          </section>
        </div>
      )}
    </>
  );
}
