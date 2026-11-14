"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import styles from "@/features/home/styles/footer.module.css";
import Link from "next/link";

export function HomeFooter() {
  return (
    <footer className={styles.footer}>
      <Link href="/" className={styles.footerBrand}>
        <PettelIcon name="paw" /> PETTEL
      </Link>
      <span>Yêu thương bé, an tâm mỗi ngày.</span>
      <Link href="/services">
        Khám phá dịch vụ <PettelIcon name="arrow" />
      </Link>
    </footer>
  );
}
