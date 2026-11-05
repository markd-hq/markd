"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Btn, Card, Logo, inputCls } from "../components/ui";
import { ApiError, call } from "../lib/api";
import { shortDate } from "../lib/format";

type Result = {
  intact: boolean; date: string; amount_naira: string; channel: string; saver: string;
  collector: string; market: string | null; recorded_at: string; ref: string; fingerprint: string;
};

function View() {
  const router = useRouter();
  const ref = useSearchParams().get("ref") ?? "";
  const [input, setInput] = useState(ref);
  const [res, setRes] = useState<Result | null>(null);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => {
    if (!ref) return;
    setRes(null);
    setErr(null);
    call<Result>(`/verify/${encodeURIComponent(ref)}`, { auth: false }).then(setRes).catch((e) => setErr(e instanceof ApiError ? e.message : "Could not check that reference."));
  }, [ref]);

  return (
    <main className="mx-auto max-w-lg px-5 py-10">
      <Link href="/"><Logo /></Link>
      <h1 className="mt-8 text-3xl text-forest-900">Verify a record</h1>
      <form className="mt-5 flex gap-2" onSubmit={(e) => { e.preventDefault(); router.push(`/verify/?ref=${encodeURIComponent(input.trim())}`); }}>
        <input className={inputCls} aria-label="Receipt reference" placeholder="Reference from the receipt" value={input} onChange={(e) => setInput(e.target.value)} />
        <Btn variant="dark" type="submit">Check</Btn>
      </form>

      {err && <Card className="mt-5 border-l-4 border-red-500"><p className="font-bold text-red-700">Not found</p><p className="mt-1 text-sm">{err}</p></Card>}

      {res && (
        <Card className={`mt-5 border-l-4 ${res.intact ? "border-emerald-600" : "border-red-600"}`}>
          <p className={`text-lg font-extrabold ${res.intact ? "text-emerald-800" : "text-red-700"}`}>
            {res.intact ? "Verified: this record is real and untouched" : "Warning: this record does not check out"}
          </p>
          <dl className="mt-4 space-y-2 text-sm">
            <Row k="Saver" v={res.saver} />
            <Row k="Amount" v={`₦${res.amount_naira}`} />
            <Row k="For the day" v={shortDate(res.date)} />
            <Row k="Collected by" v={`${res.collector}${res.market ? `, ${res.market}` : ""}`} />
            <Row k="Recorded" v={res.recorded_at} />
            <Row k="Reference" v={res.ref.toUpperCase()} />
          </dl>
          <p className="mt-4 break-all text-xs text-forest-900/50">Fingerprint: {res.fingerprint}</p>
        </Card>
      )}
    </main>
  );
}

const Row = ({ k, v }: { k: string; v: string }) => (
  <div className="flex justify-between gap-4 border-b border-cream-100 pb-2"><dt className="text-forest-900/60">{k}</dt><dd className="text-right font-semibold">{v}</dd></div>
);

export default function Verify() {
  return <Suspense><View /></Suspense>;
}
