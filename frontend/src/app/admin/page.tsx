import { AdminPage } from "@/features/admin/admin-page";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Admin Portal | PETTEL",
  description: "Bảng điều khiển vận hành và kiểm duyệt PETTEL.",
};

export default function Page() {
  return <AdminPage />;
}
