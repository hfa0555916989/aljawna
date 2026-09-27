# ملاحظات النشر

> أسماء متغيرات البيئة فقط، بلا قيم. القيم الحقيقية تعيش في `.env` على الخادم
> فقط، ولا تدخل المستودع أبدًا (docs/SPEC.md §11). راجع `.env.example` للقيم
> الافتراضية عند التطوير المحلي.

## متغيرات البيئة المطلوبة

### التطبيق وقاعدة البيانات

| المتغير | الغرض |
|---|---|
| `APP_KEY`, `APP_URL`, `APP_ENV` | أساسيات Laravel. `APP_ENV=production` على الخادم |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | الاتصال بـ PostgreSQL |
| `DB_SSLMODE` | `require` أو `verify-full` مع قاعدة Postgres مُدارة تدعم TLS (`prefer` محليًا) |
| `ADMIN_NAME`, `ADMIN_PHONE`, `ADMIN_PASSWORD` | المدير الأول (`database/seeders/AdminUserSeeder`)، مرة واحدة فقط عند التثبيت |

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

### الكابتشا والبريد

| المتغير | الغرض |
|---|---|
| `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | مفاتيح Cloudflare Turnstile الحقيقية في الإنتاج (المفاتيح في `.env.example` تجريبية فقط) |
| `MAIL_MAILER` | يبقى `log`؛ لا بريد إلكتروني في هذا النظام (docs/SPEC.md §1) |

### حدود الحماية والمنشئ

انظر `config/security.php` و`.env.example` لكل متغيرات `LOGIN_*`،
`REGISTER_MAX_PER_IP_PER_HOUR`، `RECOVERY_*`، `SUSPICIOUS_*`، `PAGES_*`،
`TRANSFERS_DAILY_LIMIT`، `BANK_CHANGE_ALERT_DAYS`، `STATS_CACHE_SECONDS`.
قيمها الافتراضية صالحة للإنتاج، وتُعدَّل من `/admin/settings` أو `.env` عند الحاجة.

## أوامر ما بعد النشر

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci && npm run build
```

### إنشاء حساب مدير إضافي

لا واجهة ويب لدعوة مدير جديد (بخلاف دعوة المشرفين من لوحة الإدارة). يتولّى ذلك
من يملك وصولًا إلى الخادم عبر أمر artisan:

```bash
php artisan admin:invite +9665XXXXXXXX
```

يطبع الأمر رابط الدعوة ورابط `wa.me` جاهزًا لإرساله للمدعو يدويًا. الرابط صالح
48 ساعة ولمرة واحدة، ويُسجَّل الإصدار والقبول في `audit_logs`.

### بيانات تجريبية (بيئة تطوير أو تجربة فقط)

```bash
php artisan db:seed --class=DemoSeeder
```

يضيف مستفيدين وهميين واضحي الزيف للعرض المحلي، ولا يُنشئ أي حساب مدير. لا
يُشغَّل في بيئة الإنتاج الحقيقية.

## الجدولة (Scheduler)

يُضاف مدخل cron واحد يشغّل `php artisan schedule:run` كل دقيقة، وفق ما يقرّره
`routes/console.php` من مهام مجدولة (مثل إغلاق طلبات الاستعادة المنتهية
`recovery:expire` كل ساعة).

## قابلية النقل

كل ما سبق مُعرَّف بالكامل عبر متغيرات البيئة (قاعدة البيانات، Redis، تخزين
الملفات)، فينتقل النظام بين مزوّدي الاستضافة أو إلى خادم داخل المملكة لاحقًا
بتغيير `.env` فقط، دون أي تعديل في الشيفرة (docs/SPEC.md §11، §12.12).
