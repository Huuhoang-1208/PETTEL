"use client";

import type { User } from "@/lib/api/types";
import { type Page, type Pet } from "@/lib/api/types";
import { areas, sizes, species } from "@/lib/constants";
import { localDate } from "@/lib/date";
import Link from "next/link";
import { useState, type FormEvent } from "react";

const behaviorLabels: Record<string, string> = {
  SHY: "Nhút nhát",
  FEARS_STRANGERS: "Sợ người lạ",
  NEEDS_WARM_UP: "Cần thời gian làm quen",
  NOT_SOCIAL_WITH_PETS: "Không quen thú cưng khác",
  SEPARATION_ANXIETY: "Lo lắng khi ở một mình",
  HIGH_ENERGY: "Nhiều năng lượng",
  SENSITIVE_TO_NOISE: "Nhạy cảm tiếng ồn",
};

const careSupportLabels: Record<string, string> = {
  ORAL_MEDICATION: "Cần uống thuốc",
  EYE_DROPS: "Cần nhỏ mắt",
  SPECIAL_DIET: "Chế độ ăn riêng",
  POST_SURGERY: "Hồi phục sau phẫu thuật",
  MOBILITY_SUPPORT: "Cần hỗ trợ đi lại",
  OTHER_SPECIAL_CARE: "Chăm sóc đặc biệt",
};

