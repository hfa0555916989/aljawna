import "server-only";
import { eq } from "drizzle-orm";
import { db } from "@/lib/db";
import { loginAttempts, users, type User } from "@/lib/db/schema";
import { securityConfig } from "@/lib/config";
import { normalizeSaudiPhone } from "@/lib/validation/saudi-phone";
import { clearPhoneThrottle, lockedSeconds, recordLoginFailure } from "./login-throttle";
import { authMessages } from "./messages";
import { burnPasswordCheck, verifyPassword } from "./password";
import { hasPanelRole } from "./permissions";

/**
 * التحقق من بيانات الدخول بالجوال وكلمة المرور (docs/SPEC.md §3, §12.2)، مطابق
 * لـ App\Actions\Auth\LoginUser: يسجّل كل محاولة في login_attempts، ولا يكشف
 * أي الحقلين خاطئ، ويقفل الدخول مؤقتًا بعد المحاولات الفاشلة.
 *
 * أدوار اللوحة (مشرف ومدير) تحتاج خطوة التحقق الثانية، وهي جزء من المرحلة 2،
 * فلا يدخلونها من هذه النسخة بعد.
 */

export type LoginResult = { ok: true; user: User } | { ok: false; error: string };

async function recordAttempt(phone: string, ip: string, succeeded: boolean): Promise<void> {
  await db.insert(loginAttempts).values({ phone: phone.slice(0, 32), ip, succeeded, createdAt: new Date() });
}

async function recordFailure(identifier: string, ip: string): Promise<void> {
  await recordAttempt(identifier, ip, false);
  await recordLoginFailure(identifier, ip);
}

export async function loginWithPassword(phoneInput: string, password: string, ip: string): Promise<LoginResult> {
  const phone = normalizeSaudiPhone(phoneInput);
  const identifier = phone ?? [...phoneInput.trim()].slice(0, 32).join("");

  const locked = await lockedSeconds(identifier, ip);

  if (locked > 0) {
    await recordAttempt(identifier, ip, false);

    return { ok: false, error: authMessages.locked(Math.ceil(locked / 60)) };
  }

  const user = phone === null ? undefined : (await db.select().from(users).where(eq(users.phone, phone)).limit(1))[0];

  if (!user) {
    await burnPasswordCheck(password);
    await recordFailure(identifier, ip);

    return { ok: false, error: authMessages.failed };
  }

  if (!(await verifyPassword(password, user.password))) {
    await recordFailure(identifier, ip);

    return { ok: false, error: authMessages.failed };
  }

  if (!user.isActive) {
    await recordAttempt(identifier, ip, false);

    return { ok: false, error: authMessages.inactive };
  }

  if (hasPanelRole(user)) {
    await recordAttempt(identifier, ip, false);

    const needsSetup = securityConfig.twoFactorRequired() && !user.appAuthenticationSecret;

    return { ok: false, error: needsSetup ? authMessages.twoFactorSetupRequired : authMessages.panelNotYetAvailable };
  }

  await recordAttempt(user.phone, ip, true);
  await clearPhoneThrottle(user.phone);

  const now = new Date();

  await db.update(users).set({ lastLoginIp: ip, lastLoginAt: now, updatedAt: now }).where(eq(users.id, user.id));

  return { ok: true, user: { ...user, lastLoginIp: ip, lastLoginAt: now } };
}
