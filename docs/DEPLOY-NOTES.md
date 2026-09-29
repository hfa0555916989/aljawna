# ملاحظات النشر

> أسماء متغيرات البيئة فقط، بلا قيم. القيم الحقيقية تعيش في `.env` على الخادم
> فقط، ولا تدخل المستودع أبدًا (docs/SPEC.md §11). راجع `.env.example` للقيم
> الافتراضية عند التطوير المحلي.

الاستضافة الدائمة على **Laravel Cloud** (docs/DECISIONS.md). قاعدة PostgreSQL
وذاكرة Valkey/Redis وتخزين الكائنات موارد مُدارة تُربط بالبيئة، فيحقن Laravel
Cloud متغيرات اتصالها تلقائيًا؛ وأي متغير مخصّص تكتبه يتقدّم عليها.

خطوات الإعداد كاملة خطوة بخطوة في `docs/RUNBOOK.md`، والتحقق بعد كل نشر في
`docs/POST-DEPLOY-CHECKLIST.md`. هذا الملف مرجع المتغيرات.

## متغيرات البيئة المطلوبة

### التطبيق وقاعدة البيانات

| المتغير | الغرض |
|---|---|
| `APP_KEY`, `APP_URL`, `APP_ENV` | أساسيات Laravel. `APP_ENV=production` على الخادم |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | الاتصال بـ PostgreSQL |
| `DB_SSLMODE` | `require` أو `verify-full` مع قاعدة Postgres مُدارة تدعم TLS (`prefer` محليًا) |
| `ADMIN_NAME`, `ADMIN_PHONE`, `ADMIN_PASSWORD` | للتطوير المحلي فقط (`database/seeders/AdminUserSeeder` لا يعمل في الإنتاج). **لا تُضبط في Laravel Cloud**: المدير الأول هناك عبر `php artisan admin:invite` |
| `ADMIN_PATH` | مسار لوحة الإدارة. ضع قيمة لا يعرفها غيرك (حروف لاتينية صغيرة وأرقام وشرطات)؛ الفارغ يعود إلى الافتراضي في `config/admin.php`. لا يوجد `/admin` |

### الأمان والوكلاء

| المتغير | الغرض |
|---|---|
| `TRUSTED_PROXIES`, `TRUSTED_PROXY_CLIENT_IP_HEADER` | **فارغان على Laravel Cloud**: يُوثق بوسيطه تلقائيًا، ويُقرأ عنوان الزائر من `CF-Connecting-IP` لا من أول `X-Forwarded-For` القابل للتزوير (docs/DECISIONS.md). عند النقل إلى خادم آخر: عناوين الوسطاء، و`CF-Connecting-IP` إن كان خلف Cloudflare فقط |
| `HSTS_MAX_AGE`, `HSTS_INCLUDE_SUBDOMAINS` | Strict-Transport-Security من التطبيق (سنة، بلا نطاقات فرعية افتراضيًا). `0` يعطّله |
| `CSP_REPORT_ONLY` | يبقى `false`. `true` للطوارئ فقط: تُرسل سياسة أمان المحتوى دون فرضها |
| `TWO_FACTOR_SETUP_LINK_MINUTES` | صلاحية رابط إعداد التحقق بخطوتين من `admin:reset-2fa` (30) |

### الكاش والجلسات والطوابير (Redis أو Valkey)

