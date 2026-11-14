import { Suspense } from "react";
import { LiveResetPassword } from "@/components/pettel/live-reset-password";
export default function Page() { return <Suspense fallback={<p>Đang tải...</p>}><LiveResetPassword /></Suspense>; }
