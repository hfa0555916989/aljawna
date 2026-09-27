<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * سجلات النسخ للإضافة فقط على مستوى قاعدة البيانات (docs/SPEC.md §9: "لا يُحذف منه شيء"):
     * لا تعديل ولا حذف لأي نسخة، ومنها نسخ الأساس المحمية، ولو باستعلام SQL مباشر.
     * والصفحات نفسها لا تُحذف، لأن نسخها وعناصر القوائم تشير إليها.
     *
     * تعيد استخدام الدالة forbid_append_only_mutation() من ترحيل audit_logs.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER page_revisions_append_only
                BEFORE UPDATE OR DELETE ON page_revisions
                FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();

            CREATE TRIGGER page_revisions_no_truncate
                BEFORE TRUNCATE ON page_revisions
                FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();

            CREATE TRIGGER menu_revisions_append_only
                BEFORE UPDATE OR DELETE ON menu_revisions
                FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();

            CREATE TRIGGER menu_revisions_no_truncate
                BEFORE TRUNCATE ON menu_revisions
                FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();

            CREATE TRIGGER pages_no_delete
                BEFORE DELETE ON pages
                FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();

            CREATE TRIGGER pages_no_truncate
                BEFORE TRUNCATE ON pages
                FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS pages_no_truncate ON pages;
            DROP TRIGGER IF EXISTS pages_no_delete ON pages;
            DROP TRIGGER IF EXISTS menu_revisions_no_truncate ON menu_revisions;
            DROP TRIGGER IF EXISTS menu_revisions_append_only ON menu_revisions;
            DROP TRIGGER IF EXISTS page_revisions_no_truncate ON page_revisions;
            DROP TRIGGER IF EXISTS page_revisions_append_only ON page_revisions;
            SQL);
    }
};
