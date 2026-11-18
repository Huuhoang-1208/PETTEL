import { ListingDetailPage } from "@/features/listings/listing-detail-page";
import type { Metadata } from "next";
import { Suspense } from "react";

export const metadata: Metadata = { title: "Chi tiết Sitter | PETTEL" };

export default function Page() {
  return (
    <Suspense fallback={<p>Đang tải...</p>}>
      <ListingDetailPage />
    </Suspense>
  );
}
