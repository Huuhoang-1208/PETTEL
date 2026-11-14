"use client";
import { SiteHeader } from "@/components/layout/site-header";
import { HomeAbout } from "@/features/home/components/home-about";
import { HomeCommunity } from "@/features/home/components/home-community";
import { HomeFooter } from "@/features/home/components/home-footer";
import { HomeHero } from "@/features/home/components/home-hero";
import { HomeServices } from "@/features/home/components/home-services";
import styles from "@/features/home/styles/page.module.css";

export function HomePage() {
  return (
    <div className={styles.page}>
      <SiteHeader />
      <main>
        <HomeHero />

        <HomeServices />

        <HomeAbout />

        <HomeCommunity />
      </main>
      <HomeFooter />
    </div>
  );
}
