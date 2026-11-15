"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon, type PettelIconName } from "@/components/ui/pettel-icon";
import { PetEditor } from "@/features/pets/components/pet-editor";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import type { Booking, Page, Pet } from "@/lib/api/types";
import { statuses } from "@/lib/constants";
import { dateTime, money } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useState, type FormEvent } from "react";
import type { OwnerProfile } from "../types";
import { OwnerEditor } from "./owner-editor";
import { OwnerSidebar } from "./owner-sidebar";
import { OwnerPetCard } from "./owner-pet-card";
import { EmergencyContactCard } from "./emergency-contacts";
import styles from "../owner-profile.module.css";

const tabs: { id: string; label: string; icon: PettelIconName }[] = [
  { id: "general", label: "Thông tin chung & Thú cưng", icon: "paw" },
  { id: "reviews", label: "Đánh giá từ Pet Sitter", icon: "message" },
  { id: "history", label: "Lịch sử đặt chăm sóc", icon: "calendar" },
  { id: "settings", label: "Cài đặt", icon: "sliders" },
];

export function OwnerDashboard() {
  const { user, refresh } = useSession();
  const profileResource = useApi<{ data: OwnerProfile }>("/api/owner-profile");
  const [petPage, setPetPage] = useState(1);
  const [bookingPage, setBookingPage] = useState(1);
  const petsResource = useApi<Page<Pet>>(`/api/pets?page=${petPage}`);
  const bookingsResource = useApi<Page<Booking>>(`/api/owner-profile/bookings?page=${bookingPage}`);
  const profile = profileResource.data?.data;
  const pets = (petsResource.data?.data ?? []).filter((pet) => pet.status === "ACTIVE");
  const bookings = bookingsResource.data?.data ?? [];
  const [tab, setTab] = useState("general");
  const [editingProfile, setEditingProfile] = useState(false);
  const [editingPet, setEditingPet] = useState<Pet | null | undefined>();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<unknown>();
  const [notice, setNotice] = useState("");
  function openEditor() { setError(undefined); setEditingProfile(true); }
  function openPetEditor(pet: Pet | null) { setError(undefined); setEditingPet(pet); }

  async function saveProfile(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload: Record<string, unknown> = { name: form.get("name"), phone: form.get("phone") || null, bio: form.get("bio") || null, area_code: form.get("area_code") || null };
    const photo = form.get("photo");
    setBusy(true); setError(undefined);
    try {
      if (photo instanceof File && photo.size > 0) {
        if (photo.size > 5 * 1024 * 1024 || !["image/jpeg", "image/png", "image/webp"].includes(photo.type)) throw new Error("Chọn ảnh JPG, PNG hoặc WebP tối đa 5 MB.");
        const upload = new FormData(); upload.append("file", photo);
        const media = await write<{ data: { id: string } }>("post", "/api/owner-profile/media", upload);
        payload.avatar_media_id = media.data.id;
      }
      await write("patch", "/api/owner-profile", payload);
      profileResource.reload(); refresh(); setEditingProfile(false); setNotice("Đã lưu hồ sơ chủ nuôi.");
    } catch (failure) { setError(failure); } finally { setBusy(false); }
  }

  async function savePet(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload: Record<string, unknown> = Object.fromEntries(form);
    const photo = form.get("photo"); delete payload.photo;
    payload.health_flags = { core_vaccines: form.has("health_core_vaccines"), rabies_vaccine: form.has("health_rabies_vaccine"), deworming: form.has("health_deworming") };
    delete payload.health_core_vaccines; delete payload.health_rabies_vaccine; delete payload.health_deworming;
    setBusy(true); setError(undefined);
    try {
      if (photo instanceof File && photo.size > 0) {
        const upload = new FormData(); upload.append("file", photo);
        const media = await write<{ data: { id: string } }>("post", "/api/pets/media", upload); payload.photo_media_id = media.data.id;
      } else if (editingPet?.photo_media_id) payload.photo_media_id = editingPet.photo_media_id;
      await write(editingPet ? "patch" : "post", editingPet ? `/api/pets/${editingPet.id}` : "/api/pets", payload);
      petsResource.reload(); profileResource.reload(); setEditingPet(undefined); setNotice("Đã lưu hồ sơ thú cưng.");
    } catch (failure) { setError(failure); } finally { setBusy(false); }
  }

  async function shareProfile() {
    const text = `${profile?.name || user?.name} · Phụ huynh thú cưng trên PETTEL\n${profile?.bio || "Yêu thương và chăm sóc bé như một thành viên trong gia đình."}\n${profile?.stats.pet_count ?? pets.length} bé cưng đang nuôi.`;
    try {
      if (navigator.share) await navigator.share({ title: "Hồ sơ chủ nuôi PETTEL", text });
      else { await navigator.clipboard.writeText(text); setNotice("Đã sao chép nội dung giới thiệu hồ sơ."); }
    } catch (failure) { if (!(failure instanceof DOMException && failure.name === "AbortError")) setNotice("Bạn có thể sao chép lời giới thiệu trong thẻ hồ sơ để chia sẻ."); }
  }

  return <div className={styles.container}>
    <header className={styles.hero}>
      <div><div className={styles.badges}><span><PettelIcon name="shield" />{profile?.email_verified ? "Đã xác minh email" : "Tài khoản chủ nuôi PETTEL"}</span><span><PettelIcon name="star" />Pet Parent Thân Thiện{profile && ` • ${profile.stats.completed_count} lượt chăm sóc hoàn thành`}</span></div><h1>Hồ Sơ Chủ Nuôi <small>(Pet Parent Profile)</small></h1><p>Gắn kết yêu thương, chia sẻ thói quen của bé và đồng hành cùng mạng lưới Pet Sitter tại PETTEL.</p></div>
      <div className={styles.heroActions}><button className={styles.secondaryButton} onClick={() => void shareProfile()}><PettelIcon name="share" />Chia sẻ hồ sơ</button><button className={styles.primaryButton} disabled={!profile} onClick={openEditor}><PettelIcon name="sliders" />Chỉnh sửa</button></div>
    </header>
    {notice && <p className={styles.notice} role="status">{notice}<button onClick={() => setNotice("")} aria-label="Ẩn thông báo">×</button></p>}
    <ApiError error={profileResource.error} />
    <div className={styles.layout}>
      <div className={styles.sideColumn}>
        <OwnerSidebar profile={profile} name={profile?.name || user?.name || "Chủ nuôi PETTEL"} email={profile?.email || user?.email || ""} />
        <EmergencyContactCard profile={profile} onUpdated={profileResource.reload} />
      </div>
      <div className={styles.mainColumn}>
        <div className={styles.tabs} role="tablist" aria-label="Các mục hồ sơ">{tabs.map((item) => <button key={item.id} id={`owner-tab-${item.id}`} role="tab" type="button" aria-selected={tab === item.id} aria-controls="owner-tab-panel" className={tab === item.id ? styles.activeTab : ""} onClick={() => setTab(item.id)}><PettelIcon name={item.icon} />{item.label}{item.id === "history" && profile ? ` (${profile.stats.booking_count})` : ""}</button>)}</div>
        <section id="owner-tab-panel" role="tabpanel" aria-labelledby={`owner-tab-${tab}`} className={styles.panel}>
          {tab === "general" && <>
            <header className={styles.panelHeading}><span className={styles.sectionIcon}><PettelIcon name="paw" /></span><div><h2>Thú cưng đang nuôi{profile && ` (${profile.stats.pet_count} bé)`}</h2><p>Hồ sơ sức khỏe, thói quen sinh hoạt và lịch chăm sóc của bé</p></div><button className={styles.smallButton} onClick={() => openPetEditor(null)}><PettelIcon name="plus" />Thêm thú cưng mới</button></header>
            <ApiError error={petsResource.error} />
            {petsResource.loading && <p className={styles.empty}>Đang tải hồ sơ thú cưng...</p>}
            <div className={styles.petGrid}>{pets.map((pet) => <OwnerPetCard key={pet.id} pet={pet} bookings={bookings} onEdit={openPetEditor} />)}</div>
            {!petsResource.loading && !pets.length && <div className={styles.empty}><PettelIcon name="paw" /><h3>Thêm thành viên nhỏ của gia đình</h3><p>Tạo hồ sơ để tìm Sitter phù hợp và chia sẻ những điều đặc biệt của bé.</p><button className={styles.primaryButton} onClick={() => openPetEditor(null)}>Thêm thú cưng</button></div>}
            {(petsResource.data?.meta?.last_page ?? 1) > 1 && <div className={styles.pager}><button disabled={petPage === 1} onClick={() => setPetPage(petPage - 1)}>Trước</button><span>Trang {petPage}</span><button disabled={petPage >= (petsResource.data?.meta?.last_page ?? 1)} onClick={() => setPetPage(petPage + 1)}>Sau</button></div>}
          </>}
          {tab === "history" && <><header className={styles.panelHeading}><span className={styles.sectionIcon}><PettelIcon name="calendar" /></span><div><h2>Lịch sử đặt chăm sóc</h2><p>Theo dõi từng lần gửi gắm bé yêu cùng PETTEL</p></div></header><ApiError error={bookingsResource.error} />{bookingsResource.loading && <p className={styles.empty}>Đang tải lịch sử...</p>}{bookings.map((booking) => <article className={styles.historyItem} key={booking.id}><div><h3>{booking.listing_title || `Đơn #${booking.id}`}</h3><p>{booking.pet_name} • {dateTime(booking.start_at)}</p><span>{statuses[booking.status] ?? booking.status}</span></div><div><strong>{money(booking.price_snapshot_vnd)}</strong><Link href={`/bookings?open=${booking.id}`}>Xem chi tiết →</Link></div></article>)}{!bookingsResource.loading && !bookings.length && <p className={styles.empty}>Bạn chưa có đơn chăm sóc nào. <Link href="/services">Tìm Sitter phù hợp →</Link></p>}{(bookingsResource.data?.meta?.last_page ?? 1) > 1 && <div className={styles.pager}><button disabled={bookingPage === 1} onClick={() => setBookingPage(bookingPage - 1)}>Trước</button><span>Trang {bookingPage}</span><button disabled={bookingPage >= (bookingsResource.data?.meta?.last_page ?? 1)} onClick={() => setBookingPage(bookingPage + 1)}>Sau</button></div>}</>}
          {tab === "reviews" && <><header className={styles.panelHeading}><span className={styles.sectionIcon}><PettelIcon name="star" /></span><div><h2>Đánh giá từ Pet Sitter</h2><p>Những chia sẻ từ người chăm về hành trình đồng hành cùng bé</p></div></header><div className={styles.empty}><PettelIcon name="heart" /><h3>Chưa có đánh giá từ Sitter</h3><p>Hiện chưa có dữ liệu đánh giá chủ nuôi để hiển thị trong hồ sơ.</p><Link className={styles.secondaryButton} href="/chat">Trao đổi với người chăm</Link></div></>}
          {tab === "settings" && <><header className={styles.panelHeading}><span className={styles.sectionIcon}><PettelIcon name="sliders" /></span><div><h2>Cài đặt hồ sơ</h2><p>Thông tin liên hệ và quyền riêng tư của bạn</p></div></header><div className={styles.settingsRow}><div><h3>Thông tin cá nhân</h3><p>{profile?.name} • {profile?.email}</p></div><button className={styles.secondaryButton} disabled={!profile} onClick={openEditor}>Chỉnh sửa</button></div><div className={styles.settingsRow}><div><h3>Quyền riêng tư</h3><p>Hồ sơ này chỉ hiển thị trong tài khoản của bạn. Khi chia sẻ, chỉ nội dung giới thiệu được gửi, không bao gồm email và số điện thoại.</p></div><PettelIcon name="shield" /></div><Link className={styles.smallButton} href="/pet-profile">Quản lý đầy đủ hồ sơ thú cưng →</Link></>}
        </section>
      </div>
    </div>
    {editingProfile && profile && <OwnerEditor profile={profile} busy={busy} error={error} onSave={saveProfile} onClose={() => setEditingProfile(false)} />}
    {editingPet !== undefined && <PetEditor editing={editingPet} busy={busy} error={error} submit={savePet} onClose={() => setEditingPet(undefined)} />}
  </div>;
}
