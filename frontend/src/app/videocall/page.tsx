import { DemoNotice } from "@/features/demo/components/demo-notice";
import { VideocallView } from "@/features/demo/components/videocall-view";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Gọi video | PETTEL" };

export default function Page() {
  return (
    <>
      <DemoNotice />
      <VideocallView />
    </>
  );
}
