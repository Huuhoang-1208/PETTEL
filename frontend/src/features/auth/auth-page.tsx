"use client";
import { SiteHeader } from "@/components/layout/site-header";
import { AuthShowcase } from "./components/auth-showcase";
import { AuthForm } from "./components/auth-form";
import { useAuthForm } from "./hooks/use-auth-form";
import styles from "./styles/login.module.css";
export function AuthPage() {
  const auth = useAuthForm();
  return (
    <>
      <SiteHeader />
      <main className={styles.page}>
        <div className={styles.card} data-registering={auth.mode === "register"}>
          <div className={styles.formSide}>
            <AuthForm auth={auth} />
          </div>
          <div className={styles.imageSide}>
            <AuthShowcase />
          </div>
        </div>
      </main>
    </>
  );
}
