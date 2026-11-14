/* eslint-disable @next/next/no-img-element -- Render listing photos with their original crop. */
"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { type FormEvent, useState } from "react";
import { areas, services, species, sizes, money, apiUrl, useApi, type Listing, type Page, type Pet } from "@/lib/api/pettel";
import { ApiError, SiteHeader, useSession } from "./session";

export const vietnamISO = (value: string) => new Date(`${value}:00+07:00`).toISOString();
export const localDate = (value: Date) => new Date(value.getTime() + 7 * 3600000).toISOString().slice(0,16);

export function LiveListings() {
  const router = useRouter();
  const query = useSearchParams();
  const { user } = useSession();
  const pets = useApi<Page<Pet>>(user ? "/api/pets" : null);
  const resource = useApi<Page<Listing>>(`/api/listings?${query.toString()}`);
  const [formError, setFormError] = useState<unknown>();
  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setFormError(undefined);
    try {
      const data = new FormData(event.currentTarget);
      const params = new URLSearchParams();
      for (const [key,value] of data) if (typeof value === "string" && value) params.set(key, key === "start_at" || key === "end_at" ? vietnamISO(value) : value.trim());
      router.push(`/services?${params}`);
    } catch (failure) { setFormError(failure); }
  }
  const page = Number(query.get("page") ?? 1);
  function paginate(value: number) { const next = new URLSearchParams(query); next.set("page", String(value)); router.push(`/services?${next}`); }
  return <><SiteHeader /><section className="app-search"><form className="app-filter-form" onSubmit={search} key={query.toString()}>
    <label>Tìm kiếm<input name="q" defaultValue={query.get("q") ?? ""} placeholder="Tên dịch vụ, mô tả..." /></label>
    <Filter name="service_type" label="Dịch vụ" choices={services} value={query.get("service_type")} />
    <Filter name="area_code" label="Khu vực" choices={areas} value={query.get("area_code")} />
    <Filter name="species" label="Loài" choices={species} value={query.get("species")} />
    <Filter name="size" label="Kích thước" choices={sizes} value={query.get("size")} />
    <label>Smart Match<select name="pet_id" defaultValue={query.get("pet_id") ?? ""} disabled={!user}><option value="">{user ? "Chọn thú cưng của tôi" : "Đăng nhập để dùng"}</option>{pets.data?.data.filter((pet) => pet.status === "ACTIVE").map((pet) => <option key={pet.id} value={pet.id}>{pet.name}</option>)}</select></label>
    <label>Bắt đầu (giờ Việt Nam)<input type="datetime-local" name="start_at" defaultValue={query.get("start_at") ? localDate(new Date(query.get("start_at")!)) : ""} /></label>
    <label>Kết thúc (giờ Việt Nam)<input type="datetime-local" name="end_at" defaultValue={query.get("end_at") ? localDate(new Date(query.get("end_at")!)) : ""} /></label>
    <label>Tổng giá từ (VND)<input type="number" name="min_price_vnd" min="0" defaultValue={query.get("min_price_vnd") ?? ""} /></label>
    <label>Tổng giá đến (VND)<input type="number" name="max_price_vnd" min="0" defaultValue={query.get("max_price_vnd") ?? ""} /></label>
    <div className="app-actions"><button className="app-button">Tìm kiếm</button><Link href="/services">Xóa bộ lọc</Link></div>
  </form><p className="app-muted">Chọn cả thời gian bắt đầu và kết thúc để tìm lịch trống và lọc theo tổng giá. Smart Match dùng loài và kích thước trong hồ sơ thú cưng.</p><ApiError error={formError ?? resource.error} /></section>
    <main className="app-discovery"><section className="app-results"><h1>Dịch vụ chăm sóc thú cưng tại Đà Nẵng</h1>{resource.loading && <p role="status">Đang tìm dịch vụ...</p>}{resource.data && <p>{resource.data.meta?.total ?? resource.data.data.length} dịch vụ phù hợp</p>}{resource.data?.data.map((item) => <Link className="listing-card" key={item.id} href={`/detail?id=${item.id}`}><div className="listing-card__image"><img src={item.media?.[0] ? apiUrl(item.media[0].thumbnail_url) : "/images/pet_hotel_building.png"} alt={item.media?.[0] ? item.title : "Ảnh minh họa dịch vụ"} /></div><div className="listing-card__content"><div><div className="listing-card__type">{services[item.service_type]} · {areas[item.area_code]}</div><h2 className="listing-card__name">{item.title}</h2><p>{item.description.slice(0,150)}</p><p className="app-muted">{item.acceptance_rules_json.species.map((value) => species[value]).join(" · ")}</p>{!!item.match_reasons?.length && <p className="app-success">Phù hợp với hồ sơ thú cưng</p>}</div><div className="listing-card__bottom"><span>★ {item.rating_average ?? "Chưa có đánh giá"} ({item.review_count})</span><strong>{money(item.price_vnd)} / {item.price_unit === "HOUR" ? "giờ" : "đêm"}</strong></div>{item.price_total_vnd !== null && <p>Tổng dự kiến: {money(item.price_total_vnd)}</p>}</div></Link>)}{!resource.loading && !resource.error && resource.data?.data.length === 0 && <p className="app-notice">Chưa có dịch vụ phù hợp. Hãy đổi bộ lọc hoặc thời gian tìm kiếm.</p>}<div className="app-actions"><button disabled={page <= 1 || resource.loading} onClick={() => paginate(page - 1)}>Trang trước</button><span>Trang {page} / {resource.data?.meta?.last_page ?? 1}</span><button disabled={page >= (resource.data?.meta?.last_page ?? 1) || resource.loading} onClick={() => paginate(page + 1)}>Trang sau</button></div></section><aside className="app-map"><img src="/images/danang_map.png" alt="Bản đồ minh họa Đà Nẵng" /><p>Bản đồ minh họa khu vực Đà Nẵng</p></aside></main></>;
}

export function Filter({ name, label, choices, value }: { name: string; label: string; choices: Record<string,string>; value?: string | null }) {
  return <label>{label}<select name={name} defaultValue={value ?? ""}><option value="">Tất cả</option>{Object.entries(choices).map(([key,text]) => <option key={key} value={key}>{text}</option>)}</select></label>;
}
