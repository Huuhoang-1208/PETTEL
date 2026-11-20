"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { write } from "@/lib/api/request";
import { useEffect, useRef, useState, type FormEvent } from "react";
import type { EmergencyContacts, OwnerProfile } from "../types";
import editorStyles from "../owner-profile.module.css";
import styles from "./emergency-contacts.module.css";

export function EmergencyContactCard({ profile, onUpdated }: { profile?: OwnerProfile; onUpdated: () => void }) {
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  const [saved, setSaved] = useState(false);
  const contacts = profile?.emergency_contacts;
  async function save(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const value = (name: string) => String(form.get(name) ?? "").trim() || null;
    const emergency: EmergencyContacts = {
      primary: { name: value("primary_name"), phone: value("primary_phone") },
      secondary: { name: value("secondary_name"), phone: value("secondary_phone"), relationship: value("secondary_relationship"), available_24h: form.has("available_24h") },
      vet: { name: value("vet_name"), phone: value("vet_phone"), doctor: value("vet_doctor"), address: value("vet_address") },
    };
    setBusy(true); setError(undefined);
    try {
      await write("patch", "/api/v1/owner-profile", { emergency_contacts: emergency });
      onUpdated(); setEditing(false); setSaved(true);
    } catch (failure) { setError(failure); } finally { setBusy(false); }
  }
  return <>
    <section className={styles.card} aria-labelledby="emergency-heading">
      <header><h2 id="emergency-heading"><span className={styles.emergencyIcon}><PettelIcon name="bell" /></span>Liên Hệ Khẩn Cấp</h2><span className={styles.priority}>Ưu tiên số 1</span></header>
      <p className={styles.intro}>Để sẵn những số điện thoại quan trọng khi bé cần hỗ trợ.</p>
      <div className={styles.contact}>
        <h3>NGƯỜI LIÊN HỆ CHÍNH</h3>
        <ContactLine name={contacts?.primary?.name ?? profile?.name} phone={contacts?.primary?.phone ?? profile?.phone} />
      </div>
      <div className={`${styles.contact} ${styles.secondary}`}>
        <div className={styles.contactHeading}><h3>NGƯỜI THÂN THAY THẾ</h3>{contacts?.secondary?.available_24h && <span>Sẵn sàng 24/7</span>}</div>
        <ContactLine name={contacts?.secondary?.name} phone={contacts?.secondary?.phone} />
        {contacts?.secondary?.relationship && <p className={styles.detail}>{contacts.secondary.relationship}</p>}
      </div>
      <div className={styles.contact}>
        <h3>PHÒNG KHÁM THÚ Y QUEN THUỘC</h3>
        <ContactLine name={contacts?.vet?.name} phone={contacts?.vet?.phone} />
        {contacts?.vet?.doctor && <p className={styles.detail}>Bác sĩ phụ trách: {contacts.vet.doctor}</p>}
        {contacts?.vet?.address && <p className={styles.address}><PettelIcon name="pin" />{contacts.vet.address}</p>}
      </div>
      <button className={styles.updateButton} type="button" disabled={!profile} onClick={() => { setError(undefined); setSaved(false); setEditing(true); }}><PettelIcon name="user" />Cập nhật thông tin khẩn cấp</button>
      {saved && <p className={styles.saved} role="status"><PettelIcon name="check" />Đã lưu thông tin liên hệ khẩn cấp.</p>}
    </section>
    {editing && profile && <EmergencyContactEditor profile={profile} busy={busy} error={error} onClose={() => setEditing(false)} onSave={save} />}
  </>;
}

function ContactLine({ name, phone }: { name?: string | null; phone?: string | null }) {
  return <div className={styles.contactLine}><strong>{name || "Chưa cập nhật"}</strong>{phone ? <a href={`tel:${phone.replace(/[^+\d]/g, "")}`} aria-label={`Gọi ${name || "liên hệ"}: ${phone}`}><PhoneIcon />{phone}</a> : <span className={styles.missing}>Chưa có số điện thoại</span>}</div>;
}