| المتغير | الغرض |
|---|---|
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | تبقى `redis` في كل البيئات |
| `REDIS_CLIENT` | `phpredis` أو `predis` بحسب توفّر إضافة PHP على الخادم |
| `REDIS_URL` | **على Laravel Cloud لا يُكتب** هو ولا `REDIS_HOST` ولا بقية متغيرات `REDIS_*` يدويًا: يحقن Laravel Cloud متغيرات اتصال Valkey تلقائيًا، والقيمة اليدوية تطغى على المحقونة (docs/RUNBOOK.md القسم 5). خيار فقط لخدمة Redis مُدارة خارج Laravel Cloud: `redis://` أو `rediss://` (TLS) بمصادقتها وعنوانها كاملة، فيتجاوز باقي متغيرات `REDIS_*` أدناه |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB`, `REDIS_CACHE_DB` | بديل عن `REDIS_URL` عند عدم الحاجة إلى TLS |

النظام لا يستخدم أي أمر أو ميزة خاصة بـ Redis؛ أي خدمة متوافقة مع بروتوكول
Redis (Valkey ومنه) تعمل دون تغيير في الشيفرة.

### تخزين الملفات (قابل للتبديل محلي/سحابي)

> **على Laravel Cloud لا يُكتب أي متغير من هذا الجدول:** تعريف القرصين `receipts` و`public` يأتي من
> `LARAVEL_CLOUD_DISK_CONFIG` ويستبدل تعريف `config/filesystems.php` كاملًا، فتُتجاهل هناك كل المتغيرات أدناه.
> هي للتطوير المحلي ولأي خادم خارج Laravel Cloud فقط (docs/RUNBOOK.md القسمان 6 و19).

| المتغير | الغرض (خارج Laravel Cloud فقط) |
|---|---|
| `RECEIPTS_FILESYSTEM_DRIVER` | `local` أو `s3` لقرص إيصالات الحوالات الخاص (`config/filesystems.php`) |
| `RECEIPTS_AWS_ACCESS_KEY_ID`, `RECEIPTS_AWS_SECRET_ACCESS_KEY`, `RECEIPTS_AWS_DEFAULT_REGION`, `RECEIPTS_AWS_BUCKET`, `RECEIPTS_AWS_URL`, `RECEIPTS_AWS_ENDPOINT`, `RECEIPTS_AWS_USE_PATH_STYLE_ENDPOINT`, `RECEIPTS_AWS_ROOT` | بيانات bucket متوافق مع S3 للإيصالات عند `RECEIPTS_FILESYSTEM_DRIVER=s3`، تُكتب كلها صراحةً. `RECEIPTS_AWS_ROOT` فارغ عند نقل ملفات من Laravel Cloud كما هي |
| `PUBLIC_FILESYSTEM_DRIVER` | `local` أو `s3` لقرص صور الهوية (الشعار والأيقونة) وصور منشئ الصفحات |
| `PUBLIC_AWS_ACCESS_KEY_ID`, `PUBLIC_AWS_SECRET_ACCESS_KEY`, `PUBLIC_AWS_DEFAULT_REGION`, `PUBLIC_AWS_BUCKET`, `PUBLIC_AWS_URL`, `PUBLIC_AWS_ENDPOINT`, `PUBLIC_AWS_USE_PATH_STYLE_ENDPOINT`, `PUBLIC_AWS_ROOT` | نفس ما سبق لقرص الصور العام، كلها صراحةً. `PUBLIC_AWS_ROOT` فارغ عند نقل ملفات من Laravel Cloud كما هي |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` | الشيفرة تعود إليها إن لم تُحدَّد نظائرها أعلاه. لا يُعتمد عليها: اكتب `RECEIPTS_AWS_*` و`PUBLIC_AWS_*` صراحةً |

الإيصالات تبقى خاصة دائمًا وتُعرض بروابط موقّعة مؤقتة (`transfers.receipt`) بعد
فحص Policy عند كل طلب، بصرف النظر عن نوع القرص. صور الهوية والصفحات على قرص
عام لأنها تُعرض للجميع.

على Laravel Cloud نظام الملفات مؤقت ولا يُشارَك بين النسخ، فيُربط bucketان من Laravel Cloud
Object Storage: `aljawna-receipts` خاص للإيصالات (disk name `receipts`)، و`aljawna-public` عام
للصور (disk name `public`)، والاسمان يطابقان اسمي القرصين في الشيفرة حرفيًا.

**ما يحقنه Laravel Cloud فعلًا:** متغيران فقط، `FILESYSTEM_DISK` و`LARAVEL_CLOUD_DISK_CONFIG` (JSON بكل
bucket مربوط). وعند الإقلاع يستبدل `Illuminate\Foundation\CloudBootstrapper::configureDisks()` تعريف
القرصين كاملًا: `driver: s3` و`region: auto` و`use_path_style_endpoint: false` و**`throw: false`**، **بلا
`root` ولا `visibility`**، والرابط العام للقرص `public` من الـ JSON. وثائق Laravel Cloud الرسمية تذكر حقن
`AWS_*`، لكن الواقع مختلف: `AWS_*` لا تُحقن. **تحققنا من الآلية في Laravel v13.32.0.**

- مع `throw: false` يفحص التطبيق نتيجة كل كتابة وحذف (`ReceiptStorage` و`BrandingImageStorage`):
  - فشل حفظ الإيصال: رسالة خطأ للمبادر، ولا تُنشأ الحوالة.
  - فشل حذف إيصال بعد مدة الاحتفاظ: يبقى المسار، ويظهر فشلًا في "صحة النظام"، ويُرسل تنبيه بالبريد.
  - فشل حفظ صورة في اللوحة: رسالة خطأ، ولا يُحفظ التغيير.
