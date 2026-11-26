import Link from "next/link";

/** الصفحة الرئيسية مؤقتًا. عدّادا المبادرات والإحصائيات في المرحلة 2 (T06). */
export default function HomePage() {
  return (
    <div className="mx-auto w-full max-w-5xl px-4 py-12">
      <h1 className="font-brand text-4xl text-pri">مبادرة العجاونة</h1>
      <p className="mt-4 max-w-prose text-lg text-muted">
        مبادرة لدعم زواج المستفيدين المسجّلين: يختار المبادر مستفيدًا ويحوّل إلى حسابه مباشرة، ويرفع إيصاله فيُحتسب فورًا.
      </p>
      <div className="mt-8 flex flex-wrap gap-3">
        <Link href="/register" className="inline-flex min-h-11 items-center rounded-[10px] bg-pri px-5 font-semibold text-pri-ink hover:opacity-90">
          إنشاء حساب
        </Link>
        <Link href="/login" className="inline-flex min-h-11 items-center rounded-[10px] border border-line bg-surface px-5 font-semibold text-pri">
          دخول
        </Link>
      </div>
    </div>
  );
}
