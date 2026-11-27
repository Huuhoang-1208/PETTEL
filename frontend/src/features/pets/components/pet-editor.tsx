"use client";

/* eslint-disable @next/next/no-img-element -- Preview selected private pet photo before upload. */
import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { type Pet } from "@/lib/api/types";
import { sizes, species } from "@/lib/constants";
import { apiUrl } from "@/lib/format";
import { useEffect, useState, type ChangeEvent, type FormEvent } from "react";
import styles from "./pet-editor.module.css";

const speciesSubtitle: Record<string, string> = {
  DOG: "Chó con, chó trưởng thành...",
  CAT: "Mèo con, mèo trưởng thành...",
  OTHER: "Thú cưng khác...",
};

export function PetEditor({
  editing,
  error,
  busy,
  submit,
  onClose,
}: {
  editing: Pet | null;
  error: unknown;
  busy: boolean;
  submit: (event: FormEvent<HTMLFormElement>) => void;
  onClose: () => void;
}) {
  const [localPhotoUrl, setLocalPhotoUrl] = useState<string | null>(null);
  const [photoError, setPhotoError] = useState("");
  const [selectedSpecies, setSelectedSpecies] = useState(editing?.species ?? "DOG");
  const [recordPhotoName, setRecordPhotoName] = useState("");

  const photoPreview =
    localPhotoUrl ??
    (editing?.photo_media_id
      ? apiUrl(`/api/v1/pets/media/${editing.photo_media_id}/content`)
      : null);

  useEffect(
    () => () => {
      if (localPhotoUrl) URL.revokeObjectURL(localPhotoUrl);
    },
    [localPhotoUrl],
  );

  function choosePhoto(event: ChangeEvent<HTMLInputElement>) {
    const file = event.currentTarget.files?.[0];
    setPhotoError("");
    if (!file) return;
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
      setPhotoError("Chọn ảnh JPG, PNG hoặc WebP.");
      event.currentTarget.value = "";
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setPhotoError("Ảnh cần có dung lượng tối đa 5 MB.");
      event.currentTarget.value = "";
      return;
    }
    setLocalPhotoUrl(URL.createObjectURL(file));
  }

  function chooseVaccinationRecord(event: ChangeEvent<HTMLInputElement>) {
    const file = event.currentTarget.files?.[0];
    setPhotoError("");
    if (!file) return;
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
      setPhotoError("Ảnh sổ tiêm cần là JPG, PNG hoặc WebP.");
      event.currentTarget.value = "";
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setPhotoError("Ảnh sổ tiêm cần có dung lượng tối đa 5 MB.");
      event.currentTarget.value = "";
      return;
    }
    setRecordPhotoName(file.name);
  }

  return (
    <div
      className={styles.backdrop}
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !busy) onClose();
      }}
    >
      <section
        className={styles.dialog}
        role="dialog"
        aria-modal="true"
        aria-labelledby="pet-dialog-title"
      >
        <header className={styles.header}>
          <div className={styles.headingIcon}>
            <PettelIcon name="paw" />
          </div>
          <div className={styles.headingText}>
            <h2 id="pet-dialog-title">
              {editing ? `Chỉnh sửa hồ sơ ${editing.name}` : "Thêm hồ sơ thú cưng mới"}
            </h2>
            <p>
              Chỉ mất vài phút để hoàn tất hồ sơ, giúp Sitter hiểu rõ và chăm sóc bé chu đáo hơn.
            </p>
          </div>
          <button
            className={styles.close}
            type="button"
            aria-label="Đóng"
            disabled={busy}
            onClick={onClose}
          >
            ×
          </button>
        </header>

        <div className={styles.stepBar}>
          <span className={styles.stepNumber}>1</span>
          <strong>Thông tin cơ bản &amp; nhận diện</strong>
          <span className={styles.privacy}>
            <PettelIcon name="shield" /> Thông tin được lưu an toàn
          </span>
        </div>

        <form
          className={styles.form}
          onSubmit={(event) => {
            if (photoError) {
              event.preventDefault();
              return;
            }
            submit(event);
          }}
        >
          <section className={styles.formSection}>
            <h3>
              1. Loại thú cưng của bạn <em>*</em>
            </h3>
            <div className={styles.speciesChoices}>
              {Object.entries(species).map(([key]) => (
                <label className={styles.speciesOption} key={key}>
                  <input
                    type="radio"
                    name="species"
                    value={key}
                    checked={selectedSpecies === key}
                    onChange={() => setSelectedSpecies(key)}
                  />
                  <span className={styles.speciesIcon}>
                    <PettelIcon name={key === "DOG" ? "dog" : key === "CAT" ? "cat" : "paw"} />
                  </span>
                  <span className={styles.speciesText}>
                    <strong>
                      {key === "DOG" ? "Bé Cún" : key === "CAT" ? "Bé Mèo" : "Bé khác"}
                    </strong>
                    <small>{speciesSubtitle[key]}</small>
                  </span>
                  <span className={styles.radioCheck} aria-hidden="true">
                    <PettelIcon name="check" />
                  </span>
                </label>
              ))}
            </div>
            {selectedSpecies === "OTHER" && (
              <label className={`${styles.field} ${styles.speciesDetail}`}>
                <span>
                  Loài cụ thể <em>*</em>
                </span>
                <input
                  name="species_detail"
                  required
                  maxLength={100}
                  defaultValue={editing?.species_detail ?? ""}
                  placeholder="Ví dụ: Thỏ, hamster, chim vẹt..."
                />
              </label>
            )}
          </section>

          <section className={styles.formSection}>
            <h3>
              2. Ảnh chân dung của bé <span>(không bắt buộc)</span>
            </h3>
            <label className={styles.uploadBox}>
              <input
                type="file"
                name="photo"
                accept="image/jpeg,image/png,image/webp"
                onChange={choosePhoto}
              />
              {photoPreview ? (
                <img className={styles.preview} src={photoPreview} alt="Xem trước ảnh thú cưng" />
              ) : (
                <span className={styles.cameraIcon}>
                  <PettelIcon name="camera" />
                </span>
              )}
              <span className={styles.uploadCopy}>
                <strong>{photoPreview ? "Ảnh đã sẵn sàng" : "Tải ảnh rõ nét khuôn mặt bé"}</strong>
                <small>Định dạng JPG, PNG hoặc WebP · tối đa 5 MB.</small>
              </span>
              <span className={styles.chooseButton}>{photoPreview ? "Đổi ảnh" : "Chọn ảnh"}</span>
            </label>
            {photoError && (
              <p className={styles.photoError} role="alert">
                {photoError}
              </p>
            )}
          </section>

          <section className={styles.formSection}>
            <h3>3. Thông tin định danh &amp; đặc điểm</h3>
            <div className={styles.fields}>
              <label className={styles.field}>
                <span>
                  Tên bé cưng <em>*</em>
                </span>
                <input
                  name="name"
                  defaultValue={editing?.name}
                  required
                  maxLength={100}
                  placeholder="Ví dụ: Bơ, Miu..."
                />
              </label>
              <label className={styles.field}>
                <span>Giống loài</span>
                <input
                  name="breed"
                  defaultValue={editing?.breed ?? ""}
                  maxLength={100}
                  placeholder="Ví dụ: Poodle, mèo Anh lông ngắn"
                />
              </label>
              <label className={styles.field}>
                <span>Độ tuổi / ghi chú tuổi</span>
                <input
                  name="age_note"
                  defaultValue={editing?.age_note ?? ""}
                  maxLength={100}
                  placeholder="Ví dụ: 2 tuổi 4 tháng"
                />
              </label>
              <label className={styles.field}>
                <span>
                  Kích cỡ <em>*</em>
                </span>
                <select name="size" required defaultValue={editing?.size ?? ""}>
                  <option value="" disabled>
                    Chọn kích cỡ
                  </option>
                  {Object.entries(sizes).map(([key, label]) => (
                    <option value={key} key={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </label>
              <label className={styles.field}>
                <span>Giới tính</span>
                <select name="sex" defaultValue={editing?.sex ?? "UNKNOWN"}>
                  <option value="UNKNOWN">Chưa rõ</option>
                  <option value="MALE">Đực</option>
                  <option value="FEMALE">Cái</option>
                </select>
              </label>
              <label className={styles.field}>
                <span>Cân nặng (kg)</span>
                <input
                  type="number"
                  name="weight_kg"
                  min="0.01"
                  max="200"
                  step="0.01"
                  defaultValue={editing?.weight_kg ?? ""}
                  placeholder="Ví dụ: 4,2"
                />
              </label>
              <label className={styles.field}>
                <span>Mã microchip (nếu có)</span>
                <input
                  name="microchip_number"
                  defaultValue={editing?.microchip_number ?? ""}
                  maxLength={80}
                  placeholder="Nhập mã nhận diện"
                />
              </label>
              <label className={`${styles.field} ${styles.wide}`}>
                <span>Ngày sinh (nếu biết)</span>
                <input
                  type="date"
                  name="birth_date"
                  defaultValue={editing?.birth_date ?? ""}
                  max={new Date().toISOString().slice(0, 10)}
                />
              </label>
            </div>
          </section>

          <section className={`${styles.formSection} ${styles.healthSection}`}>
            <div className={styles.healthHeading}>
              <h3>4. Sức khỏe &amp; tiêm chủng</h3>
              <span>
                <PettelIcon name="shield" /> Do bạn cung cấp
              </span>
            </div>
            <div className={styles.healthChecks}>
              {(
                [
                  [
                    "core_vaccines",
                    "Vaccine cơ bản",
                    editing?.health_flags?.core_vaccines ? "DONE" : "UNKNOWN",
                    editing?.core_vaccines_date ?? "",
                  ],
                  [
                    "rabies_vaccine",
                    "Vaccine dại",
                    editing?.health_flags?.rabies_vaccine ? "DONE" : "UNKNOWN",
                    editing?.rabies_vaccine_date ?? "",
                  ],
                  [
                    "deworming",
                    "Tẩy giun",
                    editing?.health_flags?.deworming ? "DONE" : "UNKNOWN",
                    editing?.deworming_date ?? "",
                  ],
                ] as const
              ).map(([key, label, legacyStatus, date]) => (
                <div className={styles.vaccineCard} key={key}>
                  <strong>{label}</strong>
                  <select
                    name={`vaccination_statuses.${key}`}
                    defaultValue={editing?.vaccination_statuses?.[key] ?? legacyStatus}
                    aria-label={`Tình trạng ${label}`}
                  >
                    <option value="DONE">{key === "deworming" ? "Đã tẩy giun" : "Đã tiêm"}</option>
                    <option value="NOT_DONE">
                      {key === "deworming" ? "Chưa tẩy giun" : "Chưa tiêm"}
                    </option>
                    <option value="UNKNOWN">Chưa rõ</option>
                  </select>
                  <input
                    type="date"
                    name={`${key}_date`}
                    defaultValue={date}
                    max={new Date().toISOString().slice(0, 10)}
                    aria-label={`Ngày ${label.toLowerCase()} (không bắt buộc)`}
                  />
                </div>
              ))}
              <label>
                <input type="hidden" name="is_neutered" value="0" />
                <input
                  type="checkbox"
                  name="is_neutered"
                  value="1"
                  defaultChecked={editing?.is_neutered ?? false}
                />{" "}
                Đã triệt sản
              </label>
            </div>
            <label className={styles.uploadBox}>
              <input
                type="file"
                name="vaccination_record_photo"
                accept="image/jpeg,image/png,image/webp"
                onChange={chooseVaccinationRecord}
              />
              <span className={styles.cameraIcon}>
                <PettelIcon name="camera" />
              </span>
              <span className={styles.uploadCopy}>
                <strong>
                  {recordPhotoName ||
                    (editing?.vaccination_record_media_id
                      ? "Đã có ảnh sổ tiêm · chọn để thay ảnh"
                      : "Thêm ảnh sổ tiêm (không bắt buộc)")}
                </strong>
                <small>JPG, PNG hoặc WebP · tối đa 5 MB.</small>
              </span>
              <span className={styles.chooseButton}>Chọn ảnh</span>
            </label>
            <label className={styles.field}>
              <span>Thông tin tiêm chủng, sức khỏe</span>
              <textarea
                name="vaccination_note"
                defaultValue={editing?.vaccination_note ?? ""}
                maxLength={2000}
                rows={2}
                placeholder="Ví dụ: Đã tiêm vaccine 5 bệnh ngày..., lịch tẩy giun..."
              />
            </label>
          </section>

          <section className={styles.formSection}>
            <h3>
              5. Tính cách &amp; cách tiếp cận <span>Chọn tất cả mục phù hợp</span>
            </h3>
            <div className={styles.tagChoices}>
              {[
                ["SHY", "Nhút nhát"],
                ["FEARS_STRANGERS", "Sợ người lạ"],
                ["NEEDS_WARM_UP", "Cần thời gian làm quen"],
                ["NOT_SOCIAL_WITH_PETS", "Không quen thú cưng khác"],
                ["SEPARATION_ANXIETY", "Lo lắng khi ở một mình"],
                ["HIGH_ENERGY", "Nhiều năng lượng"],
                ["SENSITIVE_TO_NOISE", "Nhạy cảm với tiếng ồn"],
              ].map(([value, label]) => (
                <label key={value}>
                  <input
                    type="checkbox"
                    name="behavior_tags[]"
                    value={value}
                    defaultChecked={editing?.behavior_tags?.includes(value)}
                  />
                  {label}
                </label>
              ))}
            </div>
            <label className={styles.field}>
              <span>Ghi chú thêm về tính cách</span>
              <textarea
                name="temperament"
                defaultValue={editing?.temperament ?? ""}
                maxLength={2000}
                rows={2}
                placeholder="Chia sẻ tình huống hoặc cách giúp bé cảm thấy an tâm..."
              />
            </label>
          </section>

          <section className={styles.formSection}>
            <h3>6. Chế độ ăn, dị ứng &amp; thức ăn cần tránh</h3>
            <div className={styles.fields}>
              <label className={styles.field}>
                <span>Chế độ ăn &amp; khẩu phần</span>
                <textarea
                  name="diet_notes"
                  defaultValue={editing?.diet_notes ?? ""}
                  maxLength={2000}
                  rows={2}
                  placeholder="Loại thức ăn, lượng ăn, giờ ăn..."
                />
              </label>
              <label className={styles.field}>
                <span>Dị ứng / không dung nạp</span>
                <textarea
                  name="food_allergies"
                  defaultValue={editing?.food_allergies ?? ""}
                  maxLength={2000}
                  rows={2}
                  placeholder="Ví dụ: Dị ứng thịt gà; không dung nạp sữa..."
                />
              </label>
              <label className={`${styles.field} ${styles.wide}`}>
                <span>Thức ăn hoặc thành phần cần tránh</span>
                <textarea
                  name="foods_to_avoid"
                  defaultValue={editing?.foods_to_avoid ?? ""}
                  maxLength={2000}
                  rows={2}
                  placeholder="Ví dụ: Không cho ăn cá, xương nhỏ hoặc thức ăn có gia vị..."
                />
              </label>
            </div>
            <p className={styles.helper}>
              Nếu bé có dị ứng nghiêm trọng, hãy ghi rõ mức độ và dấu hiệu cần lưu ý.
            </p>
          </section>

          <section className={styles.formSection}>
            <h3>
              7. Hỗ trợ chăm sóc cần thiết <span>Chọn tất cả mục phù hợp</span>
            </h3>
            <div className={styles.tagChoices}>
              {[
                ["ORAL_MEDICATION", "Cần uống thuốc"],
                ["EYE_DROPS", "Cần nhỏ mắt"],
                ["SPECIAL_DIET", "Cần theo chế độ ăn riêng"],
                ["POST_SURGERY", "Đang hồi phục sau phẫu thuật"],
                ["MOBILITY_SUPPORT", "Cần hỗ trợ đi lại"],
                ["OTHER_SPECIAL_CARE", "Cần chăm sóc đặc biệt khác"],
              ].map(([value, label]) => (
                <label key={value}>
                  <input
                    type="checkbox"
                    name="care_support_tags[]"
                    value={value}
                    defaultChecked={editing?.care_support_tags?.includes(value)}
                  />
                  {label}
                </label>
              ))}
            </div>
            <label className={styles.field}>
              <span>Chi tiết cần lưu ý</span>
              <textarea
                name="special_needs"
                defaultValue={editing?.special_needs ?? ""}
                maxLength={2000}
                rows={2}
                placeholder="Ghi chú cách cho thuốc hoặc hướng dẫn chăm sóc cần thiết..."
              />
            </label>
          </section>

          <ApiError error={error} />

          <footer className={styles.footer}>
            <button className={styles.cancel} type="button" disabled={busy} onClick={onClose}>
              Hủy bỏ
            </button>
            <button className={styles.save} type="submit" disabled={busy}>
              <PettelIcon name="paw" />{" "}
              {busy ? "Đang lưu hồ sơ..." : editing ? "Lưu thay đổi hồ sơ" : "Lưu hồ sơ bé yêu"}
            </button>
            <p>
              <PettelIcon name="shield" /> Bạn có thể cập nhật hồ sơ bất cứ lúc nào.
            </p>
          </footer>
        </form>
      </section>
    </div>
  );
}
