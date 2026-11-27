/* eslint-disable @next/next/no-img-element -- Private pet photos and existing fallback illustrations. */
import { PettelIcon } from "@/components/ui/pettel-icon";
import type { Booking, Pet } from "@/lib/api/types";
import { species } from "@/lib/constants";
import { apiUrl } from "@/lib/format";
import Link from "next/link";
import styles from "../owner-profile.module.css";

export function OwnerPetCard({
  pet,
  bookings,
  onEdit,
}: {
  pet: Pet;
  bookings: Booking[];
  onEdit: (pet: Pet) => void;
}) {
  const upcoming = bookings.find(
    (booking) =>
      booking.pet_id === pet.id &&
      ["REQUESTED", "AWAITING_PAYMENT", "CONFIRMED", "IN_PROGRESS"].includes(booking.status),
  );
  const image = pet.photo_media_id
    ? apiUrl(`/api/v1/pets/media/${pet.photo_media_id}/content`)
    : pet.species === "DOG"
      ? "/images/pet_sitter_poodle.png"
      : "/images/home_with_cats.png";
  return (
    <article className={styles.petCard}>
      <div className={styles.petPhoto}>
        <img
          src={image}
          alt={pet.photo_media_id ? `Ảnh ${pet.name}` : `Ảnh minh họa ${species[pet.species]}`}
        />
        <span>
          <PettelIcon
            name={pet.species === "DOG" ? "dog" : pet.species === "CAT" ? "cat" : "paw"}
          />
          {species[pet.species]} cưng
        </span>
      </div>
      <div className={styles.petTitle}>
        <h3>{pet.name}</h3>
        {pet.breed && <span>{pet.breed}</span>}
      </div>
      <p className={styles.petFacts}>
        {pet.age_note || "Chưa cập nhật tuổi"} •{" "}
        {pet.sex === "MALE" ? "Đực" : pet.sex === "FEMALE" ? "Cái" : "Chưa rõ giới tính"}
        {pet.is_neutered && " (Đã triệt sản)"}
        {pet.weight_kg ? ` • ${Number(pet.weight_kg).toLocaleString("vi-VN")}kg` : ""}
      </p>
      <div className={styles.petBadges}>
        <span>
          <PettelIcon name="shield" />
          {pet.health_flags?.core_vaccines ? "Đã cập nhật vaccine" : "Chưa xác nhận tiêm chủng"}
        </span>
        {pet.temperament && (
          <span>
            <PettelIcon name="heart" />
            {pet.temperament}
          </span>
        )}
      </div>
      <div className={styles.petHabit}>
        <strong>Thói quen quan trọng:</strong>
        <p>
          {pet.diet_notes ||
            pet.special_needs ||
            "Bổ sung chế độ ăn và lưu ý để người chăm hiểu bé hơn."}
        </p>
      </div>
      <div className={`${styles.petStatus} ${upcoming ? styles.upcoming : ""}`}>
        <PettelIcon name={upcoming ? "calendar" : "home"} />
        {upcoming
          ? `Lịch chăm sóc: ${new Date(upcoming.start_at).toLocaleDateString("vi-VN", { timeZone: "Asia/Ho_Chi_Minh" })}`
          : pet.status === "ACTIVE"
            ? "Hồ sơ đang hoạt động"
            : "Hồ sơ đã lưu trữ"}
      </div>
      <footer>
        <button type="button" onClick={() => onEdit(pet)}>
          Xem hồ sơ chi tiết <PettelIcon name="arrow" />
        </button>
        <button
          type="button"
          className={styles.roundButton}
          aria-label={`Chỉnh sửa hồ sơ ${pet.name}`}
          onClick={() => onEdit(pet)}
        >
          <PettelIcon name="sliders" />
        </button>
      </footer>
      {upcoming && (
        <Link className={styles.petBookingLink} href="/owner-profile#history">
          Xem đơn chăm sóc →
        </Link>
      )}
    </article>
  );
}
