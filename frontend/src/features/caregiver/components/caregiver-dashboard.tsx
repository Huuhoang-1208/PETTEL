"use client";

import { ApiError } from "@/components/ui/api-error";
import { ListingManager } from "@/features/caregiver/components/listing-manager";
import { ProfileForm } from "@/features/caregiver/components/profile-form";
import { write } from "@/lib/api/request";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useState } from "react";

export function CaregiverDashboard() {
  const { user, refresh } = useSession();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const enabled = user?.roles?.some((role) => role.role === "CAREGIVER");
  if (!enabled)
    return (
      <section className="app-card">
        <p>Bật vai trò người chăm để tạo hồ sơ dịch vụ và nhận yêu cầu đặt lịch.</p>
        <ApiError error={error} />
        <button
          className="app-button"
          disabled={busy}
          onClick={async () => {
            setBusy(true);
            try {
              await write("post", "/api/user/roles/caregiver");
              refresh();
            } catch (failure) {
              setError(failure);
            } finally {
              setBusy(false);
            }
          }}
        >
          Trở thành người chăm
        </button>
      </section>
    );
  return (
    <>
      <p>
        <Link href="/bookings">Quản lý đơn nhận chăm sóc →</Link>
      </p>
      <ProfileForm />
      <ListingManager />
    </>
  );
}
