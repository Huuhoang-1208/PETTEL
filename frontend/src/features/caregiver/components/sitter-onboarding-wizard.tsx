"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { write } from "@/lib/api/request";
import { areas } from "@/lib/constants";
import { useState, type FormEvent } from "react";
import type { SitterApplicationData } from "../types";
import styles from "../sitter-onboarding.module.css";

type Props = { userName: string; userEmail: string; onComplete: () => void };

const steps = [
  { title: "Hồ sơ Sitter", caption: "Thông tin cơ bản & kinh nghiệm", icon: "user" as const },
  { title: "Dịch vụ & Không gian", caption: "Biểu phí, an toàn & góc nuôi", icon: "home" as const },
  { title: "An toàn & Xác minh", caption: "Định danh eKYC & chính sách", icon: "shield" as const },
];

const services = [
  { id: "boarding", title: "Nội trú qua đêm", subtitle: "Đón bé tại nhà bạn, cập nhật hằng ngày", available: true },
  { id: "daycare", title: "Daycare ban ngày", subtitle: "Chăm sóc ban ngày theo lịch hẹn", available: true },
  { id: "walking", title: "Dắt cún đi dạo", subtitle: "Dạo bộ cùng bé trong khu vực của bạn", available: false },
  { id: "homevisit", title: "Ghé thăm tại nhà khách", subtitle: "Cho ăn, chơi và chăm sóc tại nhà khách", available: false },
];