- **ملاحظة نقل:** لا `root` على Laravel Cloud، فالإيصالات والصور في جذر الـ bucket. عند أي نقل مستقبلي
  (خادم داخل المملكة مثلًا) اضبط `RECEIPTS_AWS_ROOT` و`PUBLIC_AWS_ROOT` فارغين، أو انقل الملفات إلى مجلد
  يطابق الـ `root` المضبوط.
- خارج Laravel Cloud لا يرسل القرصان `visibility` مع s3 أيضًا (R2 يرفضها)، فالخصوصية من إعداد الـ bucket.

**`aljawna-receipts` هو الـ Default disk عمدًا:** Laravel Cloud يفرض disk افتراضيًا (أول bucket يُربط)،
فيُربط `aljawna-receipts` أولًا فيُحقن `FILESYSTEM_DISK=receipts`. الخاص أسلم من العام إذا كُتب ملف على
الـ disk الافتراضي بالخطأ. لا يُكتب `FILESYSTEM_DISK` يدويًا (docs/RUNBOOK.md القسم 6).

الرفع المؤقت في Livewire (الإيصالات والصور) على القرص `local` صراحةً (`config/livewire.php`)،
**مستقلًا عن `FILESYSTEM_DISK`**، فلا ينتقل إلى الرفع المباشر من المتصفح إلى الـ bucket الذي
تمنعه سياسة أمان المحتوى. ثم يحفظ التطبيق الملف في الـ bucket. **يصحّ هذا ما دامت هناك نسخة
واحدة من التطبيق.**
عند تفعيل autoscaling أو أكثر من نسخة يجب مراجعته، لأن الملف المؤقت قد يكون على نسخة غير
التي تستقبل طلب الحفظ.

| المتغير | الغرض |
|---|---|
| `RECEIPTS_RETENTION_MONTHS` | تُحذف صور إيصالات المبادرة بعد إقفالها بهذه المدة (6)، من التخزين ومن النسخ، وتبقى بيانات الحوالة (`receipts:purge-expired` يوميًا) |

### النسخ الاحتياطي خارج Laravel Cloud (Cloudflare R2)

| المتغير | الغرض |
|---|---|
| `BACKUP_FILESYSTEM_DRIVER` | `s3` في الإنتاج (Cloudflare R2)، و`local` للتطوير فقط |
| `BACKUP_R2_ACCESS_KEY_ID`, `BACKUP_R2_SECRET_ACCESS_KEY`, `BACKUP_R2_BUCKET`, `BACKUP_R2_ENDPOINT`, `BACKUP_R2_REGION`, `BACKUP_R2_USE_PATH_STYLE_ENDPOINT`, `BACKUP_R2_ROOT` | bucket R2 `aljawna-backups` في **حساب Cloudflare منفصل** برمز مقصور عليه. لا تعود إلى `AWS_*` المحقونة أبدًا. `BACKUP_R2_ENDPOINT` يُنسخ من إعدادات الـ bucket في Cloudflare: الصيغة بـ `.eu` (`https://<ACCOUNT_ID>.eu.r2.cloudflarestorage.com`) لا تصح إلا لـ bucket أُنشئ بخيار EU jurisdiction |
| `BACKUP_ENCRYPTION_KEY` | مفتاح التشفير قبل الرفع (`base64:` + 32 بايت). يُحفظ خارج Laravel Cloud أيضًا؛ ضياعه = استحالة الاسترجاع |
| `BACKUP_KEEP_DAILY`, `BACKUP_KEEP_WEEKLY`, `BACKUP_KEEP_MONTHLY` | احتفاظ نسخ قاعدة البيانات (14 يومًا / 8 أسابيع / 6 أشهر) |
| `RECEIPTS_BACKUP_ENABLED`, `RECEIPTS_BACKUP_MAX_AGE_HOURS` | تفعيل نسخ الإيصالات كل ساعة، والتنبيه إن فشل آخرها أو تأخر أكثر من 3 ساعات |
| `DATABASE_BACKUP_ENABLED`, `DATABASE_BACKUP_MAX_AGE_HOURS` | تفعيل نسخة قاعدة البيانات اليومية، والتنبيه إن فشلت أو تأخرت أكثر من 26 ساعة |

### الكابتشا والبريد وتنبيهات التشغيل

