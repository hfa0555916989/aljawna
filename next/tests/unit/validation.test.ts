import { describe, expect, it } from "vitest";
import { fullNameError, squishFullName } from "@/lib/validation/full-name";
import { isValidSaudiPhone, normalizeSaudiPhone, saudiPhoneAppearsIn } from "@/lib/validation/saudi-phone";

describe("normalizeSaudiPhone (منقول من tests/Unit/SaudiPhoneTest.php)", () => {
  it("يطبّع الصيغ الثلاث المقبولة", () => {
    expect(normalizeSaudiPhone("0512345678")).toBe("+966512345678");
    expect(normalizeSaudiPhone("+966512345678")).toBe("+966512345678");
    expect(normalizeSaudiPhone("00966512345678")).toBe("+966512345678");
  });

  it("يزيل المسافات والرموز الفاصلة", () => {
    expect(normalizeSaudiPhone("+966 51 234 5678")).toBe("+966512345678");
    expect(normalizeSaudiPhone("05-1234-5678")).toBe("+966512345678");
    expect(normalizeSaudiPhone("(05) 1234 5678")).toBe("+966512345678");
  });

  it("يقبل الأرقام العربية (الهندية)", () => {
    expect(normalizeSaudiPhone("٠٥١٢٣٤٥٦٧٨")).toBe("+966512345678");
    expect(normalizeSaudiPhone("+٩٦٦٥١٢٣٤٥٦٧٨")).toBe("+966512345678");
  });

  it.each(Array.from({ length: 10 }, (_, index) => 50 + index))("يقبل البادئة %i", (prefix) => {
    expect(normalizeSaudiPhone(`0${prefix}1234567`)).toBe(`+966${prefix}1234567`);
  });

  it.each([
    ["أقصر", "05123456"],
    ["أطول", "051234567890"],
    ["مصر", "+20101234567"],
    ["الإمارات", "+971501234567"],
    ["ثابت", "0112345678"],
    ["أحرف", "05ABCDEFGH"],
    ["فارغ", ""],
  ])("يرفض %s", (_label, input) => {
    expect(normalizeSaudiPhone(input)).toBeNull();
  });

  it("يرفض null", () => {
    expect(normalizeSaudiPhone(null)).toBeNull();
    expect(isValidSaudiPhone("0012345678")).toBe(false);
    expect(isValidSaudiPhone("0512345678")).toBe(true);
  });

  it.each([
    "تواصل معه على 0512345678 مساءً",
    "رقمه +966512345678",
    "رقمه + 966 51 234 5678",
    "00966512345678",
    "05-1234-5678",
    "رقمه ٠٥١٢٣٤٥٦٧٨",
    "الرقم:0512345678.",
  ])("يكشف جوالًا في النص: %s", (text) => {
    expect(saudiPhoneAppearsIn(text)).toBe(true);
  });

  it.each(["11 محاولة فاشلة خلال 24 ساعة", "0112345678", "05123456", "051234567890", "+971501234567", "2026-09-27"])(
    "لا يكشف: %s",
    (text) => {
      expect(saudiPhoneAppearsIn(text)).toBe(false);
    },
  );
});

describe("fullNameError (منقول من tests/Unit/FullNameRuleTest.php)", () => {
  it.each(["أحمد محمد علي العجاوني", "أحمد محمد علي سعيد العجاوني", "Ahmad Mohammed Ali Alajawni", "  أحمد   محمد   علي   العجاوني  "])(
    "يقبل: %s",
    (name) => {
      expect(fullNameError(name)).toBeNull();
    },
  );

  it.each(["أحمد محمد علي", "أحمد محمد علي 2024", "أحمد-محمد علي العجاوني", "أ محمد علي العجاوني", "", "   "])("يرفض: %s", (name) => {
    expect(fullNameError(name)).not.toBeNull();
  });

  it("يرفض قيمة غير نصية", () => {
    expect(fullNameError(12345678)).not.toBeNull();
  });

  it("يوحّد المسافات", () => {
    expect(squishFullName("  أحمد   محمد  علي العجاوني ")).toBe("أحمد محمد علي العجاوني");
  });
});
