"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import type { Profile, SitterApplicationData } from "@/features/caregiver/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { areas } from "@/lib/constants";
import axios from "axios";
import { useState } from "react";
import styles from "./profile-form.module.css";

const emptyProfile: Profile = {
  display_name: "",
  bio: "",
  type: "INDIVIDUAL",
  area_code: "DN_CENTER",
};
const speciesOptions = [
  ["DOG_SMALL", "Chó nhỏ"],
  ["DOG_MEDIUM", "Chó vừa"],
  ["DOG_LARGE", "Chó lớn"],
  ["CAT_SHORT_HAIR", "Mèo lông ngắn"],
  ["CAT_LONG_HAIR", "Mèo lông dài"],
  ["SMALL_PETS", "Thú cưng nhỏ"],
];
const skillOptions = [
  ["MEDICATION", "Cho uống thuốc / nhỏ mắt"],
  ["SENIOR_OR_SHY", "Pet già hoặc nhút nhát"],
  ["SPECIAL_DIET", "Chế độ ăn đặc biệt"],
  ["POST_SURGERY", "Chăm sóc sau phẫu thuật"],
];
const amenityOptions = [
  ["BALCONY_NET", "Lưới an toàn ban công"],
  ["HEPA_FILTER", "Máy lọc không khí HEPA"],
  ["CAGE_FREE", "Không gian không lồng"],
  ["AIR_CONDITIONING", "Điều hòa"],
  ["SEPARATE_FOOD_STORAGE", "Bảo quản thức ăn riêng"],
  ["CAMERA", "Camera giám sát"],
];
const petRequirementOptions = [
  ["REQUIRE_VACCINATION", "Chỉ nhận bé đã tiêm phòng đầy đủ"],
  ["ACCEPTS_MEDICATION", "Có thể chăm sóc bé cần uống thuốc"],
  ["ACCEPTS_SENIOR_OR_SHY", "Có thể nhận bé lớn tuổi hoặc nhút nhát"],
  ["ACCEPTS_SPECIAL_DIET", "Có thể theo chế độ ăn đặc biệt"],
  ["ACCEPTS_POST_SURGERY", "Có thể chăm sóc sau phẫu thuật theo hướng dẫn thú y"],
];

