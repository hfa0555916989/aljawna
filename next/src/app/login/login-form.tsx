"use client";

import Link from "next/link";
import { useActionState, useState } from "react";
import { loginAction, type LoginState } from "@/app/actions/auth";
import { FormAlert, PasswordField, SubmitButton, TextField, TurnstileWidget } from "@/components/form-fields";

export function LoginForm({ turnstileSiteKey, nonce }: { turnstileSiteKey: string | null; nonce?: string }) {
  const [state, action, pending] = useActionState<LoginState, FormData>(loginAction, {});
  // حقول متحكَّم بها: React يفرّغ الحقول غير المتحكَّم بها بعد كل إرسال، فتضيع مدخلات المستخدم عند الخطأ.
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");

  return (
    <form action={action} noValidate className="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
      <FormAlert message={state.error} />

      <TextField name="phone" label="رقم الجوال" type="tel" ltr autoComplete="tel" placeholder="05XXXXXXXX" value={phone} onChange={setPhone} />

      <PasswordField name="password" label="كلمة المرور" autoComplete="current-password" value={password} onChange={setPassword} />

      <TurnstileWidget siteKey={turnstileSiteKey} nonce={nonce} />

      <SubmitButton pending={pending}>دخول</SubmitButton>

      <p className="text-center text-sm text-muted">
        ليس لديك حساب؟{" "}
        <Link href="/register" className="inline-flex min-h-11 items-center font-medium text-pri underline-offset-4 hover:underline">
          أنشئ حسابًا
        </Link>
      </p>
    </form>
  );
}
