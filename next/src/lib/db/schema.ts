import { sql } from "drizzle-orm";
import {
  bigint,
  bigserial,
  boolean,
  check,
  date,
  index,
  integer,
  json,
  jsonb,
  numeric,
  pgTable,
  primaryKey,
  smallint,
  text,
  uniqueIndex,
  varchar,
} from "drizzle-orm/pg-core";
import { riyadhTimestamp } from "./riyadh-timestamp";

/**
 * مخطط قاعدة البيانات، مطابق لترحيلات Laravel في database/migrations بأسماء
 * الجداول والأعمدة والقيود نفسها، حتى تُنقل قاعدة الإنتاج كما هي (pg_dump).
 *
 * الأعمدة التي كانت enum في Laravel هي varchar مع CHECK، كما يولّدها Laravel على Postgres.
 */

export const userRoles = ["user", "supervisor", "admin"] as const;
export type UserRole = (typeof userRoles)[number];

export const beneficiaryStatuses = ["active", "closed"] as const;
export const transferReviewStates = ["not_reviewed", "matched", "commented", "final_reviewed"] as const;

const createdAt = () => riyadhTimestamp("created_at");
const updatedAt = () => riyadhTimestamp("updated_at");
const nowDefault = sql`CURRENT_TIMESTAMP`;

export const users = pgTable(
  "users",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    fullName: varchar("full_name", { length: 255 }).notNull(),
    phone: varchar("phone", { length: 255 }).notNull(),
    password: varchar("password", { length: 255 }).notNull(),
    role: varchar("role", { length: 255, enum: userRoles }).notNull().default("user"),
    isActive: boolean("is_active").notNull().default(true),
    showContact: boolean("show_contact").notNull().default(false),
    registeredIp: varchar("registered_ip", { length: 45 }),
    lastLoginIp: varchar("last_login_ip", { length: 45 }),
    lastLoginAt: riyadhTimestamp("last_login_at"),
    rememberToken: varchar("remember_token", { length: 100 }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
    sessionEpoch: integer("session_epoch").notNull().default(0),
    appAuthenticationSecret: text("app_authentication_secret"),
    appAuthenticationRecoveryCodes: text("app_authentication_recovery_codes"),
  },
  (table) => [
    uniqueIndex("users_phone_unique").on(table.phone),
    check("users_role_check", sql`${table.role} in ('user', 'supervisor', 'admin')`),
  ],
);

export const sessions = pgTable(
  "sessions",
  {
    id: varchar("id", { length: 255 }).primaryKey(),
    userId: bigint("user_id", { mode: "number" }),
    ipAddress: varchar("ip_address", { length: 45 }),
    userAgent: text("user_agent"),
    payload: text("payload").notNull(),
    lastActivity: integer("last_activity").notNull(),
  },
  (table) => [index("sessions_user_id_index").on(table.userId), index("sessions_last_activity_index").on(table.lastActivity)],
);

export const cache = pgTable(
  "cache",
  {
    key: varchar("key", { length: 255 }).primaryKey(),
    value: text("value").notNull(),
    expiration: bigint("expiration", { mode: "number" }).notNull(),
  },
  (table) => [index("cache_expiration_index").on(table.expiration)],
);

export const cacheLocks = pgTable(
  "cache_locks",
  {
    key: varchar("key", { length: 255 }).primaryKey(),
    owner: varchar("owner", { length: 255 }).notNull(),
    expiration: bigint("expiration", { mode: "number" }).notNull(),
  },
  (table) => [index("cache_locks_expiration_index").on(table.expiration)],
);

export const loginAttempts = pgTable(
  "login_attempts",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    phone: varchar("phone", { length: 32 }).notNull(),
    ip: varchar("ip", { length: 45 }).notNull(),
    succeeded: boolean("succeeded").notNull(),
    createdAt: createdAt().notNull().default(nowDefault),
  },
  (table) => [
    index("login_attempts_phone_created_at_index").on(table.phone, table.createdAt),
    index("login_attempts_ip_created_at_index").on(table.ip, table.createdAt),
  ],
);

