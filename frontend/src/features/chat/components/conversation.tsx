"use client";

/* eslint-disable @next/next/no-img-element -- Preserve existing API and demo image rendering. */
import { ApiError } from "@/components/ui/api-error";
import { useBookingMessages } from "@/features/chat/hooks/use-booking-messages";
import { useApi } from "@/hooks/use-api";
import { api } from "@/lib/api/client";
import { write } from "@/lib/api/request";
import { type Booking, type Message, type Page } from "@/lib/api/types";
import { apiUrl, dateTime } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";

export function Conversation({ id }: { id: string }) {
  const { user } = useSession();
  const booking = useApi<{ data: Booking }>(`/api/bookings/${id}`);
  const { messages, error: connectionError, refresh, nextPage } = useBookingMessages(id);
  const [older, setOlder] = useState<Message[]>([]);
  const [olderNext, setOlderNext] = useState<string | null | undefined>();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [loadingOlder, setLoadingOlder] = useState(false);
  const [draft, setDraft] = useState("");
  const [files, setFiles] = useState<File[]>([]);
  const feed = useRef<HTMLDivElement>(null);
  const picker = useRef<HTMLInputElement>(null);
  const merged = Array.from(
    new Map([...older, ...messages].map((message) => [message.id, message])).values(),
  ).sort(
    (a, b) => a.created_at.localeCompare(b.created_at) || (BigInt(a.id) < BigInt(b.id) ? -1 : 1),
  );
  const lastId = messages[messages.length - 1]?.id;
  useEffect(() => {
    if (feed.current) feed.current.scrollTop = feed.current.scrollHeight;
  }, [lastId]);
  async function send() {
    if ((!draft.trim() && files.length === 0) || busy) return;
    const data = new FormData();
    if (draft.trim()) data.set("body", draft.trim());
    files.forEach((file) => data.append("attachments[]", file));
    setBusy(true);
    setError(undefined);
    try {
      await write("post", `/api/bookings/${id}/messages`, data);
      setDraft("");
      setFiles([]);
      if (picker.current) picker.current.value = "";
      await refresh();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  const cursor = olderNext === undefined ? nextPage : olderNext;
  async function loadOlder() {
    if (!cursor) return;
    setLoadingOlder(true);
    setError(undefined);
    try {
      const url = new URL(cursor, api.defaults.baseURL);
      const { data } = await api.get<Page<Message>>(url.pathname + url.search);
      setOlder((current) => [...current, ...data.data]);
      setOlderNext(data.links?.next ?? null);
    } catch (failure) {
      setError(failure);
    } finally {
      setLoadingOlder(false);
    }
  }
  return (
    <main className="app-chat">
      <header className="app-card">
        <Link href={`/bookings?open=${id}`}>← Đơn #{id}</Link>
        <h1>{booking.data?.data.listing_title || "Tin nhắn của đơn chăm sóc"}</h1>
        <p>Tin nhắn giữa chủ thú cưng và người chăm. Video call/live cam chưa được kết nối.</p>
        <ApiError error={booking.error ?? error} />
        {connectionError && (
          <p className="app-error" role="alert">
            {connectionError}
          </p>
        )}
      </header>
      <div className="app-chat-feed" ref={feed} role="log" aria-live="polite">
        {cursor && (
          <button disabled={loadingOlder} onClick={() => void loadOlder()}>
            Tải tin nhắn cũ hơn
          </button>
        )}
        {merged.map((message) => (
          <article
            key={message.id}
            className={`chat-message chat-message--${String(message.sender_id) === String(user?.id) ? "owner" : "sitter"}`}
          >
            <div className="message-content">
              {message.body && <p className="message-text">{message.body}</p>}
              {message.attachments?.map((attachment) => (
                <a
                  key={attachment.id}
                  href={apiUrl(attachment.url)}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <img
                    className="app-chat-photo"
                    src={apiUrl(attachment.url)}
                    alt="Ảnh đính kèm tin nhắn"
                  />
                </a>
              ))}
              <small>{dateTime(message.created_at)}</small>
            </div>
          </article>
        ))}
        {!merged.length && !connectionError && (
          <p>Chưa có tin nhắn. Gửi lời nhắn đầu tiên cho đơn này.</p>
        )}
      </div>
      <form
        className="app-chat-composer"
        onSubmit={(event) => {
          event.preventDefault();
          void send();
        }}
      >
        <label className="app-attachment">
          Ảnh
          <input
            ref={picker}
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp"
            disabled={busy}
            onChange={(event) => {
              const selected = Array.from(event.target.files ?? []);
              if (selected.length > 5) {
                setError(new Error("Mỗi tin nhắn tối đa 5 ảnh."));
                return;
              }
              setFiles(selected);
            }}
          />
        </label>
        <input
          aria-label="Tin nhắn"
          maxLength={2000}
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
          placeholder="Nhắn tin..."
          disabled={busy}
        />
        <button className="app-button" disabled={busy || (!draft.trim() && !files.length)}>
          Gửi
        </button>
        {files.length > 0 && <span>{files.length} ảnh đã chọn</span>}
      </form>
    </main>
  );
}
