import { customType } from "drizzle-orm/pg-core";

/**
 * عمود timestamp(0) بلا منطقة زمنية، بقيمة "توقيت الرياض المحلي".
 *
 * نسخة Laravel تخزّن كل الأوقات بتوقيت التطبيق (APP_TIMEZONE=Asia/Riyadh) دون
 * منطقة زمنية، وDrizzle يفترض UTC لهذا النوع. هذا النوع يحفظ التوافق مع
 * البيانات المنقولة كما هي: الرياض UTC+3 ثابتة بلا توقيت صيفي.
 */

const RIYADH_OFFSET_MS = 3 * 60 * 60 * 1000;

/** يحوّل لحظة زمنية إلى نص "YYYY-MM-DD HH:mm:ss" بتوقيت الرياض. */
export function toRiyadhWallTime(date: Date): string {
  return new Date(date.getTime() + RIYADH_OFFSET_MS).toISOString().slice(0, 19).replace("T", " ");
}

/** يقرأ نص وقت محلي بتوقيت الرياض كما يعيده Postgres ويحوّله إلى لحظة زمنية. */
export function fromRiyadhWallTime(value: string): Date {
  return new Date(`${value.replace(" ", "T")}+03:00`);
}

export const riyadhTimestamp = customType<{ data: Date; driverData: string }>({
  dataType() {
    return "timestamp(0) without time zone";
  },
  toDriver(value: Date): string {
    return toRiyadhWallTime(value);
  },
  fromDriver(value: string): Date {
    return fromRiyadhWallTime(value);
  },
});