| المتغير | الغرض |
|---|---|
| `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | مفاتيح Cloudflare Turnstile الحقيقية في الإنتاج (المفاتيح في `.env.example` تجريبية فقط) |
| `ALERT_EMAIL` | العنوان الوحيد الذي تصله تنبيهات التشغيل (المسؤول عن الدعم الفني). فارغ = لا تنبيهات |
| `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | **Resend عبر SMTP** لتنبيهات التشغيل فقط؛ لا بريد لأي مستخدم (استثناء محصور، docs/DECISIONS.md): `smtp` و`smtps` و`smtp.resend.com` و`465` و`resend` ومفتاح Resend بصلاحية الإرسال، والمرسل من دومين موثَّق في Resend. مع `log` تبقى التنبيهات في السجل. للتجربة: `php artisan alerts:test` |
| `ALERT_COOLDOWN_MINUTES` | أقل فاصل لتكرار نفس التنبيه ما دامت المشكلة قائمة (60) |
| `ALERT_ERROR_SPIKE_THRESHOLD`, `ALERT_ERROR_SPIKE_WINDOW_MINUTES` | عدد الأخطاء خلال النافذة الذي يُعد ارتفاعًا مفاجئًا (50 خلال 15 دقيقة) |
| `MONITOR_SCHEDULER_STALE_MINUTES`, `MONITOR_QUEUE_STALE_MINUTES` | عمر آخر نبض قبل اعتبار المجدول أو عامل الطوابير متوقفًا (5) |
| `MONITOR_WATCHDOG_SECONDS` | أقل فاصل لفحص المراقبة من طلبات الويب، لاكتشاف توقف المجدول نفسه (300) |

### حدود الحماية والمنشئ

انظر `config/security.php` و`.env.example` لكل متغيرات `LOGIN_*`،
`REGISTER_MAX_PER_IP_PER_HOUR`، `RECOVERY_*`، `SUSPICIOUS_*`، `PAGES_*`،
`TRANSFERS_DAILY_LIMIT`، `BANK_CHANGE_ALERT_DAYS`، `STATS_CACHE_SECONDS`.
قيمها الافتراضية صالحة للإنتاج، وتُعدَّل من `.env` عند الحاجة.

## البناء والنشر على Laravel Cloud

أمر البناء (Build command):

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

أمر النشر (Deploy command):

```bash
php artisan migrate --force
```

- `php artisan db:seed --force` مرة واحدة فقط عند التثبيت الأول (مفاتيح الصلاحيات؛ `AdminUserSeeder` يتخطى نفسه في الإنتاج)، من تبويب Commands في Laravel Cloud أو `cloud command:run`.
- ثم المدير الأول: `php artisan admin:invite +9665XXXXXXXX` (انظر "إنشاء حساب مدير إضافي" أدناه؛ الخطوات نفسها).
- لا `storage:link` ولا `queue:restart` في أوامر النشر: Laravel Cloud يدير العمّال، وتغييرات الملفات أثناء النشر لا تبقى.
- بعد تغيير أي متغير بيئة أو مورد مربوط يُعاد النشر ليسري.

## بعد أول نشر (قائمة تحقق)

1. **المجدول:** فعّله على عنقود التطبيق (Scheduler) ليشغّل `php artisan schedule:run` كل دقيقة. مهام المراقبة تستخدم `onOneServer` فلا تتكرر مع تعدد النسخ.
2. **عامل الطوابير:** الطوابير تعمل بعامل طوابير (Queue worker) على اتصال `redis` فقط. لا يُنشأ Managed queue على Laravel Cloud، لأنه يحوّل `QUEUE_CONNECTION` إلى `cloud` تلقائيًا (docs/RUNBOOK.md القسم 7).
3. **الدخول:** `/login` للجميع. المدير والمشرف يُعدّان التحقق بخطوتين (TOTP) في صفحة قبول الدعوة نفسها قبل إنشاء الحساب، وتُعرض رموز الاسترداد مرة واحدة: احفظها خارج الجوال. تجديدها لاحقًا من قائمة المستخدم في اللوحة ("تجديد رموز الاسترداد").
4. **عنوان الزائر الحقيقي:** بعد أول دخول افتح صفحة "الأمان" في اللوحة وتأكد أن عنوان IP في سجل المحاولات هو عنوانك (لا عنوان داخلي أو عنوان Cloudflare). إن لم يكن كذلك فأوقف النشر وراجع `TRUSTED_PROXIES`.
5. **Edge network في Laravel Cloud:** اترك مفتاح HSTS هناك معطَّلًا (التطبيق يرسله)، و`X-Frame-Options: DENY` و`nosniff` على الافتراضي. تحديد المعدل في الـ WAF (باقات Growth/Business) طبقة إضافية اختيارية فوق حدود التطبيق.
6. **صحة النظام:** افتح `{ADMIN_PATH}/system-health` كمدير، وتأكد أن المجدول وعامل الطوابير "سليم" خلال دقيقتين، وأن بندي النسخ الاحتياطي "سليم" بعد تفعيلهما (docs/RUNBOOK.md القسم 10).
7. **التنبيهات:** اضبط `ALERT_EMAIL` و`MAIL_*` (Resend) ثم أعد النشر، ثم `php artisan alerts:test` (يرسل تنبيهًا تجريبيًا فورًا). `php artisan monitor:check` لا يرسل شيئًا ما دام النظام سليمًا.
8. **البقية:** `docs/POST-DEPLOY-CHECKLIST.md` كاملة.

