"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { AppShell, InviteCard, useSession } from "../components/Shell";
import { Btn, Card, Notice, Progress, ScoreRing, StatusPill, inputCls } from "../components/ui";
import { ApiError, call, type User } from "../lib/api";
import { naira, shortDate, whatsapp } from "../lib/format";
import type { Passport } from "./passport";

type Health = { status: string; score: number; label: string; missed_days: number; consecutive_missed: number };
type Mine = { id: string; name: string; daily_naira: string; cycle_days: number; days_marked: number; saved_kobo: number; cycle_ends_on: string; health: Health; collector: { business_name: string; market_name: string | null } };

export default function SaverPage() {
  const { user, logout } = useSession(["saver", "collector"]);
  return user ? <AppShell user={user} onLogout={logout}><Body user={user} /></AppShell> : null;
}

function Body({ user }: { user: User }) {
  const [mine, setMine] = useState<Mine[] | null>(null);
  const [pass, setPass] = useState<Passport | null>(null);
  const [code, setCode] = useState("");
  const [link, setLink] = useState<string | null>(null);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const load = useCallback(async () => {
    try {
      const [m, p] = await Promise.all([call<Mine[]>("/my/savings"), call<Passport>("/my/passport")]);
      setMine(m);
      setPass(p);
    } catch (e) { setMsg({ ok: false, text: e instanceof ApiError ? e.message : "Could not load." }); }
  }, []);
  useEffect(() => { void load(); }, [load]);

  async function claim(e: React.FormEvent) {
    e.preventDefault();
    try {
      await call("/savers/claim", { method: "POST", body: { claim_code: code } });
      setCode("");
      setMsg({ ok: true, text: "Linked. Your savings are now on your account." });
      await load();
    } catch (err) { setMsg({ ok: false, text: err instanceof ApiError ? err.message : "Could not link." }); }
  }

  async function share() {
    try {
      const r = await call<{ token: string }>("/my/passport/share", { method: "POST" });
      setLink(`${window.location.origin}/p/?t=${r.token}`);
      await load();
    } catch (err) { setMsg({ ok: false, text: err instanceof ApiError ? err.message : "Could not share." }); }
  }

  async function stop() {
    await call("/my/passport/revoke", { method: "POST" }).catch(() => {});
    setLink(null);
    setMsg({ ok: true, text: "Sharing switched off. Old links no longer work." });
    await load();
  }

  return (
    <>
      <h1 className="text-2xl text-forest-900">Hello, {user.name?.split(" ")[0]}</h1>
      <div className="mt-4"><Notice n={msg} /></div>

      {pass && !pass.empty && (
        <Card className="bg-forest-900 !text-white">
          <p className="text-xs font-bold uppercase tracking-wider text-gold-400">Your savings passport</p>
          <div className="mt-3 flex items-center gap-4">
            <div className="rounded-full bg-white p-1"><ScoreRing score={pass.reliability.score} size={88} /></div>
            <div>
              <p className="text-3xl font-extrabold">{pass.readiness.band}</p>
              <p className="text-sm text-forest-100">{pass.reliability.label} saver · ₦{pass.summary.total_saved_naira} saved</p>
            </div>
          </div>
          <p className="mt-3 text-sm text-forest-100">{pass.readiness.note}</p>

          {link ? (
            <div className="mt-4 rounded-xl bg-white/10 p-3">
              <p className="break-all text-xs">{link}</p>
              <div className="mt-2 grid grid-cols-3 gap-2">
                <a href={whatsapp(`My verified Markd savings passport: ${link}`)} target="_blank" rel="noopener noreferrer" className="rounded-lg bg-[#25D366] px-2 py-2 text-center text-xs font-bold text-forest-950">WhatsApp</a>
                <button className="rounded-lg bg-white px-2 py-2 text-xs font-bold text-forest-900" onClick={() => navigator.clipboard.writeText(link)}>Copy</button>
                <button className="rounded-lg border border-white/40 px-2 py-2 text-xs font-bold" onClick={stop}>Stop</button>
              </div>
            </div>
          ) : (
            <div className="mt-4 grid grid-cols-2 gap-2">
              <Btn onClick={share}>Share with a lender</Btn>
              {pass.shared && <Btn variant="ghost" onClick={stop}>Stop sharing</Btn>}
            </div>
          )}
          <p className="mt-3 text-xs text-forest-100/70">{pass.disclaimer}</p>
        </Card>
      )}

      <h2 className="mb-2 mt-6 text-lg text-forest-900">My savings</h2>
      {mine && mine.length === 0 && (
        <Card><p className="text-sm text-forest-900/70">You have no savings linked yet. Ask your collector for your Markd code and enter it below.</p></Card>
      )}
      <ul className="space-y-3">
        {mine?.map((s) => (
          <li key={s.id} className="rounded-2xl bg-white p-4 ring-1 ring-black/5">
            <div className="flex items-center justify-between">
              <div>
                <p className="font-bold text-forest-900">{s.collector.business_name}</p>
                <p className="text-sm text-forest-900/60">₦{s.daily_naira.replace(/\.00$/, "")} a day · ends {shortDate(s.cycle_ends_on)}</p>
              </div>
              <StatusPill status={s.health.status} />
            </div>
            <p className="mt-3 text-2xl font-extrabold text-forest-900">{naira(s.saved_kobo)}</p>
            <div className="mt-2"><Progress value={s.days_marked} max={s.cycle_days} /></div>
            <p className="mt-1 text-xs text-forest-900/60">{s.days_marked} of {s.cycle_days} days saved</p>
          </li>
        ))}
      </ul>

      {pass && !pass.empty && pass.recent_records.length > 0 && (
        <Card className="mt-4">
          <h2 className="text-base text-forest-900">Latest receipts</h2>
          <ul className="mt-2 space-y-1 text-sm">
            {pass.recent_records.map((r) => (
              <li key={r.ref} className="flex justify-between"><span>{shortDate(r.date)}, ₦{r.amount_naira}</span><Link className="font-bold text-forest-700 underline" href={`/verify/?ref=${r.ref}`}>Verify</Link></li>
            ))}
          </ul>
        </Card>
      )}

      <Card className="mt-4">
        <h2 className="text-base text-forest-900">Link a collector&apos;s record</h2>
        <form className="mt-2 flex gap-2" onSubmit={claim}>
          <input className={`${inputCls} uppercase`} aria-label="Markd code" placeholder="Your Markd code" maxLength={12} value={code} onChange={(e) => setCode(e.target.value)} />
          <Btn variant="dark" type="submit">Link</Btn>
        </form>
      </Card>

      <div className="mt-4"><InviteCard code={user.referral_code} /></div>
    </>
  );
}
