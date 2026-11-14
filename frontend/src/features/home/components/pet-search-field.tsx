"use client";

import { ApiError } from "@/components/ui/api-error";
import { SearchField } from "@/features/home/components/search-field";
import { petSizes, petTypes } from "@/features/home/constants";
import type { HomeSearchState } from "@/features/home/hooks/use-home-search";
import styles from "@/features/home/styles/search.module.css";
import Link from "next/link";
export function PetSearchField({
  open,
  setOpen,
  selectedPet,
  petType,
  petSize,
  hasPetsRole,
  setPetId,
  activePets,
  pets,
  setPetType,
  setPetSize,
  user,
}: Pick<
  HomeSearchState,
  | "open"
  | "setOpen"
  | "selectedPet"
  | "petType"
  | "petSize"
  | "hasPetsRole"
  | "setPetId"
  | "activePets"
  | "pets"
  | "setPetType"
  | "setPetSize"
  | "user"
>) {
  return (
    <SearchField
      field="pet"
      label="Bé cưng"
      value={selectedPet?.name ?? `1 ${petTypes[petType]} (${petSizes[petSize]})`}
      icon="paw"
      open={open}
      setOpen={setOpen}
      tone="teal"
      compact
    >
      {hasPetsRole && (
        <>
          <label>
            Hồ sơ thú cưng của tôi
            <select
              name="pet_id"
              value={selectedPet?.id ?? ""}
              onChange={(event) => setPetId(event.target.value)}
            >
              <option value="">Tìm theo loài và kích thước</option>
              {activePets.map((pet) => (
                <option key={pet.id} value={pet.id}>
                  {pet.name}
                </option>
              ))}
            </select>
          </label>
          <ApiError error={pets.error} />
          {pets.loading && <p>Đang tải hồ sơ...</p>}
        </>
      )}
      {!selectedPet && (
        <>
          <label>
            Loài thú cưng
            <select
              name="species"
              value={petType}
              onChange={(event) => setPetType(event.target.value)}
            >
              {Object.entries(petTypes).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label>
            Kích thước
            <select
              name="size"
              value={petSize}
              onChange={(event) => setPetSize(event.target.value)}
            >
              {Object.entries(petSizes).map(([value, label]) => (
                <option key={value} value={value}>
                  {label.charAt(0).toUpperCase() + label.slice(1)}
                </option>
              ))}
            </select>
          </label>
        </>
      )}
      <div className={styles.panelActions}>
        <Link href={user ? "/pet-profile" : "/login?next=%2Fpet-profile"}>
          {user ? "Quản lý hồ sơ thú cưng" : "Đăng nhập để dùng hồ sơ"}
        </Link>
        <button type="button" onClick={() => setOpen(null)}>
          Xong
        </button>
      </div>
    </SearchField>
  );
}