export function SitterOnboardingWizard({ userName, userEmail, onComplete }: Props) {
  const [step, setStep] = useState(0);
  const [displayName, setDisplayName] = useState(userName);
  const [type, setType] = useState("INDIVIDUAL");
  const [area, setArea] = useState("DN_CENTER");
  const [bio, setBio] = useState("");
  const [avatar, setAvatar] = useState("");
  const [priceBoarding, setPriceBoarding] = useState("250000");
  const [priceDaycare, setPriceDaycare] = useState("80000");
  const [selectedServices, setSelectedServices] = useState<string[]>(["boarding", "daycare"]);
  const [applicationData, setApplicationData] = useState<SitterApplicationData>({});
  const [pendingFiles, setPendingFiles] = useState<{ avatar?: File; spacePhotos: File[]; kycFront?: File; kycBack?: File; certificate?: File }>({ spacePhotos: [] });
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);

  const progress = step === 0 ? 35 : step === 1 ? 70 : 100;

  function next(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(undefined);
    const form = new FormData(event.currentTarget);
    if (step === 0) {
      const avatarFile = form.getAll("avatar").find((file): file is File => file instanceof File && file.size > 0);
      setApplicationData((current) => ({
        ...current,
        private_address: String(form.get("private_address") ?? ""),
        experience_species: form.getAll("experience_species").map(String),
        care_skills: form.getAll("care_skills").map(String),
        capacity: String(form.get("capacity") ?? "1"),
      }));
      setPendingFiles((current) => ({ ...current, ...(avatarFile ? { avatar: avatarFile } : {}) }));
    } else if (step === 1) {
      const photos = form.getAll("space_photos").filter((file): file is File => file instanceof File && file.size > 0).slice(0, 8);
      setApplicationData((current) => ({
        ...current,
        housing_type: String(form.get("housing_type") ?? "APARTMENT"),
        space_type: String(form.get("space_type") ?? "INDOOR"),
        amenities: form.getAll("amenities").map(String),
      }));
      setPendingFiles((current) => ({ ...current, spacePhotos: photos.length ? photos : current.spacePhotos }));
    }
    setStep((current) => Math.min(current + 1, 2));
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function uploadPrivateImage(file: File): Promise<number> {
    const body = new FormData();
    body.set("file", file);
    const uploaded = await write<{ data: { id: string } }>("post", "/api/caregiver/media", body);
    return Number(uploaded.data.id);
  }

  function toggleService(id: string) {
    if (!services.find((service) => service.id === id)?.available) return;
    setSelectedServices((current) => current.includes(id) ? current.filter((item) => item !== id) : [...current, id]);
  }

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    if (!selectedServices.some((service) => service === "boarding" || service === "daycare")) {
      setError(new Error("Hãy chọn ít nhất một dịch vụ hiện có thể đăng ký."));
      return;
    }
    setBusy(true);
    setError(undefined);
    try {
      const [avatarId, spaceIds, frontId, backId, certificateId] = await Promise.all([
        pendingFiles.avatar ? uploadPrivateImage(pendingFiles.avatar) : Promise.resolve(applicationData.avatar_media_id ?? null),
        Promise.all(pendingFiles.spacePhotos.map(uploadPrivateImage)),
        (form.get("kyc_front") instanceof File && (form.get("kyc_front") as File).size > 0) ? uploadPrivateImage(form.get("kyc_front") as File) : Promise.resolve(applicationData.kyc_front_media_id ?? null),
        (form.get("kyc_back") instanceof File && (form.get("kyc_back") as File).size > 0) ? uploadPrivateImage(form.get("kyc_back") as File) : Promise.resolve(applicationData.kyc_back_media_id ?? null),
        (form.get("certificate") instanceof File && (form.get("certificate") as File).size > 0) ? uploadPrivateImage(form.get("certificate") as File) : Promise.resolve(applicationData.certificate_media_id ?? null),
      ]);
      const savedApplicationData: SitterApplicationData = {
        ...applicationData,
        space_media_ids: spaceIds.length ? spaceIds : applicationData.space_media_ids ?? [],
        avatar_media_id: avatarId,
        contact_phone: String(form.get("contact_phone") ?? ""),
        contact_email: String(form.get("contact_email") ?? userEmail),
        safety_plan: String(form.get("safety_plan") ?? ""),
        kyc_front_media_id: frontId,
        kyc_back_media_id: backId,
        certificate_media_id: certificateId,
        agreements: {
          partner_terms: form.get("partner_terms") === "on",
          care_commitment: form.get("care_commitment") === "on",
        },
      };
      await write("post", "/api/user/roles/caregiver");
      await write("post", "/api/caregiver/profile", {
        display_name: displayName.trim(),
        type,
        area_code: area,
        bio: bio.trim(),
        application_data_json: savedApplicationData,
      });

      const listingPayloads = [
        ...(selectedServices.includes("boarding") ? [{
          title: `Trông giữ qua đêm cùng ${displayName.trim()}`,
          description: bio.trim(),
          area_code: area,
          service_type: "BOARDING",
          price_vnd: Number(priceBoarding),
          price_unit: "NIGHT",
          acceptance_rules_json: { schema_version: 1, species: ["DOG", "CAT"], sizes: ["SMALL", "MEDIUM"] },
        }] : []),
        ...(selectedServices.includes("daycare") ? [{
          title: `Daycare thú cưng cùng ${displayName.trim()}`,
          description: bio.trim(),
          area_code: area,
          service_type: "DAYCARE",
          price_vnd: Number(priceDaycare),
          price_unit: "HOUR",
          acceptance_rules_json: { schema_version: 1, species: ["DOG", "CAT"], sizes: ["SMALL", "MEDIUM"] },
        }] : []),
      ];

      for (const listing of listingPayloads) {
        const created = await write<{ data: { id: number } }>("post", "/api/caregiver/listings", listing);
        await write("post", `/api/caregiver/listings/${created.data.id}/submit`, {});
      }
      onComplete();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  return (
    <main className={styles.application}>
      <header className={styles.pageHeader}>
        <div>
          <span className={styles.eyebrow}><PettelIcon name="award" /> PETTEL PARTNER VERIFICATION</span>
          <h1>Đăng ký trở thành Đối tác Sitter chuyên nghiệp</h1>
          <p>Hoàn tất 3 bước đơn giản để bắt đầu nhận chăm sóc thú cưng và gia tăng thu nhập linh hoạt cùng cộng đồng PETTEL tại Đà Nẵng và toàn quốc.</p>
        </div>
        <div className={styles.progress}>
          <span>Tiến độ hoàn thiện <strong>{progress}%</strong></span>
          <i><b style={{ width: `${progress}%` }} /></i>
          <small>Đang thực hiện: Bước 0{step + 1} / 03</small>
        </div>
      </header>

      <nav className={styles.steps} aria-label="Các bước đăng ký Sitter">
        {steps.map((item, index) => <button type="button" key={item.title} className={`${styles.stepTab} ${index === step ? styles.current : index < step ? styles.complete : ""}`} onClick={() => { if (index < step) setStep(index); }} aria-current={index === step ? "step" : undefined}>
          <span className={styles.stepIcon}>{index < step ? <PettelIcon name="check" /> : <PettelIcon name={item.icon} />}</span>
          <span><small>BƯỚC 0{index + 1} • {index === step ? "ĐANG LÀM" : index < step ? "ĐÃ HOÀN TẤT" : "TIẾP THEO"}</small><strong>{item.title}</strong><em>{item.caption}</em></span>
        </button>)}
      </nav>

      <div className={styles.layout}>
        <section className={styles.formCard}>
          <ApiError error={error} />
          {step === 0 && <form onSubmit={next}>
            <div className={styles.sectionTitle}><span>1</span><div><h2>Hồ sơ cá nhân &amp; Kinh nghiệm</h2><p>Thông tin hiển thị cho khách hàng khi tìm kiếm người chăm sóc</p></div><b>BẮT BUỘC</b></div>
            <div className={styles.avatarPanel}>
              <label className={styles.avatarPreview}>
                {avatar ? <img src={avatar} alt="Xem trước ảnh đại diện Sitter" /> : <span><PettelIcon name="user" /></span>}
                <input name="avatar" type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => { const file = event.currentTarget.files?.[0]; if (file) setAvatar(URL.createObjectURL(file)); }} />
              </label>
              <div><strong>Ảnh đại diện thân thiện</strong><p>Chọn ảnh rõ mặt, nụ cười thân thiện cùng thú cưng để hồ sơ dễ nhận diện hơn.</p><label className={styles.uploadButton}><PettelIcon name="plus" /> Tải ảnh lên<input name="avatar" type="file" accept="image/png,image/jpeg,image/webp" onChange={(event) => { const file = event.currentTarget.files?.[0]; if (file) setAvatar(URL.createObjectURL(file)); }} /></label><small>JPG, PNG hoặc WEBP dưới 5MB</small></div>
            </div>
            <div className={styles.fields}>
              <label className={styles.fullField}><span className={styles.fieldTitle}>Tên hiển thị công khai <b>*</b></span><small>Hiển thị trên kết quả tìm kiếm</small><input required maxLength={100} value={displayName} onChange={(event) => setDisplayName(event.target.value)} placeholder="Ví dụ: Bảo Trâm (Hải Châu - Pet Lover)" /></label>
              <fieldset className={styles.fullField}><legend>Loại hình hoạt động <b>*</b></legend><div className={styles.radioCards}><label><input type="radio" name="type" checked={type === "INDIVIDUAL"} onChange={() => setType("INDIVIDUAL")} /><span><strong>Cá nhân (Pet Lover / Freelancer)</strong><small>Chăm sóc tại nhà riêng hoặc linh hoạt ghé thăm trực tiếp nhà của khách hàng.</small></span></label><label><input type="radio" name="type" checked={type === "FACILITY"} onChange={() => setType("FACILITY")} /><span><strong>Cơ sở chuyên nghiệp (Home-care / Studio)</strong><small>Cơ sở có phòng chuyên dụng, khuôn viên sẵn sàng riêng biệt cho thú cưng vận động.</small></span></label></div></fieldset>
              <label><span className={styles.fieldTitle}>Khu vực hoạt động chính <b>*</b></span><select value={area} onChange={(event) => setArea(event.target.value)}>{Object.entries(areas).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
              <label><span className={styles.labelHeader}><span>Địa chỉ / Tòa nhà chi tiết</span><span className={styles.optional}>riêng tư</span></span><input name="private_address" maxLength={300} placeholder="Chỉ dùng để xác minh, không hiển thị công khai" /></label>
              <label className={styles.fullField}><span className={styles.fieldTitle}>Giới thiệu bản thân &amp; Kinh nghiệm chăm sóc <b>*</b></span><textarea required minLength={40} maxLength={1000} value={bio} onChange={(event) => setBio(event.target.value)} placeholder="Chia sẻ kinh nghiệm chăm sóc, thói quen chăm bé và điều khiến bạn trở thành một Sitter đáng tin cậy..." /><small className={styles.counter}>{bio.length}/1000 ký tự</small></label>
              <div className={styles.extraPanel}><h3><PettelIcon name="award" /> Kinh nghiệm &amp; sức chứa</h3><p>Các lựa chọn này sẽ được lưu trong hồ sơ riêng của bạn.</p><strong>Loài thú cưng đã có kinh nghiệm:</strong><div className={styles.chips}>{[["DOG_SMALL", "Chó nhỏ (<10kg)"], ["DOG_MEDIUM", "Chó vừa (10–20kg)"], ["DOG_LARGE", "Chó lớn (>20kg)"], ["CAT_SHORT_HAIR", "Mèo lông ngắn"], ["CAT_LONG_HAIR", "Mèo lông dài"], ["SMALL_PETS", "Thỏ / Hamster / Bò ú"]].map(([code, item]) => <label key={code}><input name="experience_species" value={code} type="checkbox" /> {item}</label>)}</div><strong>Kỹ năng chăm sóc đặc biệt:</strong><div className={styles.chips}>{[["MEDICATION", "Biết cho uống thuốc / nhỏ mắt"], ["SENIOR_OR_SHY", "Chăm sóc pet già / tính nhút nhát"], ["SPECIAL_DIET", "Chế độ ăn kiêng / BARF"], ["POST_SURGERY", "Hỗ trợ phục hồi sau tiểu phẫu"]].map(([code, item]) => <label key={code}><input name="care_skills" value={code} type="checkbox" /> {item}</label>)}</div><strong>Số lượng thú cưng tối đa nhận cùng lúc</strong><div className={styles.chips}>{[["1", "1 bé (ưu tiên)"], ["2", "2 bé"], ["4", "3–4 bé (có người phụ trợ)"]].map(([value, item], index) => <label key={value}><input type="radio" name="capacity" value={value} defaultChecked={index === 0} /> {item}</label>)}</div></div>
            </div>
            <div className={styles.formActions}><span>Thông tin có dấu * là bắt buộc</span><button type="submit">Tiếp tục: Dịch vụ &amp; Không gian <PettelIcon name="arrow" /></button></div>
          </form>}

          {step === 1 && <form onSubmit={next}>
            <div className={styles.sectionTitle}><span>2</span><div><h2>Gói dịch vụ &amp; Không gian chăm sóc</h2><p>Thiết lập đơn giá mong muốn và mô tả môi trường sinh hoạt của thú cưng</p></div><b className={styles.infoBadge}>ĐỊNH GIÁ LINH HOẠT</b></div>
            <div className={styles.serviceList}>{services.map((service) => <article className={styles.serviceCard} key={service.id}><label className={styles.serviceHeading}><span className={styles.serviceIcon}><PettelIcon name={service.id === "walking" ? "walk" : service.id === "homevisit" ? "home" : "paw"} /></span><span><strong>{service.title}</strong><small>{service.subtitle}</small></span><input type="checkbox" checked={selectedServices.includes(service.id)} disabled={!service.available} onChange={() => toggleService(service.id)} /></label>{service.available ? <div className={styles.servicePrice}><label>Đơn giá / {service.id === "boarding" ? "đêm" : "giờ"}<input type="number" min="10000" step="10000" value={service.id === "boarding" ? priceBoarding : priceDaycare} onChange={(event) => service.id === "boarding" ? setPriceBoarding(event.target.value) : setPriceDaycare(event.target.value)} /></label><span>VNĐ / {service.id === "boarding" ? "đêm" : "giờ"}</span><small>Đơn giá sẽ được tạo thành dịch vụ và gửi PETTEL xét duyệt cùng hồ sơ.</small></div> : <span className={styles.comingSoon}>Sẽ kết nối sau</span>}</article>)}</div>
            <h3 className={styles.subHeading}>Không gian sống &amp; Tiện ích an toàn</h3>
            <div className={styles.fields}><label>Loại hình nhà ở<select name="housing_type" defaultValue="APARTMENT"><option value="APARTMENT">Chung cư cao tầng</option><option value="HOUSE">Nhà phố có sân</option><option value="VILLA">Biệt thự có rào kín</option></select></label><label>Không gian phù hợp<select name="space_type" defaultValue="INDOOR"><option value="INDOOR">Trong nhà</option><option value="INDOOR_YARD">Trong nhà và sân</option></select></label></div>
            <div className={styles.extraPanel}><strong>Tiện ích an toàn có sẵn</strong><div className={styles.chips}>{[["BALCONY_NET", "Lưới an toàn ban công"], ["HEPA_FILTER", "Máy lọc không khí HEPA"], ["CAGE_FREE", "Không gian không lồng"], ["AIR_CONDITIONING", "Điều hòa mát 24/7"], ["SEPARATE_FOOD_STORAGE", "Tủ lạnh thức ăn riêng"], ["CAMERA", "Camera giám sát"]].map(([code, item]) => <label key={code}><input name="amenities" value={code} type="checkbox" /> {item}</label>)}</div><label className={styles.photoUpload}><PettelIcon name="camera" /><span><strong>Thêm ảnh không gian thực tế</strong><small>JPG, PNG hoặc WEBP · tối đa 8 ảnh</small></span><input name="space_photos" type="file" accept="image/png,image/jpeg,image/webp" multiple /></label></div>
            <div className={styles.formActions}><button className={styles.backButton} type="button" onClick={() => setStep(0)}>Quay lại</button><button type="submit">Tiếp tục: An toàn &amp; Xác minh <PettelIcon name="arrow" /></button></div>
          </form>}

          {step === 2 && <form onSubmit={submit}>
            <div className={styles.sectionTitle}><span>3</span><div><h2>An toàn, Xác minh &amp; Cam kết</h2><p>Bảo vệ quyền lợi của bạn và tạo sự tin tưởng tuyệt đối đối với người gửi pet</p></div><b>BẮT BUỘC</b></div>
            <div className={styles.privacyNotice}><PettelIcon name="shield" /><span><strong>Bảo mật thông tin định danh cấp ngân hàng</strong><small>Thông tin định danh và tài khoản nhạy cảm không được gửi/lưu ở bước giao diện này. PETTEL sẽ bổ sung quy trình xác minh bảo mật riêng.</small></span></div>
            <div className={styles.fields}><label><span className={styles.fieldTitle}>Số điện thoại liên hệ <b>*</b></span><input name="contact_phone" type="tel" required pattern="[0-9+(). -]{9,16}" placeholder="0905 123 456" /></label><label><span className={styles.fieldTitle}>Email nhận thông báo đặt lịch <b>*</b></span><input name="contact_email" type="email" required defaultValue={userEmail} placeholder="ban@example.com" /></label></div>
            <div className={styles.kycBlock}><div className={styles.kycTitle}><strong>Xác minh Căn cước công dân (eKYC) <b>*</b></strong><small>Ảnh được lưu riêng tư, chỉ chủ tài khoản truy cập được</small></div><div className={styles.kycUploads}>{[["kyc_front", "Mặt trước CCCD gắn chip"], ["kyc_back", "Mặt sau CCCD gắn chip"]].map(([name, label]) => <label className={styles.kycUpload} key={name}><PettelIcon name="shield" /><strong>{label}</strong><small>Chọn ảnh JPG, PNG hoặc WEBP</small><input name={name} type="file" accept="image/png,image/jpeg,image/webp" /><em>Tải lên riêng tư</em></label>)}</div></div>
            <label className={styles.safetyPlan}><span className={styles.fieldTitle}>Kế hoạch an toàn &amp; Xử lý tình huống khẩn cấp <b>*</b></span><small>Bạn sẽ làm gì khi thú cưng có dấu hiệu mệt mỏi bất thường?</small><textarea name="safety_plan" required minLength={40} maxLength={1500} placeholder="1. Kiểm tra tình trạng và giữ bé ở nơi an toàn.&#10;2. Liên hệ chủ nuôi để xin ý kiến.&#10;3. Gọi cơ sở thú y gần nhất nếu cần." /></label>
            <div className={styles.certificate}><PettelIcon name="award" /><span><strong>Chứng chỉ thú y (không bắt buộc)</strong><small>Nếu có, tải ảnh để đính kèm hồ sơ Sitter.</small></span><label className={styles.uploadButton}>Chọn ảnh<input name="certificate" type="file" accept="image/png,image/jpeg,image/webp" /></label></div>
            <div className={styles.agreements}><label><input name="partner_terms" type="checkbox" required /> Tôi đồng ý với Quy chế hoạt động đối tác, Tiêu chuẩn phúc lợi động vật và Chính sách PETTEL.</label><label><input name="care_commitment" type="checkbox" required /> Tôi cam kết cung cấp môi trường an toàn, yêu thương bé như con ruột và cập nhật hình ảnh/video đúng hẹn.</label></div>
            <div className={styles.submitNotice}>Thông tin hồ sơ và lựa chọn của bạn sẽ được lưu. Ảnh đại diện, ảnh không gian, eKYC và chứng chỉ được tải lên riêng tư, không hiển thị công khai.</div>
            <div className={styles.formActions}><button className={styles.backButton} type="button" onClick={() => setStep(1)}>Quay lại</button><button type="submit" disabled={busy}>{busy ? "Đang nộp hồ sơ..." : "Nộp hồ sơ Sitter"}<PettelIcon name="arrow" /></button></div>
          </form>}
        </section>

        <aside className={styles.sidebar}>
          <section className={styles.previewCard}><div className={styles.previewHeading}><span>XEM TRƯỚC THẺ HIỂN THỊ</span><b>● Bản nháp</b></div><div className={styles.previewImage}>{avatar ? <img src={avatar} alt="Ảnh xem trước hồ sơ Sitter" /> : <span><PettelIcon name="paw" /></span>}<small>Hồ sơ PETTEL Sitter</small></div><div className={styles.previewInfo}><h3>{displayName.trim() || "Tên hiển thị của bạn"}<PettelIcon name="award" /></h3><p><PettelIcon name="pin" /> {areas[area as keyof typeof areas]} · Khu vực hoạt động</p><div><span>{type === "INDIVIDUAL" ? "Cá nhân" : "Cơ sở chuyên nghiệp"}</span><span>Bản nháp</span></div><strong>Giá dịch vụ sẽ hiển thị sau khi tạo listing</strong></div><p className={styles.previewHint}>Thẻ Sitter chỉ xuất hiện trên kết quả tìm kiếm sau khi dịch vụ được PETTEL duyệt.</p></section>
          <section className={styles.rightCard}><h3><PettelIcon name="shield" /> Quyền lợi Sitter PETTEL</h3><p><PettelIcon name="check" /> Hỗ trợ thú y và chăm sóc an toàn</p><p><PettelIcon name="check" /> Kết nối khách hàng đã xác minh</p><p><PettelIcon name="check" /> Theo dõi lịch và đơn trên cùng tài khoản</p></section>
          <section className={`${styles.rightCard} ${styles.supportCard}`}><h3><PettelIcon name="message" /> Cần hỗ trợ đăng ký?</h3><p>Đội ngũ PETTEL sẵn sàng hướng dẫn bạn hoàn thiện hồ sơ.</p><a href="/contact-support"><PettelIcon name="mail" /> Liên hệ PETTEL</a></section>
        </aside>
      </div>
    </main>
  );
}
