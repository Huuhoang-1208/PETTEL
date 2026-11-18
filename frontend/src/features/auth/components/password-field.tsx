"use client";
import { useState } from "react";
import { PettelIcon } from "@/components/ui/pettel-icon";
import styles from "../styles/form.module.css";
export function PasswordField({
  label,
  name,
  registering,
  disabled,
  forgot,
}: {
  label: string;
  name: string;
  registering: boolean;
  disabled: boolean;
  forgot?: () => void;
}) {
  const [visible, setVisible] = useState(false);
  return (
    <div className={styles.formField}>
      <div className={styles.labelRow}>
        <label htmlFor={name}>{label}</label>
        {forgot && (
          <button type="button" disabled={disabled} onClick={forgot}>
            Quên mật khẩu?
          </button>
        )}
      </div>
      <div className={styles.inputWrap}>
        <PettelIcon name="key" />
        <input
          id={name}
          name={name}
          type={visible ? "text" : "password"}
          required
          disabled={disabled}
          minLength={registering ? 8 : undefined}
          autoComplete={registering ? "new-password" : "current-password"}
          placeholder={registering ? "Ít nhất 8 ký tự" : "Nhập mật khẩu của bạn"}
        />
        <button
          className={styles.reveal}
          type="button"
          aria-label={visible ? `Ẩn ${label.toLowerCase()}` : `Hiện ${label.toLowerCase()}`}
          aria-pressed={visible}
          onClick={() => setVisible(!visible)}
        >
          <PettelIcon name={visible ? "eye-off" : "eye"} />
        </button>
      </div>
    </div>
  );
}
