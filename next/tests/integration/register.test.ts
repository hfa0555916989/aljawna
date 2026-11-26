import { beforeEach, describe, expect, it } from "vitest";
import { authMessages } from "@/lib/auth/messages";
import { verifyPassword } from "@/lib/auth/password";
import { consumeRegisterAttempt, registerInitiator, validateRegisterInput, type RegisterInput } from "@/lib/auth/register";
import { db } from "@/lib/db";
import { users } from "@/lib/db/schema";
import { createUser, resetDatabase } from "../support/database";

const IP = "203.0.113.20";

const validInput = (overrides: Partial<RegisterInput> = {}): RegisterInput => ({
  fullName: "  أحمد   محمد علي العجاوني ",
  phone: "05 1234 5678",
  phoneConfirmation: "+966512345678",
  password: "Strong-Pass-1",
  passwordConfirmation: "Strong-Pass-1",
  ...overrides,
});

beforeEach(resetDatabase);

describe("registerInitiator (docs/SPEC.md §3, FR-1..6)", () => {
  it("ينشئ حساب مبادر فعّالًا فورًا برقم موحّد واسم موحّد المسافات", async () => {
    const result = await registerInitiator(validInput(), IP);

    expect(result.ok).toBe(true);
    const [user] = await db.select().from(users);
    expect(user).toMatchObject({
      fullName: "أحمد محمد علي العجاوني",
      phone: "+966512345678",
      role: "user",
      isActive: true,
      registeredIp: IP,
    });
    expect(await verifyPassword("Strong-Pass-1", user.password)).toBe(true);
  });

  it("يرفض رقمًا مسجّلًا مسبقًا برسالة المواصفة", async () => {
    await createUser({ phone: "+966512345678" });

    expect(await registerInitiator(validInput(), IP)).toEqual({ ok: false, errors: { phone: authMessages.phoneTaken } });
  });

  it.each([
    [{ fullName: "أحمد محمد علي" }, "fullName"],
    [{ phone: "+971501234567" }, "phone"],
    [{ phoneConfirmation: "0599999999" }, "phoneConfirmation"],
    [{ password: "short", passwordConfirmation: "short" }, "password"],
    [{ passwordConfirmation: "Different-1" }, "password"],
    [{ password: "0512345678", passwordConfirmation: "0512345678" }, "password"],
    [{ password: "+966512345678", passwordConfirmation: "+966512345678" }, "password"],
  ] as const)("يرفض المدخلات غير الصالحة %#", (overrides, fieldName) => {
    expect(validateRegisterInput(validInput(overrides))).toHaveProperty(fieldName);
  });

  it("يحدّ التسجيل بخمس محاولات لكل IP في الساعة", async () => {
    for (let attempt = 0; attempt < 5; attempt += 1) {
      expect(await consumeRegisterAttempt(IP)).toBeNull();
    }

    expect(await consumeRegisterAttempt(IP)).toBe(authMessages.registerThrottled(60));
    expect(await consumeRegisterAttempt("203.0.113.21")).toBeNull();
  });
});
