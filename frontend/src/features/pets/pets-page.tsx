"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { useSession } from "@/providers/session-provider";
import { PetManager } from "@/features/pets/components/pet-manager";
import { GuestPetProfile } from "@/features/pets/guest-pet-profile";
import styles from "./pets-page.module.css";

export function PetsPage() {
  const { user, loading } = useSession();

  return (
    <>
      <SiteHeader />
      {loading ? (
        <main className={styles.page}>
          <p className={styles.loading} role="status">
            Đang tải hồ sơ thú cưng…
          </p>
        </main>
      ) : user ? (
        <main className={styles.page}>
          <PetManager />
        </main>
      ) : (
        <GuestPetProfile />
      )}
    </>
  );
}
