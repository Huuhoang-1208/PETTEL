import type { Metadata } from "next";
import { Suspense } from "react";
import { AuthPage } from "@/features/auth/auth-page";

export const metadata: Metadata = {
  title: "Đăng ký tài khoản | PETTEL",
  description: "Tạo tài khoản PETTEL để tìm người chăm và quản lý lịch chăm sóc bé cưng.",
};

export default function Page() {
  return (
    <Suspense fallback={<p>Đang tải...</p>}>
      <AuthPage />
    </Suspense>
  );
}
