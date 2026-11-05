"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useState } from "react";
import { Btn, Card, Field, Logo, Notice, inputCls } from "../components/ui";
import { ApiError, call, homeFor, session, type User } from "../lib/api";

function Form() {
  const router = useRouter();
  const q = useSearchParams();
  const [role, setRole] = useState<"saver" | "collector">(q.get("role") === "collector" ? "collector" : "saver");
  const [f, setF] = useState({ name: "", email: "", password: "", referral_code: q.get("ref") ?? "" });
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement>) => setF({ ...f, [k]: e.target.value });

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setMsg(null);
    try {
      const r = await call<{ token: string; user: User }>("/auth/register", {
        method: "POST", auth: false, body: { ...f, role, referral_code: f.referral_code || undefined },
      });
      session.save(r.token, r.user);
      router.replace(homeFor(r.user.role));
    } catch (err) {
      setMsg({ ok: false, text: err instanceof ApiError ? err.message : "Something went wrong." });
      setBusy(false);
    }
  }

  return (
    <main className="mx-auto max-w-md px-5 py-10">
      <Link href="/"><Logo /></Link>
      <h1 className="mt-8 text-3xl text-forest-900">Create your account</h1>
      <div className="mt-5 grid grid-cols-2 gap-1 rounded-xl bg-cream-100 p-1 text-sm font-bold" role="tablist">
        {(["saver", "collector"] as const).map((r) => (
          <button key={r} role="tab" aria-selected={role === r} onClick={() => setRole(r)} className={`rounded-lg py-2.5 ${role === r ? "bg-white text-forest-900 shadow-sm" : "text-forest-900/60"}`}>
            {r === "saver" ? "I am a saver" : "I am a collector"}
          </button>
        ))}
      </div>
      <Card className="mt-4">
        <Notice n={msg} />
        <form onSubmit={submit} className="space-y-4">
          <Field label="Full name"><input className={inputCls} required autoComplete="name" value={f.name} onChange={set("name")} /></Field>
          <Field label="Email"><input className={inputCls} type="email" required autoComplete="email" value={f.email} onChange={set("email")} /></Field>
          <Field label="Password (8 or more characters)"><input className={inputCls} type="password" required minLength={8} autoComplete="new-password" value={f.password} onChange={set("password")} /></Field>
          <Field label="Referral code (optional)"><input className={inputCls} value={f.referral_code} onChange={set("referral_code")} /></Field>
          <Btn variant="dark" type="submit" className="w-full" disabled={busy}>{busy ? "Creating..." : "Create account"}</Btn>
        </form>
      </Card>
      <p className="mt-4 text-center text-sm text-forest-900/70">Already have an account? <Link href="/login/" className="font-bold text-forest-900 underline">Log in</Link></p>
    </main>
  );
}

export default function Register() {
  return <Suspense><Form /></Suspense>;
}
