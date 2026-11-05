"use client";

import { useCallback, useEffect, useState } from "react";
import { AppShell, InviteCard, useSession } from "../components/Shell";
import { Btn, Card, Field, Notice, Progress, ScoreRing, StatusPill, inputCls } from "../components/ui";
import { ApiError, call, type User } from "../lib/api";
import { naira, newKey, whatsapp } from "../lib/format";

type Health = { status: string; score: number; label: string; missed_days: number; consecutive_missed: number; completion_pct: number };
type Saver = { id: string; name: string; phone: string | null; daily_kobo: number; daily_naira: string; days_marked: number; cycle_days: number; saved_kobo: number; claimed: boolean; health: Health };
type Dash = { collector: { business_name: string; market_name: string | null; status: string }; savers: number; today: { date: string; marked: number; collected_kobo: number; not_yet_marked: { public_id: string; name: string; daily_kobo: number }[] } };
type Attention = { id: string; name: string; daily_naira: string; status: string; consecutive_missed: number; missed_days: number; score: number };

export default function CollectorPage() {
  const { user, logout } = useSession(["collector"]);
  return user ? <AppShell user={user} onLogout={logout}><Body user={user} /></AppShell> : null;
}

function Body({ user }: { user: User }) {
  const [tab, setTab] = useState<"today" | "savers" | "follow">("today");
  const [dash, setDash] = useState<Dash | null>(null);
  const [needProfile, setNeedProfile] = useState(false);
  const [savers, setSavers] = useState<Saver[]>([]);
  const [att, setAtt] = useState<Attention[]>([]);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [receipt, setReceipt] = useState<string | null>(null);
  const [created, setCreated] = useState<{ name: string; claim_code: string; share_message: string; phone: string | null } | null>(null);

  const load = useCallback(async () => {
    try {
      const d = await call<Dash>("/collector/me");
      setDash(d);
      setNeedProfile(false);
      const [s, a] = await Promise.all([call<Saver[]>("/savers"), call<Attention[]>("/collector/attention")]);
      setSavers(s);
      setAtt(a);
    } catch (e) {
      if (e instanceof ApiError && e.status === 404) setNeedProfile(true);
      else setMsg({ ok: false, text: e instanceof ApiError ? e.message : "Could not load." });
    }
  }, []);
  useEffect(() => { void load(); }, [load]);

  async function mark(id: string) {
    setBusy(id);
    setMsg(null);
    try {
      const r = await call<{ receipt: string }>("/marks", { method: "POST", body: { saver_id: id, idempotency_key: newKey() } });
      setReceipt(r.receipt);
      await load();
    } catch (e) {
      setMsg({ ok: false, text: e instanceof ApiError ? e.message : "Could not mark." });
    } finally { setBusy(null); }
  }

  async function remind(id: string) {
    try {
      const r = await call<{ message: string; whatsapp_url: string | null }>(`/savers/${id}/nudge`, { method: "POST" });
      if (r.whatsapp_url) window.open(r.whatsapp_url, "_blank", "noopener,noreferrer");
      else { navigator.clipboard.writeText(r.message); setMsg({ ok: true, text: "No phone number saved. Reminder copied, paste it in WhatsApp." }); }
    } catch (e) { setMsg({ ok: false, text: e instanceof ApiError ? e.message : "Could not make the reminder." }); }
  }

  if (needProfile) return <ProfileForm onDone={load} />;
  if (!dash) return <p className="text-sm text-forest-900/60">Loading...</p>;

  return (
    <>
      <h1 className="text-2xl text-forest-900">{dash.collector.business_name}</h1>
      <p className="text-sm text-forest-900/60">{dash.collector.market_name ?? "Collector"} · {dash.collector.status === "verified" ? "Verified" : "Awaiting verification"}</p>

      <div className="mt-4 grid grid-cols-3 gap-1 rounded-xl bg-cream-100 p-1 text-sm font-bold" role="tablist">
        {([["today", "Today"], ["savers", "Savers"], ["follow", `Follow up${att.length ? ` (${att.length})` : ""}`]] as const).map(([k, l]) => (
          <button key={k} role="tab" aria-selected={tab === k} onClick={() => setTab(k)} className={`rounded-lg py-2.5 ${tab === k ? "bg-white text-forest-900 shadow-sm" : "text-forest-900/60"}`}>{l}</button>
        ))}
      </div>

      <div className="mt-4"><Notice n={msg} /></div>

      {tab === "today" && (
        <>
          <Card>
            <div className="flex items-end justify-between">
              <div>
                <p className="text-sm text-forest-900/60">Collected today</p>
                <p className="text-3xl font-extrabold text-forest-900">{naira(dash.today.collected_kobo)}</p>
              </div>
              <p className="text-sm font-semibold text-forest-900">{dash.today.marked} of {dash.savers} marked</p>
            </div>
            <div className="mt-3"><Progress value={dash.today.marked} max={dash.savers} /></div>
          </Card>

          {receipt && (
            <Card className="mt-4 border-l-4 border-emerald-600">
              <p className="text-sm font-bold text-emerald-800">Marked. Send this receipt:</p>
              <p className="mt-1 text-sm">{receipt}</p>
              <div className="mt-3 grid grid-cols-2 gap-2">
                <a href={whatsapp(receipt)} target="_blank" rel="noopener noreferrer" className="rounded-xl bg-[#25D366] px-3 py-2.5 text-center text-sm font-bold text-forest-950">WhatsApp</a>
                <Btn variant="ghost" onClick={() => navigator.clipboard.writeText(receipt)}>Copy</Btn>
              </div>
            </Card>
          )}

          <h2 className="mb-2 mt-6 text-lg text-forest-900">Still to mark</h2>
          {dash.today.not_yet_marked.length === 0 && <Card><p className="text-sm font-semibold text-emerald-800">Everyone is marked for today.</p></Card>}
          <ul className="space-y-2">
            {dash.today.not_yet_marked.map((s) => (
              <li key={s.public_id} className="flex items-center justify-between rounded-2xl bg-white p-4 ring-1 ring-black/5">
                <div><p className="font-bold text-forest-900">{s.name}</p><p className="text-sm text-forest-900/60">{naira(s.daily_kobo)} a day</p></div>
                <Btn variant="dark" disabled={busy === s.public_id} onClick={() => mark(s.public_id)}>{busy === s.public_id ? "..." : "Mark paid"}</Btn>
              </li>
            ))}
          </ul>
        </>
      )}

      {tab === "savers" && (
        <>
          <AddSaver onCreated={(c) => { setCreated(c); void load(); }} />
          {created && (
            <Card className="mt-4 border-l-4 border-gold-400">
              <p className="font-bold text-forest-900">{created.name} added</p>
              <p className="mt-1 text-sm">Their code: <span className="rounded bg-cream-100 px-2 py-1 font-mono font-bold">{created.claim_code}</span></p>
              <a href={whatsapp(created.share_message, created.phone)} target="_blank" rel="noopener noreferrer" className="mt-3 block rounded-xl bg-[#25D366] px-3 py-2.5 text-center text-sm font-bold text-forest-950">Send the code on WhatsApp</a>
            </Card>
          )}
          <ul className="mt-4 space-y-2">
            {savers.map((s) => (
              <li key={s.id} className="rounded-2xl bg-white p-4 ring-1 ring-black/5">
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <p className="truncate font-bold text-forest-900">{s.name}</p>
                    <p className="text-sm text-forest-900/60">₦{s.daily_naira.replace(/\.00$/, "")} a day · saved {naira(s.saved_kobo)}</p>
                  </div>
                  <div className="flex items-center gap-2"><StatusPill status={s.health.status} /><ScoreRing score={s.health.score} size={48} /></div>
                </div>
                <div className="mt-3"><Progress value={s.days_marked} max={s.cycle_days} /></div>
              </li>
            ))}
          </ul>
        </>
      )}

      {tab === "follow" && (
        <>
          {att.length === 0 && <Card><p className="text-sm font-semibold text-emerald-800">Nobody is behind. Great work.</p></Card>}
          <ul className="space-y-2">
            {att.map((a) => (
              <li key={a.id} className="flex items-center justify-between gap-3 rounded-2xl bg-white p-4 ring-1 ring-black/5">
                <div>
                  <p className="font-bold text-forest-900">{a.name}</p>
                  <p className="text-sm text-forest-900/60">{a.consecutive_missed} day(s) in a row · {a.missed_days} missed in total</p>
                  <div className="mt-1"><StatusPill status={a.status} /></div>
                </div>
                <Btn variant="ghost" onClick={() => remind(a.id)}>Remind</Btn>
              </li>
            ))}
          </ul>
        </>
      )}

      <div className="mt-8"><InviteCard code={user.referral_code} /></div>
    </>
  );
}

