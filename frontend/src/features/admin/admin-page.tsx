"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { ModerationDashboard } from "@/features/admin/components/moderation-dashboard";
import { RequireSession } from "@/features/auth/components/require-session";

export function AdminPage() {
  return (
    <>
      <SiteHeader />
      <main className="app-container">
        <h1>Quản trị dịch vụ</h1>
        <RequireSession next="/admin">
          <ModerationDashboard />
        </RequireSession>
      </main>
    </>
  );
}
