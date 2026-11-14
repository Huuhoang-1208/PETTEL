"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import styles from "@/features/home/styles/community.module.css";
import Link from "next/link";

export function HomeCommunity() {
  return (
    <section id="cong-dong" className={styles.community} aria-labelledby="community-title">
      <span className={styles.communityPaw}>
        <PettelIcon name="paw" />
      </span>
      <div>
        <span className={styles.eyebrow}>CÙNG NHAU CHĂM SÓC TỐT HƠN</span>
        <h2 id="community-title">Một cộng đồng, chung tình yêu thú cưng.</h2>
        <p>
          Bạn có kinh nghiệm chăm sóc và một không gian dành cho các bé? Kết nối với những người
          nuôi thú cưng cùng PETTEL.
        </p>
      </div>
      <Link href="/caregiver" className={styles.communityButton}>
        Trở thành Sitter <PettelIcon name="arrow" />
      </Link>
    </section>
  );
}
