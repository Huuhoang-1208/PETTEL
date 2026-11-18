"use client";

import { initializeDemoPets } from "@/legacy/scripts/demo-data";
import { createPageScope } from "@/legacy/scripts/page-scope";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect } from "react";

const behaviors = {
  home: () => import("@/legacy/scripts/home"),
  detail: () => import("@/legacy/scripts/detail"),
  "detail-dotung": () => import("@/legacy/scripts/detail-dotung"),
  "detail-minhnhat": () => import("@/legacy/scripts/detail-minhnhat"),
  "detail-baotram": () => import("@/legacy/scripts/detail-baotram"),
  "detail-baotram-house": () => import("@/legacy/scripts/detail-baotram-house"),
  videocall: () => import("@/legacy/scripts/videocall"),
  livecam: () => import("@/legacy/scripts/livecam"),
};

export function PageBehavior({ kind }: { kind: keyof typeof behaviors }) {
  return (
    <Suspense>
      <BehaviorEffect kind={kind} />
    </Suspense>
  );
}

function BehaviorEffect({ kind }: { kind: keyof typeof behaviors }) {
  const router = useRouter();
  const query = useSearchParams().toString();
  useEffect(() => {
    const scope = createPageScope((href) => router.push(href));
    void behaviors[kind]()
      .then(({ default: initialize }) => {
        if (!scope.active) return;
        initializeDemoPets();
        initialize(scope);
      })
      .catch((error: unknown) => {
        console.error("Không thể khởi tạo giao diện PETTEL", error);
      });
    return () => scope.cleanup();
  }, [kind, query, router]);

  return null;
}