export function ProfileForm() {
  const profile = useApi<{ data: Profile }>("/api/v1/caregiver/profile");
  const [draftOverride, setDraft] = useState<Profile | null>(null);
  const [applicationOverride, setApplication] = useState<SitterApplicationData | null>(null);
  const [pendingFiles, setPendingFiles] = useState<{
    avatar?: File;
    space: File[];
    front?: File;
    back?: File;
    certificate?: File;
  }>({ space: [] });
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [saved, setSaved] = useState(false);
  const missing = axios.isAxiosError(profile.error) && profile.error.response?.status === 404;
  const value = profile.data?.data;

  const draft = draftOverride ?? value ?? emptyProfile;
  const application = applicationOverride ?? value?.application_data_json ?? {};

  const completionItems = [
    draft.display_name.trim(),
    draft.bio?.trim(),
    draft.area_code,
    application.private_address?.trim(),
    application.contact_phone?.trim(),
    application.contact_email?.trim(),
    application.experience_species?.length,
    application.care_skills?.length,
    application.housing_type,
    application.amenities?.length,
    application.safety_plan?.trim(),
    application.kyc_front_media_id,
    application.kyc_back_media_id,
    application.experience_years !== null && application.experience_years !== undefined,
    application.care_principles?.length,
  ];
  const percent = Math.round(
    (completionItems.filter(Boolean).length / completionItems.length) * 100,
  );
  const displayName = draft.display_name.trim() || "Tên hồ sơ Sitter";
  const areaLabel = areas[draft.area_code as keyof typeof areas] ?? "Chưa chọn khu vực";
  const typeLabel =
    draft.type === "FACILITY" ? "Cơ sở chăm sóc" : "Cá nhân · Pet Lover / Freelancer";
  const avatarUrl = application.avatar_media_id
    ? `${process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080"}/api/v1/caregiver/media/${application.avatar_media_id}/content`
    : null;
  const savedContactEmail = value?.application_data_json?.contact_email?.trim().toLowerCase();
  const currentContactEmail = application.contact_email?.trim().toLowerCase();
  const contactEmailVerified = Boolean(
    value?.contact_verification?.email_verified &&
    savedContactEmail &&
    currentContactEmail === savedContactEmail,
  );

  function toggleApplicationList(
    key: "experience_species" | "care_skills" | "amenities" | "pet_requirements",
    item: string,
    checked: boolean,
  ) {
    const current = application[key] ?? [];
    setApplication({
      ...application,
      [key]: checked ? [...current, item] : current.filter((value) => value !== item),
    });
  }

  function updatePrinciple(index: number, key: "title" | "description", text: string) {
    const principles = [...(application.care_principles ?? [])];
    principles[index] = { ...principles[index], [key]: text };
    setApplication({ ...application, care_principles: principles });
  }

  if (profile.loading) return <p className={styles.loading}>Đang tải hồ sơ Sitter...</p>;

  return (
    <div className={styles.layout}>
      <section className={styles.editor}>
        <div className={styles.editorHeader}>
          <span className={styles.step}>01</span>
          <div>
            <h2>Thông tin hồ sơ</h2>
            <p>Thông tin này giúp khách hàng hiểu bạn trước khi gửi yêu cầu chăm sóc.</p>
          </div>
          <span className={styles.required}>Cơ bản</span>
        </div>

        <div className={styles.progress}>
          <div>
            <span>Mức độ hoàn thiện</span>
            <strong>{percent}%</strong>
          </div>
          <div className={styles.track}>
            <i style={{ width: `${percent}%` }} />
          </div>
          <small>
            Tiến độ tính theo thông tin cơ bản, kinh nghiệm, liên hệ, không gian và giấy tờ xác
            minh.
          </small>
        </div>

        <ApiError error={error ?? (missing ? null : profile.error)} />
        {saved && (
          <p className={styles.success}>
            <PettelIcon name="check" /> Đã lưu hồ sơ Sitter của bạn.
          </p>
        )}

        <form
          className={styles.form}
          onSubmit={async (event) => {
            event.preventDefault();
            setBusy(true);
            setError(undefined);
            setSaved(false);
            try {
              // The API response may include system fields such as id and timestamps.
              // Send only the profile fields accepted by the create/update endpoints.
              const upload = async (file: File) => {
                const body = new FormData();
                body.set("file", file);
                const uploaded = await write<{ data: { id: string } }>(
                  "post",
                  "/api/v1/caregiver/media",
                  body,
                );
                return Number(uploaded.data.id);
              };
              const [avatarId, spaceIds, frontId, backId, certificateId] = await Promise.all([
                pendingFiles.avatar
                  ? upload(pendingFiles.avatar)
                  : Promise.resolve(application.avatar_media_id ?? null),
                Promise.all(pendingFiles.space.map(upload)),
                pendingFiles.front
                  ? upload(pendingFiles.front)
                  : Promise.resolve(application.kyc_front_media_id ?? null),
                pendingFiles.back
                  ? upload(pendingFiles.back)
                  : Promise.resolve(application.kyc_back_media_id ?? null),
                pendingFiles.certificate
                  ? upload(pendingFiles.certificate)
                  : Promise.resolve(application.certificate_media_id ?? null),
              ]);
              const applicationData: SitterApplicationData = {
                ...application,
                avatar_media_id: avatarId,
                space_media_ids: spaceIds.length ? spaceIds : (application.space_media_ids ?? []),
                kyc_front_media_id: frontId,
                kyc_back_media_id: backId,
                certificate_media_id: certificateId,
              };
              const data = {
                display_name: draft.display_name,
                type: draft.type,
                area_code: draft.area_code,
                bio: draft.bio ?? "",
                application_data_json: applicationData,
              };
              await write(value ? "patch" : "post", "/api/v1/caregiver/profile", data);
              setPendingFiles({ space: [] });
              setDraft(null);
              setApplication(null);
              profile.reload();
              setSaved(true);
            } catch (failure) {
              setError(failure);
            } finally {
              setBusy(false);
            }
          }}
        >
          <label className={styles.field}>
            <span>
              Tên hiển thị <b>*</b>
            </span>
            <small>Tên khách hàng sẽ nhìn thấy trên hồ sơ công khai.</small>
            <input
              value={draft.display_name}
              onChange={(event) => setDraft({ ...draft, display_name: event.target.value })}
              name="display_name"
              required
              maxLength={100}
              placeholder="Ví dụ: Bảo Trâm (Hải Châu)"
            />
          </label>

          <fieldset className={styles.fieldset}>
            <legend>
              Loại hình hoạt động <b>*</b>
            </legend>
            <div className={styles.typeOptions}>
              <label className={draft.type === "INDIVIDUAL" ? styles.typeSelected : ""}>
                <input
                  type="radio"
                  name="type"
                  value="INDIVIDUAL"
                  checked={draft.type === "INDIVIDUAL"}
                  onChange={() => setDraft({ ...draft, type: "INDIVIDUAL" })}
                />
                <span>
                  <strong>Cá nhân</strong>
                  <small>Chăm sóc tại nhà riêng hoặc linh hoạt ghé thăm trực tiếp.</small>
                </span>
              </label>
              <label className={draft.type === "FACILITY" ? styles.typeSelected : ""}>
                <input
                  type="radio"
                  name="type"
                  value="FACILITY"
                  checked={draft.type === "FACILITY"}
                  onChange={() => setDraft({ ...draft, type: "FACILITY" })}
                />
                <span>
                  <strong>Cơ sở chăm sóc</strong>
                  <small>Có không gian chuyên dụng dành cho thú cưng.</small>
                </span>
              </label>
            </div>
          </fieldset>

          <label className={styles.field}>
            <span>
              Khu vực hoạt động chính <b>*</b>
            </span>
            <small>Khách hàng dùng khu vực này để tìm Sitter gần họ.</small>
            <select
              name="area_code"
              value={draft.area_code}
              onChange={(event) => setDraft({ ...draft, area_code: event.target.value })}
            >
              {Object.entries(areas).map(([key, label]) => (
                <option key={key} value={key}>
                  {label}
                </option>
              ))}
            </select>
          </label>

          <label className={styles.field}>
            <span>Giới thiệu & kinh nghiệm chăm sóc</span>
            <small>
              Chia sẻ kinh nghiệm, thói quen chăm sóc và điều bạn làm để thú cưng cảm thấy an toàn.
            </small>
            <textarea
              name="bio"
              value={draft.bio ?? ""}
              onChange={(event) => setDraft({ ...draft, bio: event.target.value })}
              maxLength={2000}
              rows={6}
              placeholder="Ví dụ: Tôi có kinh nghiệm chăm sóc chó mèo, quen với việc cho uống thuốc và gửi cập nhật thường xuyên cho chủ nuôi..."
            />
            <em>{(draft.bio ?? "").length}/2000 ký tự</em>
          </label>

          <div className={styles.principlesSection}>
            <div className={styles.principlesHeading}>
              <div>
                <strong>Nguyên tắc chăm sóc</strong>
                <small>
                  Chia sẻ tối đa 3 điều khách hàng có thể mong đợi khi gửi thú cưng cho bạn.
                </small>
              </div>
              <span>{application.care_principles?.length ?? 0}/3</span>
            </div>
            {(application.care_principles ?? []).map((principle, index) => (
              <fieldset className={styles.principleCard} key={`principle-${index}`}>
                <legend>Nguyên tắc {index + 1}</legend>
                <button
                  type="button"
                  className={styles.removePrinciple}
                  onClick={() =>
                    setApplication({
                      ...application,
                      care_principles: application.care_principles?.filter(
                        (_, itemIndex) => itemIndex !== index,
                      ),
                    })
                  }
                >
                  Xóa
                </button>
                <label className={styles.field}>
                  <span>Tiêu đề</span>
                  <input
                    required
                    maxLength={80}
                    value={principle.title}
                    onChange={(event) => updatePrinciple(index, "title", event.target.value)}
                    placeholder="Ví dụ: Tôn trọng thói quen của bé"
                  />
                </label>
                <label className={styles.field}>
                  <span>Mô tả</span>
                  <textarea
                    required
                    maxLength={300}
                    rows={3}
                    value={principle.description}
                    onChange={(event) => updatePrinciple(index, "description", event.target.value)}
                    placeholder="Mô tả cách bạn áp dụng nguyên tắc này khi chăm sóc."
                  />
                </label>
              </fieldset>
            ))}
            {(application.care_principles?.length ?? 0) < 3 && (
              <button
                type="button"
                className={styles.addPrinciple}
                onClick={() =>
                  setApplication({
                    ...application,
                    care_principles: [
                      ...(application.care_principles ?? []),
                      { title: "", description: "" },
                    ],
                  })
                }
              >
                ＋ Thêm nguyên tắc
              </button>
            )}
          </div>

          <section className={styles.detailSection}>
            <div className={styles.detailHeading}>
              <span>02</span>
              <div>
                <h3>Thông tin bổ sung</h3>
                <p>Thông tin riêng tư chỉ dùng để hoàn thiện hồ sơ đối tác.</p>
              </div>
            </div>
            <div className={styles.detailGrid}>
              <label className={styles.field}>
                <span>
                  Địa chỉ / tòa nhà <small>Riêng tư</small>
                </span>
                <input
                  value={application.private_address ?? ""}
                  onChange={(event) =>
                    setApplication({ ...application, private_address: event.target.value })
                  }
                  maxLength={300}
                  placeholder="Địa chỉ nơi bạn nhận chăm sóc"
                />
              </label>
              <label className={styles.field}>
                <span>Số điện thoại liên hệ</span>
                <input
                  value={application.contact_phone ?? ""}
                  onChange={(event) =>
                    setApplication({ ...application, contact_phone: event.target.value })
                  }
                  type="tel"
                  maxLength={24}
                  placeholder="0905 123 456"
                />
              </label>
              <label className={styles.field}>
                <span>Email nhận thông báo</span>
                <input
                  value={application.contact_email ?? ""}
                  onChange={(event) =>
                    setApplication({ ...application, contact_email: event.target.value })
                  }
                  type="email"
                  maxLength={255}
                  placeholder="ban@example.com"
                />
              </label>
              <label className={styles.field}>
                <span>Số năm kinh nghiệm</span>
                <input
                  type="number"
                  min={0}
                  max={60}
                  step={1}
                  value={application.experience_years ?? ""}
                  onChange={(event) =>
                    setApplication({
                      ...application,
                      experience_years:
                        event.target.value === "" ? null : Number(event.target.value),
                    })
                  }
                  placeholder="Ví dụ: 3"
                />
              </label>
              <label className={styles.field}>
                <span>Số thú cưng tối đa nhận cùng lúc</span>
                <select
                  value={application.capacity ?? "1"}
                  onChange={(event) =>
                    setApplication({ ...application, capacity: event.target.value })
                  }
                >
                  <option value="1">1 bé</option>
                  <option value="2">2 bé</option>
                  <option value="4">3–4 bé (có người phụ trợ)</option>
                </select>
              </label>
              <label className={styles.field}>
                <span>Loại hình nhà ở</span>
                <select
                  value={application.housing_type ?? "APARTMENT"}
                  onChange={(event) =>
                    setApplication({ ...application, housing_type: event.target.value })
                  }
                >
                  <option value="APARTMENT">Chung cư cao tầng</option>
                  <option value="HOUSE">Nhà phố có sân</option>
                  <option value="VILLA">Biệt thự có rào kín</option>
                </select>
              </label>
              <label className={styles.field}>
                <span>Không gian chăm sóc</span>
                <select
                  value={application.space_type ?? "INDOOR"}
                  onChange={(event) =>
                    setApplication({ ...application, space_type: event.target.value })
                  }
                >
                  <option value="INDOOR">Trong nhà</option>
                  <option value="INDOOR_YARD">Trong nhà và sân</option>
                </select>
              </label>
            </div>

            <div className={styles.verificationGrid}>
              <div className={styles.verificationItem}>
                <strong>Email nhận thông báo</strong>
                <span className={contactEmailVerified ? styles.verified : styles.notVerified}>
                  {contactEmailVerified ? "Đã xác minh" : "Chưa xác minh"}
                </span>
                <small>
                  {contactEmailVerified
                    ? "Email này trùng với email tài khoản PETTEL đã xác minh."
                    : value?.contact_verification?.email_verification_available
                      ? "Dùng email tài khoản PETTEL đã xác minh để nhận thông báo."
                      : "Luồng xác nhận email tài khoản PETTEL chưa được tích hợp."}
                </small>
              </div>
              <div className={styles.verificationItem}>
                <strong>Số điện thoại liên hệ</strong>
                <span
                  className={
                    value?.contact_verification?.phone_verified
                      ? styles.verified
                      : styles.notVerified
                  }
                >
                  {value?.contact_verification?.phone_verified ? "Đã xác minh" : "Chưa xác minh"}
                </span>
                <small>
                  {value?.contact_verification?.phone_verification_available
                    ? "Số điện thoại đã được xác minh với PETTEL."
                    : "PETTEL chưa bật xác minh số điện thoại qua mã OTP."}
                </small>
              </div>
            </div>

            <fieldset className={styles.choiceField}>
              <legend>Loài thú cưng bạn có kinh nghiệm</legend>
              <div className={styles.choiceGrid}>
                {speciesOptions.map(([code, label]) => (
                  <label key={code}>
                    <input
                      type="checkbox"
                      checked={(application.experience_species ?? []).includes(code)}
                      onChange={(event) =>
                        toggleApplicationList("experience_species", code, event.target.checked)
                      }
                    />
                    {label}
                  </label>
                ))}
              </div>
            </fieldset>
            <fieldset className={styles.choiceField}>
              <legend>Kỹ năng chăm sóc đặc biệt</legend>
              <div className={styles.choiceGrid}>
                {skillOptions.map(([code, label]) => (
                  <label key={code}>
                    <input
                      type="checkbox"
                      checked={(application.care_skills ?? []).includes(code)}
                      onChange={(event) =>
                        toggleApplicationList("care_skills", code, event.target.checked)
                      }
                    />
                    {label}
                  </label>
                ))}
              </div>
            </fieldset>
            <fieldset className={styles.choiceField}>
              <legend>Yêu cầu &amp; khả năng nhận thú cưng</legend>
              <div className={styles.choiceGrid}>
                {petRequirementOptions.map(([code, label]) => (
                  <label key={code}>
                    <input
                      type="checkbox"
                      checked={(application.pet_requirements ?? []).includes(code)}
                      onChange={(event) =>
                        toggleApplicationList("pet_requirements", code, event.target.checked)
                      }
                    />
                    {label}
                  </label>
                ))}
              </div>
            </fieldset>
            <fieldset className={styles.choiceField}>
              <legend>Tiện ích an toàn tại nơi chăm sóc</legend>
              <div className={styles.choiceGrid}>
                {amenityOptions.map(([code, label]) => (
                  <label key={code}>
                    <input
                      type="checkbox"
                      checked={(application.amenities ?? []).includes(code)}
                      onChange={(event) =>
                        toggleApplicationList("amenities", code, event.target.checked)
                      }
                    />
                    {label}
                  </label>
                ))}
              </div>
            </fieldset>

            <label className={styles.field}>
              <span>Kế hoạch an toàn & xử lý tình huống khẩn cấp</span>
              <textarea
                value={application.safety_plan ?? ""}
                onChange={(event) =>
                  setApplication({ ...application, safety_plan: event.target.value })
                }
                maxLength={1500}
                rows={4}
                placeholder="Các bước bạn sẽ thực hiện khi thú cưng có dấu hiệu bất thường..."
              />
            </label>

            <div className={styles.uploadGrid}>
              <label className={styles.uploadField}>
                <strong>Ảnh đại diện</strong>
                <small>{application.avatar_media_id ? "Đã có ảnh riêng tư" : "Chưa tải ảnh"}</small>
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  onChange={(event) =>
                    setPendingFiles({ ...pendingFiles, avatar: event.target.files?.[0] })
                  }
                />
              </label>
              <label className={styles.uploadField}>
                <strong>Ảnh không gian</strong>
                <small>
                  {application.space_media_ids?.length
                    ? `${application.space_media_ids.length} ảnh đã tải lên`
                    : "Chưa tải ảnh"}
                </small>
                <input
                  type="file"
                  multiple
                  accept="image/png,image/jpeg,image/webp"
                  onChange={(event) =>
                    setPendingFiles({
                      ...pendingFiles,
                      space: Array.from(event.target.files ?? []).slice(0, 8),
                    })
                  }
                />
              </label>
              <label className={styles.uploadField}>
                <strong>CCCD mặt trước</strong>
                <small>
                  {application.kyc_front_media_id ? "Đã tải lên riêng tư" : "Chưa tải lên"}
                </small>
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  onChange={(event) =>
                    setPendingFiles({ ...pendingFiles, front: event.target.files?.[0] })
                  }
                />
              </label>
              <label className={styles.uploadField}>
                <strong>CCCD mặt sau</strong>
                <small>
                  {application.kyc_back_media_id ? "Đã tải lên riêng tư" : "Chưa tải lên"}
                </small>
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  onChange={(event) =>
                    setPendingFiles({ ...pendingFiles, back: event.target.files?.[0] })
                  }
                />
              </label>
              <label className={styles.uploadField}>
                <strong>Chứng chỉ thú y</strong>
                <small>
                  {application.certificate_media_id ? "Đã tải lên riêng tư" : "Chưa tải lên"}
                </small>
                <input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  onChange={(event) =>
                    setPendingFiles({ ...pendingFiles, certificate: event.target.files?.[0] })
                  }
                />
              </label>
            </div>

            <fieldset className={styles.choiceField}>
              <legend>Xác nhận cam kết</legend>
              <div className={styles.agreements}>
                <label>
                  <input
                    type="checkbox"
                    checked={application.agreements?.partner_terms ?? false}
                    onChange={(event) =>
                      setApplication({
                        ...application,
                        agreements: {
                          ...application.agreements,
                          partner_terms: event.target.checked,
                        },
                      })
                    }
                  />{" "}
                  Đồng ý với quy chế hoạt động và tiêu chuẩn phúc lợi động vật.
                </label>
                <label>
                  <input
                    type="checkbox"
                    checked={application.agreements?.care_commitment ?? false}
                    onChange={(event) =>
                      setApplication({
                        ...application,
                        agreements: {
                          ...application.agreements,
                          care_commitment: event.target.checked,
                        },
                      })
                    }
                  />{" "}
                  Cam kết chăm sóc an toàn và cập nhật tình hình cho chủ nuôi.
                </label>
              </div>
            </fieldset>
          </section>

          <div className={styles.actions}>
            <span>
              <PettelIcon name="shield" /> Địa chỉ chi tiết và giấy tờ xác minh được quản lý riêng.
            </span>
            <button type="submit" disabled={busy}>
              {busy ? (
                "Đang lưu..."
              ) : (
                <>
                  <PettelIcon name="check" /> Lưu thay đổi
                </>
              )}
            </button>
          </div>
        </form>
      </section>

      <aside className={styles.side}>
        <section className={styles.preview}>
          <div className={styles.previewTop}>
            <div>
              <small>XEM TRƯỚC</small>
              <h2>Hồ sơ khách hàng sẽ thấy</h2>
            </div>
            <span>
              <i /> Bản xem trước
            </span>
          </div>
          <div className={styles.cover}>
            {avatarUrl ? (
              <img className={styles.coverAvatar} src={avatarUrl} alt="Ảnh đại diện Sitter" />
            ) : (
              <span className={styles.avatar}>
                <PettelIcon name="user" />
              </span>
            )}
            <b>Hồ sơ PETTEL Sitter</b>
          </div>
          <div className={styles.previewBody}>
            <div className={styles.nameRow}>
              <h3>{displayName}</h3>
              <span>
                <PettelIcon name="award" />
              </span>
            </div>
            <p className={styles.location}>
              <PettelIcon name="pin" /> {areaLabel}
            </p>
            <span className={styles.typeBadge}>{typeLabel}</span>
            <p className={styles.bio}>
              {draft.bio?.trim() ||
                "Phần giới thiệu của bạn sẽ xuất hiện tại đây để khách hàng hiểu thêm về kinh nghiệm và cách bạn chăm sóc thú cưng."}
            </p>
            {application.care_principles?.length ? (
              <div className={styles.previewPrinciples}>
                <strong>Nguyên tắc chăm sóc</strong>
                {application.care_principles.slice(0, 3).map((principle, index) => (
                  <p key={`${principle.title}-${index}`}>{principle.title}</p>
                ))}
              </div>
            ) : null}
            <div className={styles.previewFoot}>
              <PettelIcon name="shield" /> Thông tin hồ sơ được PETTEL quản lý
            </div>
          </div>
        </section>

        <section className={styles.tip}>
          <span>
            <PettelIcon name="award" />
          </span>
          <div>
            <h3>Mẹo tạo hồ sơ đáng tin cậy</h3>
            <p>
              Viết cụ thể kinh nghiệm, khu vực bạn nhận chăm và cách bạn cập nhật tình hình cho chủ
              nuôi.
            </p>
          </div>
        </section>

        <section className={styles.scope}>
          <h3>Hồ sơ này gồm</h3>
          <ul>
            <li>
              <PettelIcon name="check" /> Tên hiển thị
            </li>
            <li>
              <PettelIcon name="check" /> Loại hình hoạt động
            </li>
            <li>
              <PettelIcon name="check" /> Khu vực chính
            </li>
            <li>
              <PettelIcon name="check" /> Giới thiệu & kinh nghiệm
            </li>
            <li>
              <PettelIcon name="check" /> Nguyên tắc chăm sóc
            </li>
            <li>
              <PettelIcon name="check" /> Yêu cầu nhận thú cưng
            </li>
          </ul>
          <p>Ảnh đại diện, dịch vụ và giấy tờ xác minh được quản lý ở các mục riêng.</p>
        </section>
      </aside>
    </div>
  );
}
