"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { useApi } from "@/hooks/use-api";
import type { Booking, Inquiry, Page } from "@/lib/api/types";
import { statuses } from "@/lib/constants";
import Link from "next/link";
import { useState } from "react";
import type { ChatThread } from "../types";
import { Conversation } from "./conversation";
import { ChatThreadList } from "./chat-thread-list";
import styles from "../chat.module.css";

export function ChatWorkspace({ inquiryId, bookingId }: { inquiryId: string | null; bookingId: string | null }) {
  const [inquiryPage, setInquiryPage] = useState(1);
  const [bookingPage, setBookingPage] = useState(1);
  const inquiries = useApi<Page<Inquiry>>(`/api/v1/inquiries?page=${inquiryPage}`, 10000);
  const bookings = useApi<Page<Booking>>(`/api/v1/bookings?page=${bookingPage}&per_page=20`, 10000);
  const threads: ChatThread[] = [
    ...(inquiries.data?.data ?? []).map((thread) => ({ id: thread.id, kind: "inquiries" as const, title: thread.sitter_name, subtitle: thread.listing_title, listingId: thread.listing_id })),
    ...(bookings.data?.data ?? []).map((thread) => ({ id: thread.id, kind: "bookings" as const, title: thread.listing_title || `Đơn chăm sóc #${thread.id}`, subtitle: statuses[thread.status] ?? thread.status, listingId: thread.listing_id, petName: thread.pet_name, status: thread.status })),
  ];
  const explicit = inquiryId && /^\d+$/.test(inquiryId) ? { kind: "inquiries" as const, id: inquiryId } : bookingId && /^\d+$/.test(bookingId) ? { kind: "bookings" as const, id: bookingId } : null;
  const selected = explicit ?? threads[0] ?? null;
  return <main className={styles.page}>
    <div className={styles.pageHeading}><div><Link href="/services" className={styles.circleButton} aria-label="Quay lại dịch vụ"><PettelIcon name="arrow" /></Link><h1>Hộp thư &amp; Trò chuyện</h1><span className={styles.liveBadge}>LIVE CARE</span></div><span className={styles.secureBadge}><PettelIcon name="shield" /> Trao đổi riêng tư qua PETTEL</span></div>
    <div className={`${styles.workspace} ${explicit ? styles.hasSelection : ""}`}>
      <ChatThreadList threads={threads} selected={selected} loading={inquiries.loading || bookings.loading} onRefresh={() => { inquiries.reload(); bookings.reload(); }}>
        <ApiError error={inquiries.error ?? bookings.error} />
        {(inquiries.data?.meta?.last_page ?? 1) > 1 && <div className={styles.pager}><button disabled={inquiryPage === 1} onClick={() => setInquiryPage(inquiryPage - 1)}>←</button><span>Tư vấn · Trang {inquiryPage}</span><button disabled={inquiryPage >= (inquiries.data?.meta?.last_page ?? 1)} onClick={() => setInquiryPage(inquiryPage + 1)}>→</button></div>}
        {(bookings.data?.meta?.last_page ?? 1) > 1 && <div className={styles.pager}><button disabled={bookingPage === 1} onClick={() => setBookingPage(bookingPage - 1)}>←</button><span>Đơn · Trang {bookingPage}</span><button disabled={bookingPage >= (bookings.data?.meta?.last_page ?? 1)} onClick={() => setBookingPage(bookingPage + 1)}>→</button></div>}
      </ChatThreadList>
      {selected ? <Conversation key={`${selected.kind}-${selected.id}`} id={selected.id} kind={selected.kind} /> : <section className={styles.emptyConversation}><span><PettelIcon name="message" /></span><h2>Những cuộc trò chuyện đầy yêu thương</h2><p>{inquiries.loading || bookings.loading ? "Đang tải hộp thư của bạn..." : "Chọn người chăm để trao đổi về bé, lịch gửi và những điều bạn muốn dặn dò."}</p><Link className={styles.primaryButton} href="/services">Tìm Sitter phù hợp <PettelIcon name="arrow" /></Link></section>}
    </div>
  </main>;
}
