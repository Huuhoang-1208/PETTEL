"use client";

import { usePets, type Pet } from "@/legacy/scripts/pet-store";
import Link from "next/link";
import { useState, type FormEvent } from "react";

const personalities: Record<string, string> = {
  friendly: "Thân thiện",
  shy: "Nhút nhát",
  active: "Năng động",
  "indoor-only": "Chỉ ở trong nhà",
  outdoor: "Thích ở ngoài",
};

export function PetProfileView() {
  const { pets, savePets } = usePets();
  // undefined closes the dialog; null creates a new pet.
  const [editing, setEditing] = useState<Pet | null | undefined>();

  function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const data = new FormData(event.currentTarget);
    const text = (name: string) => String(data.get(name) ?? "").trim();
    const pet: Pet = {
      id: editing?.id ?? crypto.randomUUID(),
      name: text("name"),
      species: text("species"),
      sex: text("sex"),
      age: Number(text("age")),
      diet: text("diet"),
      vaccination: text("vaccination"),
      personality: data.getAll("personality").map(String),
      specialNote: text("specialNote"),
    };
    if (!pet.name || !Number.isFinite(pet.age)) return;
    savePets(editing ? pets.map((item) => (item.id === pet.id ? pet : item)) : [...pets, pet]);
    setEditing(undefined);
  }

  return (
    <>
      <header className="pet-profile-header">
        <Link href="/" className="pet-profile-back" aria-label="Quay về trang chủ">
          <svg viewBox="0 0 24 24">
            <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" />
          </svg>
        </Link>
        <h1>Thú cưng của tôi</h1>
      </header>
      <div className="pet-profile-page">
        <div className="pet-profile-intro">
          <p>
            Tạo hồ sơ cho thú cưng của bạn. Thông tin sẽ tự động được gửi khi đặt lịch và dùng cho
            Smart Match.
          </p>
        </div>
        <button className="btn-add-pet" id="btn-add-pet" onClick={() => setEditing(null)}>
          + Thêm thú cưng
        </button>
        <div className="pet-cards-container" id="pet-cards-container">
          {pets.length === 0 && (
            <div className="no-pets-message">Chưa có thú cưng nào. Hãy thêm thú cưng đầu tiên!</div>
          )}
          {pets.map((pet) => (
            <div className="pet-card" key={pet.id}>
              <div className="pet-card-header">
                <h3>
                  <i className={`fa-solid fa-${pet.species === "cat" ? "cat" : "dog"}`} />{" "}
                  {pet.name}
                </h3>
                <div className="pet-card-subtitle">Digital pet profile · Auto-shared</div>
              </div>
              <div className="pet-card-body">
                <div className="pet-info-row">
                  <Info
                    label="Species"
                    value={`${pet.species === "cat" ? "Mèo" : "Chó"} · ${pet.sex === "male" ? "Đực" : "Cái"} · ${pet.age} yrs`}
                  />
                  <Info label="Diet" value={pet.diet} />
                </div>
                <div className="pet-info-row">
                  <Info label="Vaccination" value={pet.vaccination} />
                  <Info
                    label="Personality"
                    value={pet.personality.map((p) => personalities[p] ?? p).join(" · ")}
                  />
                </div>
                {pet.specialNote && (
                  <div className="pet-special-note">
                    <div className="pet-info-label">
                      <i className="fa-solid fa-exclamation-triangle" /> Special Note
                    </div>
                    <div className="pet-info-value">{pet.specialNote}</div>
                  </div>
                )}
              </div>
              <div className="pet-card-actions">
                <button className="pet-edit-btn" onClick={() => setEditing(pet)}>
                  <i className="fa-solid fa-edit" /> Chỉnh sửa
                </button>
                <button
                  className="pet-delete-btn"
                  onClick={() => {
                    if (confirm("Bạn có chắc muốn xóa thú cưng này?"))
                      savePets(pets.filter((item) => item.id !== pet.id));
                  }}
                >
                  <i className="fa-solid fa-trash" /> Xóa
                </button>
              </div>
            </div>
          ))}
        </div>
      </div>
      {editing !== undefined && (
        <div
          className="pet-modal"
          id="pet-modal"
          style={{ display: "flex" }}
          onClick={(event) => {
            if (event.target === event.currentTarget) setEditing(undefined);
          }}
        >
          <div
            className="pet-modal-content"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
          >
            <div className="pet-modal-header">
              <h2 id="modal-title">{editing ? "Chỉnh sửa thú cưng" : "Thêm thú cưng"}</h2>
              <button
                className="pet-modal-close"
                onClick={() => setEditing(undefined)}
                aria-label="Đóng"
              >
                ×
              </button>
            </div>
            <form className="pet-form" id="pet-form" key={editing?.id ?? "new"} onSubmit={submit}>
              <div className="form-group">
                <label htmlFor="pet-name">
                  Tên thú cưng <span className="required">*</span>
                </label>
                <input
                  id="pet-name"
                  name="name"
                  required
                  defaultValue={editing?.name}
                  placeholder="Ví dụ: Min"
                />
              </div>
              <div className="form-group">
                <label>
                  Loài <span className="required">*</span>
                </label>
                <div className="form-radio-group">
                  {[
                    ["cat", "Mèo"],
                    ["dog", "Chó"],
                  ].map(([value, label]) => (
                    <label className="form-radio" key={value}>
                      <input
                        type="radio"
                        name="species"
                        value={value}
                        required
                        defaultChecked={(editing?.species ?? "cat") === value}
                      />
                      <span>
                        <i className={`fa-solid fa-${value}`} /> {label}
                      </span>
                    </label>
                  ))}
                </div>
              </div>
              <div className="form-group">
                <label>
                  Giới tính <span className="required">*</span>
                </label>
                <div className="form-radio-group">
                  {[
                    ["male", "Đực"],
                    ["female", "Cái"],
                  ].map(([value, label]) => (
                    <label className="form-radio" key={value}>
                      <input
                        type="radio"
                        name="sex"
                        value={value}
                        required
                        defaultChecked={(editing?.sex ?? "male") === value}
                      />
                      <span>{label}</span>
                    </label>
                  ))}
                </div>
              </div>
              <div className="form-group">
                <label htmlFor="pet-age">
                  Tuổi (năm) <span className="required">*</span>
                </label>
                <input
                  type="number"
                  id="pet-age"
                  name="age"
                  required
                  min="0"
                  max="30"
                  step="0.5"
                  defaultValue={editing?.age}
                  placeholder="Ví dụ: 3"
                />
              </div>
              <div className="form-group">
                <label htmlFor="pet-diet">Chế độ ăn</label>
                <textarea
                  id="pet-diet"
                  name="diet"
                  rows={2}
                  defaultValue={editing?.diet}
                  placeholder="Ví dụ: 2× daily · No fish"
                />
              </div>
              <div className="form-group">
                <label htmlFor="pet-vaccination">Tình trạng tiêm chủng</label>
                <input
                  id="pet-vaccination"
                  name="vaccination"
                  defaultValue={editing?.vaccination}
                  placeholder="Ví dụ: Up to date · Mar 2026"
                />
              </div>
              <div className="form-group">
                <label>Tính cách</label>
                <div className="form-checkbox-group">
                  {Object.entries(personalities).map(([value, label]) => (
                    <label className="form-checkbox" key={value}>
                      <input
                        type="checkbox"
                        name="personality"
                        value={value}
                        defaultChecked={editing?.personality.includes(value)}
                      />
                      <span>{label}</span>
                    </label>
                  ))}
                </div>
              </div>
              <div className="form-group">
                <label htmlFor="pet-special-note">Ghi chú đặc biệt</label>
                <textarea
                  id="pet-special-note"
                  name="specialNote"
                  rows={3}
                  defaultValue={editing?.specialNote}
                />
              </div>
              <div className="form-actions">
                <button type="button" className="btn-cancel" onClick={() => setEditing(undefined)}>
                  Hủy
                </button>
                <button type="submit" className="btn-save">
                  Lưu thẻ
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </>
  );
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div className="pet-info-col">
      <div className="pet-info-label">{label}</div>
      <div className="pet-info-value">{value || "Chưa cập nhật"}</div>
    </div>
  );
}
