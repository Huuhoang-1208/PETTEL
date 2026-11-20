"use client";

/* eslint-disable @next/next/no-img-element -- Authenticated attachments and project media. */
import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { useBookingMessages } from "@/features/chat/hooks/use-booking-messages";
import { useApi } from "@/hooks/use-api";
import { api } from "@/lib/api/client";
import { write } from "@/lib/api/request";
import { type Listing, type Message, type Page } from "@/lib/api/types";
import { apiUrl } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { contextFor, listingPhoto, type ChatKind, type ChatRecord } from "../types";
import { CareOverview } from "./care-overview";
import { ChatDetails } from "./chat-details";
import styles from "../chat.module.css";

export function Conversation({ id, kind = "bookings" }: { id: string; kind?: ChatKind }) {
  const { user } = useSession();
  const resource = useApi<{ data: ChatRecord }>(`/api/v1/${kind}/${id}`);
  const record = resource.data?.data;
  const listing = useApi<{ data: Listing }>(record?.listing_id ? `/api/v1/listings/${record.listing_id}` : null);
  const { messages, error: connectionError, refresh, nextPage } = useBookingMessages(id, kind);
  const [older, setOlder] = useState<Message[]>([]);
  const [olderNext, setOlderNext] = useState<string | null | undefined>();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [draft, setDraft] = useState("");
  const [optimisticMessage, setOptimisticMessage] = useState<Message | null>(null);
  const [files, setFiles] = useState<File[]>([]);
  const [emojisOpen, setEmojisOpen] = useState(false);
  const [detailsOpen, setDetailsOpen] = useState(false);
  const sendKey = useRef(crypto.randomUUID());
  const feed = useRef<HTMLDivElement>(null);
  const picker = useRef<HTMLInputElement>(null);
  const composer = useRef<HTMLTextAreaElement>(null);
  const nearBottom = useRef(true);
  const merged = Array.from(new Map([...older, ...messages].map((message) => [message.id, message])).values()).sort((a, b) => a.created_at.localeCompare(b.created_at) || (BigInt(a.id) < BigInt(b.id) ? -1 : 1));
  const visibleMessages = optimisticMessage ? [...merged, optimisticMessage] : merged;
  const lastId = messages[messages.length - 1]?.id;
  const context = contextFor(record, merged);
  const sitter = record?.sitter_name ?? listing.data?.data.caregiver?.display_name ?? record?.listing_title ?? "Người chăm thú cưng";
  const avatar = listingPhoto(listing.data?.data, true);
  useEffect(() => { if (feed.current && nearBottom.current) feed.current.scrollTop = feed.current.scrollHeight; }, [lastId, optimisticMessage?.id]);
  function updateDraft(value: string) { setDraft(value); sendKey.current = crypto.randomUUID(); }
  function appendDraft(value: string) { updateDraft(`${draft}${draft ? "\n" : ""}${value}`.slice(0, 2000)); composer.current?.focus(); }
  async function send() {
    if ((!draft.trim() && files.length === 0) || busy) return;
    const data = new FormData();
    const body = draft.trim();
    if (body) data.set("body", body);
    files.forEach((file) => data.append("attachments[]", file));
    setBusy(true); setError(undefined);
    const pending = body ? {
      id: `pending-${crypto.randomUUID()}`,
      sender_id: String(user?.id ?? ""),
      body,
      created_at: new Date().toISOString(),
      attachments: [],
      context: null,
    } satisfies Message : null;
    setOptimisticMessage(pending);
    try {
      await write("post", `/api/v1/${kind}/${id}/messages`, kind === "inquiries" ? { body: draft.trim(), request_key: sendKey.current } : data);
      sendKey.current = crypto.randomUUID(); setDraft(""); setFiles([]); setEmojisOpen(false);
      if (picker.current) picker.current.value = "";
      nearBottom.current = true;
      await refresh();
    } catch (failure) { setError(failure); } finally { setOptimisticMessage(null); setBusy(false); }
  }
  const cursor = olderNext === undefined ? nextPage : olderNext;
  async function loadOlder() {
    if (!cursor || loadingOlder) return;
    setLoadingOlder(true); setError(undefined);
    try {
      const url = new URL(cursor, api.defaults.baseURL);
      const { data } = await api.get<Page<Message>>(url.pathname + url.search);
      setOlder((current) => [...current, ...data.data]); setOlderNext(data.links?.next ?? null);
    } catch (failure) { setError(failure); } finally { setLoadingOlder(false); }
  }
  return <>
    <section className={styles.conversation} aria-label={`Trò chuyện với ${sitter}`}>
      <header className={styles.conversationHeader}>
        <Link href="/chat" className={`${styles.iconButton} ${styles.mobileBack}`} aria-label="Quay lại danh sách hội thoại"><PettelIcon name="arrow" /></Link>
        <span className={styles.avatar}>{avatar ? <img src={avatar} alt={sitter} /> : <PettelIcon name="paw" />}</span>
        <div className={styles.recipient}><h2>{sitter}{listing.data?.data.caregiver?.demo?.trust_level === "pro" && <span>PETTEL Pro</span>}</h2><p><i />{kind === "inquiries" ? "Trao đổi trước đặt lịch" : `Đơn chăm sóc #${id}`}<span> · {record?.listing_title}</span></p></div>
        {record?.listing_id && <Link className={styles.profileButton} href={`/detail?id=${record.listing_id}`}><PettelIcon name="home" /> Hồ sơ</Link>}
        <button type="button" className={`${styles.iconButton} ${styles.detailsToggle}`} aria-label="Thông tin lịch gửi bé" aria-expanded={detailsOpen} onClick={() => setDetailsOpen(!detailsOpen)}><PettelIcon name="sliders" /></button>
      </header>
      <div className={styles.careBanner}><PettelIcon name="shield" /><p><strong>Đồng hành cùng bé qua PETTEL Care.</strong> Lưu giữ thông tin đặt lịch và trao đổi cùng người chăm tại một nơi.</p></div>
      <ApiError error={resource.error ?? error} />
      {connectionError && <p className={styles.connectionError} role="alert">{connectionError}<button type="button" onClick={() => void refresh()}>Thử lại</button></p>}
      {detailsOpen && <div className={styles.mobileOverview}><CareOverview record={record} context={context} listing={listing.data?.data} compact /></div>}
      <div className={styles.feed} ref={feed} role="log" aria-live="polite" onScroll={() => { if (feed.current) nearBottom.current = feed.current.scrollHeight - feed.current.scrollTop - feed.current.clientHeight < 100; }}>
        {cursor && <button className={styles.loadOlder} disabled={loadingOlder} onClick={() => void loadOlder()}>{loadingOlder ? "Đang tải..." : "Xem tin nhắn trước"}</button>}
        {(record || context) && <CareOverview record={record} context={context} listing={listing.data?.data} compact />}
        {visibleMessages.map((message, index) => {
          const mine = String(message.sender_id) === String(user?.id);
          const day = new Date(message.created_at).toLocaleDateString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "numeric", month: "long", year: "numeric" });
          const previousDay = index ? new Date(visibleMessages[index - 1].created_at).toLocaleDateString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "numeric", month: "long", year: "numeric" }) : null;
          return <div key={message.id}>
            {previousDay !== day && <div className={styles.dayDivider}><span>{day}</span></div>}
            <article className={`${styles.message} ${mine ? styles.mine : styles.theirs}`}>
              {!mine && <span className={styles.smallAvatar}>{avatar ? <img src={avatar} alt="" /> : <PettelIcon name="paw" />}</span>}
              <div className={styles.messageColumn}><div className={styles.bubble}>
                {message.body && <p>{message.body}</p>}
                {message.context?.pet_name && <div className={styles.petContext}><PettelIcon name="paw" /><div><strong>Hồ sơ: {message.context.pet_name}</strong><span>{message.context.pet_breed || "Thông tin bé cưng"}{message.context.pet_weight_kg ? ` · ${Number(message.context.pet_weight_kg)}kg` : ""}</span></div></div>}
                {message.attachments?.map((attachment) => <a key={attachment.id} href={apiUrl(attachment.url)} target="_blank" rel="noopener noreferrer"><img className={styles.messagePhoto} src={apiUrl(attachment.url)} alt="Ảnh gửi trong cuộc trò chuyện" /></a>)}
              </div><time className={styles.messageTime} dateTime={message.created_at}>{message.id.startsWith("pending-") ? "Đang gửi…" : new Date(message.created_at).toLocaleTimeString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", hour: "2-digit", minute: "2-digit" })}{mine && <PettelIcon name="check" />}</time></div>
            </article>
          </div>;
        })}
        {!merged.length && !connectionError && <div className={styles.conversationEmpty}><PettelIcon name="message" /><p>Gửi lời chào đầu tiên và chia sẻ những điều đặc biệt về bé nhé.</p></div>}
      </div>
      <div className={styles.composerArea}>
        <div className={styles.quickReplies}>{["Đón bé lúc 14:00 được không?", "Có tủ lạnh trữ khẩu phần ăn riêng không?"].map((text, index) => <button type="button" key={text} disabled={busy} onClick={() => appendDraft(text)}><PettelIcon name={index ? "home" : "clock"} />{text}</button>)}</div>
        {files.length > 0 && <div className={styles.fileList}>{files.map((file, index) => <span key={`${file.name}-${index}`}>{file.name}<button type="button" aria-label={`Bỏ ảnh ${file.name}`} disabled={busy} onClick={() => setFiles(files.filter((_, i) => i !== index))}>×</button></span>)}</div>}
        {emojisOpen && <div className={styles.emojiPicker}>{["😊", "🐾", "❤️", "🐶", "🐱", "🙏"].map((emoji) => <button key={emoji} type="button" onClick={() => { appendDraft(emoji); setEmojisOpen(false); }}>{emoji}</button>)}</div>}
        <form className={styles.composer} onSubmit={(event) => { event.preventDefault(); void send(); }}>
          {kind === "bookings" && <><button className={styles.toolButton} type="button" title="Gửi ảnh" aria-label="Đính kèm ảnh" disabled={busy} onClick={() => picker.current?.click()}><PettelIcon name="camera" /></button><input className={styles.hiddenInput} ref={picker} type="file" multiple accept="image/jpeg,image/png,image/webp" disabled={busy} onChange={(event) => { const selected = Array.from(event.target.files ?? []); if (selected.length > 5 || selected.some((file) => file.size > 10 * 1024 * 1024)) { setError(new Error("Mỗi tin nhắn tối đa 5 ảnh, mỗi ảnh tối đa 10MB.")); return; } setFiles(selected); setError(undefined); }} /></>}
          <button className={styles.toolButton} type="button" aria-label="Thêm biểu tượng cảm xúc" aria-expanded={emojisOpen} disabled={busy} onClick={() => setEmojisOpen(!emojisOpen)}><span>☺</span></button>
          <textarea ref={composer} aria-label="Nội dung tin nhắn" rows={1} maxLength={2000} value={draft} onChange={(event) => updateDraft(event.target.value)} placeholder={`Nhập tin nhắn cho ${sitter}...`} disabled={busy} onKeyDown={(event) => { if (event.key === "Enter" && !event.shiftKey && !event.nativeEvent.isComposing) { event.preventDefault(); void send(); } }} />
          <button className={styles.sendButton} type="submit" aria-label={busy ? "Đang gửi tin nhắn" : "Gửi tin nhắn"} disabled={busy || (!draft.trim() && !files.length)}><PettelIcon name="arrow" /></button>
        </form>
      </div>
    </section>
    <ChatDetails key={id} record={record} context={context} listing={listing.data?.data} onUseNote={appendDraft} />
  </>;
}
