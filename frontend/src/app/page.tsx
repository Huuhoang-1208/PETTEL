import { HomePage } from "@/features/home/home-page";
import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "PETTEL — An tâm gửi gắm bé cưng",
  description:
    "Tìm người chăm sóc tận tâm gần bạn. Khám phá dịch vụ, đặt lịch và theo dõi nhật ký chăm sóc thú cưng cùng PETTEL.",
};

export default function Page() {
  return <HomePage />;
}
