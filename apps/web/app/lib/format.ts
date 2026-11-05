export const naira = (kobo: number) => `₦${Math.round(kobo / 100).toLocaleString("en-NG")}`;

export const shortDate = (iso: string) =>
  new Date(iso + (iso.length === 10 ? "T00:00:00" : "")).toLocaleDateString("en-NG", { day: "numeric", month: "short", year: "numeric" });

export const newKey = () =>
  typeof crypto !== "undefined" && "randomUUID" in crypto ? crypto.randomUUID() : `k-${Date.now()}-${Math.random().toString(16).slice(2)}`;

export const whatsapp = (text: string, phone?: string | null) =>
  `https://wa.me/${phone ? phone.replace(/\D/g, "") : ""}?text=${encodeURIComponent(text)}`;

export async function copyText(text: string) {
  if (!navigator.clipboard?.writeText) throw new Error("Clipboard access is unavailable.");
  await navigator.clipboard.writeText(text);
}
