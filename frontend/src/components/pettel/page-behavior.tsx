"use client";

import { Suspense, useEffect } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { initializeDemoPets } from "@/lib/pettel/demo-data";
import { createPageScope } from "@/lib/pettel/page-scope";

const behaviors = {
  home: () => import("@/lib/pettel/home"),
  detail: () => import("@/lib/pettel/detail"),
  "detail-dotung": () => import("@/lib/pettel/detail-dotung"),
  "detail-minhnhat": () => import("@/lib/pettel/detail-minhnhat"),
  "detail-baotram": () => import("@/lib/pettel/detail-baotram"),
  "detail-baotram-house": () => import("@/lib/pettel/detail-baotram-house"),
  videocall: () => import("@/lib/pettel/videocall"),
  livecam: () => import("@/lib/pettel/livecam"),
};

export function PageBehavior({ kind }: { kind: keyof typeof behaviors }) {
  return <Suspense><BehaviorEffect kind={kind} /></Suspense>;
}

function BehaviorEffect({ kind }: { kind: keyof typeof behaviors }) {
  const router = useRouter();
  const query = useSearchParams().toString();
  useEffect(() => {
    const scope = createPageScope((href) => router.push(href));
    void behaviors[kind]().then(({ default: initialize }) => {
      if (!scope.active) return;
      initializeDemoPets();
      initialize(scope);
    }).catch((error: unknown) => {
      console.error("Không thể khởi tạo giao diện PETTEL", error);
    });
    return () => scope.cleanup();
  }, [kind, query, router]);

  return null;
}
