/* eslint-disable @next/next/no-img-element -- Private account avatar uses authenticated media. */
import { PettelIcon } from "@/components/ui/pettel-icon";
import { areas } from "@/lib/constants";
import { apiUrl } from "@/lib/format";
import type { OwnerProfile } from "../types";
import styles from "../owner-profile.module.css";

export function OwnerSidebar({ profile, name, email }: { profile?: OwnerProfile; name: string; email: string }) {
  const initials = name.trim().split(/\s+/).slice(-2).map((part) => part.charAt(0)).join("").toUpperCase();
  return <aside className={styles.sidebar}>
    <div className={styles.cover} />
    <div className={styles.sidebarContent}>
      <div className={styles.avatar}>{profile?.avatar_media_id ? <img src={apiUrl(`/api/v1/owner-profile/media/${profile.avatar_media_id}/content`)} alt={`Ảnh đại diện ${name}`} /> : <span>{initials || <PettelIcon name="user" />}</span>}</div>
      <h2>{name}</h2><p className={styles.email}><PettelIcon name="mail" />{email}</p>
      <p className={styles.member}><PettelIcon name="pin" />{profile?.created_at ? `Thành viên từ ${new Date(profile.created_at).toLocaleDateString("vi-VN", { month: "2-digit", year: "numeric" })}` : "Thành viên PETTEL"}{profile?.area_code && ` • ${areas[profile.area_code]}`}</p>
      <section className={styles.greeting}><h3><PettelIcon name="message" /> Lời chào từ chủ nuôi</h3><p>{profile?.bio || "Chào mừng đến với hồ sơ của tôi! Tôi luôn mong các bé được chăm sóc chu đáo và yêu thương như một thành viên trong gia đình."}</p></section>
      <section className={styles.trust}><h3><PettelIcon name="shield" /> Thông tin tin cậy PETTEL</h3>
        <div><PettelIcon name="user" /><p><strong>Hồ sơ tài khoản</strong><span>Thông tin do chủ nuôi cung cấp</span></p></div>
        <div><PettelIcon name="message" /><p><strong>Số điện thoại liên hệ</strong><span>{profile?.phone || "Chưa cập nhật số điện thoại"}</span>{profile?.phone && <small>Chưa xác thực OTP</small>}</p></div>
        <div><PettelIcon name={profile?.email_verified ? "check" : "mail"} /><p><strong>Email định danh tài khoản</strong><span>{profile?.email_verified ? "Đã xác minh email" : "Email đăng nhập chưa được xác minh"}</span></p></div>
        <div className={styles.guarantee}><PettelIcon name="shield" /><p><strong>Đồng hành cùng PETTEL Care</strong><span>Theo dõi lịch chăm sóc và trao đổi với Sitter ngay trên nền tảng.</span></p></div>
      </section>
    </div>
  </aside>;
}
