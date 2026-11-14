import { PettelIcon } from "@/components/ui/pettel-icon";
import type { AuthRole } from "../hooks/use-auth-form";
import styles from "../styles/form.module.css";
export function AuthRoleSelector({
  role,
  onChange,
  disabled,
}: {
  role: AuthRole;
  onChange: (role: AuthRole) => void;
  disabled: boolean;
}) {
  return (
    <fieldset className={styles.roles} disabled={disabled}>
      <legend>VAI TRÒ CỦA BẠN TRÊN PETTEL</legend>
      {(
        [
          {
            value: "OWNER",
            icon: "paw",
            title: "Phụ huynh Pet",
            description: "Tìm sitter & gửi cưng an tâm",
          },
          {
            value: "CAREGIVER",
            icon: "home",
            title: "Trở thành Sitter",
            description: "Chia sẻ tình yêu, nhận chăm bé",
          },
        ] as const
      ).map((item) => (
        <label
          className={`${styles.role} ${role === item.value ? styles.roleSelected : ""}`}
          key={item.value}
        >
          <input
            type="radio"
            name="auth-role"
            value={item.value}
            checked={role === item.value}
            onChange={() => onChange(item.value)}
          />
          <span
            className={`${styles.roleIcon} ${item.value === "CAREGIVER" ? styles.sitterIcon : ""}`}
          >
            <PettelIcon name={item.icon} />
          </span>
          <span>
            <strong>
              {item.title}
              {role === item.value && <PettelIcon name="check" />}
            </strong>
            <small>{item.description}</small>
          </span>
        </label>
      ))}
    </fieldset>
  );
}
