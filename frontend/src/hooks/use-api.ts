"use client";

import { api } from "@/lib/api/client";
import { useEffect, useState } from "react";

export function useApi<T>(path: string | null, pollMs = 0) {
  const [version, setVersion] = useState(0);
  const [state, setState] = useState<{
    path: string | null;
    version: number;
    data?: T;
    error?: unknown;
  }>({ path: null, version: -1 });
  useEffect(() => {
    if (!path) return;
    const controller = new AbortController();
    let request = 0;
    async function load() {
      const current = ++request;
      try {
        const { data } = await api.get<T>(path!, {
          signal: controller.signal,
        });
        if (!controller.signal.aborted && current === request) setState({ path, version, data });
      } catch (error) {
        if (!controller.signal.aborted && current === request) setState({ path, version, error });
      }
    }
    void load();
    const timer = pollMs
      ? window.setInterval(() => {
          if (!document.hidden) void load();
        }, pollMs)
      : undefined;
    return () => {
      controller.abort();
      window.clearInterval(timer);
    };
  }, [path, version, pollMs]);
  return {
    data: state.path === path ? state.data : undefined,
    error: state.path === path ? state.error : undefined,
    loading: !!path && (state.path !== path || state.version !== version),
    reload: () => setVersion((value) => value + 1),
  };
}
