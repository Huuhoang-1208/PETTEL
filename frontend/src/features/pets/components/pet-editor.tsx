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
  const [photoPreview, setPhotoPreview] = useState<string | null>(null);
  const [photoError, setPhotoError] = useState("");

  useEffect(() => {
    if (!editing?.photo_media_id) return;
    setPhotoPreview(apiUrl(`/api/pets/media/${editing.photo_media_id}/content`));
  }, [editing?.photo_media_id]);

  useEffect(() => () => {
    if (photoPreview?.startsWith("blob:")) URL.revokeObjectURL(photoPreview);
  }, [photoPreview]);

  function choosePhoto(event: ChangeEvent<HTMLInputElement>) {
    const file = event.currentTarget.files?.[0];
    setPhotoError("");
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setPhotoError("Chọn ảnh JPG, PNG hoặc WebP.");
      event.currentTarget.value = "";
      return;
    }
    if (file.size > 5 * 1024 * 1024) {
      setPhotoError("Ảnh cần có dung lượng tối đa 5 MB.");
      event.currentTarget.value = "";
      return;
    }
    setPhotoPreview(URL.createObjectURL(file));
  }

  return (
    <div className={styles.backdrop} onMouseDown={(event) => { if (event.target === event.currentTarget && !busy) onClose(); }}>
      <section className={styles.dialog} role="dialog" aria-modal="true" aria-labelledby="pet-dialog-title">
        <header className={styles.header}>
          <div className={styles.headingIcon}><PettelIcon name="paw" /></div>
          <div className={styles.headingText}>
            <h2 id="pet-dialog-title">{editing ? `Chỉnh sửa hồ sơ ${editing.name}` : "Thêm hồ sơ thú cưng mới"}</h2>
            <p>Chỉ mất vài phút để hoàn tất hồ sơ, giúp Sitter hiểu rõ và chăm sóc bé chu đáo hơn.</p>
          </div>
          <button className={styles.close} type="button" aria-label="Đóng" disabled={busy} onClick={onClose}>×</button>
        </header>

        <div className={styles.stepBar}>
          <span className={styles.stepNumber}>1</span>
          <strong>Thông tin cơ bản &amp; nhận diện</strong>
          <span className={styles.privacy}><PettelIcon name="shield" /> Thông tin được lưu an toàn</span>
        </div>

        <form className={styles.form} key={editing?.id ?? "new"} onSubmit={(event) => {
          if (photoError) { event.preventDefault(); return; }
          submit(event);
        }}>
          <section className={styles.formSection}>
            <h3>1. Loại thú cưng của bạn <em>*</em></h3>
            <div className={styles.speciesChoices}>
              {Object.entries(species).map(([key, label]) => (
                <label className={styles.speciesOption} key={key}>
                  <input type="radio" name="species" value={key} defaultChecked={editing?.species ? editing.species === key : key === "DOG"} />
                  <span className={styles.speciesIcon}><PettelIcon name={key === "DOG" ? "dog" : key === "CAT" ? "cat" : "paw"} /></span>
                  <span className={styles.speciesText}><strong>{key === "DOG" ? "Bé Cún" : key === "CAT" ? "Bé Mèo" : "Bé khác"}</strong><small>{speciesSubtitle[key]}</small></span>
                  <span className={styles.radioCheck} aria-hidden="true"><PettelIcon name="check" /></span>
                </label>
              ))}
            </div>
          </section>

          <section className={styles.formSection}>
            <h3>2. Ảnh chân dung của bé <span>(không bắt buộc)</span></h3>
            <label className={styles.uploadBox}>
              <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" onChange={choosePhoto} />
              {photoPreview ? (
                <img className={styles.preview} src={photoPreview} alt="Xem trước ảnh thú cưng" />
              ) : <span className={styles.cameraIcon}><PettelIcon name="camera" /></span>}
              <span className={styles.uploadCopy}>
                <strong>{photoPreview ? "Ảnh đã sẵn sàng" : "Tải ảnh rõ nét khuôn mặt bé"}</strong>
                <small>Định dạng JPG, PNG hoặc WebP · tối đa 5 MB.</small>
              </span>
              <span className={styles.chooseButton}>{photoPreview ? "Đổi ảnh" : "Chọn ảnh"}</span>
            </label>
            {photoError && <p className={styles.photoError} role="alert">{photoError}</p>}
          </section>

          <section className={styles.formSection}>
            <h3>3. Thông tin định danh &amp; đặc điểm</h3>
            <div className={styles.fields}>
              <label className={styles.field}>
                <span>Tên bé cưng <em>*</em></span>
                <input name="name" defaultValue={editing?.name} required maxLength={100} placeholder="Ví dụ: Bơ, Miu..." />
              </label>
              <label className={styles.field}>
                <span>Giống loài</span>
                <input name="breed" defaultValue={editing?.breed ?? ""} maxLength={100} placeholder="Ví dụ: Poodle, mèo Anh lông ngắn" />
              </label>
              <label className={styles.field}>
                <span>Độ tuổi / ghi chú tuổi</span>
                <input name="age_note" defaultValue={editing?.age_note ?? ""} maxLength={100} placeholder="Ví dụ: 2 tuổi 4 tháng" />
              </label>
              <label className={styles.field}>
                <span>Kích cỡ <em>*</em></span>
                <select name="size" required defaultValue={editing?.size ?? ""}>
                  <option value="" disabled>Chọn kích cỡ</option>
                  {Object.entries(sizes).map(([key, label]) => <option value={key} key={key}>{label}</option>)}
                </select>
              </label>
              <label className={styles.field}>
                <span>Giới tính</span>
                <select name="sex" defaultValue={editing?.sex ?? "UNKNOWN"}>
                  <option value="UNKNOWN">Chưa rõ</option><option value="MALE">Đực</option><option value="FEMALE">Cái</option>
                </select>
              </label>
              <label className={styles.field}>
                <span>Cân nặng (kg)</span>
                <input type="number" name="weight_kg" min="0.01" max="200" step="0.01" defaultValue={editing?.weight_kg ?? ""} placeholder="Ví dụ: 4,2" />
              </label>
              <label className={styles.field}>
                <span>Mã microchip (nếu có)</span>
                <input name="microchip_number" defaultValue={editing?.microchip_number ?? ""} maxLength={80} placeholder="Nhập mã nhận diện" />
              </label>
              <label className={`${styles.field} ${styles.wide}`}>
                <span>Ngày sinh (nếu biết)</span>
                <input type="date" name="birth_date" defaultValue={editing?.birth_date ?? ""} max={new Date().toISOString().slice(0, 10)} />
              </label>
            </div>
          </section>

          <section className={`${styles.formSection} ${styles.healthSection}`}>
            <div className={styles.healthHeading}><h3>4. Sức khỏe &amp; tiêm chủng</h3><span><PettelIcon name="shield" /> Do bạn cung cấp</span></div>
            <div className={styles.healthChecks}>
              <label><input type="checkbox" name="health_core_vaccines" defaultChecked={editing?.health_flags?.core_vaccines ?? false} /> Đã tiêm vaccine cơ bản</label>
              <label><input type="checkbox" name="health_rabies_vaccine" defaultChecked={editing?.health_flags?.rabies_vaccine ?? false} /> Đã tiêm vaccine dại</label>
              <label><input type="checkbox" name="health_deworming" defaultChecked={editing?.health_flags?.deworming ?? false} /> Đã tẩy giun</label>
              <label><input type="hidden" name="is_neutered" value="0" /><input type="checkbox" name="is_neutered" value="1" defaultChecked={editing?.is_neutered ?? false} /> Đã triệt sản</label>
            </div>
            <label className={styles.field}>
              <span>Thông tin tiêm chủng, sức khỏe</span>
              <textarea name="vaccination_note" defaultValue={editing?.vaccination_note ?? ""} maxLength={2000} rows={2} placeholder="Ví dụ: Đã tiêm vaccine 5 bệnh ngày..., lịch tẩy giun..." />
            </label>
            <div className={styles.fields}>
              <label className={styles.field}>
                <span>Tính cách</span>
                <textarea name="temperament" defaultValue={editing?.temperament ?? ""} maxLength={2000} rows={2} placeholder="Thân thiện, nhút nhát, thích chơi..." />
              </label>
              <label className={styles.field}>
                <span>Chế độ ăn</span>
                <textarea name="diet_notes" defaultValue={editing?.diet_notes ?? ""} maxLength={2000} rows={2} placeholder="Thức ăn, khẩu phần, dị ứng..." />
              </label>
              <label className={`${styles.field} ${styles.wide}`}>
                <span>Nhu cầu chăm sóc đặc biệt</span>
                <textarea name="special_needs" defaultValue={editing?.special_needs ?? ""} maxLength={2000} rows={2} placeholder="Thuốc đang dùng hoặc lưu ý khi chăm sóc..." />
              </label>
            </div>
          </section>

          <ApiError error={error} />

          <footer className={styles.footer}>
            <button className={styles.cancel} type="button" disabled={busy} onClick={onClose}>Hủy bỏ</button>
            <button className={styles.save} type="submit" disabled={busy}>
              <PettelIcon name="paw" /> {busy ? "Đang lưu hồ sơ..." : editing ? "Lưu thay đổi hồ sơ" : "Lưu hồ sơ bé yêu"}
            </button>
            <p><PettelIcon name="shield" /> Bạn có thể cập nhật hồ sơ bất cứ lúc nào.</p>
          </footer>
        </form>
      </section>
    </div>
  );
}
