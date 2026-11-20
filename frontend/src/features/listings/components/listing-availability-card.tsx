"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import { useApi } from "@/hooks/use-api";
import { type Listing } from "@/lib/api/types";
import { useState } from "react";
import styles from "./listing-availability-card.module.css";

type AvailabilityWindow = { start_at: string; end_at: string };
type DayStatus = { date: string; status: "AVAILABLE" | "FULL"; time_slots: AvailabilityWindow[] };
type AvailabilityResponse = { data: DayStatus[]; month: string };

function timeLabel(value: string) {
  return new Intl.DateTimeFormat("vi-VN", {
    timeZone: "Asia/Ho_Chi_Minh",
    hour: "2-digit",
    minute: "2-digit",
    hourCycle: "h23",
  }).format(new Date(value));
}

function dateLabel(value: string) {
  return new Intl.DateTimeFormat("vi-VN", {
    timeZone: "Asia/Ho_Chi_Minh",
    day: "2-digit",
    month: "2-digit",
  }).format(new Date(value));
}

function windowLabel(window: AvailabilityWindow) {
  const startTime = timeLabel(window.start_at);
  const endTime = timeLabel(window.end_at);
  if (dateLabel(window.start_at) === dateLabel(window.end_at)) return `${startTime} – ${endTime}`;
  return `${dateLabel(window.start_at)} · ${startTime} – ${dateLabel(window.end_at)} · ${endTime}`;
}

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
  const [hoveredDate, setHoveredDate] = useState<string | null>(null);
  const availability = useApi<AvailabilityResponse>(
    `/api/v1/listings/${listing.id}/availability?month=${month}`,
  );
  const [year, monthNumber] = month.split("-").map(Number);
  const firstDay = new Date(year, monthNumber - 1, 1);
  const daysInMonth = new Date(year, monthNumber, 0).getDate();
  const leadingDays = (firstDay.getDay() + 6) % 7;
  const cellCount = Math.ceil((leadingDays + daysInMonth) / 7) * 7;
  const statusByDate = new Map(
    (availability.data?.data ?? []).map((day) => [day.date, day.status]),
  );
  const firstAvailable = availability.data?.data.find((day) => day.status === "AVAILABLE")?.date;
  const activeDate = selectedDate ?? firstAvailable ?? null;
  const detailDate = hoveredDate ?? selectedDate ?? firstAvailable ?? null;
  const detailDay = availability.data?.data.find((day) => day.date === detailDate);
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
          <p>Di chuột hoặc chạm vào ngày còn chỗ để xem khung giờ trống.</p>
        </div>
        <div className={styles.legend} aria-label="Chú thích trạng thái lịch">
          <span>
            <i className={styles.availableDot} /> Còn chỗ
          </span>
          <span>
            <i className={styles.fullDot} /> Kín chỗ
          </span>
          <span>
            <i className={styles.selectedDot} /> Đang chọn
          </span>
        </div>
      </header>

      <div className={styles.calendarArea} onMouseLeave={() => setHoveredDate(null)}>
        <div className={styles.calendar}>
          <div className={styles.monthHeader}>
            <h3>{title}</h3>
            <div>
              <button
                type="button"
                aria-label="Tháng trước"
                onClick={() => {
                  setMonth((value) => shiftMonth(value, -1));
                  setSelectedDate(null);
                  setHoveredDate(null);
                }}
              >
                <PettelIcon name="chevron" />
              </button>
              <button
                type="button"
                aria-label="Tháng sau"
                onClick={() => {
                  setMonth((value) => shiftMonth(value, 1));
                  setSelectedDate(null);
                  setHoveredDate(null);
                }}
              >
                <PettelIcon name="chevron" />
              </button>
            </div>
          </div>

          <div className={styles.weekdays} aria-hidden="true">
            {["T2", "T3", "T4", "T5", "T6", "T7", "CN"].map((day) => (
              <span key={day}>{day}</span>
            ))}
          </div>
          <div className={styles.days}>
            {Array.from({ length: cellCount }, (_, index) => {
              const dayNumber = index - leadingDays + 1;
              const inMonth = dayNumber >= 1 && dayNumber <= daysInMonth;
              if (!inMonth) {
                const number =
                  dayNumber < 1
                    ? new Date(year, monthNumber - 1, dayNumber).getDate()
                    : new Date(year, monthNumber - 1, dayNumber).getDate();
                return (
                  <span className={styles.outsideDay} key={`outside-${index}`}>
                    {number}
                  </span>
                );
              }

              const date = `${month}-${String(dayNumber).padStart(2, "0")}`;
              const status = statusByDate.get(date);
              const isSelected = date === activeDate && status === "AVAILABLE";
              const className = [
                styles.day,
                status === "AVAILABLE" ? styles.available : "",
                status === "FULL" ? styles.full : "",
                isSelected ? styles.selected : "",
              ]
                .filter(Boolean)
                .join(" ");

              return (
                <button
                  className={className}
                  type="button"
                  key={date}
                  disabled={status !== "AVAILABLE"}
                  aria-pressed={isSelected}
                  aria-label={`${dayNumber} ${title}: ${status === "AVAILABLE" ? "còn chỗ" : status === "FULL" ? "kín chỗ" : "đang tải"}`}
                  onMouseEnter={() => status === "AVAILABLE" && setHoveredDate(date)}
                  onFocus={() => status === "AVAILABLE" && setHoveredDate(date)}
                  onClick={() => {
                    setSelectedDate(date);
                    setHoveredDate(date);
                  }}
                >
                  {dayNumber}
                </button>
              );
            })}
          </div>
        </div>
        {detailDay && detailDay.status === "AVAILABLE" && (
          <div
            className={styles.availabilityDetails}
            role="region"
            aria-live="polite"
            aria-label={`Khung giờ trống ngày ${detailDate}`}
          >
            <div className={styles.detailsHeading}>
              <strong>Khung giờ còn trống</strong>
              <span>
                {new Intl.DateTimeFormat("vi-VN", {
                  timeZone: "Asia/Ho_Chi_Minh",
                  weekday: "long",
                  day: "2-digit",
                  month: "long",
                  year: "numeric",
                }).format(new Date(`${detailDate}T12:00:00+07:00`))}
              </span>
            </div>
            {detailDay.time_slots.length > 0 ? (
              <ul className={styles.timeSlotList}>
                {detailDay.time_slots.map((window, index) => (
                  <li key={`${window.start_at}-${window.end_at}-${index}`}>
                    <PettelIcon name="clock" />
                    {windowLabel(window)}
                  </li>
                ))}
              </ul>
            ) : (
              <p className={styles.noTimeSlots}>
                Ngày này hiện không còn khung giờ đủ dài để đặt. Vui lòng chọn ngày khác.
              </p>
            )}
          </div>
        )}
      </div>

      {availability.error ? (
        <p className={styles.note}>Chưa tải được lịch trống. Vui lòng thử lại sau.</p>
      ) : (
        <p className={styles.note}>
          * Lịch được đồng bộ từ thời gian sitter mở nhận khách; ngày đã qua hoặc không còn khung
          giờ phù hợp sẽ không thể chọn.
        </p>
      )}
    </section>
  );
}
