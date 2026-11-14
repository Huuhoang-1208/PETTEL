"use client";

/* eslint-disable @next/next/no-img-element -- Preserve existing image rendering. */
import { type Listing } from "@/lib/api/types";
import { areas, services, species } from "@/lib/constants";
import { apiUrl, money } from "@/lib/format";
import Link from "next/link";

export function ListingCard({ item }: { item: Listing }) {
  return (
    <Link className="listing-card" key={item.id} href={`/detail?id=${item.id}`}>
      <div className="listing-card__image">
        <img
          src={
            item.media?.[0] ? apiUrl(item.media[0].thumbnail_url) : "/images/pet_hotel_building.png"
          }
          alt={item.media?.[0] ? item.title : "Ảnh minh họa dịch vụ"}
        />
      </div>
      <div className="listing-card__content">
        <div>
          <div className="listing-card__type">
            {services[item.service_type]} · {areas[item.area_code]}
          </div>
          <h2 className="listing-card__name">{item.title}</h2>
          <p>{item.description.slice(0, 150)}</p>
          <p className="app-muted">
            {item.acceptance_rules_json.species.map((value) => species[value]).join(" · ")}
          </p>
          {!!item.match_reasons?.length && (
            <p className="app-success">Phù hợp với hồ sơ thú cưng</p>
          )}
        </div>
        <div className="listing-card__bottom">
          <span>
            ★ {item.rating_average ?? "Chưa có đánh giá"} ({item.review_count})
          </span>
          <strong>
            {money(item.price_vnd)} / {item.price_unit === "HOUR" ? "giờ" : "đêm"}
          </strong>
        </div>
        {item.price_total_vnd !== null && <p>Tổng dự kiến: {money(item.price_total_vnd)}</p>}
      </div>
    </Link>
  );
}
