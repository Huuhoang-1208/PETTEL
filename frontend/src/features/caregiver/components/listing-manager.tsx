"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { ListingEditor } from "@/features/caregiver/components/listing-editor";
import { ManagedListingCard } from "@/features/caregiver/components/managed-listing-card";
import { ManagedListing } from "@/features/caregiver/types";
import { useApi } from "@/hooks/use-api";
import { type Page } from "@/lib/api/types";
import { useMemo, useState } from "react";
import styles from "./listing-manager.module.css";

type Filter = "ALL" | "PENDING_REVIEW" | "ACTIVE" | "DRAFT";

const filters: { id: Filter; label: string }[] = [
  { id: "ALL", label: "Tất cả dịch vụ" },
  { id: "PENDING_REVIEW", label: "Chờ duyệt" },
  { id: "ACTIVE", label: "Đang hoạt động" },
  { id: "DRAFT", label: "Bản nháp" },
];

function isPendingReview(item: ManagedListing) {
  return item.status === "PENDING_REVIEW" || !!item.pending_change_request_id;
}

function isActive(item: ManagedListing) {
  return item.status === "ACTIVE" && !item.pending_change_request_id;
}

export function ListingManager({ includeAvailability = true }: { includeAvailability?: boolean }) {
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<Filter>("ALL");
  const listings = useApi<Page<ManagedListing>>(`/api/caregiver/listings?status=ALL&page=${page}&per_page=50`, 15000);
  const [editing, setEditing] = useState<ManagedListing | null | undefined>();
  const items = listings.data?.data ?? [];
  const visibleItems = useMemo(() => filter === "ALL" ? items : items.filter((item) =>
    filter === "PENDING_REVIEW" ? isPendingReview(item) : filter === "ACTIVE" ? isActive(item) : item.status === filter,
  ), [filter, items]);
  const activeCount = items.filter(isActive).length;
  const pendingCount = items.filter(isPendingReview).length;
  const draftCount = items.filter((item) => item.status === "DRAFT").length;

  return (
    <section className={styles.manager}>
      <header className={styles.header}>
        <div className={styles.headingCopy}>
          <span className={styles.eyebrow}><PettelIcon name="home" /> KHÔNG GIAN DỊCH VỤ</span>
          <h2>Dịch vụ của tôi</h2>
          <p>Quản lý dịch vụ, giá và trạng thái xét duyệt trên PETTEL.</p>
        </div>
        <button className={styles.createButton} type="button" onClick={() => setEditing(null)}><PettelIcon name="plus" /> Tạo dịch vụ mới</button>
      </header>

      <div className={styles.stats} aria-label="Tóm tắt dịch vụ">
        <article><span className={`${styles.statIcon} ${styles.teal}`}><PettelIcon name="home" /></span><div><small>Tổng dịch vụ</small><strong>{listings.loading ? "—" : items.length}</strong></div></article>
        <article><span className={`${styles.statIcon} ${styles.amber}`}><PettelIcon name="clock" /></span><div><small>Đang chờ duyệt</small><strong>{listings.loading ? "—" : pendingCount}</strong></div></article>
        <article><span className={`${styles.statIcon} ${styles.green}`}><PettelIcon name="check" /></span><div><small>Đang hoạt động</small><strong>{listings.loading ? "—" : activeCount}</strong></div></article>
        <article><span className={`${styles.statIcon} ${styles.purple}`}><PettelIcon name="sliders" /></span><div><small>Bản nháp</small><strong>{listings.loading ? "—" : draftCount}</strong></div></article>
      </div>

      <ApiError error={listings.error} />

      <div className={styles.listHeader}>
        <div><h3>Danh sách dịch vụ</h3><p>Dịch vụ mới sẽ xuất hiện sau khi PETTEL xét duyệt.</p></div>
        <span>{listings.data?.meta?.total ?? items.length} dịch vụ</span>
      </div>

      <nav className={styles.filters} aria-label="Lọc dịch vụ">
        {filters.map((item) => <button type="button" key={item.id} className={filter === item.id ? styles.activeFilter : undefined} onClick={() => setFilter(item.id)}>{item.label}<b>{item.id === "ALL" ? items.length : item.id === "PENDING_REVIEW" ? pendingCount : item.id === "ACTIVE" ? activeCount : items.filter((listing) => listing.status === item.id).length}</b></button>)}
      </nav>

      {listings.loading && <div className={styles.loading}><span /><p>Đang tải danh sách dịch vụ...</p></div>}

      {!listings.loading && visibleItems.length > 0 && <div className={styles.cards}>
        {visibleItems.map((listing) => <ManagedListingCard
          listing={listing}
          key={`${listing.id}-${listing.version}`}
          reload={listings.reload}
          edit={() => setEditing(listing)}
          showAvailability={includeAvailability}
          styles={styles}
        />)}
      </div>}

      {!listings.loading && visibleItems.length === 0 && <div className={styles.emptyState}>
        <span><PettelIcon name={filter === "ALL" ? "paw" : "search"} /></span>
        <h3>{filter === "ALL" ? "Bạn chưa tạo dịch vụ nào" : "Chưa có dịch vụ trong mục này"}</h3>
        <p>{filter === "ALL" ? "Tạo dịch vụ đầu tiên để giới thiệu cách bạn chăm sóc thú cưng và bắt đầu nhận khách." : "Các dịch vụ có trạng thái này sẽ hiển thị tại đây."}</p>
        {filter === "ALL" && <button className={styles.createButton} type="button" onClick={() => setEditing(null)}><PettelIcon name="plus" /> Tạo dịch vụ đầu tiên</button>}
      </div>}

      {(listings.data?.meta?.last_page ?? 1) > 1 && <div className={styles.pagination}>
        <span>Trang {page} / {listings.data?.meta?.last_page}</span>
        <div><button type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}><PettelIcon name="arrow" /> Trước</button><button type="button" disabled={page >= (listings.data?.meta?.last_page ?? 1)} onClick={() => setPage((value) => value + 1)}>Sau <PettelIcon name="arrow" /></button></div>
      </div>}

      {editing !== undefined && <div className={styles.backdrop} onMouseDown={(event) => { if (event.target === event.currentTarget) setEditing(undefined); }}>
        <div className={styles.modal} role="dialog" aria-modal="true" aria-label="Biên tập dịch vụ">
          <ListingEditor
            listing={editing}
            onSave={() => { setEditing(undefined); listings.reload(); }}
            onClose={() => setEditing(undefined)}
            styles={styles}
          />
        </div>
      </div>}
    </section>
  );
}
