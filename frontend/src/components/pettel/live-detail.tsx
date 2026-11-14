/* eslint-disable @next/next/no-img-element -- Listing photos retain the original detail layout. */
"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";
import { areas, services, species, sizes, money, apiUrl, dateTime, useApi, write, type Listing, type Page, type Pet } from "@/lib/api/pettel";
import { ApiError, RequireSession, SiteHeader, useSession } from "./session";
import { localDate, vietnamISO } from "./live-listings";

type Quote = { price_total_vnd: number; quantity: number; price_unit: string };
type Draft = { payload: { pet_id: string; listing_id: string; start_at: string; end_at: string; instructions: string }; quote: Quote; key: string };
type Review = { id: string; rating: number; body: string | null; created_at: string; is_demo: boolean };

export function LiveDetail() {
  const id = useSearchParams().get("id");
  const listing = useApi<{ data: Listing }>(id && /^\d+$/.test(id) ? `/api/listings/${id}` : null);
  return <><SiteHeader /><main className="app-container"><Link href="/services">← Tìm dịch vụ</Link><ApiError error={listing.error} />{listing.loading && <p role="status">Đang tải dịch vụ...</p>}{!id && <p>Chọn dịch vụ từ <Link href="/services">danh sách tìm kiếm</Link>.</p>}{listing.data && <Detail key={listing.data.data.id} listing={listing.data.data} />}</main></>;
}

function Detail({ listing }: { listing: Listing }) {
  const [reviewPage, setReviewPage] = useState(1);
  const reviews = useApi<Page<Review>>(`/api/listings/${listing.id}/reviews?page=${reviewPage}`);
  const [notice, setNotice] = useState("");
  return <><img className="app-hero" src={listing.media?.[0] ? apiUrl(listing.media[0].image_url) : "/images/pet_hotel_building.png"} alt={listing.media?.[0] ? listing.title : "Ảnh minh họa dịch vụ"} /><div className="app-detail-grid"><article><h1>{listing.title}</h1><p>{services[listing.service_type]} · {areas[listing.area_code]}</p><p>★ {listing.rating_average ?? "Chưa có đánh giá"} · {listing.review_count} đánh giá</p><p className="app-description">{listing.description}</p><h2>Nhận chăm sóc</h2><p>{listing.acceptance_rules_json.species.map((value) => species[value]).join(" · ")}</p><p>Kích thước: {listing.acceptance_rules_json.sizes.map((value) => sizes[value]).join(" · ")}</p><div className="app-actions"><button onClick={async () => { try { await navigator.clipboard.writeText(window.location.href); setNotice("Đã sao chép liên kết dịch vụ."); } catch { setNotice("Bạn có thể sao chép địa chỉ trang để chia sẻ."); } }}>Chia sẻ liên kết</button></div><p role="status">{notice}</p><h2>Đánh giá từ đơn đã hoàn thành</h2><ApiError error={reviews.error} />{reviews.loading && <p>Đang tải đánh giá...</p>}{reviews.data?.data.map((review) => <div className="app-card" key={review.id}><strong>{review.rating} ★</strong> · {dateTime(review.created_at)}{review.is_demo && <span> · Dữ liệu minh họa</span>}<p>{review.body || "Không có nội dung đánh giá."}</p></div>)}{reviews.data?.data.length === 0 && <p>Chưa có đánh giá.</p>}<div className="app-actions"><button disabled={reviewPage <= 1} onClick={() => setReviewPage((value) => value - 1)}>Trước</button><span>Trang {reviewPage}</span><button disabled={reviewPage >= (reviews.data?.meta?.last_page ?? 1)} onClick={() => setReviewPage((value) => value + 1)}>Sau</button></div><ReportForm target={{ listing_id: listing.id }} /></article><aside className="app-card app-booking"><h2>{money(listing.price_vnd)} / {listing.price_unit === "HOUR" ? "giờ" : "đêm"}</h2><RequireSession next={`/detail?id=${listing.id}`}><BookingForm listing={listing} /></RequireSession></aside></div></>;
}

