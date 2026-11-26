import "server-only";
import { createHash, randomBytes } from "node:crypto";
import { and, eq, ne } from "drizzle-orm";
import { cookies } from "next/headers";
import { cache } from "react";
import { securityConfig } from "@/lib/config";
import { db } from "@/lib/db";
import { sessions, users, type User } from "@/lib/db/schema";

/**
 * جلسات الدخول في جدول sessions بـ Postgres (docs/SPEC.md §12.1).
 *
 * - الكوكي يحمل رمزًا عشوائيًا (32 بايت)، والقاعدة تحفظ بصمته SHA-256 فقط.
 * - الكوكي HttpOnly وSameSite=Lax وSecure في الإنتاج.
 * - الجلسة تنتهي بعد SESSION_LIFETIME دقيقة بلا نشاط.
 * - كل جلسة مربوطة بـ users.session_epoch: رفعه ينهي كل جلسات المستخدم فورًا
 *   (تعديل رقم الدخول، §4.2)، والحساب المعطَّل لا تبقى له جلسة صالحة.
 */

export const SESSION_COOKIE = "ajawna_session";

/** لا يُحدَّث last_activity أكثر من مرة كل هذه المدة، تخفيفًا للكتابة في القاعدة. */
const TOUCH_INTERVAL_SECONDS = 60;

type SessionPayload = { epoch: number };

const nowSeconds = (): number => Math.floor(Date.now() / 1000);

export function hashSessionToken(token: string): string {
  return createHash("sha256").update(token).digest("hex");
}

function lifetimeSeconds(): number {
  return securityConfig.session.lifetimeMinutes() * 60;
}

async function setSessionCookie(token: string): Promise<void> {
  (await cookies()).set(SESSION_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === "production",
    sameSite: "lax",
    path: "/",
    maxAge: lifetimeSeconds(),
  });
}

/** ينشئ جلسة جديدة دائمًا (يقابل Session::regenerate عند الدخول) ويضبط الكوكي. */
export async function startSession(user: Pick<User, "id" | "sessionEpoch">, meta: { ip: string; userAgent: string | null }): Promise<void> {
  const jar = await cookies();
  const previousToken = jar.get(SESSION_COOKIE)?.value;

  if (previousToken) {
    await db.delete(sessions).where(eq(sessions.id, hashSessionToken(previousToken)));
  }

  const token = randomBytes(32).toString("base64url");
  const payload: SessionPayload = { epoch: user.sessionEpoch };

  await db.insert(sessions).values({
    id: hashSessionToken(token),
    userId: user.id,
    ipAddress: meta.ip,
    userAgent: meta.userAgent?.slice(0, 1000) ?? null,
    payload: JSON.stringify(payload),
    lastActivity: nowSeconds(),
  });

  await setSessionCookie(token);
}

/** ينهي الجلسة الحالية ويحذف الكوكي. */
export async function endSession(): Promise<void> {
  const jar = await cookies();
  const token = jar.get(SESSION_COOKIE)?.value;

  if (token) {
    await db.delete(sessions).where(eq(sessions.id, hashSessionToken(token)));
  }

  jar.delete(SESSION_COOKIE);
}

/** ينهي كل جلسات المستخدم عدا الحالية (بعد تغيير كلمة المرور، §12.1). */
export async function endOtherSessions(userId: number): Promise<void> {
  const token = (await cookies()).get(SESSION_COOKIE)?.value;
  const condition = token ? and(eq(sessions.userId, userId), ne(sessions.id, hashSessionToken(token))) : eq(sessions.userId, userId);

  await db.delete(sessions).where(condition);
}

/** يقرّر صلاحية جلسة من بياناتها، بلا قاعدة بيانات (يُختبر وحده). */
export function isSessionValid(
  session: { lastActivity: number; payload: string },
  user: Pick<User, "isActive" | "sessionEpoch">,
  now: number,
  lifetime: number,
): boolean {
  if (!user.isActive || now - session.lastActivity > lifetime) {
    return false;
  }

  try {
    const payload = JSON.parse(session.payload) as Partial<SessionPayload>;

    return payload.epoch === user.sessionEpoch;
  } catch {
    return false;
  }
}

/**
 * المستخدم صاحب الجلسة الحالية، أو null. يُستدعى في كل طلب يحتاج هوية، وتُخزَّن
 * نتيجته لطوال الطلب الواحد.
 */
export const currentUser = cache(async (): Promise<User | null> => {
  const token = (await cookies()).get(SESSION_COOKIE)?.value;

  if (!token) {
    return null;
  }

  const sessionId = hashSessionToken(token);
  const rows = await db
    .select({ session: sessions, user: users })
    .from(sessions)
    .innerJoin(users, eq(users.id, sessions.userId))
    .where(eq(sessions.id, sessionId))
    .limit(1);
  const row = rows[0];

  if (!row) {
    return null;
  }

  const now = nowSeconds();

  if (!isSessionValid(row.session, row.user, now, lifetimeSeconds())) {
    await db.delete(sessions).where(eq(sessions.id, sessionId));

    return null;
  }

  if (now - row.session.lastActivity >= TOUCH_INTERVAL_SECONDS) {
    await db.update(sessions).set({ lastActivity: now }).where(eq(sessions.id, sessionId));
  }

  return row.user;
});
