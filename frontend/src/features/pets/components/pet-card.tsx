"use client";

/* eslint-disable @next/next/no-img-element -- Pet photos are private media served by the authenticated API. */
import Link from "next/link";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { type Pet } from "@/lib/api/types";
import { sizes, species } from "@/lib/constants";
import { apiUrl } from "@/lib/format";
import styles from "./pet-card.module.css";

export function PetCard({
  pet,
  index,
  busy,
  onEdit,
  onAction,
}: {
  pet: Pet;
  index: number;
  busy: boolean;
  onEdit: (pet: Pet) => void;
  onAction: (pet: Pet, type: "archive" | "activate" | "delete") => Promise<void>;
}) {
  const isDog = pet.species === "DOG";
  const image = pet.photo_media_id
    ? apiUrl(`/api/pets/media/${pet.photo_media_id}/content`)
    : isDog ? "/images/pet_sitter_poodle.png" : "/images/home_with_cats.png";
  const traits = [pet.temperament, pet.special_needs].filter((value): value is string => Boolean(value?.trim()));
  const age = pet.age_note || (pet.birth_date ? new Date(pet.birth_date).toLocaleDateString("vi-VN") : "Chưa cập nhật");

  return (
    <article className={`${styles.card} ${index % 2 ? styles.violet : styles.teal}`}>
      <div className={styles.cardTop} />
      <div className={styles.content}>
        <header className={styles.header}>
          <div className={styles.avatar}>
            <img src={image} alt={pet.photo_media_id ? `Ảnh ${pet.name}` : `Ảnh minh họa ${species[pet.species] ?? "thú cưng"}`} />
            <span className={pet.status === "ACTIVE" ? styles.active : styles.archived}>
              {pet.status === "ACTIVE" ? "Đang hoạt động" : "Đã lưu trữ"}
            </span>
          </div>
          <div className={styles.identity}>
            <div className={styles.nameLine}>
              <h2>{pet.name}</h2>
              <span className={styles.speciesTag}><PettelIcon name={isDog ? "dog" : pet.species === "CAT" ? "cat" : "paw"} /> {species[pet.species] ?? "Thú cưng"}</span>
            </div>
            <p>{pet.breed || (isDog ? "Người bạn đồng hành năng động" : pet.species === "CAT" ? "Người bạn nhỏ dịu dàng" : "Thành viên đáng yêu trong gia đình")}</p>
            <div className={styles.quickFacts}>
              <span><PettelIcon name="clock" /> {age}</span>
              <span><PettelIcon name="check" /> {pet.health_flags?.core_vaccines ? "Đã tiêm vaccine cơ bản" : "Chưa xác nhận tiêm chủng"}</span>
              {pet.microchip_number && <span><PettelIcon name="shield" /> Microchip {pet.microchip_number}</span>}
            </div>
          </div>
          <details className={styles.menu}>
            <summary aria-label={`Tùy chọn hồ sơ ${pet.name}`}>⋮</summary>
            <div className={styles.menuItems}>
              <button disabled={busy} onClick={() => onEdit(pet)}>Chỉnh sửa hồ sơ</button>
              <button disabled={busy} onClick={() => void onAction(pet, pet.status === "ACTIVE" ? "archive" : "activate")}>
                {pet.status === "ACTIVE" ? "Lưu trữ hồ sơ" : "Kích hoạt hồ sơ"}
              </button>
              <button className={styles.delete} disabled={busy} onClick={() => void onAction(pet, "delete")}>Xóa hồ sơ</button>
            </div>
          </details>
        </header>

        <div className={styles.metadata}>
          <div><small>Độ tuổi</small><strong>{age}</strong></div>
          <div><small>Kích cỡ</small><strong>{sizes[pet.size ?? ""] ?? "Chưa cập nhật"}</strong></div>
          <div><small>Cân nặng</small><strong>{pet.weight_kg ? `${pet.weight_kg} kg` : "Chưa cập nhật"}</strong></div>
          <div><small>Giới tính</small><strong>{pet.sex === "MALE" ? "Đực" : pet.sex === "FEMALE" ? "Cái" : "Chưa rõ"}{pet.is_neutered ? " · Đã triệt sản" : ""}</strong></div>
        </div>

        <section className={styles.traits}>
          <h3>ĐẶC ĐIỂM &amp; TÍNH CÁCH</h3>
          {traits.length > 0 ? <div>{traits.map((trait, traitIndex) => <span key={`${trait}-${traitIndex}`}><PettelIcon name={traitIndex === 0 ? "heart" : "shield"} /> {trait}</span>)}</div> : <p>Thêm tính cách và nhu cầu riêng để Sitter hiểu bé hơn.</p>}
        </section>

        <section className={styles.health}>
          <div className={styles.healthTitle}><strong>TÌNH TRẠNG Y TẾ &amp; TIÊM CHỦNG</strong><span><PettelIcon name="shield" /> Thông tin do bạn cung cấp</span></div>
          <p><PettelIcon name={pet.vaccination_note ? "check" : "plus"} /> {pet.vaccination_note || "Chưa có ghi chú tiêm chủng. Bạn có thể bổ sung trong phần chỉnh sửa hồ sơ."}</p>
          {pet.special_needs && <p><PettelIcon name="heart" /> Lưu ý chăm sóc: {pet.special_needs}</p>}
        </section>

        <div className={styles.careGrid}>
          <article><strong><PettelIcon name="paw" /> Chế độ ăn</strong><p>{pet.diet_notes || "Chưa cập nhật chế độ ăn."}</p></article>
          <article><strong><PettelIcon name="walk" /> Ghi chú chăm sóc</strong><p>{pet.special_needs || pet.temperament || "Chia sẻ thói quen và lưu ý của bé với Sitter."}</p></article>
        </div>

        <footer className={styles.actions}>
          <Link className={styles.primary} href={`/services?pet_id=${encodeURIComponent(pet.id)}`}><PettelIcon name="calendar" /> Tìm Sitter cho {pet.name}</Link>
          <button className={styles.edit} type="button" disabled={busy} onClick={() => onEdit(pet)}><PettelIcon name="sliders" /> Chỉnh sửa</button>
          <Link className={styles.journal} href="/bookings"><PettelIcon name="camera" /> Đơn đã đặt</Link>
        </footer>
      </div>
    </article>
  );
}
