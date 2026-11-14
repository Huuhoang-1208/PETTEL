import { BookingsPage } from "@/features/bookings/bookings-page";
import { Suspense } from "react";
export default function Page() {
  return (
    <Suspense fallback={<p>Đang tải...</p>}>
      <BookingsPage />
    </Suspense>
  );
}