### إنشاء حساب مدير إضافي

لا واجهة ويب لدعوة مدير جديد (بخلاف دعوة المشرفين من لوحة الإدارة). يتولّى ذلك
من يملك وصولًا إلى بيئة Laravel Cloud عبر أمر artisan (تبويب Commands، أو
`cloud command:run {environment} --cmd='php artisan admin:invite +9665XXXXXXXX' -n`):

```bash
php artisan admin:invite +9665XXXXXXXX
```

يطبع الأمر رابط الدعوة ورابط `wa.me` جاهزًا لإرساله للمدعو يدويًا. الرابط صالح
48 ساعة ولمرة واحدة، ويُسجَّل الإصدار والقبول في `audit_logs`. يُعدّ المدعو التحقق
بخطوتين في صفحة الرابط نفسها، ولا يُنشأ الحساب إلا برمز صحيح من تطبيق المصادقة.

### أوامر الطوارئ (سطر الأوامر فقط)

لا زر في الويب يشغّل أوامر على الخادم ولا طرفية في اللوحة. في الطوارئ، من بيئة
Laravel Cloud فقط:

```bash
# مدير نسي كلمة مروره: رابط تعيين لمرة واحدة، صالح 30 دقيقة، مع رابط wa.me جاهز.
php artisan admin:reset-link +9665XXXXXXXX

# مستخدم فقد جهاز المصادقة ورموز الاسترداد: يحذف إعداد التحقق وينهي جلساته،
# ويطبع رابط إعداد لمرة واحدة (30 دقيقة) مع رابط wa.me. كلمة المرور وحدها لا
# تُدخله حتى يُعدّ التحقق من الرابط. يطلب تأكيدًا ما لم يُمرَّر --force.
php artisan admin:reset-2fa +9665XXXXXXXX --force
```

كلاهما يُسجَّل في `audit_logs`. تعيين كلمة المرور لا يلغي التحقق بخطوتين؛ من فقد
الاثنين يحتاج الأمرين معًا.

### بيانات تجريبية (بيئة تطوير أو تجربة فقط)

```bash
php artisan db:seed --class=DemoSeeder
```

يضيف مستفيدين وهميين واضحي الزيف للعرض المحلي، ولا يُنشئ أي حساب مدير. لا
يُشغَّل في بيئة الإنتاج الحقيقية.

## الجدولة (Scheduler)

يُفعَّل المجدول في Laravel Cloud فيشغّل `php artisan schedule:run` كل دقيقة، وفق
ما يقرّره `routes/console.php` من مهام مجدولة: إغلاق طلبات الاستعادة المنتهية
`recovery:expire` كل ساعة، ونبض المراقبة `monitor:heartbeat` كل دقيقة، وفحصها
وتنبيهاتها `monitor:check` كل 5 دقائق، ونسخ الإيصالات `backup:receipts` كل ساعة،
ونسخة قاعدة البيانات `backup:database` يوميًا 03:00، وحذف صور الإيصالات المنتهية
`receipts:purge-expired` يوميًا 04:30 (بتوقيت الرياض). أمرا النسخ لا يفعلان شيئًا
ما دام تفعيلهما `false`.

## قابلية النقل

كل ما سبق مُعرَّف بالكامل عبر متغيرات البيئة (قاعدة البيانات، Redis، تخزين
الملفات، وجهة النسخ)، فينتقل النظام بين مزوّدي الاستضافة أو إلى خادم داخل المملكة
لاحقًا بتغيير `.env` فقط، دون أي تعديل في الشيفرة (docs/SPEC.md §11، §12.12).
الخطوات في `docs/RUNBOOK.md` القسم 19.
