import { DemoNotice } from "@/features/demo/components/demo-notice";
import { LivecamView } from "@/features/demo/components/livecam-view";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Cam trực tiếp | PETTEL" };

export default function Page() {
  return (
    <>
      <DemoNotice />
      <LivecamView />
    </>
  );
}
