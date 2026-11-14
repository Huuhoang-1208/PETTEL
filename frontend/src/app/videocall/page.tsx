import type { Metadata } from "next";
import { VideocallView } from "@/components/pettel/videocall-view";
import { DemoNotice } from "@/components/pettel/demo-notice";

export const metadata: Metadata = { title: "Gọi video | PETTEL" };

export default function Page() {
  return <><DemoNotice /><VideocallView /></>;
}
