"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { PetManager } from "@/features/pets/components/pet-manager";
import styles from "./pets-page.module.css";

export function PetsPage() {
  return (
    <>
      <SiteHeader />
      <main className={styles.page}>
        <RequireSession next="/pet-profile">
          <PetManager />
        </RequireSession>
      </main>
    </>
  );
}