/** جداول spatie/laravel-permission كما هي (بلا فرق). */
export const permissions = pgTable(
  "permissions",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    name: varchar("name", { length: 255 }).notNull(),
    guardName: varchar("guard_name", { length: 255 }).notNull(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [uniqueIndex("permissions_name_guard_name_unique").on(table.name, table.guardName)],
);

export const roles = pgTable(
  "roles",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    name: varchar("name", { length: 255 }).notNull(),
    guardName: varchar("guard_name", { length: 255 }).notNull(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [uniqueIndex("roles_name_guard_name_unique").on(table.name, table.guardName)],
);

export const modelHasPermissions = pgTable(
  "model_has_permissions",
  {
    permissionId: bigint("permission_id", { mode: "number" })
      .notNull()
      .references(() => permissions.id, { onDelete: "cascade" }),
    modelType: varchar("model_type", { length: 255 }).notNull(),
    modelId: bigint("model_id", { mode: "number" }).notNull(),
  },
  (table) => [
    primaryKey({ name: "model_has_permissions_permission_model_type_primary", columns: [table.permissionId, table.modelId, table.modelType] }),
    index("model_has_permissions_model_id_model_type_index").on(table.modelId, table.modelType),
  ],
);

export const modelHasRoles = pgTable(
  "model_has_roles",
  {
    roleId: bigint("role_id", { mode: "number" })
      .notNull()
      .references(() => roles.id, { onDelete: "cascade" }),
    modelType: varchar("model_type", { length: 255 }).notNull(),
    modelId: bigint("model_id", { mode: "number" }).notNull(),
  },
  (table) => [
    primaryKey({ name: "model_has_roles_role_model_type_primary", columns: [table.roleId, table.modelId, table.modelType] }),
    index("model_has_roles_model_id_model_type_index").on(table.modelId, table.modelType),
  ],
);

export const roleHasPermissions = pgTable(
  "role_has_permissions",
  {
    permissionId: bigint("permission_id", { mode: "number" })
      .notNull()
      .references(() => permissions.id, { onDelete: "cascade" }),
    roleId: bigint("role_id", { mode: "number" })
      .notNull()
      .references(() => roles.id, { onDelete: "cascade" }),
  },
  (table) => [primaryKey({ name: "role_has_permissions_permission_id_role_id_primary", columns: [table.permissionId, table.roleId] })],
);

/** سجل تدقيق للإضافة فقط: Trigger في القاعدة يمنع التعديل والحذف (drizzle/0001_append_only.sql). */
export const auditLogs = pgTable(
  "audit_logs",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    actorId: bigint("actor_id", { mode: "number" }),
    action: varchar("action", { length: 64 }).notNull(),
    subjectType: varchar("subject_type", { length: 255 }),
    subjectId: bigint("subject_id", { mode: "number" }),
    meta: json("meta"),
    ip: varchar("ip", { length: 45 }),
    createdAt: createdAt().notNull().default(nowDefault),
  },
  (table) => [
    index("audit_logs_actor_id_index").on(table.actorId),
    index("audit_logs_action_index").on(table.action),
    index("audit_logs_subject_type_subject_id_index").on(table.subjectType, table.subjectId),
    index("audit_logs_created_at_index").on(table.createdAt),
  ],
);

export const beneficiaries = pgTable(
  "beneficiaries",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    displayName: varchar("display_name", { length: 255 }).notNull(),
    accountHolder: varchar("account_holder", { length: 255 }).notNull(),
    bankName: varchar("bank_name", { length: 255 }).notNull(),
    accountNumber: varchar("account_number", { length: 30 }).notNull(),
    iban: varchar("iban", { length: 24 }).notNull(),
    targetAmount: numeric("target_amount", { precision: 12, scale: 2 }).notNull(),
    targetDeadline: date("target_deadline").notNull(),
    recommendedDeadline: date("recommended_deadline").notNull(),
    weddingDate: date("wedding_date").notNull(),
    status: varchar("status", { length: 255, enum: beneficiaryStatuses }).notNull().default("active"),
    createdBy: bigint("created_by", { mode: "number" })
      .notNull()
      .references(() => users.id, { onDelete: "restrict" }),
    approvedBy: bigint("approved_by", { mode: "number" }).references(() => users.id, { onDelete: "restrict" }),
    approvedAt: riyadhTimestamp("approved_at"),
    approvalRevokedAt: riyadhTimestamp("approval_revoked_at"),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
    closedAt: riyadhTimestamp("closed_at"),
  },
  (table) => [
    uniqueIndex("beneficiaries_iban_unique").on(table.iban),
    index("beneficiaries_status_index").on(table.status),
    index("beneficiaries_approved_at_index").on(table.approvedAt),
    index("beneficiaries_closed_at_index").on(table.closedAt),
    check("beneficiaries_status_check", sql`${table.status} in ('active', 'closed')`),
  ],
);