export function ListingFilters({
  query,
  pets,
  user,
  search,
}: {
  query: Pick<URLSearchParams, "get" | "toString">;
  pets: { data?: Page<Pet>; loading?: boolean };
  user: User | null;
  search: (event: FormEvent<HTMLFormElement>) => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const [smartMatchMode, setSmartMatchMode] = useState(Boolean(query.get("pet_id")));
  const [selectedPetId, setSelectedPetId] = useState(query.get("pet_id") ?? "");
  const [serviceType, setServiceType] = useState(query.get("service_type") ?? "");
  const activePets = pets.data?.data.filter((pet) => pet.status === "ACTIVE") ?? [];
  const selectedPet = activePets.find((pet) => pet.id === selectedPetId);

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
          <button
            className={`listing-smart-match ${smartMatchMode ? "is-active" : ""}`}
            type="button"
            aria-pressed={smartMatchMode}
            onClick={() => {
              if (smartMatchMode) {
                setSmartMatchMode(false);
                setSelectedPetId("");
              } else {
                setSmartMatchMode(true);
                openSmartMatch();
              }
            }}
          >
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
            <div className="listing-advanced-heading">
              <div>
                <span className="listing-advanced-eyebrow">
                  {smartMatchMode ? "PET ID CARD · SMART MATCH" : "TÙY CHỌN TÌM KIẾM"}
                </span>
                <h2>{smartMatchMode ? "Tìm người chăm phù hợp với bé" : "Bộ lọc nâng cao"}</h2>
                <p>
                  {smartMatchMode
                    ? "PETTEL dùng hồ sơ bé để lọc loài, kích thước và ưu tiên Sitter có kinh nghiệm chăm sóc tương ứng."
                    : "Kết hợp loài, kích thước, thời gian và ngân sách để tìm dịch vụ phù hợp."}
                </p>
              </div>
              <span className="listing-advanced-hint">
                <span aria-hidden="true">✦</span>{" "}
                {smartMatchMode ? "Tìm theo nhu cầu của bé" : "Lọc theo nhu cầu đặt dịch vụ"}
              </span>
            </div>

            {!smartMatchMode && <fieldset className="listing-option-group listing-option-group--species">
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
            </fieldset>}

            {!smartMatchMode && <fieldset className="listing-option-group listing-option-group--size">
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
            </fieldset>}

            <label className={`listing-smart-select listing-smart-select--pet ${smartMatchMode ? "is-smart-match" : ""}`}>
              {smartMatchMode ? "Chọn hồ sơ thú cưng" : "Smart Match theo thú cưng"}
              <select
                id="listing-smart-match"
                name="pet_id"
                value={selectedPetId}
                onChange={(event) => setSelectedPetId(event.target.value)}
                disabled={!user}
              >
                <option value="">{user ? "Chọn thú cưng của tôi" : "Đăng nhập để dùng"}</option>
                {activePets.map((pet) => (
                  <option key={pet.id} value={pet.id}>
                    {pet.name}
                  </option>
                ))}
              </select>
            </label>

            {smartMatchMode && selectedPet && (
              <article className="listing-smart-pet-card">
                <div className="listing-smart-pet-card__identity">
                  <span className="listing-smart-pet-card__paw" aria-hidden="true">🐾</span>
                  <div>
                    <span className="listing-smart-pet-card__eyebrow">HỒ SƠ BÉ ĐƯỢC CHỌN</span>
                    <h3>{selectedPet.name}</h3>
                    <p>
                      {species[selectedPet.species as keyof typeof species] ?? selectedPet.species}
                      {selectedPet.size ? ` · ${sizes[selectedPet.size as keyof typeof sizes] ?? selectedPet.size}` : " · Chưa có kích thước"}
                      {selectedPet.age_note ? ` · ${selectedPet.age_note}` : ""}
                    </p>
                  </div>
                </div>
                <div className="listing-smart-pet-card__details">
                  {selectedPet.behavior_tags?.map((tag) => <span key={tag}>{behaviorLabels[tag] ?? tag}</span>)}
                  {selectedPet.care_support_tags?.map((tag) => <span key={tag}>{careSupportLabels[tag] ?? tag}</span>)}
                  {selectedPet.food_allergies || selectedPet.foods_to_avoid ? <span>Có lưu ý dị ứng / thức ăn cần tránh</span> : null}
                  {!selectedPet.behavior_tags?.length && !selectedPet.care_support_tags?.length && !selectedPet.food_allergies && !selectedPet.foods_to_avoid && !selectedPet.diet_notes ? (
                    <span>Thêm tính cách hoặc lưu ý chăm sóc để kết quả gợi ý sát hơn.</span>
                  ) : null}
                </div>
                <p className="listing-smart-pet-card__privacy">
                  Thông tin sức khỏe và chế độ ăn không hiển thị công khai; chỉ chia sẻ với Sitter khi bạn chọn dịch vụ hoặc đặt lịch.
                </p>
              </article>
            )}

            {smartMatchMode && !selectedPet && (
              <p className="listing-smart-empty">
                {user
                  ? pets.loading
                    ? "Đang tải hồ sơ thú cưng của bạn..."
                    : activePets.length
                      ? "Chọn một bé để PETTEL bắt đầu tìm Sitter phù hợp."
                      : <>Bạn chưa có hồ sơ thú cưng đang hoạt động. <Link href="/pet-profile">Tạo hồ sơ bé</Link> trước khi dùng Smart Match.</>
                  : <>Vui lòng <Link href="/login">đăng nhập</Link> và tạo hồ sơ thú cưng để dùng Smart Match.</>}
              </p>
            )}

            {smartMatchMode && selectedPet && (!selectedPet.species || !selectedPet.size) && (
              <p className="listing-smart-empty">
                Hồ sơ bé còn thiếu loài hoặc kích thước. Hãy cập nhật trong <Link href="/pet-profile">Hồ sơ thú cưng</Link> để Smart Match lọc chính xác.
              </p>
            )}

            {smartMatchMode && (
              <label className="listing-smart-space">
                Môi trường chăm sóc mong muốn
                <select name="space_type" defaultValue={query.get("space_type") ?? ""}>
                  <option value="">Không giới hạn</option>
                  <option value="INDOOR">Trong nhà, không có sân</option>
                  <option value="INDOOR_YARD">Trong nhà, có sân</option>
                </select>
                <small>Câu trả lời này giúp PETTEL ưu tiên không gian phù hợp với thói quen của bé.</small>
              </label>
            )}

            <label className="listing-date-filter">
              Bắt đầu
              <input
                type="datetime-local"
                name="start_at"
                defaultValue={
                  query.get("start_at") ? localDate(new Date(query.get("start_at")!)) : ""
                }
              />
            </label>
            <label className="listing-date-filter">
              Kết thúc
              <input
                type="datetime-local"
                name="end_at"
                defaultValue={query.get("end_at") ? localDate(new Date(query.get("end_at")!)) : ""}
              />
            </label>
            <fieldset className="listing-price-group">
              <legend>Ngân sách dự kiến <span>VND</span></legend>
              <div className="listing-price-fields">
                <label>
                  <span>Từ</span>
                  <input
                    type="number"
                    name="min_price_vnd"
                    min="0"
                    placeholder="Không giới hạn"
                    defaultValue={query.get("min_price_vnd") ?? ""}
                  />
                </label>
                <span className="listing-price-separator" aria-hidden="true">—</span>
                <label>
                  <span>Đến</span>
                  <input
                    type="number"
                    name="max_price_vnd"
                    min="0"
                    placeholder="Không giới hạn"
                    defaultValue={query.get("max_price_vnd") ?? ""}
                  />
                </label>
              </div>
            </fieldset>

            <div className="listing-advanced-actions">
              <Link href="/services">Xóa tất cả</Link>
              <button
                className="app-button"
                type="submit"
                disabled={smartMatchMode && (!selectedPet || !selectedPet.species || !selectedPet.size)}
              >
                {smartMatchMode ? "Tìm Sitter phù hợp" : "Áp dụng"}
              </button>
            </div>
          </div>
        )}
      </form>
    </div>
  );
}
