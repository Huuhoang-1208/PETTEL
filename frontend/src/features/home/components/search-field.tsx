"use client";

import { PettelIcon, type PettelIconName } from "@/components/ui/pettel-icon";
import styles from "@/features/home/styles/search.module.css";
import { Field } from "@/features/home/types";
import { useId, type ReactNode } from "react";

export function SearchField({
  field,
  label,
  value,
  icon,
  open,
  setOpen,
  tone,
  compact,
  children,
}: {
  field: Field;
  label: string;
  value: string;
  icon: PettelIconName;
  open: Field | null;
  setOpen: (field: Field | null) => void;
  tone: "blue" | "orange" | "purple" | "teal";
  compact?: boolean;
  children: ReactNode;
}) {
  const id = useId();
  const expanded = open === field;
  return (
    <div className={`${styles.field} ${compact ? styles.compactField : ""}`}>
      <button
        type="button"
        className={`${styles.fieldButton} ${expanded ? styles.fieldActive : ""}`}
        aria-expanded={expanded}
        aria-controls={id}
        onClick={() => setOpen(expanded ? null : field)}
      >
        <span className={`${styles.fieldIcon} ${styles[tone]}`}>
          <PettelIcon name={icon} />
        </span>
        <span className={styles.fieldText}>
          <span className={styles.fieldLabel}>{label}</span>
          <span className={styles.fieldValue}>{value}</span>
        </span>
        {!compact && field === "service" && (
          <PettelIcon name="chevron" className={styles.fieldChevron} />
        )}
      </button>
      {expanded && (
        <div id={id} className={styles.fieldPanel} role="group" aria-label={label}>
          {children}
        </div>
      )}
    </div>
  );
}