function ProfileForm({ onDone }: { onDone: () => void }) {
  const [f, setF] = useState({ business_name: "", market_name: "", lga: "" });
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement>) => setF({ ...f, [k]: e.target.value });
  return (
    <Card>
      <h1 className="text-2xl text-forest-900">Set up your collector profile</h1>
      <p className="mt-1 text-sm text-forest-900/70">This is what your savers see on their receipts.</p>
      <div className="mt-4"><Notice n={msg} /></div>
      <form className="space-y-4" onSubmit={async (e) => { e.preventDefault(); try { await call("/collector", { method: "POST", body: f }); onDone(); } catch (err) { setMsg({ ok: false, text: err instanceof ApiError ? err.message : "Could not save." }); } }}>
        <Field label="Business name"><input className={inputCls} required value={f.business_name} onChange={set("business_name")} placeholder="e.g. Ngozi Daily Thrift" /></Field>
        <Field label="Market"><input className={inputCls} value={f.market_name} onChange={set("market_name")} placeholder="e.g. Onitsha Main Market" /></Field>
        <Field label="Local government area"><input className={inputCls} value={f.lga} onChange={set("lga")} /></Field>
        <Btn variant="dark" type="submit" className="w-full">Save profile</Btn>
      </form>
    </Card>
  );
}

