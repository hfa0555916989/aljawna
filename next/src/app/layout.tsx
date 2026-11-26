import type { Metadata, Viewport } from "next";
import localFont from "next/font/local";
import { SiteHeader } from "@/components/site-header";
import "./globals.css";

const plex = localFont({
  src: [
    { path: "../fonts/IBMPlexSansArabic-Regular.ttf", weight: "400" },
    { path: "../fonts/IBMPlexSansArabic-Medium.ttf", weight: "500" },
    { path: "../fonts/IBMPlexSansArabic-SemiBold.ttf", weight: "600" },
    { path: "../fonts/IBMPlexSansArabic-Bold.ttf", weight: "700" },
  ],
  variable: "--font-plex",
  display: "swap",
});

const ruqaa = localFont({
  src: [{ path: "../fonts/ArefRuqaa-Bold.ttf", weight: "700" }],
  variable: "--font-ruqaa",
  display: "swap",
});

export const metadata: Metadata = {
  title: { default: "مبادرة العجاونة", template: "%s | مبادرة العجاونة" },
  description: "مبادرة لدعم زواج المستفيدين المسجّلين، بإحصائيات شفافة عن التقدّم.",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  viewportFit: "cover",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ar" dir="rtl" className={`${plex.variable} ${ruqaa.variable}`}>
      <body className="min-h-dvh font-sans antialiased">
        <SiteHeader />
        <main>{children}</main>
      </body>
    </html>
  );
}
