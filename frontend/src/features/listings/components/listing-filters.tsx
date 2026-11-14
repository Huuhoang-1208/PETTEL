"use client";

import { SelectFilter } from "@/components/ui/select-filter";
import type { User } from "@/lib/api/types";
import { type Page, type Pet } from "@/lib/api/types";
import { areas, services, sizes, species } from "@/lib/constants";
import { localDate } from "@/lib/date";
import Link from "next/link";
import { type FormEvent } from "react";
export function ListingFilters({
  query,
  pets,
  user,
  search,
}: {
  query: Pick<URLSearchParams, "get" | "toString">;
  pets: { data?: Page<Pet> };
  user: User | null;
  search: (event: FormEvent<HTMLFormElement>) => void;
}) {
  return (
    <form className="app-filter-form" onSubmit={search} key={query.toString()}>
      <label>
        Tìm kiếm
        <input name="q" defaultValue={query.get("q") ?? ""} placeholder="Tên dịch vụ, mô tả..." />
      </label>
      <SelectFilter
        name="service_type"
        label="Dịch vụ"
        choices={services}
        value={query.get("service_type")}
      />
      <SelectFilter
        name="area_code"
        label="Khu vực"
        choices={areas}
        value={query.get("area_code")}
      />
      <SelectFilter name="species" label="Loài" choices={species} value={query.get("species")} />
      <SelectFilter name="size" label="Kích thước" choices={sizes} value={query.get("size")} />
      <label>
        Smart Match
        <select name="pet_id" defaultValue={query.get("pet_id") ?? ""} disabled={!user}>
          <option value="">{user ? "Chọn thú cưng của tôi" : "Đăng nhập để dùng"}</option>
          {pets.data?.data
            .filter((pet) => pet.status === "ACTIVE")
            .map((pet) => (
              <option key={pet.id} value={pet.id}>
                {pet.name}
              </option>
            ))}
        </select>
      </label>
      <label>
        Bắt đầu (giờ Việt Nam)
        <input
          type="datetime-local"
          name="start_at"
          defaultValue={query.get("start_at") ? localDate(new Date(query.get("start_at")!)) : ""}
        />
      </label>
      <label>
        Kết thúc (giờ Việt Nam)
        <input
          type="datetime-local"
          name="end_at"
          defaultValue={query.get("end_at") ? localDate(new Date(query.get("end_at")!)) : ""}
        />
      </label>
      <label>
        Tổng giá từ (VND)
        <input
          type="number"
          name="min_price_vnd"
          min="0"
          defaultValue={query.get("min_price_vnd") ?? ""}
        />
      </label>
      <label>
        Tổng giá đến (VND)
        <input
          type="number"
          name="max_price_vnd"
          min="0"
          defaultValue={query.get("max_price_vnd") ?? ""}
        />
      </label>
      <div className="app-actions">
        <button className="app-button">Tìm kiếm</button>
        <Link href="/services">Xóa bộ lọc</Link>
      </div>
    </form>
  );
}
