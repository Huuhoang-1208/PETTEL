"use client";

/* eslint-disable @next/next/no-img-element -- Preserve existing API and demo image rendering. */
import { ApiError } from "@/components/ui/api-error";
import { AvailabilityManager } from "@/features/caregiver/components/availability-manager";
import { ManagedListing } from "@/features/caregiver/types";
import { write } from "@/lib/api/request";
import { apiUrl, money } from "@/lib/format";
import Link from "next/link";
import { useState, type FormEvent } from "react";

export function ManagedListingCard({
  listing,
  reload,
  edit,
}: {
  listing: ManagedListing;
  reload: () => void;
  edit: () => void;
}) {
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  async function action(name: string, payload?: unknown) {
    setBusy(true);
    setError(undefined);
    try {
      await write("post", `/api/caregiver/listings/${listing.id}/${name}`, payload);
      reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  async function upload(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    const data = new FormData(form);
    setBusy(true);
    setError(undefined);
    try {
      const media = await write<{ data: { id: string } }>("post", "/api/caregiver/media", data);
      await write("put", `/api/caregiver/listings/${listing.id}/media`, {
        media: [
          ...listing.media.map((item, index) => ({
            media_object_id: item.media_object_id,
            sort_order: index + 1,
          })),
          {
            media_object_id: media.data.id,
            sort_order: listing.media.length + 1,
          },
        ],
      });
      form.reset();
      reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  const labels: Record<string, string> = {
    DRAFT: "Bản nháp",
    PENDING_REVIEW: "Chờ duyệt",
    ACTIVE: "Đang hoạt động",
    SUSPENDED: "Tạm ngưng",
    ARCHIVED: "Đã lưu trữ",
  };
  return (
    <article className="app-card">
      <h3>{listing.title}</h3>
      <p>
        {labels[listing.status ?? ""] ?? listing.status} · {money(listing.price_vnd)}
      </p>
      {listing.latest_decision?.reason && <p>Phản hồi duyệt: {listing.latest_decision.reason}</p>}
      <ApiError error={error} />
      <div className="app-actions">
        {["DRAFT", "ACTIVE", "SUSPENDED"].includes(listing.status ?? "") &&
          !listing.pending_change_request_id && (
            <button disabled={busy} onClick={edit}>
              Chỉnh sửa
            </button>
          )}
        {listing.status === "DRAFT" && (
          <button disabled={busy} onClick={() => void action("submit")}>
            Gửi duyệt
          </button>
        )}
        {listing.status === "ACTIVE" && (
          <button
            disabled={busy}
            onClick={() =>
              void action("suspend", {
                reason: "Người chăm tạm ngưng nhận khách",
              })
            }
          >
            Tạm ngưng
          </button>
        )}
        {listing.status === "SUSPENDED" && listing.suspension_source === "CAREGIVER" && (
          <button disabled={busy} onClick={() => void action("restore")}>
            Mở lại
          </button>
        )}
        {listing.pending_change_request_id && (
          <button
            disabled={busy}
            onClick={() =>
              void action(`change-requests/${listing.pending_change_request_id}/withdraw`)
            }
          >
            Rút đề xuất chỉnh sửa
          </button>
        )}
        {listing.status === "ACTIVE" && (
          <Link href={`/detail?id=${listing.id}`}>Xem trang dịch vụ</Link>
        )}
      </div>
      {listing.status === "DRAFT" && (
        <details>
          <summary>Ảnh dịch vụ ({listing.media.length}/10)</summary>
          <div className="app-photo-grid">
            {listing.media.map((media) => (
              <img
                key={media.media_object_id}
                src={apiUrl(media.thumbnail_url)}
                alt="Ảnh dịch vụ của tôi"
              />
            ))}
          </div>
          <form className="app-form" onSubmit={upload}>
            <label>
              Thêm ảnh (JPEG/PNG/WebP, tối đa 10 MB)
              <input type="file" name="file" required accept="image/jpeg,image/png,image/webp" />
            </label>
            <button disabled={busy || listing.media.length >= 10}>Tải ảnh lên</button>
          </form>
        </details>
      )}
      <AvailabilityManager listing={listing} />
    </article>
  );
}
