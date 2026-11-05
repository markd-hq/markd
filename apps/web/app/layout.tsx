import type { Metadata, Viewport } from "next";
import "@fontsource-variable/alexandria";
import "./globals.css";
import PwaRegister from "./components/PwaRegister";
import { ToastProvider } from "./components/ToastProvider";

export const metadata: Metadata = {
  title: "Markd - your savings, verified",
  description: "A tamper-proof record of daily savings for market collectors and savers.",
  manifest: "/manifest.webmanifest",
  appleWebApp: { capable: true, title: "Markd", statusBarStyle: "default" },
  icons: { icon: "/icon-192.png", apple: "/icon-192.png" },
};

export const viewport: Viewport = { themeColor: "#0b3d2e", width: "device-width", initialScale: 1 };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body className="min-h-screen antialiased">
        <ToastProvider>{children}</ToastProvider>
        <PwaRegister />
      </body>
    </html>
  );
}
