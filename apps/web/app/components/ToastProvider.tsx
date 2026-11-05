"use client";

import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from "react";

type ToastKind = "success" | "error" | "info";
type Toast = { id: number; kind: ToastKind; message: string };
type ToastContextValue = { notify: (message: string, kind?: ToastKind) => void };

const ToastContext = createContext<ToastContextValue | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const nextId = useRef(0);
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());

  const dismiss = useCallback((id: number) => {
    const timer = timers.current.get(id);
    if (timer) clearTimeout(timer);
    timers.current.delete(id);
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const notify = useCallback((message: string, kind: ToastKind = "success") => {
    const id = ++nextId.current;
    setToasts((current) => [...current.slice(-2), { id, kind, message }]);
    timers.current.set(id, setTimeout(() => dismiss(id), kind === "error" ? 7000 : 4500));
  }, [dismiss]);

  useEffect(() => () => {
    timers.current.forEach(clearTimeout);
    timers.current.clear();
  }, []);

  return (
    <ToastContext.Provider value={{ notify }}>
      {children}
      <div className="pointer-events-none fixed inset-x-0 top-4 z-[100] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-5 sm:items-end sm:px-0" aria-live="polite" aria-relevant="additions">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            role={toast.kind === "error" ? "alert" : "status"}
            className={`toast-enter pointer-events-auto flex w-full max-w-md items-start gap-3 rounded-2xl border px-4 py-3 shadow-xl ${
              toast.kind === "success"
                ? "border-emerald-200 bg-white text-forest-950"
                : toast.kind === "error"
                  ? "border-red-200 bg-white text-red-900"
                  : "border-forest-100 bg-white text-forest-950"
            }`}
          >
            <span className={`mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-sm font-extrabold ${
              toast.kind === "success" ? "bg-emerald-100 text-emerald-800" : toast.kind === "error" ? "bg-red-100 text-red-800" : "bg-cream-100 text-forest-900"
            }`} aria-hidden="true">
              {toast.kind === "success" ? "✓" : toast.kind === "error" ? "!" : "i"}
            </span>
            <p className="min-w-0 flex-1 pt-0.5 text-sm font-semibold leading-5">{toast.message}</p>
            <button
              type="button"
              onClick={() => dismiss(toast.id)}
              className="rounded-lg p-1 text-current/60 transition hover:bg-black/5 hover:text-current focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-700"
              aria-label="Dismiss notification"
            >
              <svg viewBox="0 0 20 20" fill="none" className="size-4" aria-hidden="true">
                <path d="m5 5 10 10M15 5 5 15" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
              </svg>
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const context = useContext(ToastContext);
  if (!context) throw new Error("useToast must be used within ToastProvider");
  return context;
}
