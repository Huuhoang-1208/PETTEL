"use client";

import { useState, type FormEvent } from "react";
import { useConfirmation } from "./confirmation";
import { type Pet, type Page, species, sizes, useApi, write } from "@/lib/api/pettel";
import { ApiError, RequireSession, SiteHeader, useSession } from "./session";

export function LivePets() { return <><SiteHeader /><main className="app-container"><h1>Thú cưng của tôi</h1><p>Hồ sơ được lưu trong tài khoản và dùng khi tìm dịch vụ, đặt lịch.</p><RequireSession next="/pet-profile"><Pets /></RequireSession></main></>; }

function Pets() {
  const { user } = useSession();
  const { ask, dialog } = useConfirmation();
  const resource = useApi<Page<Pet>>(user ? "/api/pets" : null);
  const [editing, setEditing] = useState<Pet | null | undefined>();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload = Object.fromEntries(form);
    setBusy(true); setError(undefined);
    try { await write(editing ? "patch" : "post", editing ? `/api/pets/${editing.id}` : "/api/pets", payload); setEditing(undefined); resource.reload(); }
    catch (failure) { setError(failure); } finally { setBusy(false); }
  }
  async function action(pet: Pet, type: "archive" | "activate" | "delete") {
    if (type === "delete" && !(await ask(`Xóa hồ sơ ${pet.name}?`))) return;
    setBusy(true); setError(undefined);
    try { await write(type === "delete" ? "delete" : "patch", `/api/pets/${pet.id}${type === "delete" ? "" : `/${type}`}`); resource.reload(); }
    catch (failure) { setError(failure); } finally { setBusy(false); }
  }
  return <>{dialog}<ApiError error={resource.error ?? error} /><button className="app-button" onClick={() => { setEditing(null); setError(undefined); }}>+ Thêm thú cưng</button>{resource.loading && <p role="status">Đang tải hồ sơ...</p>}<div className="app-grid">{resource.data?.data.map((pet) => <article className="pet-card" key={pet.id}><div className="pet-card-header"><h2>{pet.name}</h2><span>{pet.status === "ACTIVE" ? "Đang hoạt động" : "Đã lưu trữ"}</span></div><div className="pet-card-body"><p>{species[pet.species]} · {pet.sex === "MALE" ? "Đực" : pet.sex === "FEMALE" ? "Cái" : "Chưa rõ giới tính"} · {sizes[pet.size ?? ""] ?? "Chưa chọn kích thước"}</p><p>Tuổi: {pet.age_note || pet.birth_date || "Chưa cập nhật"}</p><p>Chế độ ăn: {pet.diet_notes || "Chưa cập nhật"}</p><p>Tiêm chủng: {pet.vaccination_note || "Chưa cập nhật"}</p><p>Tính cách: {pet.temperament || "Chưa cập nhật"}</p>{pet.special_needs && <p>Chăm sóc đặc biệt: {pet.special_needs}</p>}</div><div className="pet-card-actions"><button disabled={busy} onClick={() => setEditing(pet)}>Chỉnh sửa</button><button disabled={busy} onClick={() => void action(pet, pet.status === "ACTIVE" ? "archive" : "activate")}>{pet.status === "ACTIVE" ? "Lưu trữ" : "Kích hoạt"}</button><button disabled={busy} onClick={() => void action(pet, "delete")}>Xóa</button></div></article>)}</div>{!resource.loading && !resource.error && resource.data?.data.length === 0 && <p className="app-notice">Bạn chưa có hồ sơ thú cưng. Thêm hồ sơ đầu tiên để đặt dịch vụ.</p>}
    {editing !== undefined && <div className="pet-modal" style={{ display:"flex" }}><div className="pet-modal-content" role="dialog" aria-modal="true" aria-labelledby="pet-dialog-title"><h2 id="pet-dialog-title">{editing ? "Chỉnh sửa hồ sơ" : "Thêm thú cưng"}</h2><ApiError error={error} /><form className="app-form" key={editing?.id ?? "new"} onSubmit={submit}>
      <label>Tên thú cưng<input name="name" defaultValue={editing?.name} required maxLength={100} /></label>
      <label>Loài<select name="species" defaultValue={editing?.species ?? "CAT"}>{Object.entries(species).map(([key,label]) => <option key={key} value={key}>{label}</option>)}</select></label>
      <label>Giới tính<select name="sex" defaultValue={editing?.sex ?? "UNKNOWN"}><option value="UNKNOWN">Chưa rõ</option><option value="MALE">Đực</option><option value="FEMALE">Cái</option></select></label>
      <label>Kích thước<select name="size" required defaultValue={editing?.size ?? ""}><option value="" disabled>Chọn kích thước để đặt lịch</option>{Object.entries(sizes).map(([key,label]) => <option key={key} value={key}>{label}</option>)}</select></label>
      <label>Tuổi / ghi chú tuổi<input name="age_note" defaultValue={editing?.age_note ?? ""} maxLength={100} placeholder="Ví dụ: 3 tuổi" /></label>
      <label>Ngày sinh (nếu biết)<input type="date" name="birth_date" defaultValue={editing?.birth_date ?? ""} max={new Date().toISOString().slice(0,10)} /></label>
      {[["diet_notes","Chế độ ăn"],["vaccination_note","Tiêm chủng"],["temperament","Tính cách"],["special_needs","Nhu cầu chăm sóc đặc biệt"]].map(([name,label]) => <label key={name}>{label}<textarea name={name} maxLength={2000} defaultValue={String(editing?.[name as keyof Pet] ?? "")} /></label>)}
      <div className="app-actions"><button type="button" disabled={busy} onClick={() => setEditing(undefined)}>Hủy</button><button className="app-button" disabled={busy}>{busy ? "Đang lưu..." : "Lưu hồ sơ"}</button></div>
    </form></div></div>}
  </>;
}
