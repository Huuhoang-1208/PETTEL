"use client";

/* eslint-disable @next/next/no-img-element -- Private pet photos use authenticated media. */
import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { write } from "@/lib/api/request";
import { type Listing, type Pet } from "@/lib/api/types";
import { services, species } from "@/lib/constants";
import { vietnamISO } from "@/lib/date";
import { apiUrl } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState, type FormEvent } from "react";
import styles from "./sitter-message-modal.module.css";

export type SitterMessageContext = { petId: string; start: string; end: string };
const shortDate = (value: string) => value ? new Date(value).toLocaleDateString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit", month: "2-digit" }) : "";

export function SitterMessageModal({ listing, pets, initial, onClose }: { listing: Listing; pets: Pet[]; initial: SitterMessageContext; onClose: () => void }) {
  const router = useRouter();
  const { user } = useSession();
  const dialog = useRef<HTMLDialogElement>(null);
  const [context, setContext] = useState(initial);
  const [editing, setEditing] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  const requestKey = useRef(crypto.randomUUID());
  const sitter = listing.caregiver?.display_name || listing.title;
  const pet = pets.find((item) => item.id === context.petId);
  const [body, setBody] = useState(`Chào ${sitter}, mình muốn tìm hiểu thêm về dịch vụ ${services[listing.service_type]?.toLowerCase() || "chăm sóc thú cưng"} của bạn. Bạn có thể tư vấn giúp mình không?`);
  const dateRange = context.start && context.end ? `${shortDate(context.start)} – ${shortDate(context.end)}` : "ngày mình dự định gửi";
  const questions = [
    `Dịp ${dateRange} còn nhận bé không?`,
    "Bé hơi nhát ngày đầu, bạn chăm được không?",
    "Đón bé lúc 14:00 chiều được không?",
    "Có tủ lạnh trữ khẩu phần ăn riêng không?",
  ];

  useEffect(() => {
    const element = dialog.current;
    element?.showModal();
    const previous = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => { element?.close(); document.body.style.overflow = previous; };
  }, []);

  function updateBody(value: string) {
    setBody(value);
    requestKey.current = crypto.randomUUID();
    setError(undefined);
  }

  async function send(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || !body.trim() || !user) return;
    setBusy(true);
    setError(undefined);
    try {
      const result = await write<{ id: string }>("post", "/api/v1/inquiries", {
        listing_id: listing.id, pet_id: context.petId || null,
        start_at: context.start ? vietnamISO(context.start) : null,
        end_at: context.end ? vietnamISO(context.end) : null,
        body: body.trim(), request_key: requestKey.current,
      });
      router.push(`/chat?inquiry=${result.id}`);
    } catch (failure) { setError(failure); setBusy(false); }
  }

  return <dialog ref={dialog} className={styles.dialog} aria-labelledby="sitter-message-title" onCancel={(event) => { event.preventDefault(); if (!busy) onClose(); }} onClick={(event) => { if (event.target === event.currentTarget && !busy) onClose(); }}>
    <form className={styles.form} onSubmit={(event) => void send(event)}>
      <header className={styles.header}>
        <span className={styles.headerIcon}><PettelIcon name="mail" /></span>
        <div><h2 id="sitter-message-title">Nhắn tin với Sitter {sitter}</h2><p><PettelIcon name="clock" /> Trao đổi với người chăm trước khi đặt lịch</p></div>
        <button type="button" className={styles.close} aria-label="Đóng popup nhắn tin" disabled={busy} onClick={onClose}>×</button>
      </header>
      <div className={styles.content}>
        <section className={styles.context} aria-label="Thông tin gửi kèm">
          <span className={styles.avatar}>{pet?.photo_media_id ? <img src={apiUrl(`/api/v1/pets/media/${pet.photo_media_id}/content`)} alt={pet.name} /> : <PettelIcon name={pet?.species === "CAT" ? "cat" : "dog"} />}</span>
          <div><strong>{pet ? `${pet.name} (${pet.breed || species[pet.species]}${pet.weight_kg ? ` · ${Number(pet.weight_kg).toLocaleString("vi-VN")}kg` : ""})` : "Chưa chọn bé cưng"}</strong><p>{services[listing.service_type]} · {context.start && context.end ? dateRange : "Chưa chọn ngày"}</p><small><PettelIcon name="check" /> Thông tin bé và ngày gửi được gửi kèm</small></div>
          <button type="button" className={styles.change} aria-expanded={editing} disabled={busy} onClick={() => setEditing(!editing)}><PettelIcon name="sliders" /> Đổi bé / ngày</button>
        </section>
        {editing && <div className={styles.editContext}>
          <label>Bé cưng<select value={context.petId} disabled={busy} onChange={(event) => { setContext({ ...context, petId: event.target.value }); requestKey.current = crypto.randomUUID(); }}><option value="">Chưa chọn bé</option>{pets.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>
          <label>Ngày gửi<input type="datetime-local" value={context.start} disabled={busy} onChange={(event) => { setContext({ ...context, start: event.target.value }); requestKey.current = crypto.randomUUID(); }} /></label>
          <label>Ngày đón<input type="datetime-local" value={context.end} disabled={busy} onChange={(event) => { setContext({ ...context, end: event.target.value }); requestKey.current = crypto.randomUUID(); }} /></label>
        </div>}
        <section className={styles.suggestions}><h3><PettelIcon name="star" /> Gợi ý câu hỏi nhanh <span>(Chạm để điền vào tin nhắn)</span></h3><div>{questions.map((question, index) => <button key={index} type="button" disabled={busy || body.includes(question) || `${body.trim()}\n${question}`.length > 500} onClick={() => updateBody(`${body.trim()}\n${question}`)}><PettelIcon name={index === 0 ? "calendar" : index === 1 ? "paw" : index === 2 ? "clock" : "home"} />{question}</button>)}</div></section>
        <label className={styles.messageLabel} htmlFor="sitter-message-body"><strong>Nội dung tin nhắn <b>*</b></strong><span>{body.length} /500 ký tự</span></label>
        <textarea id="sitter-message-body" className={styles.textarea} required maxLength={500} value={body} disabled={busy} onChange={(event) => updateBody(event.target.value)} />
        <div className={styles.privacy}><PettelIcon name="shield" /><div><strong>Bảo vệ thông tin &amp; PETTEL</strong><p>Chỉ bạn và người chăm trong cuộc trò chuyện có thể xem nội dung. Thông tin bé và lịch dự kiến giúp Sitter tư vấn phù hợp hơn.</p></div></div>
        <ApiError error={error} />
        {!user && <p className={styles.login}>Bạn cần <Link href={`/login?next=${encodeURIComponent(`/detail?id=${listing.id}`)}`}>đăng nhập</Link> để gửi tin nhắn.</p>}
      </div>
      <footer className={styles.footer}>
        <button type="button" className={styles.back} disabled={busy} onClick={onClose}>Quay lại hồ sơ</button>
        <div><button type="submit" className={styles.send} disabled={busy || !body.trim() || !user}>{busy ? "Đang gửi tin nhắn..." : "Gửi tin nhắn & Mở cuộc trò chuyện"}<PettelIcon name="arrow" /></button><p>Hệ thống sẽ chuyển tới cuộc trò chuyện riêng cùng {sitter}</p></div>
      </footer>
    </form>
  </dialog>;
}
