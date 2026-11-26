import type { Metadata } from "next";
import { headers } from "next/headers";
import { redirect } from "next/navigation";
import { currentUser } from "@/lib/auth/session";
import { turnstileSiteKey } from "@/lib/turnstile";
import { RegisterForm } from "./register-form";

export const metadata: Metadata = { title: "إنشاء حساب" };

export default async function RegisterPage() {
  if (await currentUser()) {
    redirect("/dashboard");
  }

  return (
    <div className="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
      <h1 className="text-2xl font-bold text-ink">إنشاء حساب</h1>
      <RegisterForm turnstileSiteKey={turnstileSiteKey()} nonce={(await headers()).get("x-nonce") ?? undefined} />
    </div>
  );
}
