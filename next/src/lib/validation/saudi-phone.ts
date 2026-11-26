/**
 * تطبيع رقم الجوال السعودي إلى صيغة موحّدة (docs/SPEC.md §3, §9).
 *
 * يقبل الصيغ: `05XXXXXXXX`، `+9665XXXXXXXX`، `009665XXXXXXXX`، بمسافات أو رموز
 * فاصلة بينها، وبالأرقام العربية (الهندية) أو اللاتينية. يُخرج دائمًا
 * `+9665XXXXXXXX`، ويرفض أي رقم غير سعودي أو غير مطابق لصيغة جوال.
 *
 * مطابق لـ App\Support\SaudiPhone في نسخة Laravel.
 */

/** جوال سعودي داخل نص حر بأي صيغة تقبلها normalizeSaudiPhone. */
const IN_TEXT_PATTERN = /(?<!\d)(?:\+\s*966|00966|966|0)[\s\-.()]*5(?:[\s\-.()]*\d){8}(?!\d)/u;

const ARABIC_INDIC_DIGITS = "٠١٢٣٤٥٦٧٨٩";

/** يحوّل الأرقام العربية (الهندية) ٠-٩ إلى أرقام لاتينية 0-9. */
export function toWesternDigits(value: string): string {
  return value.replace(/[٠-٩]/gu, (digit) => String(ARABIC_INDIC_DIGITS.indexOf(digit)));
}

/** يطبّع رقم جوال سعودي إلى `+9665XXXXXXXX`، أو يعيد null إن كان غير صالح. */
export function normalizeSaudiPhone(raw: string | null | undefined): string | null {
  if (raw === null || raw === undefined) {
    return null;
  }

  const digits = toWesternDigits(raw);
  const hasLeadingPlus = digits.trimStart().startsWith("+");
  const digitsOnly = digits.replace(/\D/gu, "");

  if (digitsOnly === "") {
    return null;
  }

  let local: string | null = null;

  if (hasLeadingPlus && digitsOnly.startsWith("966")) {
    local = digitsOnly.slice(3);
  } else if (!hasLeadingPlus && digitsOnly.startsWith("00966")) {
    local = digitsOnly.slice(5);
  } else if (!hasLeadingPlus && digitsOnly.startsWith("0") && !digitsOnly.startsWith("00")) {
    local = digitsOnly.slice(1);
  }

  if (local === null || !/^5\d{8}$/u.test(local)) {
    return null;
  }

  return `+966${local}`;
}

export function isValidSaudiPhone(raw: string | null | undefined): boolean {
  return normalizeSaudiPhone(raw) !== null;
}

/** هل يحتوي نص حر رقم جوال سعودي بأي صيغة مقبولة؟ */
export function saudiPhoneAppearsIn(text: string): boolean {
  return IN_TEXT_PATTERN.test(toWesternDigits(text));
}
