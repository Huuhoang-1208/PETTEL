"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { ApiError } from "@/components/ui/api-error";
import { ListingDetail } from "@/features/listings/components/listing-detail";
import { useApi } from "@/hooks/use-api";
import { type Listing } from "@/lib/api/types";
import Link from "next/link";
import { useSearchParams } from "next/navigation";

export function ListingDetailPage() {
  const id = useSearchParams().get("id");
  const listing = useApi<{ data: Listing }>(id && /^\d+$/.test(id) ? `/api/listings/${id}` : null);
  return (
    <>
      <SiteHeader />
      <main className="app-container">
        <Link href="/services">← Tìm dịch vụ</Link>
        <ApiError error={listing.error} />
        {listing.loading && <p role="status">Đang tải dịch vụ...</p>}
        {!id && (
          <p>
            Chọn dịch vụ từ <Link href="/services">danh sách tìm kiếm</Link>.
          </p>
        )}
        {listing.data && <ListingDetail key={listing.data.data.id} listing={listing.data.data} />}
      </main>
    </>
  );
}
