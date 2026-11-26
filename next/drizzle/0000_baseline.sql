CREATE TABLE "admin_invites" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"phone" varchar(255) NOT NULL,
	"token_hash" varchar(255) NOT NULL,
	"expires_at" timestamp(0) without time zone NOT NULL,
	"accepted_at" timestamp(0) without time zone,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone,
	"failed_attempts" smallint DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "admin_password_resets" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"user_id" bigint NOT NULL,
	"token_hash" varchar(255) NOT NULL,
	"expires_at" timestamp(0) without time zone NOT NULL,
	"used_at" timestamp(0) without time zone,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone,
	"failed_attempts" smallint DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "audit_logs" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"actor_id" bigint,
	"action" varchar(64) NOT NULL,
	"subject_type" varchar(255),
	"subject_id" bigint,
	"meta" json,
	"ip" varchar(45),
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
--> statement-breakpoint
CREATE TABLE "beneficiaries" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"display_name" varchar(255) NOT NULL,
	"account_holder" varchar(255) NOT NULL,
	"bank_name" varchar(255) NOT NULL,
	"account_number" varchar(30) NOT NULL,
	"iban" varchar(24) NOT NULL,
	"target_amount" numeric(12, 2) NOT NULL,
	"target_deadline" date NOT NULL,
	"recommended_deadline" date NOT NULL,
	"wedding_date" date NOT NULL,
	"status" varchar(255) DEFAULT 'active' NOT NULL,
	"created_by" bigint NOT NULL,
	"approved_by" bigint,
	"approved_at" timestamp(0) without time zone,
	"approval_revoked_at" timestamp(0) without time zone,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone,
	"closed_at" timestamp(0) without time zone,
	CONSTRAINT "beneficiaries_status_check" CHECK ("beneficiaries"."status" in ('active', 'closed'))
);
--> statement-breakpoint
CREATE TABLE "cache" (
	"key" varchar(255) PRIMARY KEY NOT NULL,
	"value" text NOT NULL,
	"expiration" bigint NOT NULL
);
--> statement-breakpoint
CREATE TABLE "cache_locks" (
	"key" varchar(255) PRIMARY KEY NOT NULL,
	"owner" varchar(255) NOT NULL,
	"expiration" bigint NOT NULL
);
--> statement-breakpoint
CREATE TABLE "contact_messages" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"name" varchar(150) NOT NULL,
	"phone" varchar(20) NOT NULL,
	"body" varchar(1000) NOT NULL,
	"ip" varchar(45) NOT NULL,
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
	"read_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "login_attempts" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"phone" varchar(32) NOT NULL,
	"ip" varchar(45) NOT NULL,
	"succeeded" boolean NOT NULL,
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
--> statement-breakpoint
CREATE TABLE "menu_items" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"location" varchar(20) NOT NULL,
	"label" varchar(40) NOT NULL,
	"page_id" bigint,
	"url" varchar(500),
	"position" smallint NOT NULL,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone,
	CONSTRAINT "menu_items_page_or_url" CHECK (("menu_items"."page_id" IS NULL) <> ("menu_items"."url" IS NULL))
);
--> statement-breakpoint
CREATE TABLE "menu_revisions" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"items" jsonb NOT NULL,
	"author_id" bigint,
	"is_baseline" boolean DEFAULT false NOT NULL,
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
--> statement-breakpoint
CREATE TABLE "model_has_permissions" (
	"permission_id" bigint NOT NULL,
	"model_type" varchar(255) NOT NULL,
	"model_id" bigint NOT NULL,
	CONSTRAINT "model_has_permissions_permission_model_type_primary" PRIMARY KEY("permission_id","model_id","model_type")
);
--> statement-breakpoint
CREATE TABLE "model_has_roles" (
	"role_id" bigint NOT NULL,
	"model_type" varchar(255) NOT NULL,
	"model_id" bigint NOT NULL,
	CONSTRAINT "model_has_roles_role_model_type_primary" PRIMARY KEY("role_id","model_id","model_type")
);
--> statement-breakpoint
CREATE TABLE "page_revisions" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"page_id" bigint NOT NULL,
	"blocks" jsonb NOT NULL,
	"author_id" bigint,
	"is_baseline" boolean DEFAULT false NOT NULL,
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
--> statement-breakpoint
CREATE TABLE "pages" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"slug" varchar(80) NOT NULL,
	"title" varchar(150) NOT NULL,
	"seo_description" varchar(300),
	"blocks" jsonb DEFAULT '[]'::jsonb NOT NULL,
	"status" varchar(20) DEFAULT 'draft' NOT NULL,
	"is_system" boolean DEFAULT false NOT NULL,
	"published_at" timestamp(0) without time zone,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "password_reset_requests" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"user_id" bigint NOT NULL,
	"status" varchar(255) NOT NULL,
	"claimed_by" bigint,
	"claimed_until" timestamp(0) without time zone,
	"requested_ip" varchar(45) NOT NULL,
	"expires_at" timestamp(0) without time zone NOT NULL,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "password_reset_tokens" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"request_id" bigint NOT NULL,
	"issued_by" bigint NOT NULL,
	"token_hash" varchar(255) NOT NULL,
	"sent_to_phone" varchar(255) NOT NULL,
	"is_other_number" boolean NOT NULL,
	"reason" varchar(255),
	"expires_at" timestamp(0) without time zone NOT NULL,
	"used_at" timestamp(0) without time zone,
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
--> statement-breakpoint
CREATE TABLE "permissions" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"name" varchar(255) NOT NULL,
	"guard_name" varchar(255) NOT NULL,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "recovery_logs" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"request_id" bigint NOT NULL,
	"user_id" bigint NOT NULL,
	"performed_by" bigint NOT NULL,
	"action" varchar(255) NOT NULL,
	"old_phone" varchar(255),
	"new_phone" varchar(255),
	"sent_to_phone" varchar(255),
	"reason" varchar(255),
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
--> statement-breakpoint
CREATE TABLE "role_has_permissions" (
	"permission_id" bigint NOT NULL,
	"role_id" bigint NOT NULL,
	CONSTRAINT "role_has_permissions_permission_id_role_id_primary" PRIMARY KEY("permission_id","role_id")
);
--> statement-breakpoint
CREATE TABLE "roles" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"name" varchar(255) NOT NULL,
	"guard_name" varchar(255) NOT NULL,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "sessions" (
	"id" varchar(255) PRIMARY KEY NOT NULL,
	"user_id" bigint,
	"ip_address" varchar(45),
	"user_agent" text,
	"payload" text NOT NULL,
	"last_activity" integer NOT NULL
);
--> statement-breakpoint
CREATE TABLE "site_branding" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"initiative_name" varchar(255) NOT NULL,
	"logo_light_path" varchar(255),
	"logo_dark_path" varchar(255),
	"icon_path" varchar(255),
	"logo_height" smallint DEFAULT 32 NOT NULL,
	"primary_color_key" varchar(255) NOT NULL,
	"secondary_color_key" varchar(255) NOT NULL,
	"updated_by" bigint,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "supervisor_invites" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"phone" varchar(255) NOT NULL,
	"token_hash" varchar(255) NOT NULL,
	"permissions" json NOT NULL,
	"invited_by" bigint NOT NULL,
	"expires_at" timestamp(0) without time zone NOT NULL,
	"accepted_at" timestamp(0) without time zone,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone,
	"failed_attempts" smallint DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "system_alerts" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"key" varchar(255) NOT NULL,
	"last_sent_at" timestamp(0) without time zone NOT NULL,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "system_heartbeats" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"name" varchar(255) NOT NULL,
	"last_seen_at" timestamp(0) without time zone NOT NULL,
	"status" varchar(255),
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "transfer_comments" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"transfer_id" bigint NOT NULL,
	"author_id" bigint NOT NULL,
	"body" text NOT NULL,
	"created_at" timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
