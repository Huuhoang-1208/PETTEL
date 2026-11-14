import { Suspense } from "react";
import { LiveAuth } from "@/components/pettel/live-auth";
export default function Page() { return <Suspense fallback={<p>Đang tải...</p>}><LiveAuth /></Suspense>; }
