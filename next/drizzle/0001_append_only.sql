-- سجلات للإضافة فقط، مطابقة لترحيلات Laravel (make_*_append_only). أي تعديل أو حذف يرفضه Postgres نفسه.
CREATE OR REPLACE FUNCTION forbid_append_only_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION '% is append-only: % is not allowed', TG_TABLE_NAME, TG_OP
        USING ERRCODE = 'insufficient_privilege';
END;
$$ LANGUAGE plpgsql;
--> statement-breakpoint
CREATE TRIGGER audit_logs_append_only BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER recovery_logs_append_only BEFORE UPDATE OR DELETE ON recovery_logs FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER recovery_logs_no_truncate BEFORE TRUNCATE ON recovery_logs FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER page_revisions_append_only BEFORE UPDATE OR DELETE ON page_revisions FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER page_revisions_no_truncate BEFORE TRUNCATE ON page_revisions FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER menu_revisions_append_only BEFORE UPDATE OR DELETE ON menu_revisions FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER menu_revisions_no_truncate BEFORE TRUNCATE ON menu_revisions FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER pages_no_delete BEFORE DELETE ON pages FOR EACH ROW EXECUTE FUNCTION forbid_append_only_mutation();
--> statement-breakpoint
CREATE TRIGGER pages_no_truncate BEFORE TRUNCATE ON pages FOR EACH STATEMENT EXECUTE FUNCTION forbid_append_only_mutation();