export const transfers = pgTable(
  "transfers",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    userId: bigint("user_id", { mode: "number" })
      .notNull()
      .references(() => users.id, { onDelete: "restrict" }),
    beneficiaryId: bigint("beneficiary_id", { mode: "number" })
      .notNull()
      .references(() => beneficiaries.id, { onDelete: "restrict" }),
    amount: numeric("amount", { precision: 12, scale: 2 }).notNull(),
    transferredOn: date("transferred_on").notNull(),
    receiptPath: varchar("receipt_path", { length: 255 }),
    receiptHash: varchar("receipt_hash", { length: 64 }).notNull(),
    bankReference: varchar("bank_reference", { length: 64 }),
    isRepeated: boolean("is_repeated").notNull().default(false),
    reviewState: varchar("review_state", { length: 255, enum: transferReviewStates }).notNull().default("not_reviewed"),
    assignedTo: bigint("assigned_to", { mode: "number" }).references(() => users.id, { onDelete: "restrict" }),
    assignedBy: bigint("assigned_by", { mode: "number" }).references(() => users.id, { onDelete: "restrict" }),
    assignedAt: riyadhTimestamp("assigned_at"),
    finalReviewedBy: bigint("final_reviewed_by", { mode: "number" }).references(() => users.id, { onDelete: "restrict" }),
    finalReviewedAt: riyadhTimestamp("final_reviewed_at"),
    finalNote: text("final_note"),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
    receiptPurgedAt: riyadhTimestamp("receipt_purged_at"),
  },
  (table) => [
    index("transfers_receipt_hash_index").on(table.receiptHash),
    index("transfers_bank_reference_index").on(table.bankReference),
    index("transfers_is_repeated_index").on(table.isRepeated),
    index("transfers_review_state_index").on(table.reviewState),
    index("transfers_user_id_beneficiary_id_transferred_on_index").on(table.userId, table.beneficiaryId, table.transferredOn),
    index("transfers_user_id_created_at_index").on(table.userId, table.createdAt),
    check(
      "transfers_review_state_check",
      sql`${table.reviewState} in ('not_reviewed', 'matched', 'commented', 'final_reviewed')`,
    ),
    check("transfers_receipt_present_check", sql`${table.receiptPath} is not null or ${table.receiptPurgedAt} is not null`),
  ],
);

export const transferComments = pgTable("transfer_comments", {
  id: bigserial("id", { mode: "number" }).primaryKey(),
  transferId: bigint("transfer_id", { mode: "number" })
    .notNull()
    .references(() => transfers.id, { onDelete: "restrict" }),
  authorId: bigint("author_id", { mode: "number" })
    .notNull()
    .references(() => users.id, { onDelete: "restrict" }),
  body: text("body").notNull(),
  createdAt: createdAt().notNull().default(nowDefault),
});

export const supervisorInvites = pgTable(
  "supervisor_invites",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    phone: varchar("phone", { length: 255 }).notNull(),
    tokenHash: varchar("token_hash", { length: 255 }).notNull(),
    permissions: json("permissions").notNull(),
    invitedBy: bigint("invited_by", { mode: "number" })
      .notNull()
      .references(() => users.id, { onDelete: "restrict" }),
    expiresAt: riyadhTimestamp("expires_at").notNull(),
    acceptedAt: riyadhTimestamp("accepted_at"),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
    failedAttempts: smallint("failed_attempts").notNull().default(0),
  },
  (table) => [uniqueIndex("supervisor_invites_token_hash_unique").on(table.tokenHash), index("supervisor_invites_phone_index").on(table.phone)],
);

