"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import Link from "next/link";
import { useState, type ReactNode } from "react";
import type { ChatThread } from "../types";
import styles from "../chat.module.css";

export function ChatThreadList({ threads, selected, loading, onRefresh, children }: { threads: ChatThread[]; selected: { kind: string; id: string } | null; loading: boolean; onRefresh: () => void; children: ReactNode }) {
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState("all");
  const normalize = (value: string) => value.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/đ/g, "d").toLowerCase();
  const filtered = threads.filter((thread) => (filter === "all" || thread.kind === filter) && normalize(`${thread.title} ${thread.subtitle} ${thread.petName ?? ""}`).includes(normalize(search)));
  return <aside className={styles.sidebar} aria-label="Danh sách hội thoại">
    <div className={styles.inboxHeading}><h2>Tin nhắn <span>{threads.length}</span></h2><button className={styles.iconButton} type="button" onClick={onRefresh} aria-label="Làm mới hội thoại"><PettelIcon name="clock" /></button></div>
    <label className={styles.search}><PettelIcon name="search" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm theo Sitter, bé cưng..." aria-label="Tìm cuộc trò chuyện" /></label>
    <div className={styles.filters} aria-label="Lọc hội thoại">{[["all", "Tất cả"], ["inquiries", "Tư vấn"], ["bookings", "Đã đặt lịch"]].map(([value, label]) => <button key={value} className={filter === value ? styles.activeFilter : ""} onClick={() => setFilter(value)} aria-pressed={filter === value}>{label}</button>)}</div>
    <div className={styles.threadScroll}>
      {filtered.map((thread, index) => <Link className={`${styles.thread} ${selected?.id === thread.id && selected.kind === thread.kind ? styles.activeThread : ""}`} key={`${thread.kind}-${thread.id}`} href={`/chat?${thread.kind === "inquiries" ? "inquiry" : "booking"}=${thread.id}`} aria-current={selected?.id === thread.id && selected.kind === thread.kind ? "page" : undefined}>
        <span className={`${styles.avatar} ${index % 2 ? styles.violetAvatar : ""}`}><PettelIcon name={thread.kind === "inquiries" ? "paw" : "calendar"} /></span>
        <span className={styles.threadText}><strong>{thread.title}</strong><span>{thread.subtitle}</span><small><PettelIcon name={thread.kind === "inquiries" ? "message" : "paw"} />{thread.kind === "inquiries" ? "Trao đổi trước đặt lịch" : thread.petName || `Đơn #${thread.id}`}</small></span>
      </Link>)}
      {!filtered.length && <p className={styles.emptyList}>{loading ? "Đang tải hội thoại..." : search ? "Không tìm thấy cuộc trò chuyện phù hợp." : "Chưa có cuộc trò chuyện trong mục này."}</p>}
      {children}
    </div>
    <div className={styles.sidebarFooter}><span><i /> Đồng hành cùng bé mỗi ngày</span><Link href="/services">Tìm Sitter</Link></div>
  </aside>;
}
