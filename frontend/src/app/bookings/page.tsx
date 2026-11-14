import { Suspense } from "react";
import { LiveBookings } from "@/components/pettel/live-bookings";
export default function Page() { return <Suspense fallback={<p>Đang tải...</p>}><LiveBookings /></Suspense>; }
