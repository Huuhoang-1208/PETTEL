import type { Metadata } from "next";
import { OwnerProfilePage } from "@/features/owner/owner-profile-page";

export const metadata: Metadata = { title: "Hồ sơ chủ nuôi | PETTEL" };

export default function Page() {
  return <OwnerProfilePage />;
}
