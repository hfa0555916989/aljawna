# T00 — تهيئة المشروع والبنية

- **الفرع:** `task/T00-setup`
- **يعتمد على:** —
- **المراجع:** `docs/SPEC.md` §11, §12.1

## الهدف
مشروع Laravel 13 جاهز بحزمة الأدوات، وبيئة محلية، وCI، وتخطيط RTL أساسي.

## المطلوب
1. إنشاء مشروع Laravel 13 (PHP 8.3+) وضبط `.env.example` (PostgreSQL وRedis). **الجذر غير فارغ** (فيه ملفات الحزمة) و`composer create-project` يتطلب مجلدًا فارغًا، لذلك: أنشئ المشروع في مجلد مؤقت **خارج** المستودع، ثم انقل محتوياته إلى الجذر **دون استبدال** `AGENTS.md` و`START-HERE.md` و`docs/` و`tasks/` و`.cursor/` و`.github/`. ادمج `.gitignore` إن وُجد ملفان، واكتب `README.md` للمشروع (لا يتعارض مع `START-HERE.md`).
2. `docker-compose.yml` للتطوير المحلي (PostgreSQL 17+ وRedis) أو Laravel Sail، مع شرح في README.
3. تثبيت: `livewire/livewire ^4`, `filament/filament ^5`, `spatie/laravel-permission`, `pestphp/pest ^5` (مع إضافة Laravel), `laravel/pint`, `larastan/larastan`, و`laravel/boost` (dev) وتشغيل تثبيته حسب توثيقه.
4. سكربتات Composer: `lint` (Pint --test) و`analyse` (Larastan) و`test` (Pest).
5. Tailwind 4 مع رموز `docs/DESIGN-TOKENS.md` (فاتح وداكن)، وخطوط IBM Plex Sans Arabic وAref Ruqaa مستضافة في `resources/fonts` (لا CDN).
6. اللغة `ar`، والعرض بتوقيت `Asia/Riyadh`، وهيكل `lang/ar`.
7. التخطيط الأساسي `layouts/app.blade.php`: `dir=rtl lang=ar`، ميتا `viewport-fit=cover`، هوامش `safe-area`، ترويسة وتذييل، دعم داكن.
8. `.github/workflows/ci.yml`: على كل PR يشغّل Pint وLarastan وPest مع خدمتي PostgreSQL وRedis.
9. حماية `main` في GitHub (يوثَّق في README): لا دفع مباشر، ودمج بعد نجاح CI.

## معايير القبول
- [ ] `composer lint && composer analyse && composer test` تنجح.
- [ ] الصفحة الرئيسية الفارغة تُعرض RTL بلا تمرير أفقي على 360px.
- [ ] الخطوط تُحمَّل من المشروع وليس من الشبكة.
- [ ] CI أخضر على PR تجريبي.
- [ ] `.env` غير مُتتبَّع في Git.

## الاختبارات المطلوبة
- اختبار أن الصفحة الرئيسية تُرجع 200 وأن `dir="rtl"` موجود.

## خارج النطاق
- أي منطق أعمال.

## قبل الدمج
- `composer lint && composer analyse && composer test`
- وصف PR بالقالب: ما تم، ما لم يتم، أسئلة مفتوحة، ولقطات شاشة (جوال 360px + كمبيوتر 1280px) للواجهات.
- راجع "تعريف الإنجاز" في `AGENTS.md`.
