"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { PetManager } from "@/features/pets/components/pet-manager";

export function PetsPage() {
  return (
    <>
      <SiteHeader />
      <main className="app-container">
        <h1>Thú cưng của tôi</h1>
        <p>Hồ sơ được lưu trong tài khoản và dùng khi tìm dịch vụ, đặt lịch.</p>
        <RequireSession next="/pet-profile">
          <PetManager />
        </RequireSession>
      </main>
    </>
  );
}
