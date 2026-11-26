"use client";

import Link from "next/link";
import { useActionState, useState } from "react";
import { registerAction, type RegisterState } from "@/app/actions/auth";
import { FormAlert, PasswordField, SubmitButton, TextField, TurnstileWidget } from "@/components/form-fields";
import { normalizeSaudiPhone } from "@/lib/validation/saudi-phone";

/** مؤشر تطابق فوري ✓/✗ (FR-2). null قبل كتابة التأكيد. */
function phonesMatch(phone: string, confirmation: string): boolean | null {
  if (confirmation.trim() === "") {
    return null;
  }

  const left = normalizeSaudiPhone(phone);
  const right = normalizeSaudiPhone(confirmation);

  return left !== null && right !== null ? left === right : phone.trim() === confirmation.trim();
}

function MatchStatus({ matches, yes, no }: { matches: boolean | null; yes: string; no: string }) {
  return (
    <p aria-live="polite" className="mt-1.5 min-h-5 text-sm">
      {matches === true ? (
        <span className="text-good">
          <span aria-hidden="true">✓</span> {yes}
        </span>
      ) : matches === false ? (
        <span className="text-bad">
          <span aria-hidden="true">✗</span> {no}
        </span>
      ) : null}
    </p>
  );
}

export function RegisterForm({ turnstileSiteKey, nonce }: { turnstileSiteKey: string | null; nonce?: string }) {
  const [state, action, pending] = useActionState<RegisterState, FormData>(registerAction, {});
  // حقول متحكَّم بها: React يفرّغ الحقول غير المتحكَّم بها بعد كل إرسال، فتضيع مدخلات المستخدم عند الخطأ.
  const [fullName, setFullName] = useState("");
  const [phone, setPhone] = useState("");
  const [phoneConfirmation, setPhoneConfirmation] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const errors = state.errors ?? {};

  return (
    <form action={action} noValidate className="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
      <FormAlert message={errors.form} />

      <div className="hidden" aria-hidden="true">
        <label htmlFor="website">اترك هذا الحقل فارغًا</label>
        <input id="website" name="website" type="text" tabIndex={-1} autoComplete="off" />
      </div>

      <TextField
        name="full_name"
        label="الاسم الكامل"
        autoComplete="name"
        placeholder="اكتب اسمك كاملًا"
        value={fullName}
        onChange={setFullName}
        error={errors.fullName}
      />

      <TextField
        name="phone"
        label="رقم الجوال"
        type="tel"
        ltr
        autoComplete="tel"
        placeholder="05XXXXXXXX"
        value={phone}
        onChange={setPhone}
        error={errors.phone}
      />

      <TextField
        name="phone_confirmation"
        label="تأكيد رقم الجوال"
        type="tel"
        ltr
        autoComplete="tel"
        placeholder="05XXXXXXXX"
        value={phoneConfirmation}
        onChange={setPhoneConfirmation}
        error={errors.phoneConfirmation}
      >
        <MatchStatus matches={phonesMatch(phone, phoneConfirmation)} yes="رقما الجوال متطابقان" no="رقما الجوال غير متطابقين" />
      </TextField>

      <PasswordField name="password" label="كلمة المرور" autoComplete="new-password" value={password} onChange={setPassword} error={errors.password} />

      <PasswordField
        name="password_confirmation"
        label="تأكيد كلمة المرور"
        autoComplete="new-password"
        value={passwordConfirmation}
        onChange={setPasswordConfirmation}
        error={errors.passwordConfirmation}
      >
        <MatchStatus
          matches={passwordConfirmation === "" ? null : password === passwordConfirmation}
          yes="كلمتا المرور متطابقتان"
          no="كلمتا المرور غير متطابقتين"
        />
      </PasswordField>

      <TurnstileWidget siteKey={turnstileSiteKey} nonce={nonce} error={errors.turnstile} />

      <SubmitButton pending={pending}>إنشاء الحساب</SubmitButton>

      <p className="text-center text-sm text-muted">
        لديك حساب؟{" "}
        <Link href="/login" className="inline-flex min-h-11 items-center font-medium text-pri underline-offset-4 hover:underline">
          سجّل دخولك
        </Link>
      </p>
    </form>
  );
}
