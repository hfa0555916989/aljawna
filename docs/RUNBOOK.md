# دليل التشغيل: Laravel Cloud

> خطوات إعداد الإنتاج وتشغيله واسترجاعه، للمالك (المسؤول الوحيد عن الدعم الفني، docs/DECISIONS.md).
> **لا قيم سرية في هذا الملف ولا في المستودع.** كل مفتاح يُكتب في متغيرات بيئة Laravel Cloud أو
> Secrets Manager فقط، ونسخة منه في مدير كلمات مرور خارج هذه الخدمات.
>
> أسماء واجهات Laravel Cloud هنا كما في وثائقه (سبتمبر 2026م). إن تغيّر اسم زر فالخطوة نفسها باقية.
> المرجع: https://laravel.com/cloud/docs. متغيرات البيئة بالتفصيل: `docs/DEPLOY-NOTES.md`.
> بعد كل نشر أول أو تغيير دومين: `docs/POST-DEPLOY-CHECKLIST.md`.

## المحتويات
1. [الحسابات وأين تُحفظ الأسرار](#1-الحسابات-وأين-تحفظ-الأسرار)
2. [اختيار المنطقة](#2-اختيار-المنطقة)
3. [التطبيق والبيئة](#3-التطبيق-والبيئة)
4. [قاعدة البيانات: Serverless Postgres](#4-قاعدة-البيانات-serverless-postgres)
5. [Valkey](#5-valkey)
6. [تخزين الملفات: bucketان](#6-تخزين-الملفات-bucketان)
7. [الحوسبة: المجدول وعامل الطوابير](#7-الحوسبة-المجدول-وعامل-الطوابير)
8. [أوامر البناء والنشر](#8-أوامر-البناء-والنشر)
9. [متغيرات البيئة](#9-متغيرات-البيئة)
10. [النسخ الاحتياطي إلى Cloudflare R2](#10-النسخ-الاحتياطي-إلى-cloudflare-r2)
11. [البريد: Resend للتنبيهات](#11-البريد-resend-للتنبيهات)
12. [أول نشر والمدير الأول](#12-أول-نشر-والمدير-الأول)
13. [الدومين وسجلات DNS في Hostinger](#13-الدومين-وسجلات-dns-في-hostinger)
14. [حد الصرف](#14-حد-الصرف)
15. [حماية فرع main في GitHub](#15-حماية-فرع-main-في-github)
16. [النشر اليومي والتراجع عن نشر فاشل](#16-النشر-اليومي-والتراجع-عن-نشر-فاشل)
17. [الاسترجاع من النسخ الاحتياطية](#17-الاسترجاع-من-النسخ-الاحتياطية)
18. [تغيير الدومين لاحقًا](#18-تغيير-الدومين-لاحقًا)
19. [الانتقال إلى خادم داخل المملكة](#19-الانتقال-إلى-خادم-داخل-المملكة)
20. [مرجع سريع للأوامر](#20-مرجع-سريع-للأوامر)

---

## 1. الحسابات وأين تُحفظ الأسرار

| الحساب | الغرض | ملاحظات |
|---|---|---|
| GitHub (المستودع الخاص) | الشيفرة والـ CI والنشر الآلي | لا أسرار فيه إطلاقًا |
| Laravel Cloud | الاستضافة وكل موارد الإنتاج | تحقق بخطوتين إلزامي على الحساب |
| Cloudflare **حساب منفصل** | وجهة النسخ الاحتياطي (R2) | لا علاقة له بحساب Laravel Cloud، حتى لا يُفقد الاثنان معًا |
| Resend | تنبيهات التشغيل إلى `ALERT_EMAIL` | مفتاح إرسال فقط |
| Cloudflare Turnstile | الكابتشا | يمكن أن يكون في حساب Cloudflare المنفصل نفسه |
| UptimeRobot | مراقبة التوافر من الخارج | يطلب `/` و`/up` فقط |
| Hostinger | مسجّل الدومين | **سجلات DNS فقط**، لا استضافة |

**احفظ في مدير كلمات مرور** (خارج الخدمات أعلاه) ثلاثة أشياء على الأقل:
1. `BACKUP_ENCRYPTION_KEY`: بدونه **لا يمكن استرجاع أي نسخة**. احفظ نسخة ثانية ورقية أو على وسيط غير متصل.
2. بيانات دخول حساب Cloudflare المنفصل ورموز تحققه بخطوتين.
3. رموز الاسترداد لحساب Laravel Cloud وحساب GitHub.

---

## 2. اختيار المنطقة

القرار: **أوروبا** (قرار المالك). المناطق الأوروبية في Laravel Cloud: `eu-central-1` (فرانكفورت)،
`eu-west-1` (أيرلندا)، `eu-west-2` (لندن). المنطقة تثبت للبيئة ومواردها، فاخترها قبل الإنشاء.

قياس زمن الاستجابة من جهاز داخل السعودية (PowerShell، خمس محاولات لكل منطقة، الأقل أفضل):

```powershell
foreach ($r in 'eu-central-1','eu-west-1','eu-west-2') { $t = 1..5 | ForEach-Object { [double](curl.exe -s -o NUL -w "%{time_connect}" "https://dynamodb.$r.amazonaws.com/ping") }; "{0}: {1:N0} ms" -f $r, (($t | Measure-Object -Average).Average * 1000) }
```

سجّل النتيجة في `docs/DATA-TRANSFER-REGISTER.md` (عمود الموقع) عند الاختيار. قاعدة البيانات والـ Valkey
والـ buckets **في المنطقة نفسها** دائمًا.

---

## 3. التطبيق والبيئة

1. Laravel Cloud → **New application** → اربط GitHub واختر المستودع، والفرع `main`، والمنطقة المختارة.
2. البيئة الأولى باسم `production`.
3. **Settings → General → Runtime:** PHP **8.4** (مطابق للـ CI)، وNode **24**.
4. **Settings → Deployments:** اترك **Push to deploy** مفعَّلًا على `main` (يصبح آمنًا بعد حماية الفرع، القسم 15).
5. لا تضف **HTTP basic authentication** للإنتاج. (مفيد لبيئة تجربة إن أُنشئت، على باقة Growth فأعلى.)

---

## 4. قاعدة البيانات: Serverless Postgres

1. في لوحة البيئة (infrastructure canvas) → **Add database** → إنشاء cluster جديد:
   - **Type:** Laravel Serverless Postgres، إصدار **17** (مطابق لـ PostgreSQL في الـ CI).
   - **Region:** نفس منطقة البيئة.
   - **Compute units:** من 0.25 إلى 1 كبداية.
   - **Scale to zero:** معطَّل للإنتاج، أو مهلة طويلة (مثلًا 300 ثانية). الاستيقاظ يضيف مئات الملّي ثانية لأول طلب.
   - **Point-in-time recovery retention:** **30 يومًا** (الحد الأعلى).
   - **Database name:** `ajawna`.
2. أعد النشر ليسري الربط. يحقن Laravel Cloud `DB_HOST` و`DB_USERNAME` و`DB_PASSWORD` و`DB_DATABASE` تلقائيًا.
3. اضبط يدويًا: `DB_CONNECTION=pgsql` و`DB_SSLMODE=require`.
4. إن ظهر `too many clients` لاحقًا: استخدم مضيف الـ pooler (أضف `-pooler` إلى أول مقطع من `DB_HOST`).

النسخ الاحتياطي في المستوى الأول هو الاسترجاع لأي لحظة خلال 30 يومًا داخل Laravel Cloud (القسم 17.1)،
والمستوى الثاني نسخنا المشفّرة خارجه (القسم 10).

---

## 5. Valkey

1. **Add cache** → **Laravel Valkey** → حجم Flex 250MB كبداية، في المنطقة نفسها.
2. **Eviction policy:** `volatile-lru` (يُخرج المفاتيح المؤقتة فقط عند الامتلاء، فلا تضيع مهام الطوابير).
   لا تستخدم `allkeys-lru` لأن الطوابير في Valkey نفسه.
3. **Scale to zero:** معطَّل للإنتاج (الجلسات والطوابير عليه).
4. يحقن Laravel Cloud `REDIS_HOST` و`REDIS_PASSWORD` وما يلزم. **لا تكتب** `REDIS_URL` أو `REDIS_HOST` يدويًا.
   اضبط: `REDIS_CLIENT=phpredis` و`SESSION_DRIVER=redis` و`CACHE_STORE=redis` و`QUEUE_CONNECTION=redis`.

---

## 6. تخزين الملفات: bucketان

R2 يطبّق الخصوصية على مستوى الـ bucket كله، فيلزم اثنان:

| Bucket | Visibility | Disk name | Default disk | لماذا |
|---|---|---|---|---|
| `ajawna-receipts` | **Private** | `receipts` | نعم | إيصالات الحوالات، تُعرض بروابط موقّعة مؤقتة بعد فحص الصلاحية |
| `ajawna-public` | Public | `public` | لا | الشعار والأيقونة وصور منشئ الصفحات |

1. **Add bucket** → Laravel Object Storage → لكلٍّ من الاثنين بالإعداد أعلاه.
2. اضبط: `RECEIPTS_FILESYSTEM_DRIVER=s3` و`PUBLIC_FILESYSTEM_DRIVER=s3`.
3. `AWS_URL` للـ bucket العام **لا يُحقن تلقائيًا**: انسخه من صفحة إعدادات الـ bucket العام إلى `PUBLIC_AWS_URL`.
4. إن لم يعمل أحد القرصين بعد النشر (انظر فحص رفع إيصال في `docs/POST-DEPLOY-CHECKLIST.md`)، فاضبط
   بيانات اتصاله صراحةً من **Resources → Object storage → … → View credentials**:
   `RECEIPTS_AWS_ACCESS_KEY_ID` و`RECEIPTS_AWS_SECRET_ACCESS_KEY` و`RECEIPTS_AWS_BUCKET` و`RECEIPTS_AWS_ENDPOINT`
   و`RECEIPTS_AWS_DEFAULT_REGION=auto` (ونظائرها `PUBLIC_AWS_*`).

---

## 7. الحوسبة: المجدول وعامل الطوابير

1. اضغط **App cluster** في لوحة البيئة:
   - الحجم: Flex صغير كبداية، ونسخة واحدة (replica). **Scale to zero** معطَّل للإنتاج، لأن فحص المراقبة
     من طلبات الويب يحتاج بيئة مستيقظة، والإيقاظ يؤخّر أول زائر.
   - فعّل **Scheduler**: يشغّل `php artisan schedule:run` كل دقيقة (المهام في `routes/console.php`).
   - **Background processes → New background process → Queue worker:** اتصال `redis`، عملية واحدة.
     يعيد Laravel Cloud تشغيله بعد كل نشر.
2. **لا تُنشئ Managed queue**: يغيّر `QUEUE_CONNECTION` إلى `cloud` تلقائيًا، والقرار المعتمد طوابير Redis.
3. احفظ ثم **Deploy**.

المهام المجدولة (كلها `onOneServer` و`withoutOverlapping`):

| المهمة | التوقيت |
|---|---|
| `monitor:heartbeat` | كل دقيقة |
| `monitor:check` (التنبيهات) | كل 5 دقائق |
| `recovery:expire` | كل ساعة |
| `backup:receipts` | كل ساعة عند الدقيقة 15 |
| `backup:database` | يوميًا 03:00 بتوقيت الرياض |
| `receipts:purge-expired` | يوميًا 04:30 بتوقيت الرياض |

---

## 8. أوامر البناء والنشر

**Settings → Deployments:**

Build commands:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Deploy commands:

```bash
php artisan migrate --force
```

لا تضف `queue:restart` ولا `storage:link` ولا `optimize:clear`: يديرها Laravel Cloud أو لا تبقى آثارها.
الحد الأقصى لكل منهما 15 دقيقة.

---

## 9. متغيرات البيئة

**Settings → General → Environment variables.** القائمة الكاملة بأغراضها في `docs/DEPLOY-NOTES.md`،
والقيم الافتراضية في `.env.example`. الحد الأدنى للإنتاج:

```dotenv
APP_NAME="مبادرة العجاونة"
APP_ENV=production
APP_DEBUG=false
APP_KEY=<php artisan key:generate --show محليًا>
APP_URL=https://testweb.help
APP_TIMEZONE=Asia/Riyadh
APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar
ADMIN_PATH=<مسار لا يعرفه غيرك>
LOG_LEVEL=warning
DB_CONNECTION=pgsql
DB_SSLMODE=require
REDIS_CLIENT=phpredis
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
RECEIPTS_FILESYSTEM_DRIVER=s3
PUBLIC_FILESYSTEM_DRIVER=s3
PUBLIC_AWS_URL=<من إعدادات الـ bucket العام>
TRUSTED_PROXIES=
TRUSTED_PROXY_CLIENT_IP_HEADER=
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=<حقيقي>
TURNSTILE_SECRET_KEY=<حقيقي>
# القسم 10 والقسم 11:
BACKUP_* و RECEIPTS_BACKUP_ENABLED و DATABASE_BACKUP_ENABLED
MAIL_* و ALERT_EMAIL
```

- القيم السرية (`APP_KEY` و`TURNSTILE_SECRET_KEY` و`MAIL_PASSWORD` و`BACKUP_R2_SECRET_ACCESS_KEY`
  و`BACKUP_ENCRYPTION_KEY`) يمكن وضعها في **Secrets Manager** وربطها بالبيئة بدل النص الظاهر.
- **لا تضبط** `ADMIN_NAME` ولا `ADMIN_PHONE` ولا `ADMIN_PASSWORD` في الإنتاج.
- بعد أي تغيير في المتغيرات أو الموارد: **Deploy** ليسري.

---

## 10. النسخ الاحتياطي إلى Cloudflare R2

نسختان مشفّرتان **قبل** مغادرة Laravel Cloud (XChaCha20-Poly1305، `app/Support/BackupCipher.php`):
الإيصالات الجديدة كل ساعة (ملف لكل إيصال)، وقاعدة البيانات يوميًا باحتفاظ 14 يومًا / 8 أسابيع / 6 أشهر.
قاعدة البيانات تُنسخ من التطبيق نفسه بلا `pg_dump` (قرار المالك، docs/DECISIONS.md).

### 10.1 حساب Cloudflare المنفصل وR2
1. أنشئ حساب Cloudflare جديدًا ببريد المالك، وفعّل التحقق بخطوتين.
2. **R2 Object Storage** → فعّل الخدمة (تطلب وسيلة دفع؛ الاستخدام المتوقع داخل الحصة المجانية).
3. **Create bucket:** الاسم `ajawna-backups`، و**Location → Specify jurisdiction → European Union (EU)**.
4. **R2 → Manage API tokens → Create API token:**
   - الصلاحية **Object Read & Write**، ومقصورة على bucket `ajawna-backups` وحده.
   - انسخ **Access Key ID** و**Secret Access Key** مرة واحدة إلى مدير كلمات المرور.
   - نقطة الاتصال للـ bucket الأوروبي: `https://<ACCOUNT_ID>.eu.r2.cloudflarestorage.com`.
5. (للتجارب الدورية، القسم 17.4) أنشئ رمزًا ثانيًا **Object Read only** للـ bucket نفسه.

### 10.2 مفتاح التشفير
ولّده **مرة واحدة** على جهازك (لا يمرّ بأي خدمة):

```bash
php -r "echo 'base64:'.base64_encode(sodium_crypto_secretstream_xchacha20poly1305_keygen()), PHP_EOL;"
```

احفظه في مدير كلمات المرور ونسخة غير متصلة **قبل** وضعه في Laravel Cloud. تغيير المفتاح لاحقًا يجعل النسخ
القديمة غير قابلة للاسترجاع إلا بالمفتاح القديم، فاحتفظ بكل مفتاح استُخدم.

### 10.3 المتغيرات والتفعيل

```dotenv
BACKUP_FILESYSTEM_DRIVER=s3
BACKUP_R2_ACCESS_KEY_ID=<من 10.1>
BACKUP_R2_SECRET_ACCESS_KEY=<من 10.1>
BACKUP_R2_BUCKET=ajawna-backups
BACKUP_R2_ENDPOINT=https://<ACCOUNT_ID>.eu.r2.cloudflarestorage.com
BACKUP_R2_REGION=auto
BACKUP_ENCRYPTION_KEY=<من 10.2>
RECEIPTS_BACKUP_ENABLED=true
DATABASE_BACKUP_ENABLED=true
```

القرص `backups` لا يعود إلى `AWS_*` التي يحقنها Laravel Cloud أبدًا، فلا تقع النسخ في تخزين البيئة.
**Deploy**، ثم من تبويب **Commands**:

```bash
php artisan backup:database
php artisan backup:receipts
php artisan backup:list
php artisan backup:restore-database --latest --verify
```

وتأكد في صفحة "صحة النظام" أن البندين "سليم". الفشل أو التأخر (3 ساعات للإيصالات، 26 للقاعدة) يرسل
تنبيهًا إلى `ALERT_EMAIL`.

### 10.4 الاحتفاظ بصور الإيصالات
`receipts:purge-expired` يوميًا: صور إيصالات أي مبادرة مقفلة منذ أكثر من `RECEIPTS_RETENTION_MONTHS`
(6) تُحذف من الـ bucket ومن R2، وتبقى الحوالة (المبلغ والتاريخ والمبادر). المبادرة المفتوحة لا تُمسّ.
كل حذف يُسجَّل في `audit_logs` (`receipts.purged`). للمعاينة دون حذف:

```bash
php artisan receipts:purge-expired --dry-run
```

---

## 11. البريد: Resend للتنبيهات

البريد في النظام **لتنبيهات التشغيل فقط** إلى عنوان واحد، بلا بيانات شخصية (docs/DECISIONS.md).

1. Resend → **Domains → Add domain** → `testweb.help` (أو نطاق فرعي مثل `mail.testweb.help`)، والمنطقة
   **eu-west-1** إن كانت متاحة.
2. أضف السجلات التي يعرضها Resend (DKIM من نوع TXT، وMX وTXT لـ SPF على النطاق الفرعي `send`) في
   Hostinger (القسم 13)، ثم **Verify**.
3. **API Keys → Create API key:** الصلاحية **Sending access**، والنطاق `testweb.help` فقط.
4. المتغيرات:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.resend.com
MAIL_PORT=465
MAIL_USERNAME=resend
MAIL_PASSWORD=<مفتاح Resend>
MAIL_FROM_ADDRESS=alerts@testweb.help
MAIL_FROM_NAME="مبادرة العجاونة"
ALERT_EMAIL=<بريد المالك>
```

5. **Deploy** ثم من **Commands**: `php artisan alerts:test`، وتأكد من وصول "تنبيه تجريبي".

---

## 12. أول نشر والمدير الأول

1. **Deploy** وراقب السجل حتى النجاح. يحصل البيئة على دومين `*.laravel.cloud` (لا تفهرسه محركات البحث).
2. من **Commands**، مرة واحدة فقط:

```bash
php artisan db:seed --force
php artisan admin:invite +9665XXXXXXXX
```

   الأول يزرع مفاتيح الصلاحيات (ولا ينشئ مديرًا في الإنتاج). الثاني يطبع رابط دعوة صالحًا 48 ساعة
   ورابط `wa.me`. افتح الرابط على جوالك، وأعدّ التحقق بخطوتين في صفحة الدعوة نفسها، واحفظ رموز الاسترداد.
3. أكمل `docs/POST-DEPLOY-CHECKLIST.md`.

---

## 13. الدومين وسجلات DNS في Hostinger

الدومين التجريبي `testweb.help` مسجّل في Hostinger، ويبقى DNS عنده؛ **نضيف سجلات فقط**.

1. Laravel Cloud → البيئة → **Settings → Network → Add domain** → `testweb.help`:
   - **Wildcard:** لا.
   - **Redirect:** `www.testweb.help` → `testweb.help`.
   - **Downtime preference:** بلا انقطاع (pre-verification)، فالدومين جديد.
   - **Cloudflare DNS:** لا (DNS في Hostinger، وغير proxied).
2. Hostinger → **Domains → testweb.help → DNS / Nameservers → Manage DNS records**:
   - احذف أي سجل `A` أو `AAAA` أو `CNAME` قديم للاسم `@` و`www` (صفحة Hostinger الافتراضية).
   - أضف **بالضبط** ما يعرضه Laravel Cloud (النوع والاسم والقيمة):
     - `TXT` باسم `_cf-custom-hostname` (إثبات الملكية).
     - `TXT` أو `CNAME` باسم `_acme-challenge` (شهادة TLS).
     - سجل المصدر: غالبًا `A` للاسم `@` (Hostinger لا يدعم CNAME على الجذر)، وسجل `www`.
   - TTL: 300 ثانية أثناء الإعداد.
3. ارجع إلى Laravel Cloud واضغط **Refresh** حتى تصبح الحالة **Connected** (غالبًا خلال 15 دقيقة، وقد
   تصل إلى ساعات). تحقق من جهازك:

```powershell
nslookup -type=A testweb.help
nslookup -type=TXT _cf-custom-hostname.testweb.help
```

4. اجعل `testweb.help` **Primary domain** للبيئة، واضبط `APP_URL=https://testweb.help`، ثم **Deploy**.
5. سجلات Resend (القسم 11) تُضاف في المكان نفسه.
6. **Edge network:** اترك HSTS معطَّلًا هناك (التطبيق يرسله)، و`X-Frame-Options`/`nosniff` على الافتراضي.

---

## 14. حد الصرف

1. **Organization → Settings → Billing → Spending limit** → أدخل مبلغًا شهريًا.
2. التنبيهات عند 50% و80% و90% تصل بريد مديري المنظمة تلقائيًا.
3. **Stop all compute:** اتركه **معطَّلًا** ما لم ترد سقفًا صارمًا؛ تفعيله يوقف الموقع والقاعدة والكاش عند
   بلوغ الحد حتى ترفعه (البيانات لا تضيع). القرار للمالك.
4. للمراجعة: **Organization → Usage** شهريًا.

---

## 15. حماية فرع main في GitHub

النشر آلي من `main`، فلا يدخله إلا ما نجح في CI.

GitHub → المستودع → **Settings → Rules → Rulesets → New branch ruleset**:
- **Target:** الفرع الافتراضي (`main`)، **Enforcement:** Active.
- فعّل: **Restrict deletions**، و**Block force pushes**، و**Require a pull request before merging**
  (0 موافقات إن كنت المطوّر الوحيد)، و**Require status checks to pass** مع إضافة الفحص **`quality`**
  (اسم مهمة `.github/workflows/ci.yml`) و**Require branches to be up to date before merging**.
- **Bypass list:** فارغة.

تحقق: افتح PR تجريبيًا؛ زر الدمج يبقى معطَّلًا حتى ينجح `quality`.

الأسرار: لا تضع أي مفتاح إنتاج في **Secrets and variables → Actions**؛ الـ CI لا يحتاج أيًّا منها.

---

## 16. النشر اليومي والتراجع عن نشر فاشل

**النشر:** PR → نجاح CI → دمج في `main` → نشر آلي. راقبه في **Deployments** ثم افتح `/up`.

**بناء أو أمر نشر فاشل:** لا يتحول المرور إلى الإصدار الجديد أصلًا؛ يبقى الإصدار السابق يعمل. اقرأ السجل
في **Deployments**، وأصلح في PR جديد.

**نشر نجح لكنه معطوب** (خطأ في الواجهة أو السلوك):
1. الأسرع: أعد نشر آخر commit سليم. فعّل **Settings → Deployments → Deploy hook** (مرة واحدة واحفظ الرابط
   في مدير كلمات المرور)، ثم:

```powershell
curl.exe -X POST "<DEPLOY_HOOK_URL>?commit_hash=<SHA_السليم>"
```

   الـ commit يجب أن يكون على فرع البيئة (`main`). بعدها ادفع الإصلاح بـ PR كالمعتاد.
2. الأدوم: `git revert <SHA>` في فرع، ثم PR ودمج، فينشر تلقائيًا.

**الترحيلات لا تُعكس تلقائيًا بالتراجع.** لذلك تبقى الترحيلات متوافقة مع الإصدار السابق (إضافة أعمدة لا حذفها
في النشر نفسه). إن أفسد ترحيلٌ البيانات: الاسترجاع لأي لحظة قبله (القسم 17.1).

---

## 17. الاسترجاع من النسخ الاحتياطية

### 17.1 خطأ خلال آخر 30 يومًا (الأسرع): الاسترجاع لأي لحظة
1. **Organization → Resources → Databases** → الـ cluster → الاسترجاع (Restore) إلى لحظة قبل الخطأ.
   ينشئ Laravel Cloud **قاعدة جديدة**، ولا يكتب فوق الحالية.
2. اربط القاعدة الجديدة بالبيئة بدل القديمة، ثم **Deploy**.
3. `php artisan backup:restore-receipts --force` لإعادة أي إيصال حُذف ملفه، ثم
   `php artisan receipts:purge-expired` ليُعاد تطبيق سياسة الاحتفاظ.
4. أبقِ القاعدة القديمة حتى تتأكد، ثم احذفها.

### 17.2 كارثة (فقد القاعدة أو حساب Laravel Cloud): من R2
1. بيئة جديدة وفق الأقسام 3 إلى 9، بقاعدة **جديدة**، وبنفس `BACKUP_*` و**نفس** `BACKUP_ENCRYPTION_KEY`،
   وأبقِ `RECEIPTS_BACKUP_ENABLED` و`DATABASE_BACKUP_ENABLED` على `false` حتى يكتمل الاسترجاع.
2. **Deploy** (ينشئ الجداول فارغة بالترحيلات)، ثم من **Commands**:

```bash
php artisan backup:list
php artisan backup:restore-database --latest --verify
php artisan backup:restore-database --latest --wipe --force
php artisan backup:restore-receipts --force
php artisan receipts:purge-expired
```

   - `--wipe` يحذف الجداول الفارغة ثم يبني البنية بالترحيلات التي كانت عند النسخ، ويحمّل البيانات، ويضبط
     التسلسلات، ثم ينفّذ أي ترحيل أحدث. **كل ذلك في معاملة واحدة**: أي خطأ يعيد القاعدة كما كانت.
   - نسخة أحدث من الشيفرة المنشورة تُرفض برسالة تذكر الترحيلات الناقصة: انشر الإصدار المطابق أولًا.
   - الجداول المؤقتة (الكاش والجلسات والطوابير ونبض المراقبة) لا تُنسخ: يُطلب من الجميع الدخول من جديد.
   - تبويب Commands يوقف الأمر بعد 30 دقيقة؛ النسخ الحالية تنتهي في ثوانٍ.
3. فعّل النسخ من جديد، و**Deploy**، وأكمل `docs/POST-DEPLOY-CHECKLIST.md`.

### 17.3 إيصال مفقود من التخزين

```bash
php artisan backup:restore-receipts --force              # المفقودة فقط
php artisan backup:restore-receipts --overwrite --force  # كلها من النسخ
```

### 17.4 تجربة الاسترجاع الدورية (شهريًا)
- **سريعة، في الإنتاج دون كتابة:** `php artisan backup:restore-database --latest --verify`
  (تفك التشفير وتتحقق من اكتمال كل الجداول وعدد الصفوف).
- **كاملة، كل ثلاثة أشهر:** بيئة مؤقتة في Laravel Cloud (**Replicate** مع قاعدة و bucket جديدين)، بنفس
  `BACKUP_*` لكن **رمز R2 للقراءة فقط** (10.1 #5)، ثم خطوات 17.2. قارن الأعداد في صفحة الإحصائيات ثم احذف
  البيئة ومواردها. لا تسترجع بيانات الإنتاج على جهاز شخصي.
- سجّل تاريخ كل تجربة ونتيجتها.

---

## 18. تغيير الدومين لاحقًا

بالترتيب، والدومين القديم يبقى يعمل حتى النهاية:
1. **Laravel Cloud:** أضف الدومين الجديد (القسم 13) وسجلاته عند مسجّله حتى **Connected**، ثم اجعله
   **Primary**.
2. **APP_URL:** `APP_URL=https://<الجديد>` ثم **Deploy**. روابط الدعوات والاستعادة والملفات تُبنى منه؛ الروابط
   المرسلة قبل التغيير تبقى تعمل ما دام الدومين القديم متصلًا.
3. **Resend:** أضف الدومين الجديد وتحقق منه، ثم `MAIL_FROM_ADDRESS=alerts@<الجديد>` و**Deploy**، ثم
   `php artisan alerts:test`. بعد النجاح احذف الدومين القديم من Resend، وأنشئ مفتاح API للدومين الجديد
   واحذف القديم.
4. **Turnstile:** Cloudflare → Turnstile → الـ widget → **Hostname management** → أضف الدومين الجديد
   (والمفاتيح نفسها تعمل). بعد الانتقال احذف القديم.
5. **UptimeRobot:** حدّث رابطي المراقبتين (`/` و`/up`) إلى الدومين الجديد.
6. **Bucket عام:** Laravel Cloud يضيف دومينات البيئة إلى CORS تلقائيًا. إن ربطت دومينًا مخصصًا بالـ bucket
   فحدّث `PUBLIC_AWS_URL`.
7. `docs/POST-DEPLOY-CHECKLIST.md` كاملة على الدومين الجديد.
8. بعد أسبوع على الأقل: افصل الدومين القديم من البيئة واحذف سجلاته. (HSTS سُجّل في متصفحات الزوار للدومين
   القديم، ولا يضر.)

---

## 19. الانتقال إلى خادم داخل المملكة

النظام لا يعتمد على شيء خاص بـ Laravel Cloud: كل إعداد من `.env`، والأقراص قابلة للتبديل، والنسخ مستقلة.

**الخادم الجديد** (مركز بيانات داخل المملكة):
- PHP 8.4 بإضافات: `pdo_pgsql` و`redis` و`intl` و`mbstring` و`gd` و`exif` و`fileinfo` و`sodium` و`zlib` و`bcmath`.
- PostgreSQL 17، وRedis أو Valkey، وNginx وPHP-FPM، وTLS.
- عامل طوابير دائم (Supervisor: `php artisan queue:work redis`)، وCron كل دقيقة: `php artisan schedule:run`.
- تخزين: قرص محلي خاص للإيصالات (`RECEIPTS_FILESYSTEM_DRIVER=local`) أو تخزين كائنات متوافق مع S3 داخل
  المملكة (`s3` مع `RECEIPTS_AWS_*`)، ومثله للقرص العام.

**خطوات النقل:**
1. جهّز الخادم وانشر الشيفرة نفسها، بـ `.env` كامل (القسم 9) مع:
   `TRUSTED_PROXIES=<عناوين الوسطاء>`، و`TRUSTED_PROXY_CLIENT_IP_HEADER=CF-Connecting-IP` فقط إن كان خلف
   Cloudflare، ونفس `APP_KEY` (تشفير أسرار التحقق بخطوتين) ونفس `BACKUP_ENCRYPTION_KEY`.
2. نافذة صيانة معلنة. في Laravel Cloud: أوقف المجدول، ثم من Commands آخر نسختين:
   `php artisan backup:database` و`php artisan backup:receipts`.
3. على الخادم الجديد: خطوات 17.2 (`backup:restore-database --latest --wipe --force` ثم
   `backup:restore-receipts --force`).
4. غيّر سجلات DNS إلى الخادم الجديد، وأكمل `docs/POST-DEPLOY-CHECKLIST.md`.
5. وجهة النسخ: تبقى R2 أو تنتقل إلى تخزين داخل المملكة (`BACKUP_R2_*` لأي خدمة متوافقة مع S3).
6. حدّث `docs/DATA-TRANSFER-REGISTER.md` و`docs/DECISIONS.md`، واحذف بيئة Laravel Cloud ومواردها بعد التأكد.

---

## 20. مرجع سريع للأوامر

كلها من تبويب **Commands** في Laravel Cloud (لا شيء منها من الويب):

| الأمر | الغرض |
|---|---|
| `php artisan admin:invite +9665…` | دعوة مدير (48 ساعة) |
| `php artisan admin:reset-link +9665…` | رابط تعيين كلمة مرور لمدير |
| `php artisan admin:reset-2fa +9665… --force` | إعادة إعداد التحقق بخطوتين |
| `php artisan alerts:test` | تنبيه بريد تجريبي إلى `ALERT_EMAIL` |
| `php artisan monitor:check` | فحص المراقبة يدويًا |
| `php artisan backup:database` | نسخة قاعدة بيانات الآن |
| `php artisan backup:receipts` | نسخ الإيصالات الجديدة الآن |
| `php artisan backup:list` | النسخ الموجودة |
| `php artisan backup:restore-database --latest --verify` | تحقق من أحدث نسخة دون كتابة |
| `php artisan backup:restore-database <نسخة> --wipe --force` | استرجاع كامل (القسم 17.2) |
| `php artisan backup:restore-receipts --force` | استرجاع الإيصالات المفقودة |
| `php artisan receipts:purge-expired --dry-run` | معاينة حذف الإيصالات المنتهية |
