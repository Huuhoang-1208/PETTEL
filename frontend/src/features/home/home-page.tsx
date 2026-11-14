"use client";
import { SiteHeader } from "@/components/layout/site-header";
import { HomeAbout } from "@/features/home/components/home-about";
import { HomeCaregiverCta } from "@/features/home/components/home-caregiver-cta";
import { HomeCommunity } from "@/features/home/components/home-community";
import { HomeHero } from "@/features/home/components/home-hero";
import { HomeListings } from "@/features/home/components/home-listings";
import { HomeServices } from "@/features/home/components/home-services";
import styles from "@/features/home/styles/page.module.css";

export function HomePage() {
  return (
    <div className={styles.page}>
      <SiteHeader />
      <main>
        <HomeHero />

        <HomeServices />

        <HomeListings />

        <HomeAbout />

        <HomeCommunity />

        <HomeCaregiverCta />
      </main>
    </div>
  );
}