--> statement-breakpoint
CREATE TABLE "transfers" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"user_id" bigint NOT NULL,
	"beneficiary_id" bigint NOT NULL,
	"amount" numeric(12, 2) NOT NULL,
	"transferred_on" date NOT NULL,
	"receipt_path" varchar(255),
	"receipt_hash" varchar(64) NOT NULL,
	"bank_reference" varchar(64),
	"is_repeated" boolean DEFAULT false NOT NULL,
	"review_state" varchar(255) DEFAULT 'not_reviewed' NOT NULL,
	"assigned_to" bigint,
	"assigned_by" bigint,
	"assigned_at" timestamp(0) without time zone,
	"final_reviewed_by" bigint,
	"final_reviewed_at" timestamp(0) without time zone,
	"final_note" text,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone,
	"receipt_purged_at" timestamp(0) without time zone,
	CONSTRAINT "transfers_review_state_check" CHECK ("transfers"."review_state" in ('not_reviewed', 'matched', 'commented', 'final_reviewed')),
	CONSTRAINT "transfers_receipt_present_check" CHECK ("transfers"."receipt_path" is not null or "transfers"."receipt_purged_at" is not null)
);
--> statement-breakpoint
CREATE TABLE "two_factor_setup_links" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"user_id" bigint NOT NULL,
	"token_hash" varchar(255) NOT NULL,
	"expires_at" timestamp(0) without time zone NOT NULL,
	"used_at" timestamp(0) without time zone,
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone
);
--> statement-breakpoint
CREATE TABLE "users" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"full_name" varchar(255) NOT NULL,
	"phone" varchar(255) NOT NULL,
	"password" varchar(255) NOT NULL,
	"role" varchar(255) DEFAULT 'user' NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"show_contact" boolean DEFAULT false NOT NULL,
	"registered_ip" varchar(45),
	"last_login_ip" varchar(45),
	"last_login_at" timestamp(0) without time zone,
	"remember_token" varchar(100),
	"created_at" timestamp(0) without time zone,
	"updated_at" timestamp(0) without time zone,
	"session_epoch" integer DEFAULT 0 NOT NULL,
	"app_authentication_secret" text,
	"app_authentication_recovery_codes" text,
	CONSTRAINT "users_role_check" CHECK ("users"."role" in ('user', 'supervisor', 'admin'))
);
--> statement-breakpoint
ALTER TABLE "admin_password_resets" ADD CONSTRAINT "admin_password_resets_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "beneficiaries" ADD CONSTRAINT "beneficiaries_created_by_users_id_fk" FOREIGN KEY ("created_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "beneficiaries" ADD CONSTRAINT "beneficiaries_approved_by_users_id_fk" FOREIGN KEY ("approved_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "menu_items" ADD CONSTRAINT "menu_items_page_id_pages_id_fk" FOREIGN KEY ("page_id") REFERENCES "public"."pages"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "menu_revisions" ADD CONSTRAINT "menu_revisions_author_id_users_id_fk" FOREIGN KEY ("author_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "model_has_permissions" ADD CONSTRAINT "model_has_permissions_permission_id_permissions_id_fk" FOREIGN KEY ("permission_id") REFERENCES "public"."permissions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "model_has_roles" ADD CONSTRAINT "model_has_roles_role_id_roles_id_fk" FOREIGN KEY ("role_id") REFERENCES "public"."roles"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "page_revisions" ADD CONSTRAINT "page_revisions_page_id_pages_id_fk" FOREIGN KEY ("page_id") REFERENCES "public"."pages"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "page_revisions" ADD CONSTRAINT "page_revisions_author_id_users_id_fk" FOREIGN KEY ("author_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "password_reset_requests" ADD CONSTRAINT "password_reset_requests_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "password_reset_requests" ADD CONSTRAINT "password_reset_requests_claimed_by_users_id_fk" FOREIGN KEY ("claimed_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "password_reset_tokens" ADD CONSTRAINT "password_reset_tokens_request_id_password_reset_requests_id_fk" FOREIGN KEY ("request_id") REFERENCES "public"."password_reset_requests"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "password_reset_tokens" ADD CONSTRAINT "password_reset_tokens_issued_by_users_id_fk" FOREIGN KEY ("issued_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "recovery_logs" ADD CONSTRAINT "recovery_logs_request_id_password_reset_requests_id_fk" FOREIGN KEY ("request_id") REFERENCES "public"."password_reset_requests"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "recovery_logs" ADD CONSTRAINT "recovery_logs_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "recovery_logs" ADD CONSTRAINT "recovery_logs_performed_by_users_id_fk" FOREIGN KEY ("performed_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_has_permissions" ADD CONSTRAINT "role_has_permissions_permission_id_permissions_id_fk" FOREIGN KEY ("permission_id") REFERENCES "public"."permissions"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_has_permissions" ADD CONSTRAINT "role_has_permissions_role_id_roles_id_fk" FOREIGN KEY ("role_id") REFERENCES "public"."roles"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "site_branding" ADD CONSTRAINT "site_branding_updated_by_users_id_fk" FOREIGN KEY ("updated_by") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "supervisor_invites" ADD CONSTRAINT "supervisor_invites_invited_by_users_id_fk" FOREIGN KEY ("invited_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "transfer_comments" ADD CONSTRAINT "transfer_comments_transfer_id_transfers_id_fk" FOREIGN KEY ("transfer_id") REFERENCES "public"."transfers"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "transfer_comments" ADD CONSTRAINT "transfer_comments_author_id_users_id_fk" FOREIGN KEY ("author_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "transfers" ADD CONSTRAINT "transfers_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "transfers" ADD CONSTRAINT "transfers_beneficiary_id_beneficiaries_id_fk" FOREIGN KEY ("beneficiary_id") REFERENCES "public"."beneficiaries"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "transfers" ADD CONSTRAINT "transfers_assigned_to_users_id_fk" FOREIGN KEY ("assigned_to") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "transfers" ADD CONSTRAINT "transfers_assigned_by_users_id_fk" FOREIGN KEY ("assigned_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "transfers" ADD CONSTRAINT "transfers_final_reviewed_by_users_id_fk" FOREIGN KEY ("final_reviewed_by") REFERENCES "public"."users"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "two_factor_setup_links" ADD CONSTRAINT "two_factor_setup_links_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE UNIQUE INDEX "admin_invites_token_hash_unique" ON "admin_invites" USING btree ("token_hash");--> statement-breakpoint
CREATE INDEX "admin_invites_phone_index" ON "admin_invites" USING btree ("phone");--> statement-breakpoint
CREATE UNIQUE INDEX "admin_password_resets_token_hash_unique" ON "admin_password_resets" USING btree ("token_hash");--> statement-breakpoint
CREATE INDEX "audit_logs_actor_id_index" ON "audit_logs" USING btree ("actor_id");--> statement-breakpoint
CREATE INDEX "audit_logs_action_index" ON "audit_logs" USING btree ("action");--> statement-breakpoint
CREATE INDEX "audit_logs_subject_type_subject_id_index" ON "audit_logs" USING btree ("subject_type","subject_id");--> statement-breakpoint
CREATE INDEX "audit_logs_created_at_index" ON "audit_logs" USING btree ("created_at");--> statement-breakpoint
CREATE UNIQUE INDEX "beneficiaries_iban_unique" ON "beneficiaries" USING btree ("iban");--> statement-breakpoint
CREATE INDEX "beneficiaries_status_index" ON "beneficiaries" USING btree ("status");--> statement-breakpoint
CREATE INDEX "beneficiaries_approved_at_index" ON "beneficiaries" USING btree ("approved_at");--> statement-breakpoint
CREATE INDEX "beneficiaries_closed_at_index" ON "beneficiaries" USING btree ("closed_at");--> statement-breakpoint
CREATE INDEX "cache_expiration_index" ON "cache" USING btree ("expiration");--> statement-breakpoint
CREATE INDEX "cache_locks_expiration_index" ON "cache_locks" USING btree ("expiration");--> statement-breakpoint
CREATE INDEX "contact_messages_created_at_index" ON "contact_messages" USING btree ("created_at");--> statement-breakpoint
CREATE INDEX "login_attempts_phone_created_at_index" ON "login_attempts" USING btree ("phone","created_at");--> statement-breakpoint
CREATE INDEX "login_attempts_ip_created_at_index" ON "login_attempts" USING btree ("ip","created_at");--> statement-breakpoint
CREATE INDEX "menu_items_location_position_index" ON "menu_items" USING btree ("location","position");--> statement-breakpoint
CREATE UNIQUE INDEX "menu_revisions_one_baseline" ON "menu_revisions" USING btree ("is_baseline") WHERE is_baseline;--> statement-breakpoint
CREATE INDEX "model_has_permissions_model_id_model_type_index" ON "model_has_permissions" USING btree ("model_id","model_type");--> statement-breakpoint
CREATE INDEX "model_has_roles_model_id_model_type_index" ON "model_has_roles" USING btree ("model_id","model_type");--> statement-breakpoint
CREATE INDEX "page_revisions_page_id_id_index" ON "page_revisions" USING btree ("page_id","id");--> statement-breakpoint
CREATE UNIQUE INDEX "page_revisions_one_baseline_per_page" ON "page_revisions" USING btree ("page_id") WHERE is_baseline;--> statement-breakpoint
CREATE UNIQUE INDEX "pages_slug_unique" ON "pages" USING btree ("slug");--> statement-breakpoint
CREATE INDEX "password_reset_requests_user_id_status_index" ON "password_reset_requests" USING btree ("user_id","status");--> statement-breakpoint
CREATE UNIQUE INDEX "password_reset_tokens_token_hash_unique" ON "password_reset_tokens" USING btree ("token_hash");--> statement-breakpoint
CREATE UNIQUE INDEX "permissions_name_guard_name_unique" ON "permissions" USING btree ("name","guard_name");--> statement-breakpoint
CREATE UNIQUE INDEX "roles_name_guard_name_unique" ON "roles" USING btree ("name","guard_name");--> statement-breakpoint
CREATE INDEX "sessions_user_id_index" ON "sessions" USING btree ("user_id");--> statement-breakpoint
CREATE INDEX "sessions_last_activity_index" ON "sessions" USING btree ("last_activity");--> statement-breakpoint
CREATE UNIQUE INDEX "supervisor_invites_token_hash_unique" ON "supervisor_invites" USING btree ("token_hash");--> statement-breakpoint
CREATE INDEX "supervisor_invites_phone_index" ON "supervisor_invites" USING btree ("phone");--> statement-breakpoint
CREATE UNIQUE INDEX "system_alerts_key_unique" ON "system_alerts" USING btree ("key");--> statement-breakpoint
CREATE UNIQUE INDEX "system_heartbeats_name_unique" ON "system_heartbeats" USING btree ("name");--> statement-breakpoint
CREATE INDEX "transfers_receipt_hash_index" ON "transfers" USING btree ("receipt_hash");--> statement-breakpoint
CREATE INDEX "transfers_bank_reference_index" ON "transfers" USING btree ("bank_reference");--> statement-breakpoint
CREATE INDEX "transfers_is_repeated_index" ON "transfers" USING btree ("is_repeated");--> statement-breakpoint
CREATE INDEX "transfers_review_state_index" ON "transfers" USING btree ("review_state");--> statement-breakpoint
CREATE INDEX "transfers_user_id_beneficiary_id_transferred_on_index" ON "transfers" USING btree ("user_id","beneficiary_id","transferred_on");--> statement-breakpoint
CREATE INDEX "transfers_user_id_created_at_index" ON "transfers" USING btree ("user_id","created_at");--> statement-breakpoint
CREATE UNIQUE INDEX "two_factor_setup_links_token_hash_unique" ON "two_factor_setup_links" USING btree ("token_hash");--> statement-breakpoint
CREATE INDEX "two_factor_setup_links_user_id_used_at_index" ON "two_factor_setup_links" USING btree ("user_id","used_at");--> statement-breakpoint
CREATE UNIQUE INDEX "users_phone_unique" ON "users" USING btree ("phone");