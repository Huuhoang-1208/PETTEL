"use client";

import { ApiError } from "@/components/ui/api-error";
import { type Pet } from "@/lib/api/types";
import { sizes, species } from "@/lib/constants";
import { type FormEvent } from "react";

export function PetEditor({
  editing,
  error,
  busy,
  submit,
  onClose,
}: {
  editing: Pet | null;
  error: unknown;
  busy: boolean;
  submit: (event: FormEvent<HTMLFormElement>) => void;
  onClose: () => void;
}) {
  return (
    <div className="pet-modal" style={{ display: "flex" }}>
      <div
        className="pet-modal-content"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pet-dialog-title"
      >
        <h2 id="pet-dialog-title">{editing ? "Chỉnh sửa hồ sơ" : "Thêm thú cưng"}</h2>
        <ApiError error={error} />
        <form className="app-form" key={editing?.id ?? "new"} onSubmit={submit}>
          <label>
            Tên thú cưng
            <input name="name" defaultValue={editing?.name} required maxLength={100} />
          </label>
          <label>
            Loài
            <select name="species" defaultValue={editing?.species ?? "CAT"}>
              {Object.entries(species).map(([key, label]) => (
                <option key={key} value={key}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label>
            Giới tính
            <select name="sex" defaultValue={editing?.sex ?? "UNKNOWN"}>
              <option value="UNKNOWN">Chưa rõ</option>
              <option value="MALE">Đực</option>
              <option value="FEMALE">Cái</option>
            </select>
          </label>
          <label>
            Kích thước
            <select name="size" required defaultValue={editing?.size ?? ""}>
              <option value="" disabled>
                Chọn kích thước để đặt lịch
              </option>
              {Object.entries(sizes).map(([key, label]) => (
                <option key={key} value={key}>
                  {label}
                </option>
              ))}
            </select>
          </label>
          <label>
            Tuổi / ghi chú tuổi
            <input
              name="age_note"
              defaultValue={editing?.age_note ?? ""}
              maxLength={100}
              placeholder="Ví dụ: 3 tuổi"
            />
          </label>
          <label>
            Ngày sinh (nếu biết)
            <input
              type="date"
              name="birth_date"
              defaultValue={editing?.birth_date ?? ""}
              max={new Date().toISOString().slice(0, 10)}
            />
          </label>
          {[
            ["diet_notes", "Chế độ ăn"],
            ["vaccination_note", "Tiêm chủng"],
            ["temperament", "Tính cách"],
            ["special_needs", "Nhu cầu chăm sóc đặc biệt"],
          ].map(([name, label]) => (
            <label key={name}>
              {label}
              <textarea
                name={name}
                maxLength={2000}
                defaultValue={String(editing?.[name as keyof Pet] ?? "")}
              />
            </label>
          ))}
          <div className="app-actions">
            <button type="button" disabled={busy} onClick={() => onClose()}>
              Hủy
            </button>
            <button className="app-button" disabled={busy}>
              {busy ? "Đang lưu..." : "Lưu hồ sơ"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
