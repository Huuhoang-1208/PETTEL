"use client";

/* eslint-disable @next/next/no-img-element -- API and existing project media. */
import { PettelIcon } from "@/components/ui/pettel-icon";
import { type Listing } from "@/lib/api/types";
import { areas } from "@/lib/constants";
import Link from "next/link";
import { useState } from "react";
import { bookingDetailHref, listingPhoto, type CareContext, type ChatRecord } from "../types";
import { CareOverview } from "./care-overview";
import styles from "../chat.module.css";

export function ChatDetails({ record, context, listing, onUseNote }: { record?: ChatRecord; context: CareContext | null; listing?: Listing; onUseNote: (note: string) => void }) {
  const [editing, setEditing] = useState(false);
  const [note, setNote] = useState("");
  const photo = listingPhoto(listing);
  const amenities = listing?.caregiver?.demo?.amenities.slice(0, 3) ?? [];
  return <aside className={styles.details} aria-label="Thông tin dịch vụ và lịch gửi bé">
    {record?.listing_id && <section className={styles.propertyCard}>
      <Link className={styles.propertyPhoto} href={`/detail?id=${record.listing_id}`}>
        {photo ? <img src={photo} alt={listing?.title ?? "Không gian chăm sóc"} /> : <span className={styles.propertyFallback}><PettelIcon name="home" /></span>}
        <span><strong>{listing?.title ?? record.listing_title ?? "Dịch vụ chăm sóc"}</strong><small>{listing ? areas[listing.area_code] : "Xem thông tin dịch vụ"}</small></span>
      </Link>
      {amenities.length > 0 && <div className={styles.amenities}>{amenities.map((amenity, index) => <span key={amenity}><PettelIcon name={index === 0 ? "home" : index === 1 ? "camera" : "shield"} />{amenity}</span>)}</div>}
    </section>}
    <CareOverview record={record} context={context} listing={listing} />
    <section className={styles.notes}><header><h3>Ghi chú riêng cho Sitter</h3><button onClick={() => setEditing(!editing)} aria-expanded={editing}>{editing ? "Đóng" : "Soạn ghi chú"}</button></header>
      {editing ? <><textarea aria-label="Ghi chú cho người chăm" placeholder="Khẩu phần ăn, thói quen, điều cần lưu ý..." value={note} maxLength={1500} onChange={(event) => setNote(event.target.value)} /><button className={styles.noteAction} disabled={!note.trim()} onClick={() => { onUseNote(`Mình muốn dặn thêm về bé: ${note.trim()}`); setEditing(false); }}>Thêm vào ô tin nhắn <PettelIcon name="arrow" /></button></> : <p>{note || "Thêm thói quen ăn uống, giờ đi dạo và những lưu ý để người chăm hiểu bé hơn."}</p>}
    </section>
    <div className={styles.helpCard}><span><PettelIcon name="shield" /></span><div><strong>Cần hỗ trợ từ PETTEL?</strong><p>Trao đổi về đơn và quá trình chăm sóc</p></div><Link href={record?.status ? bookingDetailHref(record) : record?.listing_id ? `/detail?id=${record.listing_id}` : "/owner-profile#history"}>Xem đơn</Link></div>
  </aside>;
}
