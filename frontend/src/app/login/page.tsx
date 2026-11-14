import { AuthPage } from "@/features/auth/auth-page";
import { Suspense } from "react";
export default function Page() {
  return (
    <Suspense fallback={<p>Đang tải...</p>}>
      <AuthPage />
    </Suspense>
  );
}
