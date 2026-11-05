"use client";

import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Card, Logo, ScoreRing } from "../components/ui";
import { ApiError, call } from "../lib/api";
import { shortDate } from "../lib/format";
import type { Passport } from "../saver/passport";

function View() {
  const t = useSearchParams().get("t") ?? "";
  const [p, setP] = useState<Passport | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    if (!t) { setErr("This link is incomplete."); return; }
    call<Passport>(`/passport/${encodeURIComponent(t)}`, { auth: false }).then(setP).catch((e) => setErr(e instanceof ApiError ? e.message : "Could not open this passport."));
  }, [t]);

  return (
    <main className="mx-auto max-w-lg px-5 py-10">
      <Link href="/"><Logo /></Link>
      <h1 className="mt-8 text-3xl text-forest-900">Savings passport</h1>
      {err && <Card className="mt-5"><p className="text-sm font-semibold text-red-700">{err}</p></Card>}
      {p && !p.empty && (
        <>
          <Card className="mt-5">
            <div className="flex items-center gap-4">
              <ScoreRing score={p.reliability.score} />
              <div>
                <p className="text-xl font-extrabold text-forest-900">{p.name}</p>
                <p className="text-sm text-forest-900/70">Reliability: {p.reliability.label}</p>
                <p className="mt-1 inline-block rounded-full bg-gold-400 px-3 py-1 text-xs font-extrabold text-forest-950">{p.readiness.band}</p>
              </div>
            </div>
            <p className="mt-3 text-sm text-forest-900/80">{p.readiness.note}</p>
          </Card>
          <Card className="mt-4">
            <dl className="space-y-2 text-sm">
              <Row k="Total saved" v={`₦${p.summary.total_saved_naira}`} />
              <Row k="Days saved" v={String(p.summary.days_marked)} />
              <Row k="Saving since" v={p.summary.saving_since ? shortDate(p.summary.saving_since) : "-"} />
              <Row k="Collector" v={p.summary.collectors.join(", ")} />
            </dl>
          </Card>
          <Card className="mt-4">
            <h2 className="text-base text-forest-900">Check the records yourself</h2>
            <ul className="mt-2 space-y-1 text-sm">
              {p.recent_records.map((r) => (
                <li key={r.ref} className="flex justify-between">
                  <span>{shortDate(r.date)}, ₦{r.amount_naira}</span>
                  <Link className="font-bold text-forest-700 underline" href={`/verify/?ref=${r.ref}`}>Verify</Link>
                </li>
              ))}
            </ul>
          </Card>
          <p className="mt-4 text-xs text-forest-900/60">{p.disclaimer}</p>
        </>
      )}
    </main>
  );
}

const Row = ({ k, v }: { k: string; v: string }) => (
  <div className="flex justify-between gap-4 border-b border-cream-100 pb-2"><dt className="text-forest-900/60">{k}</dt><dd className="text-right font-semibold">{v}</dd></div>
);

export default function Page() {
  return <Suspense><View /></Suspense>;
}
