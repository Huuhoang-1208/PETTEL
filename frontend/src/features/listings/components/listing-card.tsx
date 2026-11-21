"use client";

import { ListingPhoto } from "@/components/ui/listing-photo";
import { type Listing } from "@/lib/api/types";
import { areas, services, species } from "@/lib/constants";
import { apiUrl, money } from "@/lib/format";
import { PettelIcon } from "@/components/ui/pettel-icon";
import Link from "next/link";

const smartMatchLabels: Record<string, string> = {
  SPECIES_ACCEPTED: "Nhận loài của bé",
  SIZE_ACCEPTED: "Nhận kích thước của bé",
  DOG_SIZE_EXPERIENCE: "Có kinh nghiệm với chó cùng kích thước",
  SMALL_PET_EXPERIENCE: "Có kinh nghiệm với thú cưng nhỏ",
  CAT_BREED_EXPERIENCE: "Có kinh nghiệm với giống mèo này",
  SHY_PET_CARE: "Có kỹ năng chăm bé nhút nhát",
  MEDICATION_CARE: "Có kỹ năng cho uống thuốc / nhỏ mắt",
  SPECIAL_DIET_CARE: "Có kinh nghiệm với chế độ ăn riêng",
  POST_SURGERY_CARE: "Có kỹ năng chăm sóc sau phẫu thuật",
  SEPARATE_FOOD_STORAGE: "Có chỗ bảo quản thức ăn riêng",
  INDOOR_SPACE: "Không gian trong nhà phù hợp",
  INDOOR_YARD_SPACE: "Không gian trong nhà có sân",
};

export function ListingCard({ item }: { item: Listing }) {
  const demo = item.caregiver?.demo;
  const sitterName = item.caregiver?.display_name ?? item.title;
  const homeType =
    demo?.home_type ?? (item.caregiver?.type === "FACILITY" ? "Khách sạn thú cưng" : "Nhà riêng");
  const demoPhoto = demo?.photo_file
    ? `/images/pet-sitters/${encodeURIComponent(demo.photo_file)}`
    : null;
  return (
    <Link className="listing-card" key={item.id} href={`/detail?id=${item.id}`}>
      <div className="listing-card__image">
        <ListingPhoto
          sizes="(max-width: 700px) 100vw, 300px"
          src={
            demoPhoto ??
            (item.media?.[0]
              ? apiUrl(item.media[0].thumbnail_url)
              : "/images/pet_hotel_building.png")
          }
          alt={demoPhoto ? sitterName : item.media?.[0] ? item.title : "Ảnh minh họa dịch vụ"}
        />
        {demo?.is_demo && <span className="listing-card__demo">Hồ sơ demo</span>}
        {demo?.confidence_score != null && (
          <span className="listing-card__score">Score {demo.confidence_score}</span>
        )}
        <span className="listing-card__service">{services[item.service_type]}</span>
      </div>
      <div className="listing-card__content">
        <div>
          <div className="listing-card__type">
            {homeType} · {areas[item.area_code]}
          </div>
          <h2 className="listing-card__name">
            {sitterName}
            {demo?.trust_level === "pro" && (
              <span className="listing-card__pro">
                <PettelIcon name="award" /> PETTEL Pro
              </span>
            )}
          </h2>
          <span className="listing-card__favorite" aria-hidden="true">
            <PettelIcon name="heart" />
          </span>
          {demo?.status && <p className="listing-card__status">{demo.status}</p>}
          <p className="listing-card__amenities">
            {demo?.amenities?.slice(0, 2).join(" · ") || item.description.slice(0, 150)}
          </p>
          <p className="listing-card__pets">
            {item.acceptance_rules_json.species.map((value) => (
              <span key={value}>
                <PettelIcon name={value === "DOG" ? "dog" : value === "CAT" ? "cat" : "paw"} />
                {species[value]}
              </span>
            ))}
          </p>
          {item.smart_match && (
            <div className="listing-card__smart-match" aria-label="Lý do được Smart Match gợi ý">
              <strong>
                <span aria-hidden="true">✦</span> Gợi ý phù hợp với bé
              </strong>
              <div>
                {item.smart_match.reasons.map((reason) => (
                  <span key={reason}>{smartMatchLabels[reason] ?? reason}</span>
                ))}
              </div>
            </div>
          )}
        </div>
        <div className="listing-card__bottom">
          <span className="listing-card__rating">
            <span className="star">★</span> {item.rating_average ?? "Chưa có đánh giá"}
            <span className="count">({item.review_count} đánh giá)</span>
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
