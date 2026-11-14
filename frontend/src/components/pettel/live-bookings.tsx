/* eslint-disable @next/next/no-img-element -- Private care photos use authenticated API URLs. */
"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useRef, useState, type FormEvent } from "react";
import { type Booking, type Page, type CareUpdate, useApi, write, money, statuses, dateTime, apiUrl } from "@/lib/api/pettel";
import { ApiError, RequireSession, SiteHeader } from "./session";
import { ReportForm } from "./live-detail";
import { useConfirmation } from "./confirmation";

export function LiveBookings() {
  return <><SiteHeader /><main className="app-container"><h1>Đơn chăm sóc</h1><RequireSession next="/bookings"><Bookings /></RequireSession></main></>;
}

function Bookings() {
  const [page, setPage] = useState(1);
  const bookings = useApi<Page<Booking>>(`/api/bookings?page=${page}`, 15000);
  const query = useSearchParams();
  const [selected, setSelected] = useState<string | null>(null);
  const id = selected ?? query.get("open");
  return <><ApiError error={bookings.error} /><button onClick={bookings.reload}>Tải lại danh sách</button>{bookings.loading && <p>Đang tải đơn...</p>}<div className="app-bookings-grid"><section>{bookings.data?.data.map((booking) => <article className="app-card" key={booking.id}><h2>{booking.listing_title || `Đơn #${booking.id}`}</h2><p>{booking.pet_name || "Thú cưng"} · {booking.participant_role === "OWNER" ? "Tôi là chủ thú cưng" : "Tôi là người chăm"}</p><p>{dateTime(booking.start_at)} → {dateTime(booking.end_at)}</p><p><strong>{statuses[booking.status] ?? booking.status}</strong> · {money(booking.price_snapshot_vnd)}</p><div className="app-actions"><button className="app-button" onClick={() => setSelected(booking.id)}>Xem & quản lý</button><Link href={`/chat?booking=${booking.id}`}>Nhắn tin</Link></div></article>)}{bookings.data?.data.length === 0 && <p className="app-notice">Bạn chưa có đơn chăm sóc. <Link href="/services">Tìm dịch vụ</Link></p>}<div className="app-actions"><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Trang trước</button><span>Trang {page}</span><button disabled={page >= (bookings.data?.meta?.last_page ?? 1)} onClick={() => setPage((value) => value + 1)}>Trang sau</button></div></section><section>{id ? <BookingPanel key={id} id={id} onChange={bookings.reload} /> : <p className="app-notice">Chọn một đơn để xem trạng thái, cập nhật chăm sóc và các thao tác phù hợp.</p>}</section></div></>;
}

function BookingPanel({ id, onChange }: { id: string; onChange: () => void }) {
  const booking = useApi<{ data: Booking }>(`/api/bookings/${id}`,15000);
  const [error, setError] = useState<unknown>();
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const [attempt, setAttempt] = useState<{ id: string; amount_vnd: number; is_sandbox: boolean } | null>(null);
  const keys = useRef<Record<string,string>>({});
  const { ask, dialog } = useConfirmation();
  async function action(name: string, payload?: unknown) {
    setBusy(true); setError(undefined); setNotice("");
    const key = keys.current[name] ??= crypto.randomUUID();
    try { await write("post",`/api/bookings/${id}/${name}`,payload,key); delete keys.current[name]; setNotice("Yêu cầu đã được gửi. Trạng thái bên dưới được cập nhật từ máy chủ."); booking.reload(); onChange(); return true; }
    catch (failure) { setError(failure); return false; } finally { setBusy(false); }
  }
  async function pay() {
    setBusy(true); setError(undefined);
    try { const result = await write<{ data: NonNullable<typeof attempt> }>("post",`/api/bookings/${id}/sandbox-payments`,undefined,keys.current.payment ??= crypto.randomUUID()); setAttempt(result.data); }
    catch (failure) { setError(failure); } finally { setBusy(false); }
  }
  const value = booking.data?.data;
  const owner = value?.participant_role === "OWNER";
  return <div className="app-card">{dialog}<ApiError error={booking.error ?? error} />{booking.loading && <p>Đang cập nhật đơn...</p>}{value && <><h2>Đơn #{value.id} · {value.listing_title}</h2><p><strong>{statuses[value.status] ?? value.status}</strong></p><p>{dateTime(value.start_at)} → {dateTime(value.end_at)}</p><h3>{money(value.price_snapshot_vnd)}</h3>{value.payment_due_at && value.status === "AWAITING_PAYMENT" && <p>Hạn thanh toán: {dateTime(value.payment_due_at)}</p>}<p role="status">{notice}</p><div className="app-actions"><Link className="app-button" href={`/chat?booking=${id}`}>Mở chat của đơn</Link>
    {!owner && value.status === "REQUESTED" && <><button disabled={busy} onClick={() => void action("accept")}>Nhận đơn</button><button disabled={busy} onClick={async () => { if (await ask("Từ chối yêu cầu đặt lịch này?")) void action("reject"); }}>Từ chối</button></>}
    {!owner && value.status === "CONFIRMED" && <button disabled={busy} onClick={() => void action("start")}>Bắt đầu chăm sóc</button>}
    {!owner && value.status === "IN_PROGRESS" && <button disabled={busy} onClick={() => void action("request-completion")}>Yêu cầu xác nhận hoàn thành</button>}
    {owner && value.status === "AWAITING_PAYMENT" && <button disabled={busy} onClick={() => void pay()}>Thanh toán sandbox</button>}
    {owner && value.status === "AWAITING_CONFIRMATION" && <button disabled={busy} onClick={async () => { if (await ask("Xác nhận đã nhận lại thú cưng và hoàn thành dịch vụ?")) void action("confirm-completion"); }}>Xác nhận hoàn thành</button>}
    {["REQUESTED","AWAITING_PAYMENT","CONFIRMED"].includes(value.status) && <button disabled={busy} onClick={async () => { if (await ask("Gửi yêu cầu hủy đơn? Điều kiện hủy và hoàn tiền sẽ do máy chủ kiểm tra.")) void action("cancel"); }}>Hủy đơn</button>}
  </div>{attempt?.is_sandbox && value.status === "AWAITING_PAYMENT" && <div className="app-notice"><h3>Thanh toán thử nghiệm</h3><p>{money(attempt.amount_vnd)} · Không thu tiền thật.</p><div className="app-actions">{[["SUCCESS","Giả lập thành công"],["FAILURE","Giả lập thất bại"]].map(([result,label]) => <button key={result} disabled={busy} onClick={async () => { const success = await action(`sandbox-payments/${attempt.id}/simulate`,{result}); if (success) { setAttempt(null); delete keys.current.payment; } }}>{label}</button>)}</div></div>}
    <CareFeed booking={value} />{owner && value.status === "COMPLETED" && <ReviewForm id={id} />}<ReportForm target={{booking_id:id}} /></>}</div>;
}

