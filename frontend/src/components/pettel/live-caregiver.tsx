/* eslint-disable @next/next/no-img-element -- Caregiver image previews are authenticated API assets. */
"use client";

import Link from "next/link";
import axios from "axios";
import { useState, type FormEvent } from "react";
import { type Listing, type Page, areas, services, species, sizes, useApi, write, money, dateTime, apiUrl } from "@/lib/api/pettel";
import { ApiError, RequireSession, SiteHeader, useSession } from "./session";
import { vietnamISO } from "./live-listings";
import { useConfirmation } from "./confirmation";

type Profile = { display_name: string; bio: string | null; type: string; area_code: string };
type ManagedListing = Omit<Listing, "media"> & { version: number; suspension_source: string | null; pending_change_request_id: string | null; latest_decision: { reason: string | null } | null; media: { media_object_id: string; image_url: string; thumbnail_url: string }[] };
type Slot = { id: string; start_at: string; end_at: string };

export function LiveCaregiver() { return <><SiteHeader /><main className="app-container"><h1>Dành cho người chăm thú cưng</h1><RequireSession next="/caregiver"><Caregiver /></RequireSession></main></>; }

function Caregiver() {
  const {user,refresh} = useSession(); const [error,setError] = useState<unknown>(); const [busy,setBusy] = useState(false);
  const enabled = user?.roles?.some((role) => role.role === "CAREGIVER");
  if (!enabled) return <section className="app-card"><p>Bật vai trò người chăm để tạo hồ sơ dịch vụ và nhận yêu cầu đặt lịch.</p><ApiError error={error} /><button className="app-button" disabled={busy} onClick={async () => { setBusy(true); try { await write("post","/api/user/roles/caregiver"); refresh(); } catch(failure) { setError(failure); } finally { setBusy(false); } }}>Trở thành người chăm</button></section>;
  return <><p><Link href="/bookings">Quản lý đơn nhận chăm sóc →</Link></p><ProfileForm /><ListingManager /></>;
}

function ProfileForm() {
  const profile = useApi<{data:Profile}>("/api/caregiver/profile"); const [error,setError] = useState<unknown>(); const [busy,setBusy] = useState(false); const [saved,setSaved] = useState(false);
  const missing = axios.isAxiosError(profile.error) && profile.error.response?.status === 404;
  const value = profile.data?.data;
  if (profile.loading) return <p>Đang tải hồ sơ người chăm...</p>;
  return <details className="app-card" open={!value}><summary>{value ? `Hồ sơ: ${value.display_name}` : "Tạo hồ sơ người chăm"}</summary><ApiError error={error ?? (missing ? null : profile.error)} />{saved && <p className="app-success">Đã lưu hồ sơ.</p>}<form className="app-form" key={JSON.stringify(value)} onSubmit={async (event) => { event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget)); setBusy(true); setError(undefined); try { await write(value ? "patch" : "post","/api/caregiver/profile",data); profile.reload(); setSaved(true); } catch(failure) { setError(failure); } finally { setBusy(false); } }}><label>Tên hiển thị<input name="display_name" defaultValue={value?.display_name} required maxLength={100} /></label><label>Loại hình<select name="type" defaultValue={value?.type ?? "INDIVIDUAL"}><option value="INDIVIDUAL">Cá nhân</option><option value="FACILITY">Cơ sở chăm sóc</option></select></label><label>Khu vực<select name="area_code" defaultValue={value?.area_code ?? "DN_CENTER"}>{Object.entries(areas).map(([key,label]) => <option key={key} value={key}>{label}</option>)}</select></label><label>Giới thiệu<textarea name="bio" defaultValue={value?.bio ?? ""} maxLength={2000} /></label><button className="app-button" disabled={busy}>Lưu hồ sơ</button></form></details>;
}

