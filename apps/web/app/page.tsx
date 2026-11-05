"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Btn, Card, Logo, inputCls } from "./components/ui";

const STEPS = [
  ["1", "The collector marks the day", "Every payment is recorded in a tamper-proof ledger. Nobody can quietly change it later."],
  ["2", "The saver gets a receipt", "A short reference anyone can check. No more \"I don't remember\"."],
  ["3", "The record becomes a passport", "A steady saving history a lender can trust, shared only when you choose."],
];

export default function Landing() {
  const router = useRouter();
  const [ref, setRef] = useState("");

  return (
    <div>
      <div className="bg-gradient-to-b from-forest-950 to-forest-900">
        <header className="mx-auto flex max-w-5xl items-center justify-between px-5 py-5">
          <Logo light />
          <nav className="flex items-center gap-3 text-sm font-semibold">
            <Link href="/login/" className="text-white">Log in</Link>
            <Link href="/register/" className="rounded-xl bg-gold-400 px-4 py-2 text-forest-950">Get started</Link>
          </nav>
        </header>

        <div className="mx-auto max-w-5xl px-5 pb-16 pt-10 md:pt-16">
          <p className="inline-block rounded-full border border-white/20 px-3 py-1 text-xs font-semibold text-gold-400">For market collectors and savers</p>
          <h1 className="mt-5 max-w-2xl text-4xl leading-[1.08] text-white md:text-6xl">
            Your daily savings, now a <span className="text-gold-400">verified record.</span>
          </h1>
          <p className="mt-5 max-w-xl text-base leading-relaxed text-forest-100 md:text-lg">
            Markd gives collectors and savers a ledger nobody can quietly change. Every payment gets a receipt anyone can verify, and your saving history can help you ask for a loan.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Link href="/register/?role=collector"><Btn className="!px-6 !py-4 text-base">I am a collector</Btn></Link>
            <Link href="/register/?role=saver"><Btn variant="ghost" className="!px-6 !py-4 text-base">I am a saver</Btn></Link>
          </div>
        </div>
      </div>

      <section className="mx-auto -mt-8 max-w-3xl px-5">
        <Card>
          <h2 className="text-lg text-forest-900">Check a receipt</h2>
          <p className="mt-1 text-sm text-forest-900/70">Type the reference from a Markd receipt to see if the record is real and untouched.</p>
          <form className="mt-3 flex gap-2" onSubmit={(e) => { e.preventDefault(); if (ref.trim()) router.push(`/verify/?ref=${encodeURIComponent(ref.trim())}`); }}>
            <input className={inputCls} aria-label="Receipt reference" placeholder="e.g. 9F3A1C7E" value={ref} onChange={(e) => setRef(e.target.value)} />
            <Btn variant="dark" type="submit">Verify</Btn>
          </form>
        </Card>
      </section>

      <section className="mx-auto max-w-5xl px-5 py-16">
        <h2 className="text-center text-3xl text-forest-900">How it works</h2>
        <div className="mt-8 grid gap-4 md:grid-cols-3">
          {STEPS.map(([n, t, d]) => (
            <Card key={n}>
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-forest-900 font-extrabold text-gold-400">{n}</div>
              <h3 className="mt-4 text-lg text-forest-900">{t}</h3>
              <p className="mt-2 text-sm leading-relaxed text-forest-900/70">{d}</p>
            </Card>
          ))}
        </div>
      </section>

      <section className="bg-forest-900 py-14">
        <div className="mx-auto max-w-3xl px-5 text-center">
          <h2 className="text-3xl text-white">Why a record matters</h2>
          <p className="mt-4 text-forest-100">Lenders cannot see how small traders really save, so honest traders are turned away. Markd turns something you already do every day into proof you can show.</p>
          <p className="mt-6 text-xs text-forest-100/70">Markd records saving behaviour. It is not a lender and does not make credit decisions.</p>
        </div>
      </section>

      <footer className="mx-auto flex max-w-5xl flex-col items-center justify-between gap-2 px-5 py-8 text-xs text-forest-900/60 sm:flex-row">
        <Logo size={24} />
        <p>Demo version. Built for the Anambra Web3 Hackathon.</p>
      </footer>
    </div>
  );
}
