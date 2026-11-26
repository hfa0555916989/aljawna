"use client";

import Script from "next/script";
import { useState, type ReactNode } from "react";

const inputClass =
  "mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad";

type TextFieldProps = {
  name: string;
  label: string;
  error?: string;
  type?: "text" | "tel";
  autoComplete?: string;
  placeholder?: string;
  defaultValue?: string;
  ltr?: boolean;
  value?: string;
  onChange?: (value: string) => void;
  children?: ReactNode;
};

export function TextField({ name, label, error, type = "text", autoComplete, placeholder, defaultValue, ltr, value, onChange, children }: TextFieldProps) {
  const errorId = `${name}-error`;

  return (
    <div>
      <label htmlFor={name} className="block text-sm font-medium text-ink">
        {label}
      </label>
      <input
        id={name}
        name={name}
        type={type}
        inputMode={type === "tel" ? "tel" : undefined}
        dir={ltr ? "ltr" : undefined}
        autoComplete={autoComplete}
        placeholder={placeholder}
        defaultValue={value === undefined ? defaultValue : undefined}
        value={value}
        onChange={onChange ? (event) => onChange(event.target.value) : undefined}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        className={`${inputClass} ${ltr ? "text-start" : ""}`}
      />
      {children}
      {error ? (
        <p id={errorId} className="mt-1.5 text-sm text-bad">
          {error}
        </p>
      ) : null}
    </div>
  );
}

type PasswordFieldProps = {
  name: string;
  label: string;
  error?: string;
  autoComplete: string;
  value?: string;
  onChange?: (value: string) => void;
  children?: ReactNode;
};

/** حقل كلمة مرور بزر إظهار/إخفاء (FR-3). */
export function PasswordField({ name, label, error, autoComplete, value, onChange, children }: PasswordFieldProps) {
  const [shown, setShown] = useState(false);
  const errorId = `${name}-error`;

  return (
    <div>
      <label htmlFor={name} className="block text-sm font-medium text-ink">
        {label}
      </label>
      <div className="relative mt-1.5">
        <input
          id={name}
          name={name}
          type={shown ? "text" : "password"}
          autoComplete={autoComplete}
          value={value}
          onChange={onChange ? (event) => onChange(event.target.value) : undefined}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
          className="block w-full min-h-11 rounded-[10px] border border-line bg-surface py-2 ps-3 pe-20 text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
        />
        <button
          type="button"
          onClick={() => setShown((current) => !current)}
          aria-pressed={shown}
          aria-controls={name}
          className="absolute inset-y-0 end-0 flex min-h-11 min-w-16 items-center justify-center rounded-e-[10px] px-3 text-sm font-medium text-pri hover:underline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-pri"
        >
          {shown ? "إخفاء" : "إظهار"}
          <span className="sr-only">{label}</span>
        </button>
      </div>
      {children}
      {error ? (
        <p id={errorId} className="mt-1.5 text-sm text-bad">
          {error}
        </p>
      ) : null}
    </div>
  );
}

/** ودجت Cloudflare Turnstile: يضيف الحقل cf-turnstile-response إلى النموذج تلقائيًا. */
export function TurnstileWidget({ siteKey, nonce, error }: { siteKey: string | null; nonce?: string; error?: string }) {
  if (!siteKey) {
    return null;
  }

  return (
    <div>
      <Script src="https://challenges.cloudflare.com/turnstile/v0/api.js" strategy="afterInteractive" nonce={nonce} />
      <div className="cf-turnstile" data-sitekey={siteKey} data-language="ar" />
      {error ? (
        <p role="alert" className="mt-1.5 text-sm text-bad">
          {error}
        </p>
      ) : null}
    </div>
  );
}

export function FormAlert({ message }: { message?: string }) {
  if (!message) {
    return null;
  }

  return (
    <p role="alert" className="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">
      {message}
    </p>
  );
}

export function SubmitButton({ pending, children }: { pending: boolean; children: ReactNode }) {
  return (
    <button
      type="submit"
      disabled={pending}
      className="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri disabled:opacity-60"
    >
      {children}
    </button>
  );
}
