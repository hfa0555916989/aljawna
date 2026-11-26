/**
 * قاعدة الاسم الكامل (docs/SPEC.md §3): أربع كلمات فأكثر، أحرف عربية أو لاتينية
 * ومسافات فقط، وكل كلمة حرفان على الأقل. مطابقة لـ App\Rules\FullName.
 */

const MIN_WORDS = 4;
const MIN_WORD_LENGTH = 2;

/** يعيد رسالة الخطأ، أو null إن كان الاسم صالحًا. */
export function fullNameError(value: unknown): string | null {
  if (typeof value !== "string") {
    return "يجب أن يكون الاسم الكامل نصًا.";
  }

  const trimmed = value.trim();

  if (trimmed === "" || !/^[؀-ۿa-zA-Z\s]+$/u.test(trimmed)) {
    return "يُسمح بالأحرف العربية أو اللاتينية والمسافات فقط في الاسم الكامل.";
  }

  const words = trimmed.split(/\s+/u).filter((word) => word !== "");

  if (words.length < MIN_WORDS) {
    return "اكتب اسمك كاملًا (أربع كلمات على الأقل).";
  }

  if (words.some((word) => [...word].length < MIN_WORD_LENGTH)) {
    return "كل كلمة في الاسم الكامل يجب أن تكون حرفين على الأقل.";
  }

  return null;
}

/** يوحّد المسافات قبل الحفظ، كما في RegisterUser. */
export function squishFullName(value: string): string {
  return value.trim().replace(/\s+/gu, " ");
}
