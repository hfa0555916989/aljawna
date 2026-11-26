import { NextResponse, type NextRequest } from "next/server";

/**
 * رؤوس الأمان لكل صفحة (docs/SPEC.md §12.1, §12.13؛ docs/DECISIONS.md):
 * - CSP بـ nonce لكل طلب، بلا 'unsafe-inline' ولا 'unsafe-eval' في الإنتاج
 *   (النسخة السابقة احتاجتهما لـ Alpine وLivewire). المصدر الخارجي الوحيد Turnstile.
 * - HSTS على الإنتاج، وحماية من التأطير وتخمين نوع المحتوى.
 */

const TURNSTILE_ORIGIN = "https://challenges.cloudflare.com";

export function buildContentSecurityPolicy(nonce: string, isDev: boolean): string {
  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic' ${TURNSTILE_ORIGIN}${isDev ? " 'unsafe-eval'" : ""}`,
    `style-src 'self' 'nonce-${nonce}'`,
    "img-src 'self' blob: data:",
    "font-src 'self'",
    `frame-src ${TURNSTILE_ORIGIN}`,
    `connect-src 'self'${isDev ? " ws:" : ""}`,
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
    ...(isDev ? [] : ["upgrade-insecure-requests"]),
  ].join("; ");
}

export function proxy(request: NextRequest) {
  const nonce = Buffer.from(crypto.randomUUID()).toString("base64");
  const isDev = process.env.NODE_ENV === "development";
  const policy = buildContentSecurityPolicy(nonce, isDev);
  const headerName = process.env.CSP_REPORT_ONLY === "true" ? "Content-Security-Policy-Report-Only" : "Content-Security-Policy";

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  requestHeaders.set(headerName, policy);

  const response = NextResponse.next({ request: { headers: requestHeaders } });

  response.headers.set(headerName, policy);
  response.headers.set("X-Content-Type-Options", "nosniff");
  response.headers.set("X-Frame-Options", "DENY");
  response.headers.set("Referrer-Policy", "strict-origin-when-cross-origin");
  response.headers.set("Permissions-Policy", "camera=(), microphone=(), geolocation=()");

  const hstsMaxAge = Number(process.env.HSTS_MAX_AGE ?? 31536000);

  if (!isDev && hstsMaxAge > 0) {
    response.headers.set("Strict-Transport-Security", `max-age=${hstsMaxAge}${process.env.HSTS_INCLUDE_SUBDOMAINS === "true" ? "; includeSubDomains" : ""}`);
  }

  return response;
}

export const config = {
  matcher: [
    {
      source: "/((?!_next/static|_next/image|favicon.ico).*)",
      missing: [
        { type: "header", key: "next-router-prefetch" },
        { type: "header", key: "purpose", value: "prefetch" },
      ],
    },
  ],
};
