import type { ButtonHTMLAttributes, ReactNode } from "react";

export function Logo({ size = 32, light = false }: { size?: number; light?: boolean }) {
  return (
    <span className="inline-flex items-center gap-2">
      <svg width={size} height={size} viewBox="0 0 200 200" aria-hidden="true">
        <rect width="200" height="200" rx="44" fill={light ? "#14674c" : "#0b3d2e"} />
        <g fill="#fff">
          <rect x="52" y="56" width="11" height="88" rx="5.5" />
          <rect x="75" y="56" width="11" height="88" rx="5.5" />
          <rect x="98" y="56" width="11" height="88" rx="5.5" />
          <rect x="121" y="56" width="11" height="88" rx="5.5" />
        </g>
        <line x1="42" y1="132" x2="148" y2="68" stroke="#f2b544" strokeWidth="12" strokeLinecap="round" />
      </svg>
      <span className={`text-xl font-extrabold ${light ? "text-white" : "text-forest-900"}`}>markd</span>
    </span>
  );
}

type BtnProps = ButtonHTMLAttributes<HTMLButtonElement> & { variant?: "primary" | "dark" | "ghost" | "danger" };
export function Btn({ variant = "primary", className = "", ...p }: BtnProps) {
  const v = {
    primary: "bg-gold-400 text-forest-950 hover:bg-gold-500",
    dark: "bg-forest-900 text-white hover:bg-forest-700",
    ghost: "border border-forest-900/20 bg-white text-forest-900 hover:bg-cream-100",
    danger: "border border-red-300 bg-white text-red-700",
  }[variant];
  return (
    <button
      className={`inline-flex items-center justify-center gap-2 rounded-xl px-5 py-3 text-sm font-bold transition hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50 ${v} ${className}`}
      {...p}
    />
  );
}

export function Card({ children, className = "" }: { children: ReactNode; className?: string }) {
  return <section className={`rounded-2xl bg-white p-5 shadow-[0_12px_40px_-28px_rgba(6,42,32,0.38)] ring-1 ring-forest-900/8 ${className}`}>{children}</section>;
}

export const inputCls =
  "w-full rounded-xl border border-forest-900/15 bg-white px-4 py-3 text-base outline-none transition placeholder:text-forest-900/40 focus:border-forest-700 focus:ring-2 focus:ring-forest-700/20";

export function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-sm font-semibold text-forest-900">
      {label}
      <div className="mt-1 font-normal">{children}</div>
    </label>
  );
}

const STATUS: Record<string, { label: string; cls: string }> = {
  on_track: { label: "On track", cls: "bg-emerald-100 text-emerald-800" },
  late: { label: "Late", cls: "bg-amber-100 text-amber-800" },
  at_risk: { label: "At risk", cls: "bg-orange-100 text-orange-800" },
  defaulted: { label: "Defaulted", cls: "bg-red-100 text-red-800" },
};
export function StatusPill({ status }: { status: string }) {
  const s = STATUS[status] ?? { label: status.replaceAll("_", " "), cls: "bg-slate-100 text-slate-700" };
  return <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${s.cls}`}>{s.label}</span>;
}

export function ScoreRing({ score, size = 84 }: { score: number; size?: number }) {
  const r = 36;
  const c = 2 * Math.PI * r;
  const safeScore = Math.max(0, Math.min(100, score));
  const color = safeScore >= 85 ? "#14674c" : safeScore >= 60 ? "#e3a22b" : "#c2410c";
  return (
    <svg width={size} height={size} viewBox="0 0 90 90" role="img" aria-label={`Reliability score ${score} out of 100`}>
      <circle cx="45" cy="45" r={r} fill="none" stroke="#f3ecdc" strokeWidth="8" />
      <circle cx="45" cy="45" r={r} fill="none" stroke={color} strokeWidth="8" strokeLinecap="round" strokeDasharray={`${(c * safeScore) / 100} ${c}`} transform="rotate(-90 45 45)" />
      <text x="45" y="52" textAnchor="middle" fontSize="22" fontWeight="800" fill="#10231a">{safeScore}</text>
    </svg>
  );
}

export function Notice({ n }: { n: { ok: boolean; text: string } | null }) {
  if (!n) return null;
  return (
    <p role={n.ok ? "status" : "alert"} className={`mb-4 rounded-xl px-4 py-3 text-sm font-semibold ${n.ok ? "bg-emerald-100 text-emerald-900" : "bg-red-100 text-red-800"}`}>
      {n.text}
    </p>
  );
}

export function Progress({ value, max }: { value: number; max: number }) {
  const safeMax = Math.max(max, 1);
  const safeValue = Math.max(0, Math.min(value, safeMax));
  return (
    <div className="h-2.5 w-full rounded-full bg-cream-100" role="progressbar" aria-valuenow={safeValue} aria-valuemax={safeMax}>
      <div className="h-2.5 rounded-full bg-forest-700 transition-[width]" style={{ width: `${(safeValue / safeMax) * 100}%` }} />
    </div>
  );
}
