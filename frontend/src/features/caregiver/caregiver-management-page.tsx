"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { CaregiverDashboard } from "@/features/caregiver/components/caregiver-dashboard";
import styles from "./sitter-management.module.css";

export function CaregiverManagementPage() {
  return (
    <>
      <SiteHeader />
      <main className={styles.page}>
        <RequireSession next="/caregiver/manage" redirectToLogin>
          <CaregiverDashboard />
        </RequireSession>
      </main>
    </>
  );
}
