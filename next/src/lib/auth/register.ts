import "server-only";
import { eq } from "drizzle-orm";
import { cacheHit } from "@/lib/cache-store";
import { securityConfig } from "@/lib/config";
import { db } from "@/lib/db";
import { users, type User } from "@/lib/db/schema";
import { fullNameError, squishFullName } from "@/lib/validation/full-name";
import { normalizeSaudiPhone } from "@/lib/validation/saudi-phone";
import { authMessages } from "./messages";
import { hashPassword } from "./password";

/**
 * تسجيل المبادر (docs/SPEC.md §3, FR-1..6)، مطابق لـ App\Livewire\Auth\Register
 * وApp\Actions\Auth\RegisterUser: حساب فعّال فورًا، ورقم فريد، وحدّ للتسجيل لكل IP
 * في الساعة تُحتسب فيه كل محاولة ناجحة أو فاشلة (§12.3).
 */

export type RegisterInput = {
  fullName: string;
  phone: string;
  phoneConfirmation: string;
  password: string;
  passwordConfirmation: string;
};

export type FieldErrors = Partial<Record<keyof RegisterInput | "form" | "turnstile", string>>;

const REGISTER_DECAY_SECONDS = 3600;

/** يستهلك محاولة من حد الـ IP، ويعيد رسالة خطأ إن تجاوزه. */
export async function consumeRegisterAttempt(ip: string): Promise<string | null> {
  const { attempts, availableIn } = await cacheHit(`register:ip:${ip}`, REGISTER_DECAY_SECONDS);

  if (attempts > securityConfig.register.maxPerIpPerHour()) {
    return authMessages.registerThrottled(Math.ceil(availableIn / 60));
  }

  return null;
}

/** هل يطابق تأكيد الجوال الجوال؟ null قبل كتابة التأكيد (للمؤشر الفوري ✓/✗). */
export function phoneConfirmationMatches(phone: string, confirmation: string): boolean | null {
  if (confirmation.trim() === "") {
    return null;
  }

  const normalizedPhone = normalizeSaudiPhone(phone);
  const normalizedConfirmation = normalizeSaudiPhone(confirmation);

  if (normalizedPhone !== null && normalizedConfirmation !== null) {
    return normalizedPhone === normalizedConfirmation;
  }

  return phone.trim() === confirmation.trim();
}

/** التحقق من الحقول دون قاعدة البيانات. */
export function validateRegisterInput(input: RegisterInput): FieldErrors {
  const errors: FieldErrors = {};
  const phone = normalizeSaudiPhone(input.phone);

  const nameError = input.fullName.trim() === "" ? authMessages.required : fullNameError(input.fullName);

  if (nameError) {
    errors.fullName = nameError;
  } else if (input.fullName.length > 255) {
    errors.fullName = "الاسم الكامل أطول من المسموح.";
  }

  if (input.phone.trim() === "") {
    errors.phone = authMessages.required;
  } else if (input.phone.length > 32 || phone === null) {
    errors.phone = authMessages.phoneInvalid;
  }

  if (input.phoneConfirmation.trim() === "") {
    errors.phoneConfirmation = authMessages.required;
  } else if (phoneConfirmationMatches(input.phone, input.phoneConfirmation) !== true) {
    errors.phoneConfirmation = authMessages.phoneMismatch;
  }

  if (input.password === "") {
    errors.password = authMessages.required;
  } else if (input.password.length < 8) {
    errors.password = authMessages.passwordTooShort;
  } else if (input.password.length > 255) {
    errors.password = "كلمة المرور أطول من المسموح.";
  } else if (input.password !== input.passwordConfirmation) {
    errors.password = authMessages.passwordMismatch;
  } else if (input.password.trim() === input.phone.trim() || (phone !== null && normalizeSaudiPhone(input.password) === phone)) {
    errors.password = authMessages.passwordEqualsPhone;
  }

  if (input.passwordConfirmation === "") {
    errors.passwordConfirmation = authMessages.required;
  }

  return errors;
}

export type RegisterResult = { ok: true; user: User } | { ok: false; errors: FieldErrors };

/** ينشئ الحساب بعد نجاح التحقق والكابتشا. */
export async function registerInitiator(input: RegisterInput, ip: string): Promise<RegisterResult> {
  const errors = validateRegisterInput(input);
  const phone = normalizeSaudiPhone(input.phone);

  if (Object.keys(errors).length > 0 || phone === null) {
    return { ok: false, errors };
  }

  const existing = await db.select({ id: users.id }).from(users).where(eq(users.phone, phone)).limit(1);

  if (existing.length > 0) {
    return { ok: false, errors: { phone: authMessages.phoneTaken } };
  }

  const now = new Date();

  try {
    const [user] = await db
      .insert(users)
      .values({
        fullName: squishFullName(input.fullName),
        phone,
        password: await hashPassword(input.password),
        role: "user",
        isActive: true,
        registeredIp: ip,
        lastLoginIp: ip,
        lastLoginAt: now,
        createdAt: now,
        updatedAt: now,
      })
      .returning();

    return { ok: true, user };
  } catch (error) {
    if (isUniqueViolation(error)) {
      return { ok: false, errors: { phone: authMessages.phoneTaken } };
    }

    throw error;
  }
}

function isUniqueViolation(error: unknown): boolean {
  const code = (error as { code?: string; cause?: { code?: string } } | null)?.code ?? (error as { cause?: { code?: string } } | null)?.cause?.code;

  return code === "23505";
}
