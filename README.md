# مبادرة العجاونة

نظام لدعم زواج مستفيدين متزوجين مسجّلين. يختار المبادر مستفيدًا فيحوّل إلى حسابه البنكي مباشرة، ثم يرفع إيصاله فتدخل الحوالة في الإحصائيات فورًا. **المال لا يمرّ عبر الموقع إطلاقًا.**

راجع `START-HERE.md` لدليل بدء استخدام حزمة Cursor، و`docs/SPEC.md` للمواصفة الكاملة (المرجع الأعلى)، و`AGENTS.md` لتعليمات الوكيل.

## الحزمة التقنية

PHP 8.3+ · Laravel 13 · Livewire 4 · Alpine.js · Tailwind CSS 4 · Filament 5 · PostgreSQL · Redis · spatie/laravel-permission · Pest 5 · Laravel Pint · Larastan.

## البيئة المحلية

يتطلب المشروع Docker لتشغيل PostgreSQL وRedis محليًا:

```bash
cp .env.example .env
docker compose up -d
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

- `docker-compose.yml` يشغّل خدمتين: `postgres` (PostgreSQL 17) على المنفذ `5432`، و`redis` (Redis 7) على المنفذ `6379`. القيم في `.env.example` مطابقة لإعدادات الخدمتين.
- لا تُدفع `.env` إلى Git (مستبعد في `.gitignore`)؛ كل مطوّر ينسخه من `.env.example` ويضبط قيمه محليًا.
- الخطوط (IBM Plex Sans Arabic وAref Ruqaa) مستضافة محليًا في `resources/fonts`، ولا تُحمَّل من أي CDN.

## الجودة قبل أي Pull Request

```bash
composer lint      # Laravel Pint (فحص التنسيق)
composer analyse   # Larastan (تحليل ساكن، المستوى 8)
composer test      # Pest
```

يجب أن تنجح الثلاثة قبل فتح أي PR (انظر `.cursor/rules/50-git-workflow.mdc`).

## سياسة الفرع الرئيسي (`main`)

فرع `main` محمي على GitHub وفق الإعداد التالي (يُفعَّل من إعدادات المستودع: *Settings → Branches → Branch protection rules*):

- **لا دفع مباشر** إلى `main`؛ كل تغيير يمر عبر Pull Request من فرع مهمة (`task/T##-اسم-قصير`).
- **يتطلب نجاح فحوصات CI** (`.github/workflows/ci.yml`: Pint وLarastan وPest) قبل السماح بالدمج.
- **يتطلب مراجعة (Review) واحدة على الأقل** قبل الدمج.
- **الدمج بطريقة Squash** فقط، مع حذف الفرع بعد الدمج.
- يُمنع فرض الدفع (force-push) وحذف الفرع مباشرة من غير المخوّلين.

## سير العمل والمهام

كل مهمة من `tasks/` تُنفَّذ في فرعها الخاص وتُفتح لها PR مستقل. راجع `tasks/00-INDEX.md` للترتيب، و`.cursor/rules/50-git-workflow.mdc` لتفاصيل رسائل الـ commit والدمج.
