"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Btn, Card, Field, Logo, Notice, inputCls } from "../components/ui";
import { ApiError, call, homeFor, session, type User } from "../lib/api";

export default function Login() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setBusy(true);
    setMsg(null);
    try {
      const r = await call<{ token: string; user: User }>("/auth/login", { method: "POST", auth: false, body: { email, password } });
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
      <h1 className="mt-8 text-3xl text-forest-900">Welcome back</h1>
      <Card className="mt-6">
        <Notice n={msg} />
        <form onSubmit={submit} className="space-y-4">
          <Field label="Email"><input className={inputCls} type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
          <Field label="Password"><input className={inputCls} type="password" autoComplete="current-password" required value={password} onChange={(e) => setPassword(e.target.value)} /></Field>
          <Btn variant="dark" type="submit" className="w-full" disabled={busy}>{busy ? "Logging in..." : "Log in"}</Btn>
        </form>
      </Card>
      <p className="mt-4 text-center text-sm text-forest-900/70">New here? <Link href="/register/" className="font-bold text-forest-900 underline">Create an account</Link></p>
    </main>
  );
}