function AddSaver({ onCreated }: { onCreated: (c: { name: string; claim_code: string; share_message: string; phone: string | null }) => void }) {
  const [open, setOpen] = useState(false);
  const [f, setF] = useState({ name: "", phone: "", daily_naira: "" });
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement>) => setF({ ...f, [k]: e.target.value });
  if (!open) return <Btn variant="dark" className="w-full" onClick={() => setOpen(true)}>+ Add a saver</Btn>;
  return (
    <Card>
      <Notice n={msg} />
      <form className="space-y-3" onSubmit={async (e) => {
        e.preventDefault();
        try {
          const r = await call<{ name: string; claim_code: string; share_message: string; phone: string | null }>("/savers", { method: "POST", body: { name: f.name, phone: f.phone || undefined, daily_naira: Number(f.daily_naira) } });
          onCreated(r);
          setF({ name: "", phone: "", daily_naira: "" });
          setOpen(false);
        } catch (err) { setMsg({ ok: false, text: err instanceof ApiError ? err.message : "Could not add." }); }
      }}>
        <Field label="Name"><input className={inputCls} required value={f.name} onChange={set("name")} /></Field>
        <Field label="Phone (optional, for reminders)"><input className={inputCls} inputMode="tel" value={f.phone} onChange={set("phone")} placeholder="0803 123 4567" /></Field>
        <Field label="Daily amount (₦)"><input className={inputCls} required inputMode="numeric" value={f.daily_naira} onChange={set("daily_naira")} placeholder="1000" /></Field>
        <div className="grid grid-cols-2 gap-2"><Btn variant="ghost" type="button" onClick={() => setOpen(false)}>Cancel</Btn><Btn variant="dark" type="submit">Add saver</Btn></div>
      </form>
    </Card>
  );
}
