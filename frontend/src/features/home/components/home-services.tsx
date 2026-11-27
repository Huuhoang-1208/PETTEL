import { PettelIcon, type PettelIconName } from "@/components/ui/pettel-icon";
import styles from "@/features/home/styles/home-services.module.css";
import Link from "next/link";

const services: {
  title: string;
  description: string;
  price: string;
  icon: PettelIconName;
  tone: string;
  href?: string;
}[] = [
  {
    title: "Gửi gắm qua đêm",
    description:
      "Bé ở lại ngôi nhà ấm cúng của Sitter, được chăm sóc cá nhân hoá như một thành viên trong gia đình.",
    price: "Từ 200.000đ/đêm",
    icon: "home",
    tone: "cyan",
    href: "/services?service_type=BOARDING",
  },
  {
    title: "Chăm sóc tại gia",
    description:
      "Sitter tới ở hoặc chăm sóc tại căn hộ của bạn, giúp bé giữ nguyên nhịp sống quen thuộc không bị stress.",
    price: "Từ 250.000đ/ngày",
    icon: "pin",
    tone: "orange",
  },
  {
    title: "Dắt cún đi dạo",
    description:
      "Các buổi vận động 30 đến 60 phút có lộ trình GPS trực tiếp, nước uống và vệ sinh cẩn thận dọc đường.",
    price: "Từ 80.000đ/buổi",
    icon: "walk",
    tone: "purple",
  },
  {
    title: "Ghé thăm định kỳ",
    description:
      "Ghé thăm 30–45 phút: cho ăn theo khẩu phần, thay nước, dọn khay cát cho mèo, chải lông và ôm ấp.",
    price: "Từ 95.000đ/lần",
    icon: "clock",
    tone: "blue",
  },
];

export function HomeServices() {
  return (
    <section
      className="bg-[#faf8ff] px-4 pt-7 pb-[30px] sm:px-6"
      aria-labelledby="home-services-title"
    >
      <div className="mx-auto max-w-[1120px]">
        <div className="mb-5 flex flex-col gap-2.5 sm:mb-6 sm:flex-row sm:items-start sm:justify-between sm:gap-5 lg:items-center lg:gap-9">
          <div>
            <p className="mb-[7px] text-[11px] leading-[1.4] font-bold tracking-[0.35px] text-[#007b91]">
              HỆ SINH THÁI TẬN TÂM
            </p>
            <h2
              id="home-services-title"
              className="text-[22px] leading-[1.4] font-extrabold tracking-[-0.65px] text-[#102139] sm:text-[clamp(20px,2vw,25px)]"
            >
              Dịch vụ phù hợp với từng thói quen của pet
            </h2>
          </div>
          <p className="max-w-none text-[12px] leading-[1.6] text-[#45617a] sm:max-w-[300px] lg:max-w-[400px]">
            Linh hoạt theo nhu cầu: từ kỳ nghỉ dài ngày đến vài vòng dạo công viên trong tuần.
          </p>
        </div>
        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
          {services.map((service) => {
            const content = (
              <>
                <div className="mb-4 flex items-center justify-between gap-2">
                  <span className={`${styles.icon} ${styles[service.tone]}`}>
                    <PettelIcon name={service.icon} />
                  </span>
                </div>
                <h3 className="mb-1.5 text-[16px] leading-[1.4] font-extrabold tracking-[-0.4px]">
                  {service.title}
                </h3>
                <p className="mb-[22px] text-[11px] leading-[1.65] text-[#45617a] max-[540px]:text-[12px]">
                  {service.description}
                </p>
                <div
                  className={`mt-auto flex items-center justify-between gap-3 text-[11px] leading-[1.5] font-semibold max-[540px]:text-[12px] ${styles.cardFooter} ${styles[service.tone]}`}
                >
                  <span>{service.price}</span>
                  <PettelIcon name="arrow" />
                </div>
              </>
            );
            return service.href ? (
              <Link
                key={service.title}
                href={service.href}
                className="flex min-h-[220px] min-w-0 flex-col bg-white p-[22px] text-[#102139] no-underline shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-[0_8px_22px_#193b4b12] focus-visible:outline-2 focus-visible:outline-offset-[3px] focus-visible:outline-[#89ccd5] motion-reduce:transition-none sm:min-h-[224px]"
                aria-label={`${service.title} — tìm dịch vụ`}
              >
                {content}
              </Link>
            ) : (
              <article
                key={service.title}
                className="flex min-h-[220px] min-w-0 flex-col bg-white p-[22px] text-[#102139] shadow-sm sm:min-h-[224px]"
              >
                {content}
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
