import { drizzle } from "drizzle-orm/postgres-js";
import { migrate } from "drizzle-orm/postgres-js/migrator";
import postgres from "postgres";

/** يعيد بناء قاعدة الاختبار من الترحيلات مرة واحدة قبل كل الاختبارات. */
export default async function setup(): Promise<void> {
  const url = process.env.TEST_DATABASE_URL ?? "postgres://ajawna:secret@127.0.0.1:5432/ajawna_next_test";
  const client = postgres(url, { max: 1, onnotice: () => {} });

  await client.unsafe("drop schema if exists public cascade; drop schema if exists drizzle cascade; create schema public;");
  await migrate(drizzle(client), { migrationsFolder: "drizzle" });
  await client.end();
}
