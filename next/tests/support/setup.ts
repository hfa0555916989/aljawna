import { vi } from "vitest";

/**
 * قاعدة اختبار منفصلة (ajawna_next_test). تُطبَّق الترحيلات مرة واحدة في
 * global-setup.ts، وتُفرَّغ الجداول قبل كل اختبار (tests/support/database.ts).
 */
process.env.DATABASE_URL = process.env.TEST_DATABASE_URL ?? "postgres://ajawna:secret@127.0.0.1:5432/ajawna_next_test";
process.env.BCRYPT_ROUNDS = "4";
process.env.TURNSTILE_ENABLED = "false";

/** كوكيز ورؤوس طلب وهمية بدل next/headers خارج Next.js. */
const cookieJar = new Map<string, string>();
const requestHeaders = new Headers();

export const testRequest = { cookieJar, requestHeaders };

vi.mock("next/headers", () => ({
  cookies: async () => ({
    get: (name: string) => (cookieJar.has(name) ? { name, value: cookieJar.get(name) as string } : undefined),
    set: (name: string, value: string) => {
      cookieJar.set(name, value);
    },
    delete: (name: string) => {
      cookieJar.delete(name);
    },
  }),
  headers: async () => requestHeaders,
}));
