import type { Metadata } from "next";
import { AdminLoginPage } from "@/features/admin/admin-login-page";

export const metadata: Metadata = {
  title: "Đăng nhập quản trị | PETTEL",
  description: "Đăng nhập cổng quản trị PETTEL dành cho đội ngũ nội bộ.",
};

export default function Page() {
  return <AdminLoginPage />;
}
