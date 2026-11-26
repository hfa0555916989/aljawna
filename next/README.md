# مبادرة العجاونة — نسخة Next.js

إعادة بناء الموقع بـ Next.js وTypeScript للنشر على Vercel، وفق `docs/SPEC.md` وخطة النقل.
يبقى موقع Laravel في جذر المستودع هو الإنتاج حتى التحويل النهائي.

## ما تم (المرحلة 1: الأساس)

- مخطط قاعدة البيانات كاملًا بـ Drizzle، مطابق لترحيلات Laravel بأسماء الجداول والأعمدة والقيود، مع Triggers سجلات الإضافة فقط.
- التسجيل والدخول بالجوال، والقفل المؤقت المتضاعف، وحد التسجيل لكل IP، والكابتشا وحقل الفخ.
- جلسات في Postgres مربوطة بـ `session_epoch`، وتنتهي فورًا عند تعطيل الحساب.
- الصلاحيات بجداول spatie نفسها وقواعد `Gate::before` نفسها.
- CSP بـ nonce لكل طلب بلا `unsafe-inline` ولا `unsafe-eval`، وHSTS.

## التشغيل محليًا

```bash
cp .env.example .env.local
npm install
npm run db:migrate
npm run dev
```

## الاختبارات

تحتاج Postgres محليًا وقاعدة `ajawna_next_test` (أو `TEST_DATABASE_URL`):

```bash
npm test
npm run lint
npm run typecheck
```

## ملاحظات توافق مع بيانات Laravel

- **الأوقات:** Laravel يخزّن الأوقات بتوقيت الرياض بلا منطقة زمنية. العمود `riyadhTimestamp` (`src/lib/db/riyadh-timestamp.ts`) يقرأها ويكتبها بالطريقة نفسها.
- **كلمات المرور:** تجزئات `$2y$` من Laravel تُقبل كما هي.
- **الحدود والقفل:** في جدول `cache` بـ Postgres بدل Redis، ببادئة `ajawna-next:`.
