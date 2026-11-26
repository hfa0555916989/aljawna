import "server-only";
import { securityConfig } from "@/lib/config";

/**
 * التحقق من رمز Cloudflare Turnstile على الخادم (docs/SPEC.md §11, §12.3).
 * يعيد true إن كان الرمز صالحًا، أو إن كان Turnstile معطَّلًا في الإعدادات.
 */
export async function verifyTurnstile(token: string | null, ip: string): Promise<boolean> {
  if (!securityConfig.turnstile.enabled()) {
    return true;
  }

  const secret = securityConfig.turnstile.secretKey();

  if (!token || !secret) {
    return false;
  }

  try {
    const response = await fetch(securityConfig.turnstile.verifyUrl(), {
      method: "POST",
      body: new URLSearchParams({ secret, response: token, remoteip: ip }),
      signal: AbortSignal.timeout(5000),
    });

    if (!response.ok) {
      return false;
    }

    const result = (await response.json()) as { success?: boolean };

    return result.success === true;
  } catch {
    return false;
  }
}

export function turnstileSiteKey(): string | null {
  return securityConfig.turnstile.enabled() ? securityConfig.turnstile.siteKey() : null;
}
