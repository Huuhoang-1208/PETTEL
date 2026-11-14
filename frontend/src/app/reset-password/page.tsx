import { ResetPasswordPage } from "@/features/auth/reset-password-page";
import { Suspense } from "react";
export default function Page() {
  return (
    <Suspense fallback={<p>Đang tải...</p>}>
      <ResetPasswordPage />
    </Suspense>
  );
}
