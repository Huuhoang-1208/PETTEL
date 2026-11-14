"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { CaregiverDashboard } from "@/features/caregiver/components/caregiver-dashboard";

export function CaregiverPage() {
  return (
    <>
      <SiteHeader />
      <main className="app-container">
        <h1>Dành cho người chăm thú cưng</h1>
        <RequireSession next="/caregiver">
          <CaregiverDashboard />
        </RequireSession>
      </main>
    </>
  );
}