function CareFeed({ booking }: { booking: Booking }) {
  const [page, setPage] = useState(1);
  const feed = useApi<Page<CareUpdate>>(`/api/bookings/${booking.id}/care-updates?page=${page}&per_page=20`,30000);
  const [error,setError] = useState<unknown>(); const [busy,setBusy] = useState(false);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); const form = event.currentTarget; const data = new FormData(form); const file = data.get("image"); if (file instanceof File && !file.size) data.delete("image");
    setBusy(true); setError(undefined);
    try { await write("post",`/api/bookings/${booking.id}/care-updates`,data); form.reset(); feed.reload(); } catch(failure) { setError(failure); } finally { setBusy(false); }
  }
  return <section><h3>Cập nhật chăm sóc</h3><ApiError error={feed.error ?? error} />{feed.data?.data.map((update) => <article className="app-update" key={update.id}><p><strong>{update.sender.name}</strong> · {dateTime(update.created_at)}</p><p>{update.redacted ? "Nội dung đã được ẩn." : update.body}</p>{update.image_url && <img src={apiUrl(update.image_url)} alt="Ảnh cập nhật chăm sóc" />}</article>)}{feed.data?.data.length === 0 && <p>Chưa có cập nhật chăm sóc.</p>}<div className="app-actions"><button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Trước</button><span>Trang {page}</span><button disabled={page >= (feed.data?.meta?.last_page ?? 1)} onClick={() => setPage((value) => value + 1)}>Sau</button></div>{booking.participant_role === "CAREGIVER" && booking.status === "IN_PROGRESS" && <form className="app-form" onSubmit={submit}><label>Nội dung cập nhật<textarea name="body" maxLength={2000} /></label><label>Ảnh (JPEG/PNG/WebP, tối đa 10 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp" /></label><button disabled={busy} className="app-button">Gửi cập nhật</button></form>}</section>;
}

function ReviewForm({ id }: { id: string }) {
  const [sent,setSent] = useState(false); const [error,setError] = useState<unknown>(); const [busy,setBusy] = useState(false);
  return <details className="app-card"><summary>Đánh giá dịch vụ</summary>{sent ? <p role="status">Đã gửi đánh giá. Cảm ơn bạn!</p> : <form className="app-form" onSubmit={async (event) => { event.preventDefault(); const data = new FormData(event.currentTarget); setBusy(true); setError(undefined); try { await write("post",`/api/bookings/${id}/reviews`,{rating:Number(data.get("rating")),body:data.get("body")}); setSent(true); } catch(failure) { setError(failure); } finally { setBusy(false); } }}><label>Điểm đánh giá<select name="rating" defaultValue="5">{[5,4,3,2,1].map((rating) => <option key={rating} value={rating}>{rating} sao</option>)}</select></label><label>Nhận xét<textarea name="body" maxLength={1000} /></label><ApiError error={error} /><button disabled={busy} className="app-button">Gửi đánh giá</button></form>}</details>;
}
