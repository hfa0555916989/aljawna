import { eq, sql } from "drizzle-orm";
import { beforeEach, describe, expect, it } from "vitest";
import { can, decidePermission, grantedPermissions, permissionTemplates, syncPermissions } from "@/lib/auth/permissions";
import { currentUser, endOtherSessions, endSession, hashSessionToken, SESSION_COOKIE, startSession } from "@/lib/auth/session";
import { db } from "@/lib/db";
import { auditLogs, sessions, users } from "@/lib/db/schema";
import { clientIpFromHeaders } from "@/lib/request-ip";
import { buildContentSecurityPolicy } from "@/proxy";
import { createUser, resetDatabase } from "../support/database";
import { testRequest } from "../support/setup";

const meta = { ip: "203.0.113.30", userAgent: "vitest" };

beforeEach(resetDatabase);

describe("الجلسات (docs/SPEC.md §12.1)", () => {
  it("تحفظ القاعدة بصمة الرمز لا الرمز نفسه، وتعيد صاحب الجلسة", async () => {
    const user = await createUser();

    await startSession(user, meta);

    const token = testRequest.cookieJar.get(SESSION_COOKIE) as string;
    const [row] = await db.select().from(sessions);
    expect(row.id).toBe(hashSessionToken(token));
    expect(row.id).not.toBe(token);
    expect((await currentUser())?.id).toBe(user.id);
  });

  it("رفع session_epoch ينهي الجلسة فورًا (تعديل رقم الدخول §4.2)", async () => {
    const user = await createUser();
    await startSession(user, meta);

    await db.update(users).set({ sessionEpoch: sql`${users.sessionEpoch} + 1` }).where(eq(users.id, user.id));

    expect(await currentUser()).toBeNull();
    expect(await db.select().from(sessions)).toHaveLength(0);
  });

  it("تعطيل الحساب ينهي الجلسة فورًا", async () => {
    const user = await createUser();
    await startSession(user, meta);

    await db.update(users).set({ isActive: false }).where(eq(users.id, user.id));

    expect(await currentUser()).toBeNull();
  });

  it("تنتهي الجلسة بعد مدة الخمول", async () => {
    const user = await createUser();
    await startSession(user, meta);

    await db.update(sessions).set({ lastActivity: Math.floor(Date.now() / 1000) - 121 * 60 });

    expect(await currentUser()).toBeNull();
  });

  it("الدخول يستبدل الجلسة السابقة، والخروج يحذفها", async () => {
    const user = await createUser();
    await startSession(user, meta);
    const firstToken = testRequest.cookieJar.get(SESSION_COOKIE);

    await startSession(user, meta);

    expect(testRequest.cookieJar.get(SESSION_COOKIE)).not.toBe(firstToken);
    expect(await db.select().from(sessions)).toHaveLength(1);

    await endSession();

    expect(testRequest.cookieJar.has(SESSION_COOKIE)).toBe(false);
    expect(await db.select().from(sessions)).toHaveLength(0);
  });

  it("endOtherSessions تبقي الجلسة الحالية وحدها", async () => {
    const user = await createUser();
    await db.insert(sessions).values({ id: "other-device", userId: user.id, payload: '{"epoch":0}', lastActivity: Math.floor(Date.now() / 1000) });
    await startSession(user, meta);

    await endOtherSessions(user.id);

    const remaining = await db.select().from(sessions);
    expect(remaining).toHaveLength(1);
    expect(remaining[0].id).toBe(hashSessionToken(testRequest.cookieJar.get(SESSION_COOKIE) as string));
  });
});

describe("الصلاحيات (docs/SPEC.md §2)", () => {
  it("المدير يملك كل شيء، والمبادر لا يملك شيئًا، والمعطَّل لا يملك شيئًا", async () => {
    const admin = await createUser({ role: "admin" });
    const initiator = await createUser();
    const inactiveAdmin = await createUser({ role: "admin", isActive: false });

    expect(await can(admin, "supervisors.manage")).toBe(true);
    expect(await can(initiator, "stats.view")).toBe(false);
    expect(await can(inactiveAdmin, "stats.view")).toBe(false);
  });

  it("المشرف يملك ما مُنح فقط، ويسري التغيير فورًا", async () => {
    const supervisor = await createUser({ role: "supervisor" });

    await syncPermissions(supervisor.id, permissionTemplates.transfers_reviewer.permissions);

    expect(await can(supervisor, "transfers.review")).toBe(true);
    expect(await can(supervisor, "users.suspend")).toBe(false);

    await syncPermissions(supervisor.id, []);

    expect(await can(supervisor, "transfers.review")).toBe(false);
  });

  it("الصلاحيات المعتمدة على recovery.handle لا تعمل بدونها", async () => {
    const supervisor = await createUser({ role: "supervisor" });

    await syncPermissions(supervisor.id, ["recovery.change_phone"]);

    expect(await grantedPermissions(supervisor.id)).toEqual(new Set(["recovery.change_phone"]));
    expect(await can(supervisor, "recovery.change_phone")).toBe(false);
    expect(decidePermission(supervisor, new Set(["recovery.handle", "recovery.change_phone"]), "recovery.change_phone")).toBe(true);
  });

  it("تُخزَّن في جداول spatie بنوع النموذج نفسه كي تبقى الصلاحيات المنقولة صالحة", async () => {
    const supervisor = await createUser({ role: "supervisor" });

    await syncPermissions(supervisor.id, ["stats.view"]);

    const rows = await db.execute<{ model_type: string; name: string; guard_name: string }>(
      sql`select m.model_type, p.name, p.guard_name from model_has_permissions m join permissions p on p.id = m.permission_id`,
    );
    expect([...rows]).toEqual([{ model_type: "App\\Models\\User", name: "stats.view", guard_name: "web" }]);
  });
});

describe("سجل التدقيق للإضافة فقط", () => {
  it("يرفض Postgres تعديل السجل أو حذفه", async () => {
    await db.insert(auditLogs).values({ action: "test", createdAt: new Date() });

    await expect(db.update(auditLogs).set({ action: "changed" })).rejects.toThrow();
    await expect(db.delete(auditLogs)).rejects.toThrow();
  });
});

describe("عنوان الزائر ورؤوس الأمان", () => {
  it("يقرأ x-real-ip على Vercel فقط، ويتجاهل x-forwarded-for المزوَّر", () => {
    const requestHeaders = new Headers({ "x-forwarded-for": "6.6.6.6, 203.0.113.9", "x-real-ip": "203.0.113.9" });

    expect(clientIpFromHeaders(requestHeaders, { VERCEL: "1" })).toBe("203.0.113.9");
    expect(clientIpFromHeaders(requestHeaders, {})).toBe("127.0.0.1");
    expect(clientIpFromHeaders(new Headers({ "x-real-ip": "not an ip<script>" }), { VERCEL: "1" })).toBe("127.0.0.1");
  });

  it("سياسة CSP بلا unsafe-inline ولا unsafe-eval في الإنتاج", () => {
    const policy = buildContentSecurityPolicy("abc", false);

    expect(policy).toContain("script-src 'self' 'nonce-abc'");
    expect(policy).toContain("frame-ancestors 'none'");
    expect(policy).not.toContain("unsafe-inline");
    expect(policy).not.toContain("unsafe-eval");
  });
});
