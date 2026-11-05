"use client";

import { useEffect, useState } from "react";
import { AppShell, useSession } from "../components/Shell";
import { Card, Notice } from "../components/ui";
import { ApiError, call } from "../lib/api";
import { naira } from "../lib/format";

type Stats = {
  users: { total: number; new_7d: number; active_24h: number; by_role: Record<string, number>; wallets_linked: number };
  collectors: { total: number; verified: number };
  savers: { total: number; active: number; marked_last_7d: number };
  marks: { today: number; last_7d: number; volume_kobo_today: number; volume_kobo_7d: number; volume_kobo_30d: number };
  ai: { questions: number; last_24h: number; failed: number };
  referrals: Record<string, number>;
  events_7d: Record<string, number>;
};

export default function AdminPage() {
  const { user, logout } = useSession(["admin"]);
  return user ? <AppShell user={user} onLogout={logout}><Body /></AppShell> : null;
}

function Body() {
  const [s, setS] = useState<Stats | null>(null);
  const [err, setErr] = useState<{ ok: boolean; text: string } | null>(null);
  useEffect(() => { call<Stats>("/admin/stats").then(setS).catch((e) => setErr({ ok: false, text: e instanceof ApiError ? e.message : "Could not load." })); }, []);
  if (!s) return <Notice n={err} />;

  const Stat = ({ label, value, sub }: { label: string; value: string | number; sub?: string }) => (
    <Card><p className="text-xs font-bold uppercase tracking-wide text-forest-900/50">{label}</p><p className="mt-1 text-3xl font-extrabold text-forest-900">{value}</p>{sub && <p className="text-xs text-forest-900/60">{sub}</p>}</Card>
  );

  return (
    <>
      <h1 className="text-2xl text-forest-900">Traction</h1>
      <div className="mt-4 grid grid-cols-2 gap-3">
        <Stat label="Users" value={s.users.total} sub={`${s.users.new_7d} new this week`} />
        <Stat label="Active (24h)" value={s.users.active_24h} />
        <Stat label="Collectors" value={s.collectors.total} sub={`${s.collectors.verified} verified`} />
        <Stat label="Savers" value={s.savers.total} sub={`${s.savers.marked_last_7d} paid this week`} />
        <Stat label="Marks today" value={s.marks.today} sub={naira(s.marks.volume_kobo_today)} />
        <Stat label="Volume (7 days)" value={naira(s.marks.volume_kobo_7d)} />
        <Stat label="Volume (30 days)" value={naira(s.marks.volume_kobo_30d)} />
        <Stat label="Wallets linked" value={s.users.wallets_linked} />
        <Stat label="AI questions" value={s.ai.questions} sub={`${s.ai.failed} unanswered`} />
        <Stat label="Referrals" value={Object.values(s.referrals).reduce((a, b) => a + b, 0)} />
      </div>
      <Card className="mt-4">
        <h2 className="text-base text-forest-900">Activity, last 7 days</h2>
        <ul className="mt-2 space-y-1 text-sm">
          {Object.entries(s.events_7d).map(([k, v]) => <li key={k} className="flex justify-between"><span>{k.replaceAll("_", " ")}</span><b>{v}</b></li>)}
        </ul>
      </Card>
    </>
  );
}
