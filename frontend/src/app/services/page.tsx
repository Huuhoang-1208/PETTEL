import type { Metadata } from "next";
import { Suspense } from "react";
import { LiveListings } from "@/components/pettel/live-listings";

export const metadata: Metadata = {
  title: "Dịch vụ chăm sóc thú cưng | PETTEL",
  description: "Tìm người chăm sóc thú cưng, lọc lịch trống và đặt dịch vụ tại Đà Nẵng cùng PETTEL.",
};

export default function Page() {
  return <Suspense fallback={<p className="app-notice">Đang tải dịch vụ...</p>}><LiveListings /></Suspense>;
}