function PhoneIcon() {
  return <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m7 3 3 5-3 2c1.5 3.5 3.5 5.5 7 7l2-3 5 3c0 3-2 4-4 4C10 21 3 14 3 7c0-2 1-4 4-4Z" /></svg>;
}

function EmergencyContactEditor({ profile, busy, error, onClose, onSave }: { profile: OwnerProfile; busy: boolean; error: unknown; onClose: () => void; onSave: (event: FormEvent<HTMLFormElement>) => void }) {
  const dialog = useRef<HTMLDialogElement>(null);
  const contacts = profile.emergency_contacts;
  useEffect(() => {
    const element = dialog.current;
    element?.showModal();
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => { element?.close(); document.body.style.overflow = previous; };
  }, []);
  return <dialog ref={dialog} className={editorStyles.editor} aria-labelledby="emergency-editor-title" onCancel={(event) => { event.preventDefault(); if (!busy) onClose(); }}>
    <form onSubmit={onSave}>
      <header><span><PettelIcon name="bell" /></span><h2 id="emergency-editor-title">Liên hệ khẩn cấp</h2><button type="button" disabled={busy} aria-label="Đóng cập nhật liên hệ" onClick={onClose}>×</button></header>
      <div className={editorStyles.editorBody}>
        <ApiError error={error} />
        <fieldset className={styles.fieldset}><legend>Người liên hệ chính</legend><div className={editorStyles.editorGrid}>
          <label>Họ và tên<input name="primary_name" maxLength={150} defaultValue={contacts?.primary?.name ?? profile.name} disabled={busy} /></label>
          <label>Số điện thoại<input name="primary_phone" type="tel" maxLength={30} defaultValue={contacts?.primary?.phone ?? profile.phone ?? ""} disabled={busy} /></label>
        </div></fieldset>
        <fieldset className={styles.fieldset}><legend>Người thân thay thế</legend><div className={editorStyles.editorGrid}>
          <label>Họ và tên<input name="secondary_name" maxLength={150} defaultValue={contacts?.secondary?.name ?? ""} disabled={busy} /></label>
          <label>Số điện thoại<input name="secondary_phone" type="tel" maxLength={30} defaultValue={contacts?.secondary?.phone ?? ""} disabled={busy} /></label>
          <label>Mối quan hệ<input name="secondary_relationship" maxLength={80} placeholder="Vợ/chồng, người thân, bạn bè..." defaultValue={contacts?.secondary?.relationship ?? ""} disabled={busy} /></label>
        </div><label className={styles.checkbox}><input name="available_24h" type="checkbox" defaultChecked={contacts?.secondary?.available_24h ?? false} disabled={busy} />Có thể liên hệ 24/7</label></fieldset>
        <fieldset className={styles.fieldset}><legend>Phòng khám thú y quen thuộc</legend><div className={editorStyles.editorGrid}>
          <label>Tên phòng khám<input name="vet_name" maxLength={150} defaultValue={contacts?.vet?.name ?? ""} disabled={busy} /></label>
          <label>Số điện thoại<input name="vet_phone" type="tel" maxLength={30} defaultValue={contacts?.vet?.phone ?? ""} disabled={busy} /></label>
          <label>Bác sĩ phụ trách<input name="vet_doctor" maxLength={150} defaultValue={contacts?.vet?.doctor ?? ""} disabled={busy} /></label>
        </div><label>Địa chỉ<input name="vet_address" maxLength={300} defaultValue={contacts?.vet?.address ?? ""} disabled={busy} /></label></fieldset>
      </div>
      <footer><button className={editorStyles.secondaryButton} type="button" disabled={busy} onClick={onClose}>Hủy bỏ</button><button className={editorStyles.primaryButton} type="submit" disabled={busy}><PettelIcon name="check" />{busy ? "Đang lưu..." : "Lưu thông tin"}</button></footer>
    </form>
  </dialog>;
}
