"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { call, homeFor, session, type Role, type User } from "../lib/api";
import { copyText, whatsapp } from "../lib/format";
import { useToast } from "./ToastProvider";
import { Card, Logo } from "./ui";

export function useSession(roles: Role[]) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const key = roles.join(",");

  useEffect(() => {
    const u = session.user();
    if (!u || !session.token()) {
      router.replace("/login/");
      return;
    }
    if (!key.split(",").includes(u.role)) {
      router.replace(homeFor(u.role));
      return;
    }
    setUser(u);
  }, [key, router]);

  const logout = () => {
    call("/logout", { method: "POST" }).catch(() => {});
    session.clear();
    router.replace("/login/");
  };
  return { user, logout };
}

export function AppShell({ user, onLogout, children }: { user: User; onLogout: () => void; children: React.ReactNode }) {
  return (
    <div className="min-h-screen pb-16">
      <header className="sticky top-0 z-30 border-b border-white/10 bg-forest-900/95 shadow-sm backdrop-blur">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-3.5">
          <Link href="/" aria-label="Markd home"><Logo light /></Link>
          <div className="flex items-center gap-3 text-sm text-white">
            <span className="hidden rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-semibold capitalize text-forest-100 sm:inline">{user.role}</span>
            <span className="hidden text-forest-100 sm:inline">{user.name}</span>
            <button onClick={onLogout} className="rounded-xl bg-white/10 px-3.5 py-2 font-semibold transition hover:bg-white/15 focus-visible:outline-white">Log out</button>
          </div>
        </div>
      </header>
      <main className="mx-auto max-w-3xl px-4 pt-7 sm:px-6">{children}</main>
    </div>
  );
}

export function InviteCard({ code }: { code: string }) {
  const { notify } = useToast();
  const [link, setLink] = useState("");
  useEffect(() => setLink(`${window.location.origin}/register/?ref=${code}`), [code]);
  const text = `Join me on Markd, the savings record you can verify: ${link}`;

  async function copyLink() {
    try {
      await copyText(link);
      notify("Invite link copied.");
    } catch {
      notify("Could not copy the invite link. Please copy it from the address bar.", "error");
    }
  }

  return (
    <Card>
      <div className="flex items-start gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold-400/25 text-lg text-forest-900" aria-hidden="true">↗</span>
        <div>
          <h2 className="text-lg text-forest-900">Invite a friend</h2>
          <p className="mt-1 text-sm leading-relaxed text-forest-900/70">You earn a flat bonus when your friend has saved for 7 days. Nothing is taken from their savings.</p>
        </div>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-2">
        <a href={whatsapp(text)} target="_blank" rel="noopener noreferrer" className="rounded-xl bg-[#25D366] px-3 py-3 text-center text-sm font-bold text-forest-950 transition hover:brightness-95">WhatsApp</a>
        <button onClick={copyLink} className="rounded-xl border border-forest-900/20 px-3 py-3 text-sm font-bold text-forest-900 transition hover:bg-cream-100">Copy link</button>
      </div>
    </Card>
  );
}
