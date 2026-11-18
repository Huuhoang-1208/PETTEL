"use client";

import { ApiError } from "@/components/ui/api-error";
import { BookingPanel } from "@/features/bookings/components/booking-panel";
import { useApi } from "@/hooks/use-api";
import { type Booking, type Page } from "@/lib/api/types";
import { statuses } from "@/lib/constants";
import { dateTime, money } from "@/lib/format";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";

export function BookingList() {
  const [page, setPage] = useState(1);
  const bookings = useApi<Page<Booking>>(`/api/bookings?page=${page}`, 15000);
  const query = useSearchParams();
  const [selected, setSelected] = useState<string | null>(null);
  const id = selected ?? query.get("open");
  return (
    <>
      <ApiError error={bookings.error} />
      <button onClick={bookings.reload}>Tải lại danh sách</button>
      {bookings.loading && <p>Đang tải đơn...</p>}
      <div className="app-bookings-grid">
        <section>
          {bookings.data?.data.map((booking) => (
            <article className="app-card" key={booking.id}>
              <h2>{booking.listing_title || `Đơn #${booking.id}`}</h2>
              <p>
                {booking.pet_name || "Thú cưng"} ·{" "}
                {booking.participant_role === "OWNER" ? "Tôi là chủ thú cưng" : "Tôi là người chăm"}
              </p>
              <p>
                {dateTime(booking.start_at)} → {dateTime(booking.end_at)}
              </p>
              <p>
                <strong>{statuses[booking.status] ?? booking.status}</strong> ·{" "}
                {money(booking.price_snapshot_vnd)}
              </p>
              <div className="app-actions">
                <button className="app-button" onClick={() => setSelected(booking.id)}>
                  Xem & quản lý
                </button>
                <Link href={`/chat?booking=${booking.id}`}>Nhắn tin</Link>
              </div>
            </article>
          ))}
          {bookings.data?.data.length === 0 && (
            <p className="app-notice">
              Bạn chưa có đơn chăm sóc. <Link href="/services">Tìm dịch vụ</Link>
            </p>
          )}
          <div className="app-actions">
            <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
              Trang trước
            </button>
            <span>Trang {page}</span>
            <button
              disabled={page >= (bookings.data?.meta?.last_page ?? 1)}
              onClick={() => setPage((value) => value + 1)}
            >
              Trang sau
            </button>
          </div>
        </section>
        <section>
          {id ? (
            <BookingPanel key={id} id={id} onChange={bookings.reload} />
          ) : (
            <p className="app-notice">
              Chọn một đơn để xem trạng thái, cập nhật chăm sóc và các thao tác phù hợp.
            </p>
          )}
        </section>
      </div>
    </>
  );
}
