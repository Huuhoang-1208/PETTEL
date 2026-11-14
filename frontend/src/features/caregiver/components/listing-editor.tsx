"use client";

import { ApiError } from "@/components/ui/api-error";
import { ManagedListing } from "@/features/caregiver/types";
import { write } from "@/lib/api/request";
import { areas, services, sizes, species } from "@/lib/constants";
import { useState, type FormEvent } from "react";

export function ListingEditor({
  listing,
  onSave,
  onClose,
}: {
  listing: ManagedListing | null;
  onSave: () => void;
  onClose: () => void;
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
        await write("post", `/api/caregiver/listings/${listing.id}/change-requests`, {
          base_listing_version: listing.version,
          changes: payload,
        });
      else
        await write(
          listing ? "patch" : "post",
          listing ? `/api/caregiver/listings/${listing.id}` : "/api/caregiver/listings",
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
      <h2>{live ? "Gửi đề xuất chỉnh sửa" : listing ? "Chỉnh sửa bản nháp" : "Tạo dịch vụ"}</h2>
      <ApiError error={error} />
      <form className="app-form" onSubmit={submit}>
        <label>
          Tiêu đề
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
          Dịch vụ
          <select name="service_type" defaultValue={listing?.service_type ?? "DAYCARE"}>
            {Object.entries(services).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Khu vực
          <select name="area_code" defaultValue={listing?.area_code ?? "DN_CENTER"}>
            {Object.entries(areas).map(([key, label]) => (
              <option key={key} value={key}>
                {label}
              </option>
            ))}
          </select>
        </label>
        <label>
          Giá VND / giờ (ban ngày) hoặc đêm (lưu trú)
          <input
            type="number"
            name="price_vnd"
            required
            min="1"
            step="1"
            defaultValue={listing?.price_vnd}
          />
        </label>
        <fieldset>
          <legend>Loài nhận chăm</legend>
          {Object.entries(species).map(([key, label]) => (
            <label className="app-check" key={key}>
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
        <fieldset>
          <legend>Kích thước nhận chăm</legend>
          {Object.entries(sizes).map(([key, label]) => (
            <label className="app-check" key={key}>
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
        <div className="app-actions">
          <button disabled={busy} className="app-button">
            {live ? "Gửi đề xuất duyệt" : "Lưu bản nháp"}
          </button>
          <button type="button" disabled={busy} onClick={onClose}>
            Đóng
          </button>
        </div>
      </form>
    </>
  );
}
