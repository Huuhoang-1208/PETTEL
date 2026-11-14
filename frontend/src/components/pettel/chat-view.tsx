/* eslint-disable @next/next/no-img-element -- Keep the original chat photo presentation. */
"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { sittersData } from "@/lib/pettel/demo-data";

const responses = [
  { text: "miu đang chơi với em", image: "/images/miu đang chơi với em.jpg" },
  { text: "Miu đang chơi với chó em chị ạ", image: "/images/Miu đang chơi với chó em chị ạ.jpg" },
  { text: "miu đang đi dạo", image: "/images/miu đang đi dạo.jpg" },
  { text: "Miu đang chơi với mun", image: "/images/Miu đang chơi với mun.jpg" },
];
type Message = { id: string; text: string; time: string; image?: string; owner: boolean };

export function ChatView() {
  const search = useSearchParams();
  const id = Number(search.get("id")) || 3;
  return <Conversation key={id} sitterId={id} />;
}

function Conversation({ sitterId }: { sitterId: number }) {
  const sitter = sittersData.find((item) => item.id === sitterId) ?? sittersData[2];
  const [messages, setMessages] = useState<Message[]>([]);
  const [draft, setDraft] = useState("");
  const container = useRef<HTMLDivElement>(null);
  const timers = useRef(new Set<number>());

  useEffect(() => {
    document.title = `Chat với ${sitter.name} | PETTEL`;
    const pending = timers.current;
    return () => pending.forEach((id) => window.clearTimeout(id));
  }, [sitter.name]);
  useEffect(() => {
    if (container.current) container.current.scrollTop = container.current.scrollHeight;
  }, [messages]);

  function send() {
    const text = draft.trim();
    if (!text) return;
    const time = () => new Date().toLocaleTimeString("vi-VN", { hour: "2-digit", minute: "2-digit" });
    setMessages((current) => [...current, { id: crypto.randomUUID(), text, time: time(), owner: true }]);
    setDraft("");
    const timer = window.setTimeout(() => {
      timers.current.delete(timer);
      const response = responses[Math.floor(Math.random() * responses.length)];
      setMessages((current) => [...current, { ...response, id: crypto.randomUUID(), time: time(), owner: false }]);
    }, 1000 + Math.random() * 1000);
    timers.current.add(timer);
  }

  return (
    <>
      <div className="chat-header" id="chat-header">
        <Link href={`/detail?id=${sitter.id}`} className="chat-back-btn" id="back-btn" aria-label="Quay lại chi tiết">
          <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z" /></svg>
        </Link>
        <div className="chat-header-info"><div className="chat-avatar"><img src={sitter.image} alt={sitter.name} /></div><div className="chat-header-text"><div className="chat-sitter-name" id="chat-sitter-name">{sitter.name}</div><div className="chat-online-status"><span className="online-dot" /> Đang online</div></div></div>
        <div className="chat-header-actions">
          <Link href={`/videocall?id=${sitter.id}`} className="chat-action-btn" id="video-call-btn" title="Gọi video"><i className="fa-solid fa-video" /></Link>
          {sitter.verifiedDetails.liveUpdates && <Link href={`/livecam?id=${sitter.id}`} className="chat-action-btn" id="live-cam-btn" title="Xem cam trực tiếp"><i className="fa-solid fa-camera" /></Link>}
        </div>
      </div>
      <div className="chat-container" id="chat-container"><div className="chat-messages" id="chat-messages" ref={container} role="log" aria-live="polite">
        {messages.map((message) => <div key={message.id} className={`chat-message chat-message--${message.owner ? "owner" : "sitter"}`}><div className="message-content">{message.image ? <div className="message-photo-card"><img src={message.image} alt={message.text} /><div className="message-photo-caption">{message.text} · {message.time}</div></div> : <><div className="message-text">{message.text}</div><div className="message-time">{message.time}</div></>}</div></div>)}
      </div></div>
      <form className="chat-input-container" onSubmit={(event) => { event.preventDefault(); send(); }}>
        <button type="button" className="chat-attach-btn" title="Đính kèm ảnh"><i className="fa-solid fa-image" /></button>
        <input className="chat-input" id="chat-input" placeholder="Nhắn tin..." aria-label="Tin nhắn" value={draft} onChange={(event) => setDraft(event.target.value)} />
        <button className="chat-send-btn" id="chat-send-btn" aria-label="Gửi tin nhắn"><i className="fa-solid fa-paper-plane" /></button>
      </form>
    </>
  );
}
