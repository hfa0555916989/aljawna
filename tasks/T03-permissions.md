# T03 — الصلاحيات والسياسات

- **الفرع:** `task/T03-permissions`
- **يعتمد على:** T01
- **المراجع:** `docs/SPEC.md` §2 (كامل)

## الهدف
نظام صلاحيات دقيق يُفحص على الخادم في كل طلب، مع قوالب أدوار جاهزة وقواعد الحماية.

## المطلوب
1. تثبيت `spatie/laravel-permission` وبذر المفاتيح: `stats.view`, `transfers.view`, `transfers.review`, `transfers.assign`, `users.view`, `users.suspend`, `recovery.handle`, `recovery.other_number`, `recovery.change_phone`, `security.view`, `stats.recovery`, `settings.manage`, `beneficiaries.manage`, `content.manage`, `messages.view`, `converter.use`, `supervisors.manage`.
2. المدير يملك كل شيء ضمنيًا عبر `Gate::before`. والمشرف يأخذ صلاحيات مباشرة.
3. ثابت `SENSITIVE_PERMISSIONS`: `recovery.other_number`, `recovery.change_phone`, `beneficiaries.manage`, `settings.manage`, `content.manage`, `supervisors.manage`.
4. القوالب (config/seeder): مراجع الحوالات، مسؤول الدعم والاستعادة، مشرف متابعة، مشرف أمان، مسؤول المستفيدين (الصلاحيات في §2).
5. Action واحدة لتعديل صلاحيات مشرف تطبّق القواعد: لا يعدّل المشرف صلاحيات نفسه، ولا يمنح ما لا يملكه، ولا تُسحب صلاحيات المدير، ويبقى مدير واحد على الأقل.
6. `recovery.other_number` و`recovery.change_phone` تتطلبان `recovery.handle` (تحقّق من ذلك).
7. تسجيل كل تغيير صلاحيات في `audit_logs` (يُنشأ الجدول والخدمة `Audit::record()` هنا، وتُستكمل حمايته في T14).

## معايير القبول
- [ ] كل قاعدة أعلاه مفروضة على الخادم وليس فقط في الواجهة.
- [ ] تغيير الصلاحيات يسري فورًا (لا تخزين في الجلسة، وإبطال كاش spatie عند التغيير).

## الاختبارات المطلوبة
- اختبار لكل قاعدة (مشرف يعدّل نفسه، يمنح ما لا يملك، آخر مدير...).
- اختبار أن قالبًا يمنح المجموعة الصحيحة.
- اختبار 403 للوصول المباشر.

## قبل الدمج
- `composer lint && composer analyse && composer test`
- وصف PR بالقالب: ما تم، ما لم يتم، أسئلة مفتوحة، ولقطات شاشة (جوال 360px + كمبيوتر 1280px) للواجهات.
- راجع "تعريف الإنجاز" في `AGENTS.md`.
