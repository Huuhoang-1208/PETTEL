"use client";

import Link from "next/link";
import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { useConfirmation } from "@/components/ui/confirmation-dialog";
import { PetCard } from "@/features/pets/components/pet-card";
import { PetEditor } from "@/features/pets/components/pet-editor";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Page, type Pet } from "@/lib/api/types";
import { useSession } from "@/providers/session-provider";
import { useState, type FormEvent } from "react";
import styles from "./pet-manager.module.css";

const benefits = [
  {
    icon: "star" as const,
    title: "Ghép đôi thông minh (Smart Match)",
    text: "Thông tin loài, kích thước và tính cách giúp gợi ý người chăm phù hợp hơn.",
    accent: "purple",
  },
  {
    icon: "shield" as const,
    title: "PETTEL Care khi đặt dịch vụ",
    text: "Xem quyền lợi bảo vệ và hỗ trợ áp dụng cho từng đơn trước khi xác nhận.",
    accent: "teal",
  },
  {
    icon: "camera" as const,
    title: "Cập nhật ảnh & nhật ký",
    text: "Trao đổi với Sitter để theo dõi ảnh và cập nhật trong thời gian chăm sóc.",
    accent: "orange",
  },
];

export function PetManager() {
  const { user } = useSession();
  const { ask, dialog } = useConfirmation();
  const resource = useApi<Page<Pet>>(user ? "/api/pets" : null);
  const pets = resource.data?.data ?? [];
  const [editing, setEditing] = useState<Pet | null | undefined>();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload: Record<string, unknown> = Object.fromEntries(form);
    const photo = form.get("photo");
    const vaccinationRecordPhoto = form.get("vaccination_record_photo");
    delete payload.photo;
    delete payload.vaccination_record_photo;
    delete payload["behavior_tags[]"];
    delete payload["care_support_tags[]"];
    const vaccinationStatuses = Object.fromEntries(
      ["core_vaccines", "rabies_vaccine", "deworming"].map((key) => [
        key,
        form.get(`vaccination_statuses.${key}`) || "UNKNOWN",
      ]),
    );
    for (const key of Object.keys(payload))
      if (key.startsWith("vaccination_statuses.")) delete payload[key];
    payload.vaccination_statuses = vaccinationStatuses;
    payload.behavior_tags = form.getAll("behavior_tags[]");
    payload.care_support_tags = form.getAll("care_support_tags[]");
    payload.species_detail = form.get("species") === "OTHER" ? form.get("species_detail") : null;
    payload.health_flags = Object.fromEntries(
      Object.entries(vaccinationStatuses).map(([key, value]) => [key, value === "DONE"]),
    );
    setBusy(true);
    setError(undefined);
    try {
      if (photo instanceof File && photo.size > 0) {
        const photoData = new FormData();
        photoData.append("file", photo);
        const uploaded = await write<{ data: { id: string } }>(
          "post",
          "/api/pets/media",
          photoData,
        );
        payload.photo_media_id = uploaded.data.id;
      } else if (editing?.photo_media_id) {
        payload.photo_media_id = editing.photo_media_id;
      }
      if (vaccinationRecordPhoto instanceof File && vaccinationRecordPhoto.size > 0) {
        const photoData = new FormData();
        photoData.append("file", vaccinationRecordPhoto);
        const uploaded = await write<{ data: { id: string } }>(
          "post",
          "/api/pets/media",
          photoData,
        );
        payload.vaccination_record_media_id = uploaded.data.id;
      } else if (editing?.vaccination_record_media_id) {
        payload.vaccination_record_media_id = editing.vaccination_record_media_id;
      }
      await write(
        editing ? "patch" : "post",
        editing ? `/api/pets/${editing.id}` : "/api/pets",
        payload,
      );
      setEditing(undefined);
      resource.reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  async function action(pet: Pet, type: "archive" | "activate" | "delete") {
    if (type === "delete" && !(await ask(`Xóa hồ sơ ${pet.name}?`))) return;
    setBusy(true);
    setError(undefined);
    try {
      await write(
        type === "delete" ? "delete" : "patch",
        `/api/pets/${pet.id}${type === "delete" ? "" : `/${type}`}`,
      );
      resource.reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  const activeCount = pets.filter((pet) => pet.status === "ACTIVE").length;
  const vaccinationCount = pets.filter(
    (pet) =>
      pet.vaccination_statuses?.core_vaccines === "DONE" ||
      Boolean(pet.health_flags?.core_vaccines) ||
      Boolean(pet.vaccination_record_media_id),
  ).length;
  const matchCount = pets.filter((pet) =>
    Boolean(pet.species && pet.size && (pet.temperament?.trim() || pet.behavior_tags?.length)),
  ).length;

  return (
    <>
      {dialog}
      <div className={styles.breadcrumb}>
        <Link href="/">Trang chủ</Link>
        <span>/</span>
        <span>Tài khoản</span>
        <span>/</span>
        <strong>Hồ sơ thú cưng</strong>
      </div>

      <section className={styles.intro}>
        <div>
          <div className={styles.titleLine}>
            <h1>Hồ sơ thú cưng của tôi</h1>
            <span className={styles.countBadge}>{pets.length} bé yêu</span>
          </div>
          <p>
            Thông tin của bé được lưu an toàn để giúp bạn tìm Sitter phù hợp và đặt lịch nhanh hơn.
          </p>
        </div>
        <button
          className={styles.addButton}
          type="button"
          onClick={() => {
            setEditing(null);
            setError(undefined);
          }}
        >
          <PettelIcon name="plus" /> Thêm hồ sơ thú cưng mới
        </button>
      </section>

      <ApiError error={resource.error ?? error} />

      <section className={styles.metrics} aria-label="Tổng quan hồ sơ thú cưng">
        <Metric icon="paw" label="Thú cưng trong hồ sơ" value={`${pets.length} bé`} tone="teal" />
        <Metric
          icon="shield"
          label="Sổ tiêm chủng"
          value={`${vaccinationCount}/${pets.length} đã cập nhật`}
          tone="orange"
        />
        <Metric
          icon="star"
          label="Hồ sơ Smart Match"
          value={`${matchCount}/${pets.length} đủ thông tin`}
          tone="purple"
        />
        <Metric icon="heart" label="Đang hoạt động" value={`${activeCount} bé`} tone="blue" />
      </section>

      {resource.loading && (
        <p className={styles.loading} role="status">
          Đang tải hồ sơ thú cưng...
        </p>
      )}
      {!resource.loading && pets.length > 0 && (
        <section className={styles.petGrid} aria-label="Danh sách thú cưng">
          {pets.map((pet, index) => (
            <PetCard
              key={pet.id}
              pet={pet}
              index={index}
              busy={busy}
              onEdit={setEditing}
              onAction={action}
            />
          ))}
        </section>
      )}

      {!resource.loading && !resource.error && pets.length === 0 && (
        <section className={styles.emptyState}>
          <span className={styles.emptyIcon}>
            <PettelIcon name="paw" />
          </span>
          <h2>Bạn muốn đón thêm một thiên thần lông xù?</h2>
          <p>Tạo hồ sơ riêng để PETTEL gợi ý Sitter phù hợp và giúp bạn đặt lịch thuận tiện hơn.</p>
          <button
            className={styles.secondaryButton}
            type="button"
            onClick={() => {
              setEditing(null);
              setError(undefined);
            }}
          >
            <PettelIcon name="plus" /> Thêm bé cưng ngay
          </button>
        </section>
      )}

      <section className={styles.whySection}>
        <div className={styles.sectionHeading}>
          <div>
            <span>QUYỀN LỢI DÀNH CHO PETTEL</span>
            <h2>Tại sao hồ sơ chi tiết lại quan trọng với bé?</h2>
          </div>
          <p>
            Thông tin về loài, kích thước và thói quen giúp người chăm chuẩn bị tốt hơn cho từng bé.
          </p>
        </div>
        <div className={styles.benefits}>
          {benefits.map((benefit) => (
            <article className={`${styles.benefit} ${styles[benefit.accent]}`} key={benefit.title}>
              <span className={styles.benefitIcon}>
                <PettelIcon name={benefit.icon} />
              </span>
              <h3>{benefit.title}</h3>
              <p>{benefit.text}</p>
              <Link href="/services">
                Tìm hiểu dịch vụ <PettelIcon name="arrow" />
              </Link>
            </article>
          ))}
        </div>
      </section>

      <aside className={styles.vetBar}>
        <span className={styles.vetIcon}>
          <PettelIcon name="plus" />
        </span>
        <div>
          <strong>Mạng lưới bác sĩ thú y đối tác tại Đà Nẵng</strong>
          <p>Tham khảo hỗ trợ thú y và thông tin chăm sóc khi trao đổi với Sitter.</p>
        </div>
        <a href="tel:19006868">Hotline hỗ trợ · 1900 6868</a>
      </aside>

      {editing !== undefined && (
        <PetEditor
          key={editing?.id ?? "new"}
          editing={editing}
          error={error}
          busy={busy}
          submit={submit}
          onClose={() => setEditing(undefined)}
        />
      )}
    </>
  );
}

function Metric({
  icon,
  label,
  value,
  tone,
}: {
  icon: "paw" | "shield" | "star" | "heart";
  label: string;
  value: string;
  tone: string;
}) {
  return (
    <article className={`${styles.metric} ${styles[tone]}`}>
      <span>
        <PettelIcon name={icon} />
      </span>
      <div>
        <small>{label}</small>
        <strong>{value}</strong>
      </div>
    </article>
  );
}
