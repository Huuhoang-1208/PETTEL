"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { ManagedListing } from "@/features/caregiver/types";
import { write } from "@/lib/api/request";
import { areas, services, sizes, species } from "@/lib/constants";
import { useState, type FormEvent } from "react";

export function ListingEditor({
  listing,
  onSave,
  onClose,
  styles,
}: {
  listing: ManagedListing | null;
  onSave: () => void;
  onClose: () => void;
  styles: Record<string, string>;
}) {
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const live = listing && listing.status !== "DRAFT";
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const service = String(data.get("service_type"));
    const payload = {
      title: data.get("title"),
      description: data.get("description"),
      area_code: data.get("area_code"),
      service_type: service,
      price_vnd: Number(data.get("price_vnd")),
      price_unit: service === "DAYCARE" ? "HOUR" : "NIGHT",
      acceptance_rules_json: {
        schema_version: 1,
        species: data.getAll("species"),
        sizes: data.getAll("sizes"),
      },
    };
    setBusy(true);
    setError(undefined);
    try {
      if (live)
        await write("post", `/api/v1/caregiver/listings/${listing.id}/change-requests`, {
          base_listing_version: listing.version,
          changes: payload,
        });
      else
        await write(
          listing ? "patch" : "post",
          listing ? `/api/v1/caregiver/listings/${listing.id}` : "/api/v1/caregiver/listings",
          payload,
        );
      onSave();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <header className={styles.editorHeader}>
        <div>
          <span className={styles.editorEyebrow}>
            <PettelIcon name="home" /> PETTEL SITTER
          </span>
          <h2>
            {live
              ? "Đề xuất chỉnh sửa dịch vụ"
              : listing
                ? "Chỉnh sửa bản nháp"
                : "Tạo dịch vụ mới"}
          </h2>
          <p>
            {live
              ? "Thay đổi sẽ được gửi PETTEL xem xét trước khi áp dụng."
              : "Mô tả rõ dịch vụ để khách hàng dễ chọn hơn."}
          </p>
        </div>
        <button
          className={styles.closeButton}
          type="button"
          onClick={onClose}
          aria-label="Đóng cửa sổ"
        >
          ×
        </button>
      </header>
      <ApiError error={error} />
      <form className={`${styles.editorForm} app-form`} onSubmit={submit}>
        <label>
          Tên dịch vụ
          <input name="title" defaultValue={listing?.title} required maxLength={120} />
        </label>
        <label>
          Mô tả
          <textarea
            name="description"
            defaultValue={listing?.description}
            required
            maxLength={2000}
          />
        </label>
        <label>
          Loại dịch vụ
          <select name="service_type" defaultValue={listing?.service_type ?? "DAYCARE"}>
            {Object.entries(services).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Khu vực nhận chăm
          <select name="area_code" defaultValue={listing?.area_code ?? "DN_CENTER"}>
            {Object.entries(areas).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Giá dịch vụ (VND / giờ hoặc đêm)
          <input
            type="number"
            name="price_vnd"
            required
            min="1"
            step="1"
            defaultValue={listing?.price_vnd}
          />
        </label>
        <fieldset className={styles.editorFieldset}>
          <legend>Loài nhận chăm</legend>
          {Object.entries(species).map(([key, label]) => (
            <label className={styles.editorCheck} key={key}>
              <input
                type="checkbox"
                name="species"
                value={key}
                defaultChecked={
                  listing ? listing.acceptance_rules_json.species.includes(key) : key === "CAT"
                }
              />
              {label}
            </label>
          ))}
        </fieldset>
        <fieldset className={styles.editorFieldset}>
          <legend>Kích thước nhận chăm</legend>
          {Object.entries(sizes).map(([key, label]) => (
            <label className={styles.editorCheck} key={key}>
              <input
                type="checkbox"
                name="sizes"
                value={key}
                defaultChecked={
                  listing ? listing.acceptance_rules_json.sizes.includes(key) : key === "SMALL"
                }
              />
              {label}
            </label>
          ))}
        </fieldset>
        <div className={styles.editorActions}>
          <button disabled={busy} className={styles.primaryButton}>
            {live ? "Gửi đề xuất duyệt" : "Lưu bản nháp"}
          </button>
          <button
            type="button"
            disabled={busy}
            className={styles.secondaryButton}
            onClick={onClose}
          >
            Đóng
          </button>
        </div>
      </form>
    </>
  );
}
