/** Owns the prototype's DOM listeners and timers for one React mount. */
export function createPageScope(navigate: (href: string) => void) {
  const controller = new AbortController();
  const timeouts = new Set<number>();
  const intervals = new Set<number>();
  const disposers: Array<() => void> = [];

  return {
    navigate,
    get active() {
      return !controller.signal.aborted;
    },
    listen(
      target: EventTarget,
      type: string,
      listener: EventListenerOrEventListenerObject,
      options?: boolean | AddEventListenerOptions,
    ) {
      // Effects run after DOMContentLoaded on client navigation as well.
      if (target === document && type === "DOMContentLoaded") {
        if (typeof listener === "function") listener.call(document, new Event(type));
        else listener.handleEvent(new Event(type));
        return;
      }
      target.addEventListener(type, listener, {
        ...(typeof options === "boolean" ? { capture: options } : options),
        signal: controller.signal,
      });
    },
    setTimeout(callback: () => void, delay = 0) {
      const id = window.setTimeout(() => {
        timeouts.delete(id);
        if (!controller.signal.aborted) callback();
      }, delay);
      timeouts.add(id);
      return id;
    },
    clearTimeout(id: number) {
      window.clearTimeout(id);
      timeouts.delete(id);
    },
    setInterval(callback: () => void, delay: number) {
      const id = window.setInterval(callback, delay);
      intervals.add(id);
      return id;
    },
    clearInterval(id: number) {
      window.clearInterval(id);
      intervals.delete(id);
    },
    onCleanup(dispose: () => void) {
      disposers.push(dispose);
    },
    cleanup() {
      controller.abort();
      timeouts.forEach((id) => window.clearTimeout(id));
      intervals.forEach((id) => window.clearInterval(id));
      disposers.forEach((dispose) => dispose());
    },
  };
}

export type PageScope = ReturnType<typeof createPageScope>;
