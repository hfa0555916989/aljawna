import type { Metadata } from "next";
import { redirect } from "next/navigation";
import { currentUser } from "@/lib/auth/session";

export const metadata: Metadata = { title: "لوحتي" };

/**
 * لوحة المبادر. المؤشرات والحوالات تأتي في المرحلة 3 (T07–T08)؛ هنا هيكلها
 * والتحقق من الجلسة فقط.
 */
export default async function DashboardPage() {
  const user = await currentUser();

  if (!user) {
    redirect("/login");
  }

  const firstName = user.fullName.trim().split(/\s+/u)[0];

  return (
    <div className="mx-auto w-full max-w-5xl px-4 py-8">
      <h1 className="text-2xl font-bold text-ink">أهلًا {firstName}</h1>
      <p className="mt-2 text-muted">
        رقمك المسجّل: <span dir="ltr">{user.phone}</span>
      </p>
      <section className="mt-6 rounded-[14px] border border-line bg-surface p-4 text-muted sm:p-6">
        رفع الحوالات ومؤشراتك تُضاف في المرحلة القادمة.
      </section>
    </div>
  );
}
