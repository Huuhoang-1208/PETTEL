"use client";
/* eslint-disable @next/next/no-img-element -- Keep the existing illustrative map asset. */
import { SiteHeader } from "@/components/layout/site-header";
import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
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
  const apiQuery = new URLSearchParams(query.toString());
  apiQuery.set("per_page", "5");
  const resource = useApi<Page<Listing>>(`/api/listings?${apiQuery.toString()}`);
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
  const totalPages = Math.max(1, resource.data?.meta?.last_page ?? 1);
  const totalResults = resource.data?.meta?.total ?? resource.data?.data.length ?? 0;
  const firstVisiblePage = Math.max(1, Math.min(page - 2, totalPages - 4));
  const visiblePages = Array.from(
    { length: Math.min(5, totalPages) },
    (_, index) => firstVisiblePage + index,
  );
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
        <ApiError error={formError ?? resource.error} />
      </section>
      <main className="app-discovery">
        <section className="app-results">
          <h1>
            {resource.data
              ? `${resource.data.meta?.total ?? resource.data.data.length} Sitter ở Đà Nẵng`
              : "Sitter ở Đà Nẵng"}
          </h1>
          {resource.loading && <p role="status">Đang tìm dịch vụ...</p>}
          {resource.data?.data.map((item) => (
            <ListingCard key={item.id} item={item} />
          ))}
          {!resource.loading && !resource.error && resource.data?.data.length === 0 && (
            <p className="app-notice">
              Chưa có dịch vụ phù hợp. Hãy đổi bộ lọc hoặc thời gian tìm kiếm.
            </p>
          )}
        </section>
        <aside className="app-map">
          <img src="/images/danang_map.png" alt="Bản đồ minh họa Đà Nẵng" />
        </aside>
        {resource.data && (
          <nav className="listing-pagination" aria-label="Phân trang dịch vụ">
            <p>
              Trang {page} trên {totalPages} ({totalResults} kết quả)
            </p>
            <div className="listing-pagination__controls">
              <button
                className="listing-pagination__arrow"
                type="button"
                aria-label="Trang trước"
                disabled={page <= 1 || resource.loading}
                onClick={() => paginate(page - 1)}
              >
                <PettelIcon name="chevron" />
              </button>
              {firstVisiblePage > 1 && (
                <>
                  <button type="button" onClick={() => paginate(1)} aria-label="Trang 1">
                    1
                  </button>
                  {firstVisiblePage > 2 && <span aria-hidden="true">…</span>}
                </>
              )}
              {visiblePages.map((pageNumber) => (
                <button
                  key={pageNumber}
                  type="button"
                  aria-label={`Trang ${pageNumber}`}
                  aria-current={pageNumber === page ? "page" : undefined}
                  className={pageNumber === page ? "is-current" : undefined}
                  disabled={resource.loading}
                  onClick={() => paginate(pageNumber)}
                >
                  {pageNumber}
                </button>
              ))}
              {firstVisiblePage + visiblePages.length - 1 < totalPages && (
                <>
                  {firstVisiblePage + visiblePages.length < totalPages && (
                    <span aria-hidden="true">…</span>
                  )}
                  <button
                    type="button"
                    onClick={() => paginate(totalPages)}
                    aria-label={`Trang ${totalPages}`}
                  >
                    {totalPages}
                  </button>
                </>
              )}
              <button
                className="listing-pagination__arrow"
                type="button"
                aria-label="Trang sau"
                disabled={page >= totalPages || resource.loading}
                onClick={() => paginate(page + 1)}
              >
                <PettelIcon name="chevron" />
              </button>
            </div>
          </nav>
        )}
      </main>
    </>
  );
}
