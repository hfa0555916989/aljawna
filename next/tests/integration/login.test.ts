import { eq } from "drizzle-orm";
import { beforeEach, describe, expect, it } from "vitest";
import { loginWithPassword } from "@/lib/auth/login";
import { authMessages } from "@/lib/auth/messages";
import { db } from "@/lib/db";
import { loginAttempts, users } from "@/lib/db/schema";
import { createUser, resetDatabase } from "../support/database";

const IP = "203.0.113.10";

beforeEach(resetDatabase);

describe("loginWithPassword (docs/SPEC.md §3, §12.2)", () => {
  it("يدخل المبادر بأي صيغة لرقمه ويسجّل المحاولة الناجحة", async () => {
    const user = await createUser({ phone: "+966512345678" });

    const result = await loginWithPassword("٠٥١٢٣٤٥٦٧٨", "Password-123", IP);

    expect(result.ok).toBe(true);
    const attempts = await db.select().from(loginAttempts);
    expect(attempts).toHaveLength(1);
    expect(attempts[0]).toMatchObject({ phone: "+966512345678", ip: IP, succeeded: true });
    const [updated] = await db.select().from(users).where(eq(users.id, user.id));
    expect(updated.lastLoginIp).toBe(IP);
    expect(updated.lastLoginAt).toBeInstanceOf(Date);
  });

  it("يقبل كلمة مرور مجزّأة في Laravel ($2y$) كما هي بعد النقل", async () => {
    await createUser({ phone: "+966512345678" });
    await db
      .update(users)
      .set({ password: "$2y$12$vRsEX007h9PXZSFJ0gdFJuly8HHJUU6s/u1Yuh82pVEJgC6OYyZG2" })
      .where(eq(users.phone, "+966512345678"));

    expect((await loginWithPassword("0512345678", "Laravel-Pass-123", IP)).ok).toBe(true);
    expect((await loginWithPassword("0512345678", "wrong", IP)).ok).toBe(false);
  });

  it("رسالة موحّدة لكلمة مرور خاطئة ولرقم غير مسجّل", async () => {
    await createUser({ phone: "+966512345678" });

    expect(await loginWithPassword("0512345678", "wrong-password", IP)).toEqual({ ok: false, error: authMessages.failed });
    expect(await loginWithPassword("0599999999", "Password-123", IP)).toEqual({ ok: false, error: authMessages.failed });
    expect(await loginWithPassword("not-a-phone", "Password-123", IP)).toEqual({ ok: false, error: authMessages.failed });

    const attempts = await db.select().from(loginAttempts);
    expect(attempts.map((attempt) => attempt.succeeded)).toEqual([false, false, false]);
  });

  it("يقفل الرقم بعد 5 محاولات فاشلة حتى بكلمة المرور الصحيحة", async () => {
    await createUser({ phone: "+966512345678" });

    for (let attempt = 0; attempt < 5; attempt += 1) {
      await loginWithPassword("0512345678", "wrong-password", `198.51.100.${attempt}`);
    }

    const result = await loginWithPassword("0512345678", "Password-123", "198.51.100.200");

    expect(result.ok).toBe(false);
    expect(!result.ok && result.error).toBe(authMessages.locked(15));
  });

  it("يقفل الـ IP بعد 5 محاولات فاشلة على أرقام مختلفة", async () => {
    await createUser({ phone: "+966512345678" });

    for (let attempt = 0; attempt < 5; attempt += 1) {
      await loginWithPassword(`059000000${attempt}`, "wrong-password", IP);
    }

    const result = await loginWithPassword("0512345678", "Password-123", IP);

    expect(!result.ok && result.error).toBe(authMessages.locked(15));
  });

  it("تتضاعف مدة القفل مع تكرار القفل", async () => {
    await createUser({ phone: "+966512345678" });

    for (let attempt = 0; attempt < 5; attempt += 1) {
      await loginWithPassword("0512345678", "wrong-password", `198.51.100.${attempt}`);
    }

    await db.execute(
      // انتهاء القفل الأول دون انتظار: نُرجع وقت انتهائه إلى الماضي.
      (await import("drizzle-orm")).sql`update cache set expiration = 0 where key like '%:lock'`,
    );

    for (let attempt = 0; attempt < 5; attempt += 1) {
      await loginWithPassword("0512345678", "wrong-password", `198.51.100.${10 + attempt}`);
    }

    const result = await loginWithPassword("0512345678", "Password-123", "198.51.100.200");

    expect(!result.ok && result.error).toBe(authMessages.locked(30));
  });

  it("يمنع الحساب المعطَّل برسالة خاصة", async () => {
    await createUser({ phone: "+966512345678", isActive: false });

    expect(await loginWithPassword("0512345678", "Password-123", IP)).toEqual({ ok: false, error: authMessages.inactive });
  });

  it("لا يدخل المشرف أو المدير قبل خطوة التحقق بخطوتين (المرحلة 2)", async () => {
    await createUser({ phone: "+966512345678", role: "supervisor" });
    await createUser({ phone: "+966512345679", role: "admin", appAuthenticationSecret: "encrypted" });

    expect(await loginWithPassword("0512345678", "Password-123", IP)).toEqual({ ok: false, error: authMessages.twoFactorSetupRequired });
    expect(await loginWithPassword("0512345679", "Password-123", IP)).toEqual({ ok: false, error: authMessages.panelNotYetAvailable });
  });
});
