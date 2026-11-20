"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { HomeListingCard } from "@/features/home/components/home-listing-card";
import styles from "@/features/home/styles/home-listings.module.css";
import { useApi } from "@/hooks/use-api";
import type { Listing, Page } from "@/lib/api/types";
import Image from "next/image";
import Link from "next/link";

const filters = [
  { label: "Tất cả", href: "/services", active: true },
  { label: "Trung tâm Đà Nẵng", href: "/services?area_code=DN_CENTER" },
  { label: "Gửi gắm", href: "/services?service_type=BOARDING" },
  { label: "Chăm ban ngày", href: "/services?service_type=DAYCARE" },
];

export function HomeListings() {
  const resource = useApi<Page<Listing>>("/api/v1/listings?per_page=3");
  const count = resource.data?.meta?.total ?? resource.data?.data.length ?? 0;

  return (
    <section className={styles.section} aria-labelledby="home-listings-title">
      <div className={styles.container}>
        <div className={styles.heading}>
          <div>
            <p className={styles.eyebrow}>● Khu vực Đà Nẵng</p>
            <h2 id="home-listings-title">
              {resource.loading ? "Sitter uy tín" : `${count}+ Sitter uy tín`} sẵn sàng nhận chăm
              sóc
            </h2>
          </div>
          <Link className={styles.smartMatch} href="/services">
            ✨ PETTEL Smart Match™
          </Link>
        </div>

        <nav className={styles.filters} aria-label="Lọc dịch vụ nổi bật">
          {filters.map((filter) => (
            <Link
              className={filter.active ? styles.activeFilter : styles.filter}
              href={filter.href}
              key={filter.label}
            >
              {filter.label}
            </Link>
          ))}
          <Link className={styles.moreFilters} href="/services" aria-label="Tất cả bộ lọc">
            <PettelIcon name="sliders" />
          </Link>
        </nav>

        <div className={styles.discovery}>
          <div className={styles.results}>
            {resource.loading && (
              <p className={styles.notice} role="status">
                Đang tìm sitter phù hợp...
              </p>
            )}
            <ApiError error={resource.error} />
            {resource.data?.data.map((item) => (
              <HomeListingCard key={item.id} item={item} />
            ))}
            {!resource.loading && !resource.error && resource.data?.data.length === 0 && (
              <p className={styles.notice}>Chưa có dịch vụ phù hợp ở khu vực này.</p>
            )}
            <Link className={styles.allListings} href="/services">
              Xem tất cả dịch vụ <span aria-hidden="true">→</span>
            </Link>
          </div>

          <aside className={styles.map} aria-label="Bản đồ khu vực dịch vụ Đà Nẵng">
            <Image
              src="/images/danang_map.png"
              alt="Bản đồ các dịch vụ chăm sóc thú cưng tại Đà Nẵng"
              fill
              sizes="(max-width: 800px) 100vw, 40vw"
            />
            <div className={styles.mapLabel}>
              <span className={styles.mapDot} />
              <span>Hải Châu, Đà Nẵng · {count} địa điểm</span>
            </div>
            <Link className={styles.mapAction} href="/services">
              Mở danh sách dịch vụ <span aria-hidden="true">↗</span>
            </Link>
          </aside>
        </div>
      </div>
    </section>
  );
}
