import type { Metadata } from "next";
import { LivecamView } from "@/components/pettel/livecam-view";
import { DemoNotice } from "@/components/pettel/demo-notice";

export const metadata: Metadata = { title: "Cam trực tiếp | PETTEL" };

export default function Page() {
  return <><DemoNotice /><LivecamView /></>;
}
