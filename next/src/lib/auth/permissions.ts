import "server-only";
import { and, eq, inArray } from "drizzle-orm";
import { db } from "@/lib/db";
import { modelHasPermissions, permissions, type User } from "@/lib/db/schema";

/**
 * مفاتيح الصلاحيات القابلة للمنح للمشرفين (docs/SPEC.md §2)، وفحصها بنفس قواعد
 * Gate::before في نسخة Laravel:
 * - الحساب المعطَّل لا يملك شيئًا.
 * - المدير يملك كل شيء ضمنيًا.
 * - المشرف يملك ما مُنح له مباشرة، بشرط امتلاك ما تعتمد عليه الصلاحية.
 * - المبادر لا يملك أي مفتاح.
 *
 * الصلاحيات في جداول spatie نفسها (model_type = App\Models\User، guard = web).
 */

export const permissionKeys = [
  "stats.view",
  "transfers.view",
  "transfers.review",
  "transfers.assign",
  "users.view",
  "users.suspend",
  "recovery.handle",
  "recovery.other_number",
  "recovery.change_phone",
  "security.view",
  "stats.recovery",
  "settings.manage",
  "beneficiaries.manage",
  "content.manage",
  "messages.view",
  "converter.use",
  "supervisors.manage",
] as const;

export type PermissionKey = (typeof permissionKeys)[number];

/** الصلاحيات الحساسة جدًا: يمنحها المدير وحده (§2, §12.5). */
export const sensitivePermissions: readonly PermissionKey[] = [
  "recovery.other_number",
  "recovery.change_phone",
  "beneficiaries.manage",
  "settings.manage",
  "content.manage",
  "supervisors.manage",
];

/** الصلاحيات التي لا تعمل هذه الصلاحية بدونها. */
export function permissionRequires(key: PermissionKey): PermissionKey[] {
  return key === "recovery.other_number" || key === "recovery.change_phone" ? ["recovery.handle"] : [];
}

/** قوالب الأدوار الجاهزة (config/permission_templates.php). */
export const permissionTemplates: Record<string, { label: string; permissions: PermissionKey[] }> = {
  transfers_reviewer: { label: "مراجع الحوالات", permissions: ["stats.view", "transfers.view", "transfers.review"] },
  support_recovery: { label: "مسؤول الدعم والاستعادة", permissions: ["recovery.handle", "users.view"] },
  follow_up: { label: "مشرف متابعة", permissions: ["stats.view", "transfers.view", "users.view", "converter.use"] },
  security: { label: "مشرف أمان", permissions: ["security.view", "users.suspend"] },
  beneficiaries: { label: "مسؤول المستفيدين", permissions: ["beneficiaries.manage", "stats.view", "transfers.view"] },
};

export const USER_MODEL_TYPE = "App\\Models\\User";
export const PERMISSION_GUARD = "web";

export function hasPanelRole(user: Pick<User, "role">): boolean {
  return user.role === "supervisor" || user.role === "admin";
}

/** الصلاحيات الممنوحة للمستخدم مباشرة (بلا منح المدير الضمني). */
export async function grantedPermissions(userId: number): Promise<Set<PermissionKey>> {
  const rows = await db
    .select({ name: permissions.name })
    .from(modelHasPermissions)
    .innerJoin(permissions, eq(permissions.id, modelHasPermissions.permissionId))
    .where(
      and(
        eq(modelHasPermissions.modelId, userId),
        eq(modelHasPermissions.modelType, USER_MODEL_TYPE),
        eq(permissions.guardName, PERMISSION_GUARD),
      ),
    );

  return new Set(rows.map((row) => row.name).filter((name): name is PermissionKey => (permissionKeys as readonly string[]).includes(name)));
}

/** قرار الصلاحية من بيانات المستخدم ومنحه المباشر، بلا قاعدة بيانات (يُختبر وحده). */
export function decidePermission(user: Pick<User, "role" | "isActive">, granted: ReadonlySet<PermissionKey>, key: PermissionKey): boolean {
  if (!user.isActive) {
    return false;
  }

  if (user.role === "admin") {
    return true;
  }

  if (user.role !== "supervisor") {
    return false;
  }

  if (permissionRequires(key).some((required) => !granted.has(required))) {
    return false;
  }

  return granted.has(key);
}

/** فحص الصلاحية على الخادم عند كل طلب (§2: تسري التغييرات فورًا). */
export async function can(user: Pick<User, "id" | "role" | "isActive">, key: PermissionKey): Promise<boolean> {
  if (!user.isActive || user.role === "user") {
    return false;
  }

  if (user.role === "admin") {
    return true;
  }

  return decidePermission(user, await grantedPermissions(user.id), key);
}

/** يضمن وجود صفوف المفاتيح في جدول permissions (مثل PermissionSeeder). */
export async function seedPermissionKeys(): Promise<void> {
  const existing = await db
    .select({ name: permissions.name })
    .from(permissions)
    .where(and(eq(permissions.guardName, PERMISSION_GUARD), inArray(permissions.name, [...permissionKeys])));
  const existingNames = new Set(existing.map((row) => row.name));
  const now = new Date();
  const missing = permissionKeys.filter((key) => !existingNames.has(key));

  if (missing.length > 0) {
    await db
      .insert(permissions)
      .values(missing.map((name) => ({ name, guardName: PERMISSION_GUARD, createdAt: now, updatedAt: now })))
      .onConflictDoNothing();
  }
}

/** يستبدل صلاحيات المشرف المباشرة بالقائمة المعطاة. */
export async function syncPermissions(userId: number, keys: readonly PermissionKey[]): Promise<void> {
  await seedPermissionKeys();

  await db.transaction(async (tx) => {
    await tx.delete(modelHasPermissions).where(and(eq(modelHasPermissions.modelId, userId), eq(modelHasPermissions.modelType, USER_MODEL_TYPE)));

    if (keys.length === 0) {
      return;
    }

    const rows = await tx
      .select({ id: permissions.id })
      .from(permissions)
      .where(and(eq(permissions.guardName, PERMISSION_GUARD), inArray(permissions.name, [...keys])));

    await tx.insert(modelHasPermissions).values(rows.map((row) => ({ permissionId: row.id, modelType: USER_MODEL_TYPE, modelId: userId })));
  });
}
