/**
 * الإعدادات من متغيرات البيئة، بنفس الأسماء والقيم الافتراضية في config/security.php.
 */

const intFromEnv = (name: string, fallback: number): number => {
  const raw = process.env[name];
  const parsed = raw === undefined || raw === "" ? Number.NaN : Number.parseInt(raw, 10);

  return Number.isFinite(parsed) ? parsed : fallback;
};

const boolFromEnv = (name: string, fallback: boolean): boolean => {
  const raw = process.env[name];

  if (raw === undefined || raw === "") {
    return fallback;
  }

  return ["1", "true", "yes", "on"].includes(raw.toLowerCase());
};

export const securityConfig = {
  login: {
    maxAttempts: () => intFromEnv("LOGIN_MAX_ATTEMPTS", 5),
    decayMinutes: () => intFromEnv("LOGIN_DECAY_MINUTES", 15),
    lockoutMinutes: () => intFromEnv("LOGIN_LOCKOUT_MINUTES", 15),
    lockoutMaxMinutes: () => intFromEnv("LOGIN_LOCKOUT_MAX_MINUTES", 1440),
    strikesResetHours: () => intFromEnv("LOGIN_STRIKES_RESET_HOURS", 24),
  },
  register: {
    maxPerIpPerHour: () => intFromEnv("REGISTER_MAX_PER_IP_PER_HOUR", 5),
  },
  session: {
    lifetimeMinutes: () => intFromEnv("SESSION_LIFETIME", 120),
  },
  twoFactorRequired: () => boolFromEnv("TWO_FACTOR_REQUIRED", true),
  turnstile: {
    enabled: () => boolFromEnv("TURNSTILE_ENABLED", false),
    siteKey: () => process.env.TURNSTILE_SITE_KEY || null,
    secretKey: () => process.env.TURNSTILE_SECRET_KEY || null,
    verifyUrl: () => process.env.TURNSTILE_VERIFY_URL || "https://challenges.cloudflare.com/turnstile/v0/siteverify",
  },
} as const;