function ListingManager() {
  const [page,setPage] = useState(1); const listings = useApi<Page<ManagedListing>>(`/api/caregiver/listings?status=ALL&page=${page}`); const [editing,setEditing] = useState<ManagedListing | null | undefined>();
  return <section><h2>Dịch vụ của tôi</h2><p>Dịch vụ mới cần quản trị viên duyệt trước khi xuất hiện trên trang tìm kiếm.</p><ApiError error={listings.error} /><button className="app-button" onClick={() => setEditing(null)}>+ Tạo dịch vụ</button>{listings.loading && <p>Đang tải dịch vụ...</p>}{editing !== undefined && <div className="pet-modal" style={{display:"flex"}}><div className="pet-modal-content" role="dialog" aria-modal="true" aria-label="Biên tập dịch vụ"><ListingEditor listing={editing} onSave={() => { setEditing(undefined); listings.reload(); }} onClose={() => setEditing(undefined)} /></div></div>}{listings.data?.data.map((listing) => <ManagedCard listing={listing} key={`${listing.id}-${listing.version}`} reload={listings.reload} edit={() => setEditing(listing)} />)}{listings.data?.data.length === 0 && <p>Chưa có dịch vụ. Tạo hồ sơ người chăm rồi thêm dịch vụ đầu tiên.</p>}<div className="app-actions"><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Trước</button><span>Trang {page}</span><button disabled={page >= (listings.data?.meta?.last_page ?? 1)} onClick={() => setPage((value) => value + 1)}>Sau</button></div></section>;
}

function ListingEditor({ listing, onSave, onClose }: { listing: ManagedListing | null; onSave: () => void; onClose: () => void }) {
  const [error,setError] = useState<unknown>(); const [busy,setBusy] = useState(false);
  const live = listing && listing.status !== "DRAFT";
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const data = new FormData(event.currentTarget); const service = String(data.get("service_type"));
    const payload = { title:data.get("title"),description:data.get("description"),area_code:data.get("area_code"),service_type:service,price_vnd:Number(data.get("price_vnd")),price_unit:service === "DAYCARE" ? "HOUR" : "NIGHT",acceptance_rules_json:{schema_version:1,species:data.getAll("species"),sizes:data.getAll("sizes")} };
    setBusy(true); setError(undefined);
    try { if (live) await write("post",`/api/caregiver/listings/${listing.id}/change-requests`,{base_listing_version:listing.version,changes:payload}); else await write(listing ? "patch" : "post",listing ? `/api/caregiver/listings/${listing.id}` : "/api/caregiver/listings",payload); onSave(); } catch(failure) { setError(failure); } finally { setBusy(false); }
  }
  return <><h2>{live ? "Gửi đề xuất chỉnh sửa" : listing ? "Chỉnh sửa bản nháp" : "Tạo dịch vụ"}</h2><ApiError error={error} /><form className="app-form" onSubmit={submit}><label>Tiêu đề<input name="title" defaultValue={listing?.title} required maxLength={120} /></label><label>Mô tả<textarea name="description" defaultValue={listing?.description} required maxLength={2000} /></label><label>Dịch vụ<select name="service_type" defaultValue={listing?.service_type ?? "DAYCARE"}>{Object.entries(services).map(([key,label]) => <option key={key} value={key}>{label}</option>)}</select></label><label>Khu vực<select name="area_code" defaultValue={listing?.area_code ?? "DN_CENTER"}>{Object.entries(areas).map(([key,label]) => <option key={key} value={key}>{label}</option>)}</select></label><label>Giá VND / giờ (ban ngày) hoặc đêm (lưu trú)<input type="number" name="price_vnd" required min="1" step="1" defaultValue={listing?.price_vnd} /></label><fieldset><legend>Loài nhận chăm</legend>{Object.entries(species).map(([key,label]) => <label className="app-check" key={key}><input type="checkbox" name="species" value={key} defaultChecked={listing ? listing.acceptance_rules_json.species.includes(key) : key === "CAT"} />{label}</label>)}</fieldset><fieldset><legend>Kích thước nhận chăm</legend>{Object.entries(sizes).map(([key,label]) => <label className="app-check" key={key}><input type="checkbox" name="sizes" value={key} defaultChecked={listing ? listing.acceptance_rules_json.sizes.includes(key) : key === "SMALL"} />{label}</label>)}</fieldset><div className="app-actions"><button disabled={busy} className="app-button">{live ? "Gửi đề xuất duyệt" : "Lưu bản nháp"}</button><button type="button" disabled={busy} onClick={onClose}>Đóng</button></div></form></>;
}

