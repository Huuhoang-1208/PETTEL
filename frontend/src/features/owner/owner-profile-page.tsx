"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { OwnerDashboard } from "./components/owner-dashboard";
import styles from "./owner-profile.module.css";

export function OwnerProfilePage() {
  return <><SiteHeader /><main className={styles.page}><RequireSession next="/owner-profile"><OwnerDashboard /></RequireSession></main></>;
}
