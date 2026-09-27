# ملاحظات النشر

> أسماء متغيرات البيئة فقط، بلا قيم. القيم الحقيقية تعيش في `.env` على الخادم
> فقط، ولا تدخل المستودع أبدًا (docs/SPEC.md §11). راجع `.env.example` للقيم
> الافتراضية عند التطوير المحلي.

الاستضافة الدائمة على **Laravel Cloud** (docs/DECISIONS.md). قاعدة PostgreSQL
وذاكرة Valkey/Redis وتخزين الكائنات موارد مُدارة تُربط بالبيئة، فيحقن Laravel
Cloud متغيرات اتصالها تلقائيًا؛ وأي متغير مخصّص تكتبه يتقدّم عليها.

## متغيرات البيئة المطلوبة

### التطبيق وقاعدة البيانات

| المتغير | الغرض |
|---|---|
| `APP_KEY`, `APP_URL`, `APP_ENV` | أساسيات Laravel. `APP_ENV=production` على الخادم |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | الاتصال بـ PostgreSQL |
| `DB_SSLMODE` | `require` أو `verify-full` مع قاعدة Postgres مُدارة تدعم TLS (`prefer` محليًا) |
| `ADMIN_NAME`, `ADMIN_PHONE`, `ADMIN_PASSWORD` | المدير الأول (`database/seeders/AdminUserSeeder`)، مرة واحدة فقط عند التثبيت |
| `ADMIN_PATH` | مسار لوحة الإدارة. ضع قيمة لا يعرفها غيرك (حروف لاتينية صغيرة وأرقام وشرطات)؛ الفارغ يعود إلى الافتراضي في `config/admin.php`. لا يوجد `/admin` |

### الكاش والجلسات والطوابير (Redis أو Valkey)

| المتغير | الغرض |
|---|---|
| `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` | تبقى `redis` في كل البيئات |
| `REDIS_CLIENT` | `phpredis` أو `predis` بحسب توفّر إضافة PHP على الخادم |
| `REDIS_URL` | يُفضَّل مع خدمة مُدارة: `redis://` أو `rediss://` (TLS) بمصادقتها وعنوانها كاملة، فيتجاوز باقي متغيرات `REDIS_*` أدناه |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB`, `REDIS_CACHE_DB` | بديل عن `REDIS_URL` عند عدم الحاجة إلى TLS |

النظام لا يستخدم أي أمر أو ميزة خاصة بـ Redis؛ أي خدمة متوافقة مع بروتوكول
Redis (Valkey ومنه) تعمل دون تغيير في الشيفرة.

### تخزين الملفات (قابل للتبديل محلي/سحابي)

| المتغير | الغرض |
|---|---|
| `RECEIPTS_FILESYSTEM_DRIVER` | `local` أو `s3` لقرص إيصالات الحوالات الخاص (`config/filesystems.php`) |
| `RECEIPTS_AWS_ACCESS_KEY_ID`, `RECEIPTS_AWS_SECRET_ACCESS_KEY`, `RECEIPTS_AWS_DEFAULT_REGION`, `RECEIPTS_AWS_BUCKET`, `RECEIPTS_AWS_URL`, `RECEIPTS_AWS_ENDPOINT`, `RECEIPTS_AWS_USE_PATH_STYLE_ENDPOINT`, `RECEIPTS_AWS_ROOT` | بيانات bucket متوافق مع S3 للإيصالات عند `RECEIPTS_FILESYSTEM_DRIVER=s3`؛ تُترك فارغة لاستخدام `AWS_*` العامة إن كانت كافية |
| `PUBLIC_FILESYSTEM_DRIVER` | `local` أو `s3` لقرص صور الهوية (الشعار والأيقونة) وصور منشئ الصفحات |
| `PUBLIC_AWS_ACCESS_KEY_ID`, `PUBLIC_AWS_SECRET_ACCESS_KEY`, `PUBLIC_AWS_DEFAULT_REGION`, `PUBLIC_AWS_BUCKET`, `PUBLIC_AWS_URL`, `PUBLIC_AWS_ENDPOINT`, `PUBLIC_AWS_USE_PATH_STYLE_ENDPOINT`, `PUBLIC_AWS_ROOT` | نفس ما سبق لقرص الصور العام |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` | قيم S3 عامة تُستخدم احتياطًا إن لم تُحدَّد نظائرها أعلاه |

الإيصالات تبقى خاصة دائمًا وتُعرض بروابط موقّعة مؤقتة (`transfers.receipt`) بعد
فحص Policy عند كل طلب، بصرف النظر عن نوع القرص. صور الهوية والصفحات على قرص
عام لأنها تُعرض للجميع.

على Laravel Cloud نظام الملفات مؤقت ولا يُشارَك بين النسخ، فيُضبط القرصان على
`s3` مع bucketين من Laravel Cloud Object Storage: خاص للإيصالات، وعام للصور.

### الكابتشا والبريد وتنبيهات التشغيل

