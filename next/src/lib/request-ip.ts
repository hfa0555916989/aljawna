import "server-only";
import { headers } from "next/headers";

/**
 * عنوان الزائر الحقيقي، أساس القفل المؤقت وحدود المعدّل (docs/SPEC.md §12.2).
 *
 * على Vercel تضع المنصة x-real-ip بعنوان الاتصال الفعلي وتستبدل أي قيمة أرسلها
 * الزائر، فهي وحدها موثوقة. أما x-forwarded-for فقد يبدأ بقيمة مزوّرة من الزائر،
 * فلا يُقرأ إطلاقًا. خارج Vercel يُضبط TRUSTED_CLIENT_IP_HEADER صراحةً لوسيط
 * موثوق، وإلا يُعامل كل الزوار كعنوان واحد محلي.
 */
export function clientIpFromHeaders(requestHeaders: Headers, env: Record<string, string | undefined> = process.env): string {
  const headerName = env.TRUSTED_CLIENT_IP_HEADER || (env.VERCEL ? "x-real-ip" : "");
  const value = headerName ? requestHeaders.get(headerName)?.trim() : undefined;

  if (value && /^[0-9a-fA-F:.]{2,45}$/u.test(value)) {
    return value;
  }

  return "127.0.0.1";
}

export async function clientIp(): Promise<string> {
  return clientIpFromHeaders(await headers());
}

export async function clientUserAgent(): Promise<string | null> {
  return (await headers()).get("user-agent");
}
