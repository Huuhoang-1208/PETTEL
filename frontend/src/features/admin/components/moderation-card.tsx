"use client";

/* eslint-disable @next/next/no-img-element -- Preserve existing API and demo image rendering. */
import { ApiError } from "@/components/ui/api-error";
import { listingStates } from "@/features/admin/constants";
import { write } from "@/lib/api/request";
import { type Listing } from "@/lib/api/types";
import { areas, services } from "@/lib/constants";
import { apiUrl, money } from "@/lib/format";
import { useState } from "react";

export function ModerationCard({
  listing,
  path,
  refresh,
}: {
  listing: Listing;
  path?: string;
  refresh: () => void;
}) {
  const [reason, setReason] = useState("");
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  async function act(action: string) {
    setBusy(true);
    setError(undefined);
    try {
      await write(
        "post",
        `${path ?? `/api/admin/listings/${listing.id}`}/${action}`,
        reason ? { reason } : undefined,
      );
      refresh();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  return (
    <article className="app-card">
      <h2>{listing.title}</h2>
      <p>
        {services[listing.service_type]} · {areas[listing.area_code]} · {money(listing.price_vnd)}
      </p>
      <p className="app-description">{listing.description}</p>
      <p>
        Trạng thái:{" "}
        {path ? "Đề xuất chỉnh sửa" : (listingStates[listing.status ?? ""] ?? "Chưa xác định")}
      </p>
      <div className="app-photo-grid">
        {listing.media
          ?.filter((media) => media.thumbnail_url)
          .map((media, index) => (
            <img key={index} src={apiUrl(media.thumbnail_url)} alt="Ảnh dịch vụ cần duyệt" />
          ))}
      </div>
      <label>
        Lý do / ghi chú
        <input value={reason} onChange={(event) => setReason(event.target.value)} maxLength={500} />
      </label>
      <ApiError error={error} />
      <div className="app-actions">
        {(path || listing.status === "PENDING_REVIEW") && (
          <>
            <button className="app-button" disabled={busy} onClick={() => void act("approve")}>
              Duyệt
            </button>
            <button disabled={busy || !reason.trim()} onClick={() => void act("reject")}>
              Từ chối (cần lý do)
            </button>
          </>
        )}
        {!path && listing.status === "ACTIVE" && (
          <button disabled={busy || !reason.trim()} onClick={() => void act("suspend")}>
            Tạm ngưng (cần lý do)
          </button>
        )}
        {!path && listing.status === "SUSPENDED" && (
          <button disabled={busy || !reason.trim()} onClick={() => void act("restore")}>
            Khôi phục (cần lý do)
          </button>
        )}
      </div>
    </article>
  );
}
