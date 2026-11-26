import { sql } from "drizzle-orm";
import { db } from "@/lib/db";
import { users, type User } from "@/lib/db/schema";
import { hashPassword } from "@/lib/auth/password";
import { testRequest } from "./setup";

/**
 * يفرّغ كل الجداول بين الاختبارات. سجلات الإضافة فقط محمية بـ Triggers تمنع
 * TRUNCATE، فتُعطَّل في جلسة الاختبار وحدها (session_replication_role).
 */
export async function resetDatabase(): Promise<void> {
  await db.transaction(async (tx) => {
    await tx.execute(sql`set local session_replication_role = replica`);
    await tx.execute(sql`
      do $$ declare tables text;
      begin
        select string_agg(format('%I', tablename), ', ') into tables from pg_tables where schemaname = 'public';
        execute 'truncate table ' || tables || ' restart identity cascade';
      end $$;
    `);
  });

  testRequest.cookieJar.clear();

  for (const key of [...testRequest.requestHeaders.keys()]) {
    testRequest.requestHeaders.delete(key);
  }
}

let phoneCounter = 0;

/** مصنع مستخدمين للاختبارات، بقيم صالحة افتراضيًا. */
export async function createUser(overrides: Partial<typeof users.$inferInsert> & { plainPassword?: string } = {}): Promise<User> {
  phoneCounter += 1;

  const { plainPassword = "Password-123", ...values } = overrides;
  const now = new Date();
  const [user] = await db
    .insert(users)
    .values({
      fullName: "أحمد محمد علي العجاوني",
      phone: `+9665${String(10000000 + phoneCounter).slice(-8)}`,
      password: await hashPassword(plainPassword),
      role: "user",
      createdAt: now,
      updatedAt: now,
      ...values,
    })
    .returning();

  return user;
}
