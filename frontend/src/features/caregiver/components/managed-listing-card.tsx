"use client";

/* eslint-disable @next/next/no-img-element -- Preserve existing API and demo image rendering. */
import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { AvailabilityManager } from "@/features/caregiver/components/availability-manager";
import { ManagedListing } from "@/features/caregiver/types";
import { write } from "@/lib/api/request";
import { areas, services, sizes, species } from "@/lib/constants";
import { apiUrl, money } from "@/lib/format";
import Link from "next/link";
import { useState, type FormEvent } from "react";

export function ManagedListingCard({
  listing,
  reload,
  edit,
  showAvailability = true,
  styles,
}: {
  listing: ManagedListing;
  reload: () => void;
  edit: () => void;
  showAvailability?: boolean;
  styles: Record<string, string>;
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
  const displayedStatus = listing.pending_change_request_id ? "PENDING_REVIEW" : listing.status;
  const serviceLabel =
    services[listing.service_type as keyof typeof services] ?? listing.service_type;
  const statusTone =
    displayedStatus === "ACTIVE"
      ? styles.statusActive
      : displayedStatus === "PENDING_REVIEW"
        ? styles.statusPending
        : displayedStatus === "SUSPENDED"
          ? styles.statusPaused
          : displayedStatus === "DRAFT"
            ? styles.statusDraft
            : styles.statusArchived;
  return (
    <article className={styles.serviceCard}>
      <header className={styles.cardTop}>
        <span className={styles.serviceIcon}>
          <PettelIcon name={listing.service_type === "BOARDING" ? "home" : "calendar"} />
        </span>
        <div className={styles.serviceHeading}>
          <span>{serviceLabel}</span>
          <h3>{listing.title}</h3>
        </div>
        <span className={`${styles.statusBadge} ${statusTone}`}>
          <i />
          {labels[displayedStatus ?? ""] ?? displayedStatus}
        </span>
      </header>
      <p className={styles.description}>{listing.description}</p>
      {listing.pending_change_request_id && (
        <p className={styles.reviewNote}>
          <PettelIcon name="clock" />
          <span>
            <strong>Đề xuất chỉnh sửa đang chờ duyệt</strong>Khách vẫn có thể đặt theo thông tin đã
            duyệt. Thay đổi mới sẽ hiển thị sau khi PETTEL chấp thuận.
          </span>
        </p>
      )}
      <div className={styles.details}>
        <span>
          <PettelIcon name="pin" />{" "}
          {areas[listing.area_code as keyof typeof areas] ?? listing.area_code}
        </span>
        <span>
          <PettelIcon name="paw" />{" "}
          {(listing.acceptance_rules_json.species ?? [])
            .map((item) => species[item as keyof typeof species] ?? item)
            .join(", ") || "Chưa chọn loài"}
        </span>
        <span>
          <PettelIcon name="shield" />{" "}
          {(listing.acceptance_rules_json.sizes ?? [])
            .map((item) => sizes[item as keyof typeof sizes] ?? item)
            .join(", ") || "Mọi kích thước"}
        </span>
      </div>
      <div className={styles.cardBottom}>
        <div className={styles.price}>
          <small>Giá niêm yết</small>
          <strong>
            {money(listing.price_vnd)} <em>/ {listing.price_unit === "NIGHT" ? "đêm" : "giờ"}</em>
          </strong>
        </div>
        <ApiError error={error} />
        <div className={styles.actions}>
          {["DRAFT", "ACTIVE", "SUSPENDED"].includes(listing.status ?? "") &&
            !listing.pending_change_request_id && (
              <button className={styles.secondaryButton} disabled={busy} onClick={edit}>
                <PettelIcon name="sliders" /> Chỉnh sửa
              </button>
            )}
          {listing.status === "DRAFT" && (
            <button
              className={styles.primaryButton}
              disabled={busy}
              onClick={() => void action("submit")}
            >
              <PettelIcon name="arrow" /> Gửi duyệt
            </button>
          )}
          {listing.status === "ACTIVE" && (
            <button
              className={styles.secondaryButton}
              disabled={busy}
              onClick={() => void action("suspend", { reason: "Người chăm tạm ngưng nhận khách" })}
            >
              Tạm ngưng
            </button>
          )}
          {listing.status === "SUSPENDED" && listing.suspension_source === "CAREGIVER" && (
            <button
              className={styles.primaryButton}
              disabled={busy}
              onClick={() => void action("restore")}
            >
              <PettelIcon name="check" /> Mở nhận khách
            </button>
          )}
          {listing.pending_change_request_id && (
            <button
              className={styles.secondaryButton}
              disabled={busy}
              onClick={() =>
                void action(`change-requests/${listing.pending_change_request_id}/withdraw`)
              }
            >
              Rút đề xuất chỉnh sửa
            </button>
          )}
          {listing.status === "ACTIVE" && (
            <Link className={styles.linkButton} href={`/detail?id=${listing.id}`}>
              <PettelIcon name="eye" /> Xem trang dịch vụ
            </Link>
          )}
        </div>
      </div>
      {listing.latest_decision?.reason && (
        <div className={styles.reviewNote}>
          <PettelIcon name="message" />
          <span>
            <strong>Phản hồi từ PETTEL</strong>
            {listing.latest_decision.reason}
          </span>
        </div>
      )}
      {listing.status === "DRAFT" && (
        <details className={styles.photoDetails}>
          <summary>
            <PettelIcon name="camera" /> Ảnh dịch vụ <span>{listing.media.length}/10</span>
          </summary>
          <div className={styles.photoGrid}>
            {listing.media.map((media) => (
              <img
                key={media.media_object_id}
                src={apiUrl(media.thumbnail_url)}
                alt="Ảnh dịch vụ của tôi"
              />
            ))}
          </div>
          <form className={styles.photoUpload} onSubmit={upload}>
            <label>
              Thêm ảnh chất lượng tốt
              <input type="file" name="file" required accept="image/jpeg,image/png,image/webp" />
            </label>
            <button disabled={busy || listing.media.length >= 10}>
              <PettelIcon name="plus" /> Tải ảnh
            </button>
          </form>
        </details>
      )}
      {showAvailability && (
        <div className={styles.availability}>
          <AvailabilityManager listings={[listing]} />
        </div>
      )}
    </article>
  );
}
