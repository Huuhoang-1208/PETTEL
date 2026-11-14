"use client";
/* eslint-disable @next/next/no-img-element -- Keep the existing illustrative map asset. */
import { SiteHeader } from "@/components/layout/site-header";
import { ApiError } from "@/components/ui/api-error";
import { ListingCard } from "@/features/listings/components/listing-card";
import { ListingFilters } from "@/features/listings/components/listing-filters";
import { useApi } from "@/hooks/use-api";
import { type Listing, type Page, type Pet } from "@/lib/api/types";
import { vietnamISO } from "@/lib/date";
import { useSession } from "@/providers/session-provider";
import { useRouter, useSearchParams } from "next/navigation";
import { type FormEvent, useState } from "react";

export function ListingsPage() {
  const router = useRouter();
  const query = useSearchParams();
  const { user } = useSession();
  const pets = useApi<Page<Pet>>(user ? "/api/pets" : null);
  const resource = useApi<Page<Listing>>(`/api/listings?${query.toString()}`);
  const [formError, setFormError] = useState<unknown>();
  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setFormError(undefined);
    try {
      const data = new FormData(event.currentTarget);
      const params = new URLSearchParams();
      for (const [key, value] of data)
        if (typeof value === "string" && value)
          params.set(
            key,
            key === "start_at" || key === "end_at" ? vietnamISO(value) : value.trim(),
          );
      router.push(`/services?${params}`);
    } catch (failure) {
      setFormError(failure);
    }
  }
  const page = Number(query.get("page") ?? 1);
  function paginate(value: number) {
    const next = new URLSearchParams(query);
    next.set("page", String(value));
    router.push(`/services?${next}`);
  }
  return (
    <>
      <SiteHeader />
      <section className="app-search">
        <ListingFilters
          key={query.toString()}
          query={query}
          pets={pets}
          user={user}
          search={search}
        />
        <p className="app-muted">
          Chọn cả thời gian bắt đầu và kết thúc để tìm lịch trống và lọc theo tổng giá. Smart Match
          dùng loài và kích thước trong hồ sơ thú cưng.
        </p>
        <ApiError error={formError ?? resource.error} />
      </section>
      <main className="app-discovery">
        <section className="app-results">
          <h1>Dịch vụ chăm sóc thú cưng tại Đà Nẵng</h1>
          {resource.loading && <p role="status">Đang tìm dịch vụ...</p>}
          {resource.data && (
            <p>{resource.data.meta?.total ?? resource.data.data.length} dịch vụ phù hợp</p>
          )}
          {resource.data?.data.map((item) => (
            <ListingCard key={item.id} item={item} />
          ))}
          {!resource.loading && !resource.error && resource.data?.data.length === 0 && (
            <p className="app-notice">
              Chưa có dịch vụ phù hợp. Hãy đổi bộ lọc hoặc thời gian tìm kiếm.
            </p>
          )}
          <div className="app-actions">
            <button disabled={page <= 1 || resource.loading} onClick={() => paginate(page - 1)}>
              Trang trước
            </button>
            <span>
              Trang {page} / {resource.data?.meta?.last_page ?? 1}
            </span>
            <button
              disabled={page >= (resource.data?.meta?.last_page ?? 1) || resource.loading}
              onClick={() => paginate(page + 1)}
            >
              Trang sau
            </button>
          </div>
        </section>
        <aside className="app-map">
          <img src="/images/danang_map.png" alt="Bản đồ minh họa Đà Nẵng" />
          <p>Bản đồ minh họa khu vực Đà Nẵng</p>
        </aside>
      </main>
    </>
  );
}
