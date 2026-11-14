"use client";

import { ApiError } from "@/components/ui/api-error";
import { ListingEditor } from "@/features/caregiver/components/listing-editor";
import { ManagedListingCard } from "@/features/caregiver/components/managed-listing-card";
import { ManagedListing } from "@/features/caregiver/types";
import { useApi } from "@/hooks/use-api";
import { type Page } from "@/lib/api/types";
import { useState } from "react";

export function ListingManager() {
  const [page, setPage] = useState(1);
  const listings = useApi<Page<ManagedListing>>(`/api/caregiver/listings?status=ALL&page=${page}`);
  const [editing, setEditing] = useState<ManagedListing | null | undefined>();
  return (
    <section>
      <h2>Dịch vụ của tôi</h2>
      <p>Dịch vụ mới cần quản trị viên duyệt trước khi xuất hiện trên trang tìm kiếm.</p>
      <ApiError error={listings.error} />
      <button className="app-button" onClick={() => setEditing(null)}>
        + Tạo dịch vụ
      </button>
      {listings.loading && <p>Đang tải dịch vụ...</p>}
      {editing !== undefined && (
        <div className="pet-modal" style={{ display: "flex" }}>
          <div
            className="pet-modal-content"
            role="dialog"
            aria-modal="true"
            aria-label="Biên tập dịch vụ"
          >
            <ListingEditor
              listing={editing}
              onSave={() => {
                setEditing(undefined);
                listings.reload();
              }}
              onClose={() => setEditing(undefined)}
            />
          </div>
        </div>
      )}
      {listings.data?.data.map((listing) => (
        <ManagedListingCard
          listing={listing}
          key={`${listing.id}-${listing.version}`}
          reload={listings.reload}
          edit={() => setEditing(listing)}
        />
      ))}
      {listings.data?.data.length === 0 && (
        <p>Chưa có dịch vụ. Tạo hồ sơ người chăm rồi thêm dịch vụ đầu tiên.</p>
      )}
      <div className="app-actions">
        <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
          Trước
        </button>
        <span>Trang {page}</span>
        <button
          disabled={page >= (listings.data?.meta?.last_page ?? 1)}
          onClick={() => setPage((value) => value + 1)}
        >
          Sau
        </button>
      </div>
    </section>
  );
}
