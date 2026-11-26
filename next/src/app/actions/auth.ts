"use server";

import { redirect } from "next/navigation";
import { loginWithPassword } from "@/lib/auth/login";
import { authMessages } from "@/lib/auth/messages";
import { consumeRegisterAttempt, registerInitiator, type FieldErrors } from "@/lib/auth/register";
import { currentUser, endSession, startSession } from "@/lib/auth/session";
import { clientIp, clientUserAgent } from "@/lib/request-ip";
import { verifyTurnstile } from "@/lib/turnstile";

/**
 * إجراءات الدخول والتسجيل والخروج. Server Actions في Next.js تفحص أصل الطلب
 * (Origin) فتحمي من CSRF، والفحوص كلها هنا على الخادم.
 */

export type LoginState = { error?: string };

const field = (formData: FormData, name: string): string => {
  const value = formData.get(name);

  return typeof value === "string" ? value : "";
};

export async function loginAction(_previous: LoginState, formData: FormData): Promise<LoginState> {
  const phone = field(formData, "phone");
  const password = field(formData, "password");
  const ip = await clientIp();

  if (phone.trim() === "" || password === "") {
    return { error: authMessages.failed };
  }

  if (!(await verifyTurnstile(field(formData, "cf-turnstile-response") || null, ip))) {
    return { error: authMessages.captchaFailed };
  }

  const result = await loginWithPassword(phone, password, ip);

  if (!result.ok) {
    return { error: result.error };
  }

  await startSession(result.user, { ip, userAgent: await clientUserAgent() });

  redirect("/dashboard");
}

export type RegisterState = { errors?: FieldErrors };

export async function registerAction(_previous: RegisterState, formData: FormData): Promise<RegisterState> {
  const input = {
    fullName: field(formData, "full_name"),
    phone: field(formData, "phone"),
    phoneConfirmation: field(formData, "phone_confirmation"),
    password: field(formData, "password"),
    passwordConfirmation: field(formData, "password_confirmation"),
  };
  const ip = await clientIp();

  const throttled = await consumeRegisterAttempt(ip);

  if (throttled) {
    return { errors: { form: throttled } };
  }

  if (field(formData, "website") !== "") {
    return { errors: { form: authMessages.honeypot } };
  }

  if (!(await verifyTurnstile(field(formData, "cf-turnstile-response") || null, ip))) {
    return { errors: { turnstile: authMessages.captchaFailed } };
  }

  const result = await registerInitiator(input, ip);

  if (!result.ok) {
    return { errors: result.errors };
  }

  await startSession(result.user, { ip, userAgent: await clientUserAgent() });

  redirect("/dashboard");
}

export async function logoutAction(): Promise<void> {
  if (await currentUser()) {
    await endSession();
  }

  redirect("/");
}
