"use client";

/* eslint-disable @next/next/no-img-element -- Preserve API media and local demo asset URLs. */
import { PettelIcon } from "@/components/ui/pettel-icon";
import { type Listing } from "@/lib/api/types";
import { areas, services } from "@/lib/constants";
import { apiUrl } from "@/lib/format";
import Link from "next/link";
import { useState } from "react";
import styles from "./listing-detail-hero.module.css";

const demoGallery = [
  "/images/pet-sitters/Labrador-retriever-sitting-on-the-couch-next-to-a-man-giving-a-high-five.jpg",
  "/images/home_with_cats.png",
  "/images/hotel_interior.png",
  "/images/miu đang đi dạo.jpg",
  "/images/auth-pet-family.png",
];

export function ListingDetailHero({ listing }: { listing: Listing }) {
  const [saved, setSaved] = useState(false);
  const [showGallery, setShowGallery] = useState(false);
  const [notice, setNotice] = useState("");
  const caregiver = listing.caregiver;
  const area = areas[listing.area_code] ?? "Đà Nẵng";
  const media = listing.media?.map((item) => apiUrl(item.image_url)).filter(Boolean) ?? [];
  const demoPhoto = caregiver?.demo?.photo_file
    ? `/images/pet-sitters/${encodeURIComponent(caregiver.demo.photo_file)}`
    : null;
  const photos = Array.from({ length: 5 }, (_, index) =>
    media[index] ?? (index === 0 && demoPhoto ? demoPhoto : demoGallery[index]),
  );
  const verifiedSteps = caregiver?.demo?.verified_details
    ? Object.values(caregiver.demo.verified_details).filter(Boolean).length
    : 5;
  const title = `${caregiver?.display_name ?? "Người chăm sóc"} • ${listing.title}`;

  async function shareListing() {
    try {
      await navigator.clipboard.writeText(window.location.href);
      setNotice("Đã sao chép liên kết dịch vụ.");
    } catch {
      setNotice("Bạn có thể sao chép địa chỉ trang để chia sẻ.");
    }
  }

  return (
    <section className={styles.hero}>
      <div className={styles.topline}>
        <nav className={styles.breadcrumbs} aria-label="Đường dẫn">
          <Link href="/services">Dịch vụ</Link>
          <span>›</span>
          <span>Đà Nẵng</span>
          <span>›</span>
          <span>{area}</span>
          <span>›</span>
          <strong>{caregiver?.display_name ?? listing.title}</strong>
        </nav>
        <div className={styles.actions}>
          <button type="button" onClick={shareListing}>
            <PettelIcon name="share" /> Chia sẻ
          </button>
          <button
            type="button"
            aria-pressed={saved}
            className={saved ? styles.saved : undefined}
            onClick={() => setSaved((value) => !value)}
          >
            <PettelIcon name="heart" /> {saved ? "Đã lưu hồ sơ" : "Lưu hồ sơ"}
          </button>
        </div>
      </div>

      <div className={styles.heading}>
        <div>
          <div className={styles.badges}>
            {caregiver?.demo?.trust_level === "pro" && (
              <span className={styles.pro}><PettelIcon name="award" /> PETTEL PRO ELITE</span>
            )}
            <span className={styles.verified}>
              <PettelIcon name="shield" /> Đã xác thực {verifiedSteps} bước
            </span>
            <span className={styles.response}>Cách bạn 800m · Phản hồi trong 5 phút</span>
          </div>
          <h1>{title}</h1>
          <p className={styles.location}>
            <PettelIcon name="pin" /> {area}, Đà Nẵng · {services[listing.service_type]}
          </p>
        </div>
        <div className={styles.rating}>
          <PettelIcon name="star" />
          <strong>{listing.rating_average?.toFixed(1) ?? "Mới"}</strong>
          <span />
          <small><b>{listing.review_count}</b> đánh giá<br />94% gửi lại ≥ 3 lần</small>
        </div>
      </div>

      <div className={styles.gallery}>
        {photos.map((photo, index) => (
          <button
            key={`${photo}-${index}`}
            className={`${styles.photo} ${index === 0 ? styles.mainPhoto : ""}`}
            type="button"
            onClick={() => setShowGallery(true)}
            aria-label={`Mở ảnh ${index + 1}`}
          >
            <img
              src={photo}
              alt={`${listing.title} – ảnh ${index + 1}`}
              onError={(event) => {
                event.currentTarget.onerror = null;
                event.currentTarget.src = demoGallery[index] ?? demoGallery[0];
              }}
            />
            {index === 0 && <span className={styles.photoCaption}>PETTEL · {listing.title}</span>}
          </button>
        ))}
        <button className={styles.viewAll} type="button" onClick={() => setShowGallery(true)}>
          <PettelIcon name="camera" /> Xem tất cả {Math.max(photos.length, media.length)} ảnh & clip
        </button>
      </div>
      {notice && <p className={styles.notice} role="status">{notice}</p>}
      {showGallery && (
        <div className={styles.lightbox} role="dialog" aria-modal="true" aria-label="Ảnh dịch vụ">
          <button className={styles.close} type="button" onClick={() => setShowGallery(false)}>
            Đóng
          </button>
          <div className={styles.lightboxGrid}>
            {photos.map((photo, index) => (
              <img
                key={`${photo}-large-${index}`}
                src={photo}
                alt={`${listing.title} – ảnh ${index + 1}`}
                onError={(event) => {
                  event.currentTarget.onerror = null;
                  event.currentTarget.src = demoGallery[index] ?? demoGallery[0];
                }}
              />
            ))}
          </div>
        </div>
      )}
    </section>
  );
}
