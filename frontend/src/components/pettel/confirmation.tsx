"use client";

import { useCallback, useEffect, useId, useRef, useState } from "react";

type Request = { message: string; resolve: (accepted: boolean) => void; previousFocus: HTMLElement | null };

/** App-owned confirmation remains accessible without a blocking browser dialog. */
export function useConfirmation() {
  const [request, setRequest] = useState<Request | null>(null);
  const pending = useRef<Request | null>(null);
  const titleId = useId();
  const finish = useCallback((accepted: boolean) => {
    const current = pending.current;
    pending.current = null;
    setRequest(null);
    current?.resolve(accepted);
    current?.previousFocus?.focus();
  }, []);
  const ask = useCallback((message: string) => new Promise<boolean>((resolve) => {
    pending.current?.resolve(false);
    const value = { message, resolve, previousFocus: document.activeElement instanceof HTMLElement ? document.activeElement : null };
    pending.current = value;
    setRequest(value);
  }), []);
  useEffect(() => () => { pending.current?.resolve(false); }, []);
  useEffect(() => {
    if (!request) return;
    const original = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKey = (event: KeyboardEvent) => { if (event.key === "Escape") finish(false); };
    document.addEventListener("keydown", onKey);
    return () => { document.body.style.overflow = original; document.removeEventListener("keydown", onKey); };
  }, [request, finish]);

  const dialog = request && <div className="pet-modal" style={{ display: "flex" }}><section className="pet-modal-content" role="dialog" aria-modal="true" aria-labelledby={titleId}>
    <h2 id={titleId}>Xác nhận thao tác</h2><p>{request.message}</p><div className="app-actions"><button autoFocus onClick={() => finish(false)}>Quay lại</button><button className="app-button" onClick={() => finish(true)}>Xác nhận</button></div>
  </section></div>;
  return { ask, dialog };
}
