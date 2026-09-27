<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * سجل كلمات المرور المستعادة للإضافة فقط على مستوى قاعدة البيانات (docs/SPEC.md §4.3)،
     * بالدالة نفسها التي تحمي audit_logs، فلا يتجاوز حمايةَ Eloquent استعلامُ SQL مباشر.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER recovery_logs_append_only
                BEFORE UPDATE OR DELETE ON recovery_logs
                FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();

            CREATE TRIGGER recovery_logs_no_truncate
                BEFORE TRUNCATE ON recovery_logs
                FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS recovery_logs_no_truncate ON recovery_logs;
            DROP TRIGGER IF EXISTS recovery_logs_append_only ON recovery_logs;
            SQL);
    }
};
