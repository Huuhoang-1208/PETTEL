"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import { useApi } from "@/hooks/use-api";
import { type Listing } from "@/lib/api/types";
import { useState } from "react";
import styles from "./listing-availability-card.module.css";

type DayStatus = { date: string; status: "AVAILABLE" | "FULL" };
type AvailabilityResponse = { data: DayStatus[]; month: string };

function currentMonthInVietnam(): string {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Ho_Chi_Minh",
    year: "numeric",
    month: "2-digit",
  }).formatToParts(new Date());
  const year = parts.find((part) => part.type === "year")?.value ?? "2026";
  const month = parts.find((part) => part.type === "month")?.value ?? "01";
  return `${year}-${month}`;
}

function shiftMonth(month: string, offset: number): string {
  const [year, monthNumber] = month.split("-").map(Number);
  const date = new Date(year, monthNumber - 1 + offset, 1);
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}`;
}

export function ListingAvailabilityCard({ listing }: { listing: Listing }) {
  const [month, setMonth] = useState(currentMonthInVietnam);
  const [selectedDate, setSelectedDate] = useState<string | null>(null);
  const availability = useApi<AvailabilityResponse>(
    `/api/listings/${listing.id}/availability?month=${month}`,
  );
  const [year, monthNumber] = month.split("-").map(Number);
  const firstDay = new Date(year, monthNumber - 1, 1);
  const daysInMonth = new Date(year, monthNumber, 0).getDate();
  const leadingDays = (firstDay.getDay() + 6) % 7;
  const cellCount = Math.ceil((leadingDays + daysInMonth) / 7) * 7;
  const statusByDate = new Map((availability.data?.data ?? []).map((day) => [day.date, day.status]));
  const firstAvailable = availability.data?.data.find((day) => day.status === "AVAILABLE")?.date;
  const activeDate = selectedDate ?? firstAvailable ?? null;
  const title = new Intl.DateTimeFormat("vi-VN", {
    month: "long",
    year: "numeric",
    timeZone: "Asia/Ho_Chi_Minh",
  }).format(new Date(year, monthNumber - 1, 1));

  return (
    <section className={styles.card} aria-labelledby="listing-availability-title">
      <header className={styles.header}>
        <div>
          <h2 id="listing-availability-title">
            <PettelIcon name="calendar" /> Lịch trống &amp; Tính khả dụng
          </h2>
          <p>Cập nhật tự động theo thời gian thực</p>
        </div>
        <div className={styles.legend} aria-label="Chú thích trạng thái lịch">
          <span><i className={styles.availableDot} /> Còn chỗ</span>
          <span><i className={styles.fullDot} /> Kín chỗ</span>
          <span><i className={styles.selectedDot} /> Đang chọn</span>
        </div>
      </header>

      <div className={styles.calendar}>
        <div className={styles.monthHeader}>
          <h3>{title}</h3>
          <div>
            <button
              type="button"
              aria-label="Tháng trước"
              onClick={() => { setMonth((value) => shiftMonth(value, -1)); setSelectedDate(null); }}
            >
              <PettelIcon name="chevron" />
            </button>
            <button
              type="button"
              aria-label="Tháng sau"
              onClick={() => { setMonth((value) => shiftMonth(value, 1)); setSelectedDate(null); }}
            >
              <PettelIcon name="chevron" />
            </button>
          </div>
        </div>

        <div className={styles.weekdays} aria-hidden="true">
          {["T2", "T3", "T4", "T5", "T6", "T7", "CN"].map((day) => <span key={day}>{day}</span>)}
        </div>
        <div className={styles.days}>
          {Array.from({ length: cellCount }, (_, index) => {
            const dayNumber = index - leadingDays + 1;
            const inMonth = dayNumber >= 1 && dayNumber <= daysInMonth;
            if (!inMonth) {
              const number = dayNumber < 1
                ? new Date(year, monthNumber - 1, dayNumber).getDate()
                : new Date(year, monthNumber - 1, dayNumber).getDate();
              return <span className={styles.outsideDay} key={`outside-${index}`}>{number}</span>;
            }

            const date = `${month}-${String(dayNumber).padStart(2, "0")}`;
            const status = statusByDate.get(date);
            const isSelected = date === activeDate && status === "AVAILABLE";
            const className = [
              styles.day,
              status === "AVAILABLE" ? styles.available : "",
              status === "FULL" ? styles.full : "",
              isSelected ? styles.selected : "",
            ].filter(Boolean).join(" ");

            return (
              <button
                className={className}
                type="button"
                key={date}
                disabled={status !== "AVAILABLE"}
                aria-pressed={isSelected}
                aria-label={`${dayNumber} ${title}: ${status === "AVAILABLE" ? "còn chỗ" : status === "FULL" ? "kín chỗ" : "đang tải"}`}
                onClick={() => setSelectedDate(date)}
              >
                {dayNumber}
              </button>
            );
          })}
        </div>
      </div>

      {availability.error ? (
        <p className={styles.note}>Chưa tải được lịch trống. Vui lòng thử lại sau.</p>
      ) : (
        <p className={styles.note}>
          * Lịch được đồng bộ từ thời gian sitter mở nhận khách; ngày đã qua hoặc không còn khung giờ phù hợp sẽ không thể chọn.
        </p>
      )}
    </section>
  );
}
