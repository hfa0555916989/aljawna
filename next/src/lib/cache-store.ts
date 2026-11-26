import "server-only";
import { sql as rawSql } from "drizzle-orm";
import { db } from "@/lib/db";

/**
 * مخزن مؤقت على جدول cache في Postgres نفسه، بدل Redis: يكفي لحدود المحاولات
 * والقفل المؤقت بحجم هذا الموقع، ولا يضيف مزوّدًا خارجيًا جديدًا.
 *
 * expiration بالثواني (Unix). المفاتيح ببادئة خاصة كي لا تختلط بمفاتيح نسخة Laravel.
 */

const PREFIX = "ajawna-next:";

const nowSeconds = (): number => Math.floor(Date.now() / 1000);

type CacheRow = { value: string; expiration: number };

export async function cacheGet(key: string): Promise<string | null> {
  const rows = await db.execute<CacheRow>(
    rawSql`select value, expiration from cache where key = ${PREFIX + key} and expiration > ${nowSeconds()}`,
  );

  return rows[0]?.value ?? null;
}

export async function cachePut(key: string, value: string, ttlSeconds: number): Promise<void> {
  await db.execute(rawSql`
    insert into cache (key, value, expiration) values (${PREFIX + key}, ${value}, ${nowSeconds() + ttlSeconds})
    on conflict (key) do update set value = excluded.value, expiration = excluded.expiration
  `);
}

export async function cacheForget(key: string): Promise<void> {
  await db.execute(rawSql`delete from cache where key = ${PREFIX + key}`);
}

/**
 * يزيد عدّاد نافذة زمنية ذريًا ويعيد قيمته بعد الزيادة، والثواني المتبقية على النافذة.
 * النافذة تبدأ عند أول زيادة وتنتهي بعد decaySeconds (مثل RateLimiter::hit).
 */
export async function cacheHit(key: string, decaySeconds: number): Promise<{ attempts: number; availableIn: number }> {
  const now = nowSeconds();
  const rows = await db.execute<CacheRow>(rawSql`
    insert into cache (key, value, expiration) values (${PREFIX + key}, '1', ${now + decaySeconds})
    on conflict (key) do update set
      value = case when cache.expiration <= ${now} then '1' else ((cache.value)::integer + 1)::text end,
      expiration = case when cache.expiration <= ${now} then excluded.expiration else cache.expiration end
    returning value, expiration
  `);
  const row = rows[0];

  return { attempts: Number(row?.value ?? 1), availableIn: Math.max(0, Number(row?.expiration ?? now) - now) };
}

/** عدد المحاولات الحالية في النافذة، والثواني المتبقية عليها. */
export async function cacheAttempts(key: string): Promise<{ attempts: number; availableIn: number }> {
  const now = nowSeconds();
  const rows = await db.execute<CacheRow>(
    rawSql`select value, expiration from cache where key = ${PREFIX + key} and expiration > ${now}`,
  );
  const row = rows[0];

  return row ? { attempts: Number(row.value), availableIn: Number(row.expiration) - now } : { attempts: 0, availableIn: 0 };
}

/** حذف المنتهي، يُستدعى من مهمة مجدولة. */
export async function cachePruneExpired(): Promise<void> {
  await db.execute(rawSql`delete from cache where key like ${PREFIX + "%"} and expiration <= ${nowSeconds()}`);
}