export const passwordResetRequests = pgTable(
  "password_reset_requests",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    userId: bigint("user_id", { mode: "number" })
      .notNull()
      .references(() => users.id, { onDelete: "restrict" }),
    status: varchar("status", { length: 255 }).notNull(),
    claimedBy: bigint("claimed_by", { mode: "number" }).references(() => users.id, { onDelete: "restrict" }),
    claimedUntil: riyadhTimestamp("claimed_until"),
    requestedIp: varchar("requested_ip", { length: 45 }).notNull(),
    expiresAt: riyadhTimestamp("expires_at").notNull(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [index("password_reset_requests_user_id_status_index").on(table.userId, table.status)],
);

export const passwordResetTokens = pgTable(
  "password_reset_tokens",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    requestId: bigint("request_id", { mode: "number" })
      .notNull()
      .references(() => passwordResetRequests.id, { onDelete: "restrict" }),
    issuedBy: bigint("issued_by", { mode: "number" })
      .notNull()
      .references(() => users.id, { onDelete: "restrict" }),
    tokenHash: varchar("token_hash", { length: 255 }).notNull(),
    sentToPhone: varchar("sent_to_phone", { length: 255 }).notNull(),
    isOtherNumber: boolean("is_other_number").notNull(),
    reason: varchar("reason", { length: 255 }),
    expiresAt: riyadhTimestamp("expires_at").notNull(),
    usedAt: riyadhTimestamp("used_at"),
    createdAt: createdAt().notNull().default(nowDefault),
  },
  (table) => [uniqueIndex("password_reset_tokens_token_hash_unique").on(table.tokenHash)],
);

/** سجل الاستعادة للإضافة فقط (Trigger). */
export const recoveryLogs = pgTable("recovery_logs", {
  id: bigserial("id", { mode: "number" }).primaryKey(),
  requestId: bigint("request_id", { mode: "number" })
    .notNull()
    .references(() => passwordResetRequests.id, { onDelete: "restrict" }),
  userId: bigint("user_id", { mode: "number" })
    .notNull()
    .references(() => users.id, { onDelete: "restrict" }),
  performedBy: bigint("performed_by", { mode: "number" })
    .notNull()
    .references(() => users.id, { onDelete: "restrict" }),
  action: varchar("action", { length: 255 }).notNull(),
  oldPhone: varchar("old_phone", { length: 255 }),
  newPhone: varchar("new_phone", { length: 255 }),
  sentToPhone: varchar("sent_to_phone", { length: 255 }),
  reason: varchar("reason", { length: 255 }),
  createdAt: createdAt().notNull().default(nowDefault),
});

export const siteBranding = pgTable("site_branding", {
  id: bigserial("id", { mode: "number" }).primaryKey(),
  initiativeName: varchar("initiative_name", { length: 255 }).notNull(),
  logoLightPath: varchar("logo_light_path", { length: 255 }),
  logoDarkPath: varchar("logo_dark_path", { length: 255 }),
  iconPath: varchar("icon_path", { length: 255 }),
  logoHeight: smallint("logo_height").notNull().default(32),
  primaryColorKey: varchar("primary_color_key", { length: 255 }).notNull(),
  secondaryColorKey: varchar("secondary_color_key", { length: 255 }).notNull(),
  updatedBy: bigint("updated_by", { mode: "number" }).references(() => users.id, { onDelete: "set null" }),
  createdAt: createdAt(),
  updatedAt: updatedAt(),
});

export const pages = pgTable(
  "pages",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    slug: varchar("slug", { length: 80 }).notNull(),
    title: varchar("title", { length: 150 }).notNull(),
    seoDescription: varchar("seo_description", { length: 300 }),
    blocks: jsonb("blocks").notNull().default(sql`'[]'::jsonb`),
    status: varchar("status", { length: 20 }).notNull().default("draft"),
    isSystem: boolean("is_system").notNull().default(false),
    publishedAt: riyadhTimestamp("published_at"),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [uniqueIndex("pages_slug_unique").on(table.slug)],
);

export const pageRevisions = pgTable(
  "page_revisions",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    pageId: bigint("page_id", { mode: "number" })
      .notNull()
      .references(() => pages.id, { onDelete: "restrict" }),
    blocks: jsonb("blocks").notNull(),
    authorId: bigint("author_id", { mode: "number" }).references(() => users.id, { onDelete: "restrict" }),
    isBaseline: boolean("is_baseline").notNull().default(false),
    createdAt: createdAt().notNull().default(nowDefault),
  },
  (table) => [
    index("page_revisions_page_id_id_index").on(table.pageId, table.id),
    uniqueIndex("page_revisions_one_baseline_per_page").on(table.pageId).where(sql`is_baseline`),
  ],
);

