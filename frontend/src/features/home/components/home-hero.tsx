"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import { HomeSearch } from "@/features/home/components/home-search";
import styles from "@/features/home/styles/hero.module.css";

export function HomeHero() {
  return (
    <section className={styles.hero} aria-labelledby="home-title">
      <div className={styles.heroContent}>
        <div className={styles.assurance}>
          <PettelIcon name="shield" />
          <strong>Nền tảng chăm sóc thú cưng</strong>
          <span className={styles.assuranceDot}>•</span>
          <span>Đồng hành cùng bé mỗi ngày</span>
        </div>
        <h1 id="home-title" className={styles.title}>
          Yên tâm gửi gắm bé cưng cho
          <br className={styles.titleBreak} />{" "}
          <span className={styles.highlight}>người chăm sóc tận tâm</span> gần bạn
        </h1>
        <p className={styles.subtitle}>
          Kết nối với người chăm sóc thú cưng tại Đà Nẵng. Tìm dịch vụ phù hợp,
          <br className={styles.subtitleBreak} />
          đặt lịch và theo dõi nhật ký chăm sóc của bé mỗi ngày.
        </p>
        <HomeSearch />
        <ul className={styles.trustStrip} aria-label="Trải nghiệm cùng PETTEL">
          <li>
            <PettelIcon name="star" />
            <span>Đánh giá từ đơn đã hoàn thành</span>
          </li>
          <li>
            <PettelIcon name="shield" />
            <span>Thông tin đặt lịch minh bạch</span>
          </li>
          <li>
            <PettelIcon name="camera" />
            <span>Cập nhật nhật ký & hình ảnh</span>
          </li>
          <li>
            <PettelIcon name="heart" />
            <span>Đồng hành cùng người chăm</span>
          </li>
        </ul>
      </div>
    </section>
  );
}