function BookingForm({ listing }: { listing: Listing }) {
  const router = useRouter();
  const pets = useApi<Page<Pet>>("/api/pets");
  const [draft, setDraft] = useState<Draft | null>(null);
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const tomorrow = new Date(); tomorrow.setUTCDate(tomorrow.getUTCDate() + 1); tomorrow.setUTCHours(2,0,0,0);
  const end = new Date(tomorrow.getTime() + (listing.service_type === "DAYCARE" ? 2 : 24) * 3600000);
  async function quote(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    setBusy(true); setError(undefined);
    try {
      const payload = { listing_id: listing.id, pet_id: String(form.get("pet_id")), start_at: vietnamISO(String(form.get("start_at"))), end_at: vietnamISO(String(form.get("end_at"))), instructions: String(form.get("instructions") ?? "") };
      const data = await write<Quote>("post", "/api/bookings/quote", payload);
      setDraft({ payload, quote: data, key: crypto.randomUUID() });
    } catch (failure) { setError(failure); } finally { setBusy(false); }
  }
  async function book() {
    if (!draft || busy) return;
    setBusy(true); setError(undefined);
    try { const result = await write<{ id: string }>("post", "/api/bookings", draft.payload, draft.key); router.push(`/bookings?open=${result.id}`); }
    catch (failure) { setError(failure); setBusy(false); }
  }
  const availablePets = pets.data?.data.filter((pet) => pet.status === "ACTIVE") ?? [];
  return <><ApiError error={error ?? pets.error} /><form className="app-form" onSubmit={quote} onChange={() => setDraft(null)}><label>Thú cưng<select name="pet_id" required defaultValue=""><option value="" disabled>Chọn thú cưng</option>{availablePets.map((pet) => <option key={pet.id} value={pet.id}>{pet.name} ({species[pet.species]} · {sizes[pet.size ?? ""] ?? "chưa chọn kích thước"})</option>)}</select></label>{!pets.loading && availablePets.length === 0 && <Link href="/pet-profile">Thêm hồ sơ thú cưng trước khi đặt lịch</Link>}<label>Bắt đầu (giờ Việt Nam)<input type="datetime-local" name="start_at" required defaultValue={localDate(tomorrow)} /></label><label>Kết thúc (giờ Việt Nam)<input type="datetime-local" name="end_at" required defaultValue={localDate(end)} /></label><p className="app-muted">{listing.service_type === "DAYCARE" ? "Chăm ban ngày: 1–12 giờ trong cùng ngày." : "Lưu trú: từ 12 giờ đến 30 ngày."}</p><label>Hướng dẫn chăm sóc<textarea name="instructions" maxLength={2000} /></label><button className="app-button" disabled={busy || pets.loading || availablePets.length === 0}>{busy ? "Đang kiểm tra..." : "Kiểm tra giá & lịch trống"}</button></form><p>Đơn cần người chăm chấp nhận trước khi thanh toán. Thanh toán hiện ở môi trường sandbox.</p>{draft && <div className="pet-modal" style={{display:"flex"}}><section className="pet-modal-content" role="dialog" aria-modal="true" aria-labelledby="booking-dialog"><h2 id="booking-dialog">Xác nhận yêu cầu đặt lịch</h2><p>{listing.title}</p><p>{dateTime(draft.payload.start_at)} → {dateTime(draft.payload.end_at)}</p><p>{draft.quote.quantity} {draft.quote.price_unit === "HOUR" ? "giờ" : "đêm"}</p><h3>Tổng: {money(draft.quote.price_total_vnd)}</h3><ApiError error={error} /><div className="app-actions"><button disabled={busy} onClick={() => setDraft(null)}>Quay lại</button><button className="app-button" disabled={busy} onClick={() => void book()}>{busy ? "Đang gửi..." : "Gửi yêu cầu đặt lịch"}</button></div></section></div>}</>;
}

export function ReportForm({ target }: { target: { listing_id?: string; booking_id?: string } }) {
  const { user } = useSession();
  const [error, setError] = useState<unknown>();
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);
  return <details className="app-card"><summary>Báo cáo vấn đề</summary>{!user ? <Link href="/login">Đăng nhập để báo cáo</Link> : sent ? <p role="status">Đã gửi báo cáo để quản trị viên xem xét.</p> : <form className="app-form" onSubmit={async (event) => { event.preventDefault(); const form = new FormData(event.currentTarget); setBusy(true); setError(undefined); try { await write("post","/api/reports",{ ...target, reason_code: form.get("reason_code"), description: form.get("description") }); setSent(true); } catch (failure) { setError(failure); } finally { setBusy(false); } }}><label>Lý do<select name="reason_code"><option value="INACCURATE_INFORMATION">Thông tin không chính xác</option><option value="SERVICE_ISSUE">Vấn đề dịch vụ</option><option value="OTHER">Khác</option></select></label><label>Mô tả<textarea name="description" required maxLength={2000} /></label><ApiError error={error} /><button className="app-button" disabled={busy}>Gửi báo cáo</button></form>}</details>;
}
