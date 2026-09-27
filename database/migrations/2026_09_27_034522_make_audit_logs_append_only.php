<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * سجل التدقيق للإضافة فقط على مستوى قاعدة البيانات (docs/SPEC.md §9 audit_logs، FR-23):
     * يرفض PostgreSQL أي UPDATE أو DELETE أو TRUNCATE ولو باستعلام SQL مباشر يتجاوز Eloquent.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION forbid_append_only_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% is append-only: % is not allowed', TG_TABLE_NAME, TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER audit_logs_append_only
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();

            CREATE TRIGGER audit_logs_no_truncate
                BEFORE TRUNCATE ON audit_logs
                FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs;
            DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs;
            DROP FUNCTION IF EXISTS forbid_append_only_mutation();
            SQL);
    }
};
