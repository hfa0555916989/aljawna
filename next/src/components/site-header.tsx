import Link from "next/link";
import { logoutAction } from "@/app/actions/auth";
import { currentUser } from "@/lib/auth/session";

/** رأس الموقع: اسم المبادرة وروابط الدخول أو لوحة المبادر. */
export async function SiteHeader() {
  const user = await currentUser();

  return (
    <header className="border-b border-line bg-surface">
      <nav className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-2">
        <Link href="/" className="inline-flex min-h-11 items-center font-brand text-2xl text-pri">
          مبادرة العجاونة
        </Link>

        <div className="flex items-center gap-2 text-sm">
          {user ? (
            <>
              <Link href="/dashboard" className="inline-flex min-h-11 items-center px-2 font-medium text-pri hover:underline">
                لوحتي
              </Link>
              <form action={logoutAction}>
                <button type="submit" className="inline-flex min-h-11 items-center px-2 font-medium text-muted hover:underline">
                  خروج
                </button>
              </form>
            </>
          ) : (
            <>
              <Link href="/login" className="inline-flex min-h-11 items-center px-2 font-medium text-pri hover:underline">
                دخول
              </Link>
              <Link
                href="/register"
                className="inline-flex min-h-11 items-center rounded-[10px] bg-pri px-4 font-semibold text-pri-ink hover:opacity-90"
              >
                إنشاء حساب
              </Link>
            </>
          )}
        </div>
      </nav>
    </header>
  );
}
