import { LoginPage } from "@/features/auth/login-page";
import { Suspense } from "react";
export default function Page() {
  return (
    <Suspense fallback={<p>Đang tải...</p>}>
      <LoginPage />
    </Suspense>
  );
}
