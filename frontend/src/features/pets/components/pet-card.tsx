"use client";

import { type Pet } from "@/lib/api/types";
import { sizes, species } from "@/lib/constants";

export function PetCard({
  pet,
  busy,
  onEdit,
  onAction,
}: {
  pet: Pet;
  busy: boolean;
  onEdit: (pet: Pet) => void;
  onAction: (pet: Pet, type: "archive" | "activate" | "delete") => Promise<void>;
}) {
  return (
    <article className="pet-card" key={pet.id}>
      <div className="pet-card-header">
        <h2>{pet.name}</h2>
        <span>{pet.status === "ACTIVE" ? "Đang hoạt động" : "Đã lưu trữ"}</span>
      </div>
      <div className="pet-card-body">
        <p>
          {species[pet.species]} ·{" "}
          {pet.sex === "MALE" ? "Đực" : pet.sex === "FEMALE" ? "Cái" : "Chưa rõ giới tính"} ·{" "}
          {sizes[pet.size ?? ""] ?? "Chưa chọn kích thước"}
        </p>
        <p>Tuổi: {pet.age_note || pet.birth_date || "Chưa cập nhật"}</p>
        <p>Chế độ ăn: {pet.diet_notes || "Chưa cập nhật"}</p>
        <p>Tiêm chủng: {pet.vaccination_note || "Chưa cập nhật"}</p>
        <p>Tính cách: {pet.temperament || "Chưa cập nhật"}</p>
        {pet.special_needs && <p>Chăm sóc đặc biệt: {pet.special_needs}</p>}
      </div>
      <div className="pet-card-actions">
        <button disabled={busy} onClick={() => onEdit(pet)}>
          Chỉnh sửa
        </button>
        <button
          disabled={busy}
          onClick={() => void onAction(pet, pet.status === "ACTIVE" ? "archive" : "activate")}
        >
          {pet.status === "ACTIVE" ? "Lưu trữ" : "Kích hoạt"}
        </button>
        <button disabled={busy} onClick={() => void onAction(pet, "delete")}>
          Xóa
        </button>
      </div>
    </article>
  );
}