export const menuItems = pgTable(
  "menu_items",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    location: varchar("location", { length: 20 }).notNull(),
    label: varchar("label", { length: 40 }).notNull(),
    pageId: bigint("page_id", { mode: "number" }).references(() => pages.id, { onDelete: "restrict" }),
    url: varchar("url", { length: 500 }),
    position: smallint("position").notNull(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [
    index("menu_items_location_position_index").on(table.location, table.position),
    check("menu_items_page_or_url", sql`(${table.pageId} IS NULL) <> (${table.url} IS NULL)`),
  ],
);

export const menuRevisions = pgTable(
  "menu_revisions",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    items: jsonb("items").notNull(),
    authorId: bigint("author_id", { mode: "number" }).references(() => users.id, { onDelete: "restrict" }),
    isBaseline: boolean("is_baseline").notNull().default(false),
    createdAt: createdAt().notNull().default(nowDefault),
  },
  (table) => [uniqueIndex("menu_revisions_one_baseline").on(table.isBaseline).where(sql`is_baseline`)],
);

export const adminInvites = pgTable(
  "admin_invites",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    phone: varchar("phone", { length: 255 }).notNull(),
    tokenHash: varchar("token_hash", { length: 255 }).notNull(),
    expiresAt: riyadhTimestamp("expires_at").notNull(),
    acceptedAt: riyadhTimestamp("accepted_at"),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
    failedAttempts: smallint("failed_attempts").notNull().default(0),
  },
  (table) => [uniqueIndex("admin_invites_token_hash_unique").on(table.tokenHash), index("admin_invites_phone_index").on(table.phone)],
);

export const contactMessages = pgTable(
  "contact_messages",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    name: varchar("name", { length: 150 }).notNull(),
    phone: varchar("phone", { length: 20 }).notNull(),
    body: varchar("body", { length: 1000 }).notNull(),
    ip: varchar("ip", { length: 45 }).notNull(),
    createdAt: createdAt().notNull().default(nowDefault),
    readAt: riyadhTimestamp("read_at"),
  },
  (table) => [index("contact_messages_created_at_index").on(table.createdAt)],
);

export const systemHeartbeats = pgTable(
  "system_heartbeats",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    name: varchar("name", { length: 255 }).notNull(),
    lastSeenAt: riyadhTimestamp("last_seen_at").notNull(),
    status: varchar("status", { length: 255 }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [uniqueIndex("system_heartbeats_name_unique").on(table.name)],
);

export const systemAlerts = pgTable(
  "system_alerts",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    key: varchar("key", { length: 255 }).notNull(),
    lastSentAt: riyadhTimestamp("last_sent_at").notNull(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [uniqueIndex("system_alerts_key_unique").on(table.key)],
);

export const adminPasswordResets = pgTable(
  "admin_password_resets",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    userId: bigint("user_id", { mode: "number" })
      .notNull()
      .references(() => users.id, { onDelete: "restrict" }),
    tokenHash: varchar("token_hash", { length: 255 }).notNull(),
    expiresAt: riyadhTimestamp("expires_at").notNull(),
    usedAt: riyadhTimestamp("used_at"),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
    failedAttempts: smallint("failed_attempts").notNull().default(0),
  },
  (table) => [uniqueIndex("admin_password_resets_token_hash_unique").on(table.tokenHash)],
);

export const twoFactorSetupLinks = pgTable(
  "two_factor_setup_links",
  {
    id: bigserial("id", { mode: "number" }).primaryKey(),
    userId: bigint("user_id", { mode: "number" })
      .notNull()
      .references(() => users.id, { onDelete: "cascade" }),
    tokenHash: varchar("token_hash", { length: 255 }).notNull(),
    expiresAt: riyadhTimestamp("expires_at").notNull(),
    usedAt: riyadhTimestamp("used_at"),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (table) => [
    uniqueIndex("two_factor_setup_links_token_hash_unique").on(table.tokenHash),
    index("two_factor_setup_links_user_id_used_at_index").on(table.userId, table.usedAt),
  ],
);

export type User = typeof users.$inferSelect;