| المتغير | الغرض |
|---|---|
| `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | مفاتيح Cloudflare Turnstile الحقيقية في الإنتاج (المفاتيح في `.env.example` تجريبية فقط) |
| `ALERT_EMAIL` | العنوان الوحيد الذي تصله تنبيهات التشغيل (المسؤول عن الدعم الفني). فارغ = لا تنبيهات |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | ناقل حقيقي (مثل `smtp`) لتنبيهات التشغيل فقط؛ لا بريد لأي مستخدم (استثناء محصور، docs/DECISIONS.md). مع `log` تبقى التنبيهات في السجل |
| `ALERT_COOLDOWN_MINUTES` | أقل فاصل لتكرار نفس التنبيه ما دامت المشكلة قائمة (60) |
| `ALERT_ERROR_SPIKE_THRESHOLD`, `ALERT_ERROR_SPIKE_WINDOW_MINUTES` | عدد الأخطاء خلال النافذة الذي يُعد ارتفاعًا مفاجئًا (50 خلال 15 دقيقة) |
| `MONITOR_SCHEDULER_STALE_MINUTES`, `MONITOR_QUEUE_STALE_MINUTES` | عمر آخر نبض قبل اعتبار المجدول أو عامل الطوابير متوقفًا (5) |
| `MONITOR_WATCHDOG_SECONDS` | أقل فاصل لفحص المراقبة من طلبات الويب، لاكتشاف توقف المجدول نفسه (300) |
| `RECEIPTS_BACKUP_ENABLED`, `RECEIPTS_BACKUP_MAX_AGE_HOURS` | يبقى `false` ("غير مُعدّ") حتى T21، ثم يُنبَّه عند فشل آخر نسخة أو تأخرها (26 ساعة) |

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

- `php artisan db:seed --force` مرة واحدة فقط عند التثبيت الأول (المدير الأول والصلاحيات)، من تبويب Commands في Laravel Cloud أو `cloud command:run`.
- لا `storage:link` ولا `queue:restart` في أوامر النشر: Laravel Cloud يدير العمّال، وتغييرات الملفات أثناء النشر لا تبقى.
- بعد تغيير أي متغير بيئة أو مورد مربوط يُعاد النشر ليسري.

## بعد أول نشر (قائمة تحقق)

1. **المجدول:** فعّله على عنقود التطبيق (Scheduler) ليشغّل `php artisan schedule:run` كل دقيقة. مهام المراقبة تستخدم `onOneServer` فلا تتكرر مع تعدد النسخ.
2. **عامل الطوابير:** عامل (Queue worker أو طوابير مُدارة) على اتصال `redis`.
3. **الدخول:** `/login` للجميع. أول دخول لأي مدير أو مشرف يُلزمه بإعداد التحقق بخطوتين (TOTP) في تطبيق مصادقة، ويعرض رموز الاسترداد مرة واحدة: احفظها خارج الجوال.
4. **صحة النظام:** افتح `{ADMIN_PATH}/system-health` كمدير، وتأكد أن المجدول وعامل الطوابير "سليم" خلال دقيقتين، وأن النسخ الاحتياطي "غير مُعدّ" حتى T21.
5. **التنبيهات:** اضبط `ALERT_EMAIL` و`MAIL_*` ثم أعد النشر. للتجربة: `php artisan monitor:check` (لا يرسل شيئًا ما دام النظام سليمًا).

### إنشاء حساب مدير إضافي

لا واجهة ويب لدعوة مدير جديد (بخلاف دعوة المشرفين من لوحة الإدارة). يتولّى ذلك
من يملك وصولًا إلى بيئة Laravel Cloud عبر أمر artisan (تبويب Commands، أو
`cloud command:run {environment} --cmd='php artisan admin:invite +9665XXXXXXXX' -n`):

```bash
php artisan admin:invite +9665XXXXXXXX
```

يطبع الأمر رابط الدعوة ورابط `wa.me` جاهزًا لإرساله للمدعو يدويًا. الرابط صالح
48 ساعة ولمرة واحدة، ويُسجَّل الإصدار والقبول في `audit_logs`. عند أول دخول يُلزَم
المدير الجديد بإعداد التحقق بخطوتين قبل أي صفحة في اللوحة.

### أوامر الطوارئ (سطر الأوامر فقط)

لا زر في الويب يشغّل أوامر على الخادم ولا طرفية في اللوحة. في الطوارئ، من بيئة
Laravel Cloud فقط:

```bash
# مدير نسي كلمة مروره: رابط تعيين لمرة واحدة، صالح 30 دقيقة، مع رابط wa.me جاهز.
php artisan admin:reset-link +9665XXXXXXXX

# مستخدم فقد جهاز المصادقة ورموز الاسترداد: يحذف إعداد التحقق وينهي جلساته،
# فيُلزَم بإعداده من جديد عند دخوله التالي. يطلب تأكيدًا ما لم يُمرَّر --force.
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
وتنبيهاتها `monitor:check` كل 5 دقائق.

## قابلية النقل

كل ما سبق مُعرَّف بالكامل عبر متغيرات البيئة (قاعدة البيانات، Redis، تخزين
الملفات)، فينتقل النظام بين مزوّدي الاستضافة أو إلى خادم داخل المملكة لاحقًا
بتغيير `.env` فقط، دون أي تعديل في الشيفرة (docs/SPEC.md §11، §12.12).
