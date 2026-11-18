"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { areas } from "@/lib/constants";
import { useEffect, useRef, type FormEvent } from "react";
import type { OwnerProfile } from "../types";
import styles from "../owner-profile.module.css";

export function OwnerEditor({ profile, busy, error, onClose, onSave }: { profile: OwnerProfile; busy: boolean; error: unknown; onClose: () => void; onSave: (event: FormEvent<HTMLFormElement>) => void }) {
  const dialog = useRef<HTMLDialogElement>(null);
  useEffect(() => {
    const element = dialog.current;
    element?.showModal();
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => { element?.close(); document.body.style.overflow = previous; };
  }, []);
  return <dialog ref={dialog} className={styles.editor} aria-labelledby="owner-editor-title" onCancel={(event) => { event.preventDefault(); if (!busy) onClose(); }}>
    <form onSubmit={onSave}><header><span><PettelIcon name="user" /></span><h2 id="owner-editor-title">Chỉnh sửa hồ sơ chủ nuôi</h2><button type="button" aria-label="Đóng chỉnh sửa" disabled={busy} onClick={onClose}>×</button></header>
      <div className={styles.editorBody}><ApiError error={error} />
        <label>Họ và tên <b>*</b><input name="name" required maxLength={255} defaultValue={profile.name} autoComplete="name" disabled={busy} /></label>
        <label>Email đăng nhập<input value={profile.email} readOnly aria-readonly="true" /></label>
        <div className={styles.editorGrid}><label>Số điện thoại<input name="phone" type="tel" maxLength={30} defaultValue={profile.phone ?? ""} autoComplete="tel" placeholder="Nhập số điện thoại" disabled={busy} /></label><label>Khu vực<select name="area_code" defaultValue={profile.area_code ?? ""} disabled={busy}><option value="">Chưa chọn khu vực</option>{Object.entries(areas).map(([code, label]) => <option key={code} value={code}>{label}</option>)}</select></label></div>
        <label>Lời chào từ chủ nuôi<textarea name="bio" rows={4} maxLength={2000} defaultValue={profile.bio ?? ""} placeholder="Chia sẻ một chút về bạn và các bé cưng..." disabled={busy} /></label>
        <label>Ảnh đại diện<input name="photo" type="file" accept="image/jpeg,image/png,image/webp" disabled={busy} /><small>JPG, PNG hoặc WebP, tối đa 5 MB. Ảnh được lưu riêng trong tài khoản.</small></label>
      </div><footer><button type="button" className={styles.secondaryButton} disabled={busy} onClick={onClose}>Hủy bỏ</button><button type="submit" className={styles.primaryButton} disabled={busy}><PettelIcon name="check" />{busy ? "Đang lưu..." : "Lưu thay đổi"}</button></footer>
    </form>
  </dialog>;
}
