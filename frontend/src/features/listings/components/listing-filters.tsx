"use client";

import type { User } from "@/lib/api/types";
import { type Page, type Pet } from "@/lib/api/types";
import { areas, sizes, species } from "@/lib/constants";
import { localDate } from "@/lib/date";
import Link from "next/link";
import { useState, type FormEvent } from "react";

export function ListingFilters({
  query,
  pets,
  user,
  search,
}: {
  query: Pick<URLSearchParams, "get" | "toString">;
  pets: { data?: Page<Pet> };
  user: User | null;
  search: (event: FormEvent<HTMLFormElement>) => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const [serviceType, setServiceType] = useState(query.get("service_type") ?? "");

  function openSmartMatch() {
    setExpanded(true);
    window.setTimeout(() => document.getElementById("listing-smart-match")?.focus(), 0);
  }

  return (
    <div className="listing-filter-shell">
      <form className="listing-filter-form" onSubmit={search}>
        <input type="hidden" name="service_type" value={serviceType} />
        <div className="listing-filter-toolbar">
          <button
            className="listing-filter-chip"
            type="button"
            onClick={() => setExpanded((value) => !value)}
            aria-expanded={expanded}
          >
            Giá <span className="listing-chevron" aria-hidden="true" />
          </button>
          <label className="listing-filter-chip listing-area-chip">
            <span>Khu vực</span>
            <select name="area_code" defaultValue={query.get("area_code") ?? ""}>
              <option value="">Tất cả</option>
              {Object.entries(areas).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
            <span className="listing-chevron" aria-hidden="true" />
          </label>
          <span className="listing-chip-divider" aria-hidden="true" />
          <button
            className={`listing-filter-chip ${serviceType === "DAYCARE" ? "is-selected" : ""}`}
            type="button"
            aria-pressed={serviceType === "DAYCARE"}
            onClick={() => setServiceType((value) => (value === "DAYCARE" ? "" : "DAYCARE"))}
          >
            Trông tại gia
          </button>
          <button
            className={`listing-filter-chip ${serviceType === "BOARDING" ? "is-selected" : ""}`}
            type="button"
            aria-pressed={serviceType === "BOARDING"}
            onClick={() => setServiceType((value) => (value === "BOARDING" ? "" : "BOARDING"))}
          >
            Gửi gắm
          </button>
          <button
            className="listing-filter-chip is-unavailable"
            type="button"
            disabled
            title="Dịch vụ này chưa được hỗ trợ trong danh sách dịch vụ"
          >
            Dắt đi dạo
          </button>
          <span className="listing-chip-divider" aria-hidden="true" />
          <button
            className="listing-filter-chip is-unavailable"
            type="button"
            disabled
            title="Bộ lọc này chưa được hỗ trợ"
          >
            Chỉ PETTEL Pro
          </button>
          <button
            className="listing-filter-chip is-unavailable"
            type="button"
            disabled
            title="Bộ lọc này chưa được hỗ trợ"
          >
            Verified trở lên
          </button>
          <span className="listing-chip-divider" aria-hidden="true" />
          <button className="listing-smart-match" type="button" onClick={openSmartMatch}>
            <span aria-hidden="true">✦</span> Smart Match
          </button>
          <button
            className="listing-expand-button"
            type="button"
            aria-label={expanded ? "Thu gọn bộ lọc" : "Mở đầy đủ bộ lọc"}
            aria-expanded={expanded}
            onClick={() => setExpanded((value) => !value)}
          >
            <svg viewBox="0 0 24 24" aria-hidden="true">
              <path d="M4 7h9m4 0h3M4 17h3m4 0h9M13 4v6M9 14v6" />
            </svg>
          </button>
        </div>

        {expanded && (
          <div className="listing-advanced-panel">
            <fieldset className="listing-option-group">
              <legend>Loại thú cưng</legend>
              <div className="listing-choice-list">
                <label>
                  <input
                    type="radio"
                    name="species"
                    value=""
                    defaultChecked={!query.get("species")}
                  />
                  Tất cả
                </label>
                {Object.entries(species).map(([value, label]) => (
                  <label key={value}>
                    <input
                      type="radio"
                      name="species"
                      value={value}
                      defaultChecked={query.get("species") === value}
                    />
                    {label}
                  </label>
                ))}
              </div>
            </fieldset>

            <fieldset className="listing-option-group">
              <legend>Kích thước</legend>
              <div className="listing-choice-list">
                <label>
                  <input type="radio" name="size" value="" defaultChecked={!query.get("size")} />
                  Tất cả
                </label>
                {Object.entries(sizes).map(([value, label]) => (
                  <label key={value}>
                    <input
                      type="radio"
                      name="size"
                      value={value}
                      defaultChecked={query.get("size") === value}
                    />
                    {label}
                  </label>
                ))}
              </div>
            </fieldset>

            <label className="listing-smart-select">
              Smart Match theo thú cưng
              <select
                id="listing-smart-match"
                name="pet_id"
                defaultValue={query.get("pet_id") ?? ""}
                disabled={!user}
              >
                <option value="">{user ? "Chọn thú cưng của tôi" : "Đăng nhập để dùng"}</option>
                {pets.data?.data
                  .filter((pet) => pet.status === "ACTIVE")
                  .map((pet) => (
                    <option key={pet.id} value={pet.id}>
                      {pet.name}
                    </option>
                  ))}
              </select>
            </label>

            <label>
              Bắt đầu
              <input
                type="datetime-local"
                name="start_at"
                defaultValue={
                  query.get("start_at") ? localDate(new Date(query.get("start_at")!)) : ""
                }
              />
            </label>
            <label>
              Kết thúc
              <input
                type="datetime-local"
                name="end_at"
                defaultValue={query.get("end_at") ? localDate(new Date(query.get("end_at")!)) : ""}
              />
            </label>
            <label>
              Tổng giá từ (VND)
              <input
                type="number"
                name="min_price_vnd"
                min="0"
                defaultValue={query.get("min_price_vnd") ?? ""}
              />
            </label>
            <label>
              Tổng giá đến (VND)
              <input
                type="number"
                name="max_price_vnd"
                min="0"
                defaultValue={query.get("max_price_vnd") ?? ""}
              />
            </label>

            <div className="listing-advanced-actions">
              <Link href="/services">Xóa tất cả</Link>
              <button className="app-button" type="submit">
                Áp dụng
              </button>
            </div>
          </div>
        )}
      </form>
    </div>
  );
}
