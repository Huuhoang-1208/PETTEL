"use client";

import { SitterManagementDashboard } from "@/features/caregiver/components/sitter-management-dashboard";
import { SitterOnboardingWizard } from "@/features/caregiver/components/sitter-onboarding-wizard";
import { ApiError } from "@/components/ui/api-error";
import { useApi } from "@/hooks/use-api";
import { type Profile } from "@/features/caregiver/types";
import axios from "axios";
import { useRouter } from "next/navigation";
import { useSession } from "@/providers/session-provider";

export function CaregiverDashboard() {
  const { user, refresh } = useSession();
  const router = useRouter();
  const enabled = user?.roles?.some((role) => role.role === "CAREGIVER");
  const profile = useApi<{ data: Profile }>(enabled ? "/api/caregiver/profile" : null);
  const finishOnboarding = () => {
    refresh();
    profile.reload();
    router.push("/caregiver/manage");
  };

  if (!enabled)
    return (
      <SitterOnboardingWizard userName={user?.name ?? ""} userEmail={user?.email ?? ""} onComplete={finishOnboarding} />
    );

  if (profile.loading) return <p>Đang kiểm tra hồ sơ Sitter...</p>;

  const profileMissing = axios.isAxiosError(profile.error) && profile.error.response?.status === 404;
  if (profileMissing)
    return (
      <SitterOnboardingWizard
        userName={user?.name ?? ""}
        userEmail={user?.email ?? ""}
        onComplete={finishOnboarding}
      />
    );

  if (profile.error) return <><ApiError error={profile.error} /><button type="button" onClick={profile.reload}>Thử tải lại</button></>;

  return <SitterManagementDashboard profile={profile.data!.data} />;
}
