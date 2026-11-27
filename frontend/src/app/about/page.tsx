import type { Metadata } from "next";
import { AboutPage } from "@/features/about/about-page";

export const metadata: Metadata = {
  title: "Về PETTEL | Câu chuyện của chúng tôi",
  description:
    "Câu chuyện, sứ mệnh và những giá trị của PETTEL — nơi thú cưng được chăm sóc chu đáo như tại chính ngôi nhà của mình.",
};

export default function Page() {
  return <AboutPage />;
}
