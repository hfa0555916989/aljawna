import "server-only";
import bcrypt from "bcryptjs";

/**
 * تجزئة كلمات المرور بـ bcrypt (docs/SPEC.md §12.1)، متوافقة مع تجزئات Laravel
 * المنقولة ($2y$، 12 دورة)، فيدخل الجميع بكلمات مرورهم الحالية.
 */

const ROUNDS = Number(process.env.BCRYPT_ROUNDS ?? 12);

export async function hashPassword(plain: string): Promise<string> {
  return bcrypt.hash(plain, ROUNDS);
}

export async function verifyPassword(plain: string, hash: string): Promise<boolean> {
  try {
    return await bcrypt.compare(plain, hash);
  } catch {
    return false;
  }
}

/** تجزئة ثابتة لموازنة زمن الاستجابة عند رقم غير مسجّل، كي لا يُكشف بالتوقيت. */
const DUMMY_HASH = "$2y$12$C6UzMDM.H6dfI/f/IKcEeO5h0X1nP4QGtvuMtUz7ShE0Q9k7d2l3a";

export async function burnPasswordCheck(plain: string): Promise<void> {
  await verifyPassword(plain, DUMMY_HASH);
}
