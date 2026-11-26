import "server-only";
import { createHash } from "node:crypto";
import { cacheForget, cacheGet, cacheHit, cachePut } from "@/lib/cache-store";
import { securityConfig } from "@/lib/config";

/**
 * القفل المؤقت للدخول (docs/SPEC.md §3, §12.2)، مطابق لـ App\Services\LoginThrottle:
 * بعد عدد من المحاولات الفاشلة خلال نافذة زمنية، لكل جوال ولكل IP على حدة،
 * يُقفل الدخول مدةً تتضاعف مع تكرار القفل حتى حد أقصى.
 */

const phoneKey = (phone: string) => `login:phone:${createHash("sha256").update(phone).digest("hex")}`;
const ipKey = (ip: string) => `login:ip:${ip}`;
const lockKey = (key: string) => `${key}:lock`;
const strikesKey = (key: string) => `${key}:strikes`;

const nowSeconds = (): number => Math.floor(Date.now() / 1000);

async function remainingLockSeconds(key: string): Promise<number> {
  const lockedUntil = Number(await cacheGet(lockKey(key)));

  return Number.isFinite(lockedUntil) ? Math.max(0, lockedUntil - nowSeconds()) : 0;
}

async function lock(key: string): Promise<void> {
  const strikes = Number((await cacheGet(strikesKey(key))) ?? 0) + 1;

  await cachePut(strikesKey(key), String(strikes), securityConfig.login.strikesResetHours() * 3600);

  const minutes = Math.min(securityConfig.login.lockoutMinutes() * 2 ** (strikes - 1), securityConfig.login.lockoutMaxMinutes());
  const lockedUntil = nowSeconds() + minutes * 60;

  await cachePut(lockKey(key), String(lockedUntil), minutes * 60);
}

/** الثواني المتبقية على القفل (0 إن لم يكن مقفلًا) للجوال أو للـ IP. */
export async function lockedSeconds(phone: string, ip: string): Promise<number> {
  const [byPhone, byIp] = await Promise.all([remainingLockSeconds(phoneKey(phone)), remainingLockSeconds(ipKey(ip))]);

  return Math.max(byPhone, byIp);
}

export async function recordLoginFailure(phone: string, ip: string): Promise<void> {
  const maxAttempts = securityConfig.login.maxAttempts();
  const decaySeconds = securityConfig.login.decayMinutes() * 60;

  for (const key of [phoneKey(phone), ipKey(ip)]) {
    const { attempts } = await cacheHit(key, decaySeconds);

    if (attempts >= maxAttempts) {
      await cacheForget(key);
      await lock(key);
    }
  }
}

/**
 * بعد دخول ناجح: تصفير محاولات الجوال وتكرار قفله. عدّاد الـ IP لا يُصفَّر كي لا
 * يُستغل الدخول بحساب آخر لتخطي حد الـ IP.
 */
export async function clearPhoneThrottle(phone: string): Promise<void> {
  const key = phoneKey(phone);

  await cacheForget(key);
  await cacheForget(strikesKey(key));
}
