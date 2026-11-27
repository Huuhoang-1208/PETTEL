"use client";
import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import Link from "next/link";
import type { AuthFormState } from "../hooks/use-auth-form";
import styles from "../styles/form.module.css";
import { AuthRoleSelector } from "./auth-role-selector";
import { PasswordField } from "./password-field";
export function AuthForm({ auth }: { auth: AuthFormState }) {
  const { mode, role, setRole, error, busy, sent, changeMode, submit } = auth;
  const registering = mode === "register";
  const forgot = mode === "forgot-password";
  const backendUrl = (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8080").replace(
    /\/$/,
    "",
  );
  const googleRedirectUrl = `${backendUrl}/auth/google/redirect`;
  return (
    <section className={styles.formPanel} aria-label="Tài khoản PETTEL">
      <div className={styles.formContent}>
        <div
          className={styles.tabs}
          data-registering={registering}
          aria-label="Chọn đăng nhập hoặc đăng ký"
        >
          <button
            type="button"
            className={!registering ? styles.activeTab : ""}
            aria-pressed={!registering}
            disabled={busy}
            onClick={() => changeMode("login")}
          >
            Đăng nhập
          </button>
          <button
            type="button"
            className={registering ? styles.activeTab : ""}
            aria-pressed={registering}
            disabled={busy}
            onClick={() => changeMode("register")}
          >
            Đăng ký tài khoản
          </button>
        </div>
        {forgot ? (
          <div className={styles.forgotHeading}>
            <h2>Quên mật khẩu?</h2>
            <p>Nhập email của bạn để nhận liên kết đặt lại mật khẩu.</p>
          </div>
        ) : (
          <>
            <AuthRoleSelector role={role} onChange={setRole} disabled={busy} />
            <div className={styles.socialOptions}>
              <a
                href={googleRedirectUrl}
                aria-disabled={busy}
                tabIndex={busy ? -1 : undefined}
                onClick={busy ? (event) => event.preventDefault() : undefined}
              >
                <span className={styles.googleMark}>G</span>
                Tiếp tục bằng tài khoản Google
              </a>
              <div>
                <button type="button" disabled>
                  <PettelIcon name="user" />
                  Apple ID
                </button>
                <button type="button" disabled>
                  <PettelIcon name="clock" />
                  OTP không mật khẩu
                </button>
              </div>
              <small>Apple ID và OTP sẽ sớm có mặt.</small>
            </div>
            <div className={styles.divider}>
              <span>hoặc qua thông tin cá nhân</span>
            </div>
          </>
        )}
        <ApiError error={error} />
        {sent ? (
          <div className={styles.sent} role="status">
            <PettelIcon name="mail" />
            <h2>Kiểm tra hộp thư của bạn</h2>
            <p>
              Yêu cầu đã được xử lý. Nếu email có tài khoản, bạn sẽ nhận được liên kết đặt lại mật
              khẩu.
            </p>
          </div>
        ) : (
          <form className={styles.form} key={mode} onSubmit={submit}>
            {registering && (
              <label className={styles.formField}>
                Họ và tên
                <div className={styles.inputWrap}>
                  <PettelIcon name="user" />
                  <input
                    name="name"
                    required
                    disabled={busy}
                    maxLength={100}
                    autoComplete="name"
                    placeholder="Tên của bạn"
                  />
                </div>
              </label>
            )}
            <label className={styles.formField}>
              Email
              <div className={styles.inputWrap}>
                <PettelIcon name="mail" />
                <input
                  name="email"
                  type="email"
                  required
                  disabled={busy}
                  autoComplete="email"
                  placeholder="ban@example.com"
                />
              </div>
            </label>
            {!forgot && (
              <PasswordField
                label="Mật khẩu"
                name="password"
                registering={registering}
                disabled={busy}
                forgot={!registering ? () => changeMode("forgot-password") : undefined}
              />
            )}
            {registering && (
              <PasswordField
                label="Nhập lại mật khẩu"
                name="password_confirmation"
                registering
                disabled={busy}
              />
            )}
            <button className={styles.submit} disabled={busy}>
              {busy
                ? "Đang xử lý..."
                : forgot
                  ? "Gửi liên kết đặt lại"
                  : registering
                    ? "Tạo tài khoản PETTEL"
                    : "Đăng Nhập Vào PETTEL"}
              <PettelIcon name="arrow" />
            </button>
          </form>
        )}
        {forgot && (
          <button className={styles.backLink} disabled={busy} onClick={() => changeMode("login")}>
            ← Quay lại đăng nhập
          </button>
        )}
      </div>
      <div className={styles.helpRow}>
        <span>
          <PettelIcon name="heart" />
          Luôn đồng hành cùng bé cưng
        </span>
        <Link href="/services">Khám phá dịch vụ</Link>
        <Link href="/about">Về PETTEL</Link>
        <Link href="/admin/login">Đăng nhập nội bộ</Link>
      </div>
    </section>
  );
}
