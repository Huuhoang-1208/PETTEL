"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { BookingList } from "@/features/bookings/components/booking-list";

export function BookingsPage() {
  return (
    <>
      <SiteHeader />
      <main className="app-container">
        <h1>Đơn chăm sóc</h1>
        <RequireSession next="/bookings">
          <BookingList />
        </RequireSession>
      </main>
    </>
  );
}
