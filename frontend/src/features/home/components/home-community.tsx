"use client";

import styles from "@/features/home/styles/community.module.css";
import Image from "next/image";
import { useState } from "react";

const reviews = [
  {
    quote:
      "Mình có công tác Hà Nội 4 ngày và gửi bé Bo (Poodle) ở nhà chị Bảo Trâm tại Hải Châu. Cứ 2 tiếng chị lại gửi video bé ăn hạt và chơi bóng. Về đến bé thơm tho, vui vẻ, không hề bị hoảng loạn như khi ở chuồng khách sạn thú cưng.",
    name: "Nguyễn Thu Thảo",
    pet: "Mẹ của Bé Bodo (Poodle 3 tuổi)",
    avatar: "/images/auth-pet-family.png",
  },
  {
    quote:
      "Bé nhà mình nhát người lạ nên mình chọn gói Ghé thăm tận nhà của PETTEL. Bạn sitter đến đúng giờ, cẩn thận khử khuẩn tay trước khi tiếp xúc, dọn vệ sinh khay cát sạch bóng và chải lông cho các bé. Cực kỳ tin cậy!",
    name: "Trần Hoàng Long",
    pet: "Ba của Mochi & Latte (Mèo Anh)",
    avatar: "/images/home_with_cats.png",
  },
  {
    quote:
      "Tính năng Smart Match gợi ý chính xác bạn sitter có sân vườn cho chú chó Husky nhiều năng lượng của mình. Giá cả minh bạch niêm yết rõ ràng ngay, có cả hợp đồng bảo hiểm điện tử rõ ràng gửi về email.",
    name: "Lê Ngọc Mai",
    pet: "Mẹ của bé Kenji (Husky)",
    avatar: "/images/pet_sitter_poodle.png",
  },
];

export function HomeCommunity() {
  const [offset, setOffset] = useState(0);
  const visibleReviews = reviews.map((_, index) => reviews[(index + offset) % reviews.length]);

  return (
    <section id="cong-dong" className={styles.community} aria-labelledby="community-title">
      <div className={styles.heading}>
        <div>
          <span className={styles.eyebrow}>NIỀM TIN TỪ CỘNG ĐỒNG</span>
          <h2 id="community-title">Các ba mẹ nói gì về PETTEL?</h2>
        </div>
        <div className={styles.controls}>
          <button
            type="button"
            aria-label="Xem đánh giá trước"
            onClick={() => setOffset((current) => (current - 1 + reviews.length) % reviews.length)}
          >
            <span aria-hidden="true">←</span>
          </button>
          <button
            type="button"
            aria-label="Xem đánh giá tiếp theo"
            onClick={() => setOffset((current) => (current + 1) % reviews.length)}
          >
            <span aria-hidden="true">→</span>
          </button>
        </div>
      </div>
      <div className={styles.reviewGrid} aria-live="polite">
        {visibleReviews.map((review) => (
          <article className={styles.review} key={review.name}>
            <div className={styles.stars} aria-label="5 trên 5 sao">
              ★ ★ ★ ★ ★
            </div>
            <blockquote>“{review.quote}”</blockquote>
            <div className={styles.reviewer}>
              <Image src={review.avatar} alt="" width={42} height={42} />
              <div>
                <strong>{review.name}</strong>
                <span>{review.pet}</span>
              </div>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