function ManagedCard({ listing, reload, edit }: { listing: ManagedListing; reload: () => void; edit: () => void }) {
  const [error,setError] = useState<unknown>(); const [busy,setBusy] = useState(false);
  async function action(name:string,payload?:unknown) { setBusy(true); setError(undefined); try { await write("post",`/api/caregiver/listings/${listing.id}/${name}`,payload); reload(); } catch(failure) { setError(failure); } finally { setBusy(false); } }
  async function upload(event:FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); setBusy(true); setError(undefined);
    try { const media = await write<{data:{id:string}}>("post","/api/caregiver/media",data); await write("put",`/api/caregiver/listings/${listing.id}/media`,{media:[...listing.media.map((item,index) => ({media_object_id:item.media_object_id,sort_order:index+1})),{media_object_id:media.data.id,sort_order:listing.media.length+1}]}); form.reset(); reload(); } catch(failure) { setError(failure); } finally { setBusy(false); }
  }
  const labels:Record<string,string> = {DRAFT:"Bản nháp",PENDING_REVIEW:"Chờ duyệt",ACTIVE:"Đang hoạt động",SUSPENDED:"Tạm ngưng",ARCHIVED:"Đã lưu trữ"};
  return <article className="app-card"><h3>{listing.title}</h3><p>{labels[listing.status ?? ""] ?? listing.status} · {money(listing.price_vnd)}</p>{listing.latest_decision?.reason && <p>Phản hồi duyệt: {listing.latest_decision.reason}</p>}<ApiError error={error} /><div className="app-actions">{["DRAFT","ACTIVE","SUSPENDED"].includes(listing.status ?? "") && !listing.pending_change_request_id && <button disabled={busy} onClick={edit}>Chỉnh sửa</button>}{listing.status === "DRAFT" && <button disabled={busy} onClick={() => void action("submit")}>Gửi duyệt</button>}{listing.status === "ACTIVE" && <button disabled={busy} onClick={() => void action("suspend",{reason:"Người chăm tạm ngưng nhận khách"})}>Tạm ngưng</button>}{listing.status === "SUSPENDED" && listing.suspension_source === "CAREGIVER" && <button disabled={busy} onClick={() => void action("restore")}>Mở lại</button>}{listing.pending_change_request_id && <button disabled={busy} onClick={() => void action(`change-requests/${listing.pending_change_request_id}/withdraw`)}>Rút đề xuất chỉnh sửa</button>}{listing.status === "ACTIVE" && <Link href={`/detail?id=${listing.id}`}>Xem trang dịch vụ</Link>}</div>{listing.status === "DRAFT" && <details><summary>Ảnh dịch vụ ({listing.media.length}/10)</summary><div className="app-photo-grid">{listing.media.map((media) => <img key={media.media_object_id} src={apiUrl(media.thumbnail_url)} alt="Ảnh dịch vụ của tôi" />)}</div><form className="app-form" onSubmit={upload}><label>Thêm ảnh (JPEG/PNG/WebP, tối đa 10 MB)<input type="file" name="file" required accept="image/jpeg,image/png,image/webp" /></label><button disabled={busy || listing.media.length >= 10}>Tải ảnh lên</button></form></details>}<Availability listing={listing} /></article>;
}

function Availability({ listing }: { listing: ManagedListing }) {
  const [page,setPage] = useState(1); const slots = useApi<Page<Slot>>(`/api/caregiver/listings/${listing.id}/availability-slots?page=${page}`); const [error,setError] = useState<unknown>(); const [busy,setBusy] = useState(false);
  const editable = ["DRAFT","ACTIVE","SUSPENDED"].includes(listing.status ?? "");
  const { ask, dialog } = useConfirmation();
  return <>{dialog}<details><summary>Lịch nhận chăm sóc</summary><ApiError error={slots.error ?? error} />{slots.data?.data.map((slot) => <p key={slot.id}>{dateTime(slot.start_at)} → {dateTime(slot.end_at)} {editable && <button disabled={busy} onClick={async () => { if (!(await ask("Xóa khung lịch này?"))) return; setBusy(true); try { await write("delete",`/api/caregiver/listings/${listing.id}/availability-slots/${slot.id}`); slots.reload(); } catch(failure) { setError(failure); } finally { setBusy(false); } }}>Xóa</button>}</p>)}<div className="app-actions"><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Trước</button><button disabled={page >= (slots.data?.meta?.last_page ?? 1)} onClick={() => setPage((value) => value + 1)}>Sau</button></div>{editable && <form className="app-form" onSubmit={async (event) => { event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); setBusy(true); setError(undefined); try { await write("post",`/api/caregiver/listings/${listing.id}/availability-slots`,{start_at:vietnamISO(String(data.get("start"))),end_at:vietnamISO(String(data.get("end")))}); form.reset(); slots.reload(); } catch(failure) { setError(failure); } finally { setBusy(false); } }}><label>Bắt đầu (giờ Việt Nam)<input type="datetime-local" name="start" required /></label><label>Kết thúc (giờ Việt Nam)<input type="datetime-local" name="end" required /></label><button disabled={busy}>Thêm lịch trống</button></form>}</details></>;
}
