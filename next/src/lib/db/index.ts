import "server-only";
import { drizzle } from "drizzle-orm/postgres-js";
import postgres from "postgres";
import * as schema from "./schema";

/**
 * اتصال واحد لكل نسخة دالة. على Vercel يُستخدم رابط Neon المجمَّع (pooled)،
 * فيكفي اتصال أو اتصالان لكل نسخة.
 *
 * TimeZone=Asia/Riyadh كي تطابق القيم الافتراضية (CURRENT_TIMESTAMP) في القاعدة
 * توقيتَ الرياض المحلي الذي تخزّنه نسخة Laravel (riyadh-timestamp.ts).
 */
function createClient() {
  const url = process.env.DATABASE_URL;

  if (!url) {
    throw new Error("DATABASE_URL is not set.");
  }

  return postgres(url, {
    max: Number(process.env.DATABASE_POOL_MAX ?? 3),
    prepare: false,
    connection: { TimeZone: "Asia/Riyadh" },
  });
}

const globalForDb = globalThis as unknown as { ajawnaSql?: ReturnType<typeof postgres> };

export const sql = globalForDb.ajawnaSql ?? createClient();

if (process.env.NODE_ENV !== "production") {
  globalForDb.ajawnaSql = sql;
}

export const db = drizzle(sql, { schema });

export type Database = typeof db;
export type Transaction = Parameters<Parameters<Database["transaction"]>[0]>[0];
