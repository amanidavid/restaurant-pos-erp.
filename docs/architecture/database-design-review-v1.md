# RMIS Architecture and Database Design Review v1

Status: Architecture decisions confirmed on 20 August 2026  
Source reviewed: `RMIS - Software Requirements Specification v1.0`, 59 pages  
Target repository: Laravel 13, PHP 8.3, PostgreSQL

## 1. Executive decision

RMIS should be implemented as a modular monolith with:

- one landlord/control-plane PostgreSQL database owned by ELTECH;
- one separate PostgreSQL operational database for every tenant business;
- many branches within each tenant database;
- Redis for cache, distributed locks, queues, rate limits, and the WebSocket backplane;
- Laravel Reverb for real-time delivery;
- object storage for images, receipts, documents, and proofs;
- one tenant-specific reporting database/read store per tenant, never a shared cross-tenant warehouse;
- an outbox/inbox and idempotency pattern for every external or asynchronous integration.

Laravel 13 is the confirmed backend and Laravel Reverb is the confirmed WebSocket implementation. The SRS clauses that mandate ASP.NET Core .NET 10, EF Core, SignalR, RabbitMQ, and Hangfire must be amended. The system is online-only: offline POS, offline KDS, local sync peers, provisional offline receipts, offline conflict resolution, and the related offline acceptance criteria are out of scope.

## 2. Tenant and branch boundaries

```text
ELTECH platform
  landlord database
    global identities, tenant accounts, device sessions, tenants, domains, database locations,
    packages, subscriptions, platform billing, provisioning,
    separate platform operators, support, announcements, fleet status

  tenant database: Kilimanjaro Restaurants Ltd
    local tenant-user projections and branch-scoped permissions
    company and shared master data
    branch: Masaki
    branch: Mlimani City
    branch: Arusha

  tenant database: Cafe Zanzibar
    completely separate identity, master, operational, and financial data
```

### Landlord database owns

- `users` as global human identities and `user_identities` as password/Google identity links;
- `tenant_accounts` as exactly one account per global user per tenant, with tenant-bound status and sessions;
- `tenants`, `tenant_domains`, `database_clusters`, `tenant_databases`, and provisioning state;
- `packages`, `package_features`, `subscriptions`, and capacity limits;
- `platform_users` as a separate ELTECH operator identity, with separate platform roles/permissions and audit;
- platform invoices and payments;
- tenant health and non-sensitive aggregate usage counters.

The landlord database must not contain tenant orders, customers, stock, staff, journals, or detailed reports. Database credentials should be encrypted through an application key or, preferably, referenced from a secret manager. Platform support impersonation must be consented, time-boxed, reasoned, and audited.

### Tenant database owns

- local `tenant_users` projections, roles, permissions, branch/department access, and tenant audit;
- all organisation, menu, recipe, sales, kitchen, inventory, procurement, finance, CRM, staff, delivery, notification, and integration records;
- tenant-level integration configuration and provider account references;
- multiple branches and their operational data.

Because the database itself is the tenant isolation boundary, repeating `tenant_id` on every tenant table is redundant and creates update/consistency risk. The SRS rule requiring `tenant_id` on every tenant row should be amended for database-per-tenant mode. Every branch-owned operational record still carries `branch_id`.

### Global identity, one account per tenant, and the tenant-user projection

Google login makes email unsuitable as a duplicated tenant-account identity key. Separate authentication identity from tenant access:

- one global `users` row represents the human and owns an optional globally unique normalized email, an optional globally unique normalized phone, at least one of those login identifiers, and the password lifecycle;
- `user_identities` links authentication providers using the stable provider subject, for example Google's `sub`, never email;
- `tenant_accounts` represents exactly one account for that identity in one tenant and has `unique(user_id, tenant_id)`;
- JWT/device sessions are tenant-account-bound, so a Tenant A session can never select Tenant B.

If the same human works for Tenant A and Tenant B, there is one global identity and two tenant accounts. This is not one unrestricted cross-tenant account: each tenant account has independent status, invitation, ownership metadata, branch assignments, roles, permissions and active-device session.

The base `users` row solves authentication but does not safely solve tenant-local foreign keys, Spatie assignments, audit actor references, employee linkage, or moving a tenant database to another server. PostgreSQL cannot enforce a normal foreign key from a tenant database on another server to the landlord `users` table.

Use this cardinality:

```text
landlord.users 1 --- * landlord.user_identities
landlord.users 1 --- * landlord.tenant_accounts * --- 1 landlord.tenants
landlord.tenant_accounts 1 --- 1 tenant database.tenant_users
tenant_users 1 --- * tenant_user_branches * --- 1 branches
tenant_users 1 --- 0..1 employees
```

`tenant_users` is not another login account and has no password. It is a local authorization/principal projection containing `id`, public `uuid`, immutable `tenant_account_uuid`, immutable `base_user_uuid`, display snapshot, status, and timestamps. Tenant operational tables use `tenant_user_id` bigint foreign keys. The projection can be rebuilt from the matching landlord tenant account if necessary.

If one person works for Tenant A and Tenant B, Google/password authenticates the global identity, but the application issues a separate tenant-bound JWT/device session for the selected authorized tenant account. There is no cross-tenant switch using one issued token.

### Recommended landlord identity and tenant-location tables

`users` should contain `id`, `uuid`, optional globally unique normalized email, optional globally unique normalized E.164 phone, name fields, optional password hash, separate verification timestamps, global status and timestamps. At least one of email or phone is required. Password login accepts an explicit `login_type` of `email` or `phone`, normalizes the selected value, and performs one exact indexed lookup. Password reset and verification follow the same selected identifier type. `user_identities` contains `user_id`, provider, provider subject, verified email snapshot and last-used time, with `unique(provider, provider_subject)` and `unique(user_id, provider)`. Google's subject is the identity key; email is profile/contact data and may change.

If a first Google login returns an email already used by a password account, do not automatically link accounts from the email match alone. Require the user to authenticate the existing account or complete a signed invitation/account-link confirmation, then attach the verified Google `sub`. This prevents a provider-email collision or reassignment from becoming an account-takeover path.

`tenant_accounts` contains `id`, `uuid`, `user_id`, `tenant_id`, status, ownership metadata, invitation/joined/ended timestamps, token version and timestamps, with `unique(user_id, tenant_id)`. `platform_users` remains separate so a tenant identity can never become a Control Center identity.

`tenant_databases` should preserve movement history rather than assuming one permanent host. Recommended fields are `tenant_id`, `database_cluster_id`, `database_name`, `database_username`, `password_secret_reference`, `status` (`provisioning`, `active`, `draining`, `retired`, `failed`), `is_primary`, `schema_version`, `provisioned_at`, `activated_at`, and `retired_at`. Host, port, region, SSL mode, and server health belong to `database_clusters`. Prefer a secret-manager reference over storing even an encrypted password in the landlord database.

`tenant_domains` should contain normalized domain, type (`platform_subdomain`, `custom`), verification-token hash, verification status/time, primary flag, SSL status, and timestamps. The domain and `X-Tenant-Id` must resolve to the same active tenant when both are supplied; a mismatch is rejected. Moving a tenant database changes only its active `tenant_databases` record and does not change user, tenant, domain, or public UUIDs.

## 3. Identifier standard

Every aggregate or entity exposed through an API, event, import/export, or QR code uses both identifiers:

```text
id       bigint generated by default as identity primary key
uuid     uuid not null unique
```

- All database foreign keys use bigint `*_id` for compact indexes and fast joins.
- Public UUIDs are UUIDv7 generated by Laravel before insert. Laravel 13's `HasUuids` generates UUIDv7, but because RMIS retains bigint primary keys, a shared `HasPublicUuid` trait populates only the secondary `uuid` column with `Str::uuid7()`. Do not use PostgreSQL `gen_random_uuid()` for public identifiers because it generates UUIDv4.
- APIs and route model binding expose only `uuid`.
- Foreign identifiers received by APIs use UUID and are resolved to bigint within the active tenant connection.
- Pure pivot/detail tables that are never independently addressed do not require UUID unless they participate in sync or audit references.
- UUID must never replace a uniqueness rule. Business uniqueness remains explicit, for example `(branch_id, business_date, order_number)`.
- Integrations may generate UUIDs before reaching the server. The server resolves referenced UUIDs and inserts rows with normal bigint foreign keys in one transaction.

Recommended Laravel model convention: `getRouteKeyName(): string` returns `uuid`, scoped bindings confirm branch ownership, and resources never serialize internal IDs.

## 4. Cross-cutting columns and constraints

Use these only where the domain needs them, rather than adding every column to every table:

- `id bigint`, `uuid uuid`, `created_at timestamptz`, `updated_at timestamptz`;
- `branch_id bigint` on branch-owned operational rows;
- `business_date date` on every financial and operational transaction;
- `created_by_id`, `updated_by_id`, `approved_by_id`, or `performed_by_id` where accountability is required;
- `status` constrained by a PostgreSQL check constraint or application enum/state object;
- `version bigint` or `lock_version` for optimistic concurrency on mutable aggregates;
- `deleted_at` only for deactivatable master data; no soft deletes on immutable ledgers;
- `metadata jsonb` only for provider payload extensions or genuinely variable attributes, never as a substitute for normalized core fields.

Money should use `numeric(19,4)`. Stock and recipe quantities should normally use `numeric(20,6)`. Percentages and rates should use a documented exact numeric scale. All timestamps use `timestamptz`; business date is a separate `date` derived from branch close rules.

## 5. Module map and complexity

Complexity includes data integrity, concurrency, integrations, accounting impact, and operational risk—not screen count.

| Domain module | Complexity | Main implementation boundary |
|---|---:|---|
| Platform Control Center | Critical | Landlord-only tenant provisioning, subscription gates, billing, support, fleet audit |
| Identity, tenant accounts, sessions, RBAC | Critical | Global password/Google identity, one account and active device per tenant, local principal projection, branch-team permissions |
| Organisation and multi-branch | High | Branch configuration, business dates, head-office masters and branch overrides |
| Menu, pricing, modifiers, combos, promotions | High | Effective-dated master data and deterministic price/promotion snapshots |
| Recipe, UoM, yield, and costing | Critical | Versioned recipes, base-unit conversion, sub-recipes, historical cost snapshots |
| POS and order lifecycle | Critical | Central transaction spine, concurrent amendments, splits, transfers, and strict online transaction handling |
| Floor, tables, and table sessions | High | Live occupancy, table joins/transfers, server attribution, conflict handling |
| Reservations, waitlist, and events | High | Capacity/overlap rules, deposits, table allocation, notifications |
| Digital and aggregator ordering | Critical | Public security, shared order engine, availability race handling, adapter ingestion |
| KDS and station routing | Critical | Item-level state, routing, course firing, timers, reconnect refresh, and real-time push |
| Billing, tender, fiscal, refund | Critical | Exact allocation, mixed tenders, provider/fiscal adapters, reversal-only corrections |
| Cashier sessions and cash control | High | Drawer exclusivity, blind count, variance approval, Z-report, ledger posting |
| Inventory, batches, and stores | Critical | Append-only stock ledger, FEFO allocation, weighted-average valuation, concurrency |
| Automatic consumption and variance | Critical | Idempotent production consumption/reversal, COGS, theoretical-versus-actual chain |
| Procurement and accounts payable | Critical | Approval workflow, PO/GRN/invoice matching, landed-cost allocation, AP posting |
| Waste management | High | Controlled stock-ledger movement, approval threshold, variance attribution |
| Delivery | High | Dispatch, zones, driver state, proof, COD reconciliation, channel profitability |
| CRM, loyalty, gift value, campaigns | High | Consent, deduplication, append-only loyalty/value ledgers, privacy and messaging |
| Staff, roster, attendance, payroll, tips | Critical | Branch/department assignment, time rules, commissions, payroll, deductions, statutory outputs |
| Financial accounting | Critical | Balanced immutable journals, idempotent posting rules, periods, AR/AP and reconciliation |
| Reporting and BI | Critical | Separate read model/store, reproducible measures, drill-through, as-of timestamps |
| Intelligence and advisory | Very high | Forecast evaluation and sourced narratives; no model-generated numbers |
| Notifications | Medium/High | Templates, preferences, durable fan-out, retry, provider delivery state |
| Fraud and internal control | Critical | Approval cases, baselines, traceable alerts, no opaque automatic accusations |
| Audit and administration | Critical | Hash-chained audit, read audit for sensitive data, configuration and export |
| Hardware and mobile shells | High | ESC/POS, drawer, scanner, scale, device agent, push and unreliable connectivity |

## 6. High-level tenant schema by domain

### Foundation and access

- `companies`, `branches`, `departments`, `locations`, `branch_configurations`, `business_dates`;
- `tenant_users`, `tenant_user_branches`, `staff_profiles`, `user_department_access`;
- Spatie tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`;
- `audit_entries`, `approval_requests`, `approval_steps`, `integration_accounts`.

Spatie Teams is enabled with internal bigint `branch_id` as its team foreign key and attaches roles/permissions to the tenant-local `TenantUser` model. A public `X-Branch-Id: <branch-uuid>` header is resolved before `setPermissionsTeamId($branchId)` is called. `tenant_user_branches` determines which branches a user may select; Spatie determines what the user may do in the selected branch. Department and record scopes remain explicit policy inputs and are not encoded into permission names.

Authentication and scope order is:

1. Require and validate `X-Tenant-Id: <tenant-uuid>` or resolve the same tenant from its verified domain.
2. Authenticate password or Google identity globally. For Google, validate issuer, audience, expiry, nonce and signature, then resolve `user_identities` by `(provider = google, provider_subject = sub)`; do not link by email alone.
3. Verify an active `tenant_accounts` row for the global user and resolved tenant. Google sign-in must never create tenant access unless an invitation/onboarding rule authorizes it.
4. Issue a tenant-bound JWT containing global user UUID, tenant-account UUID, tenant UUID, `jti`, device-session UUID and expiry.
5. On API requests, reject unless the token tenant, header/domain tenant, tenant account and active device session all agree.
6. Resolve the active tenant database and make it current.
7. Resolve the tenant account's local `tenant_users` projection.
8. Return all active branches from `tenant_user_branches` that the principal may access. If the user has two branches, both appear in the branch selector.
9. For a branch operation, require `X-Branch-Id: <branch-uuid>`, verify the assignment, resolve internal `branch_id`, set the Spatie team context, clear loaded role/permission relations, and enforce the exact permission and policy.

The JWT contains the immutable tenant-account context but not branch permission lists because they become stale when access changes. Tenant-account status, device session, branch access and permissions are checked from current server-side state.

### One active device session per tenant account

Enforce the rule in the landlord database because authentication and JWT issuance happen there. `user_device_sessions` contains `id`, `uuid`, `tenant_account_id`, stable application `device_uuid`, device name/type/platform, refresh-token hash, current/last `jti`, IP and user-agent snapshots, `is_active`, started/last-seen/expires timestamps, and revoked timestamp/reason.

Use a PostgreSQL partial unique index on `tenant_account_id where is_active = true` so a tenant account has at most one logical active session. Login first marks an expired session inactive and locks the tenant-account row in a transaction to avoid two simultaneous logins racing past the check.

Login behavior:

1. If there is no active session, register the device session and issue tokens.
2. If the same logical device/session is refreshing, continue it; several browser tabs do not create several devices.
3. If another device is active, return HTTP `409` with code `ACTIVE_DEVICE_SESSION_EXISTS` and a safe device summary.
4. Offer `POST /api/v1/auth/sessions/current/logout` and `POST /api/v1/auth/sessions/revoke-other`. Revoking another device requires recent password/Google reauthentication or MFA and is audited.
5. Access tokens are short-lived. Every request checks the cached active session/token version; logout/revocation invalidates the cache immediately so an old JWT cannot remain usable until its nominal expiry.
6. Stale sessions expire after a configured inactivity/maximum lifetime. A broken or lost device can be revoked by the tenant user or an authorized tenant administrator.

The single-device rule is per tenant account, not per global identity. The same human may have one active device in Tenant A and another in Tenant B because those are separate authorized accounts.

### Menu, pricing, and recipe

- `menus`, `menu_categories`, `products`, `product_translations`, `product_branch_settings`;
- `modifier_groups`, `modifiers`, `product_modifier_groups`, `modifier_group_options`;
- `combos`, `combo_components`;
- `price_lists`, `price_list_items`, `promotions`, `promotion_rules`, `availability_overrides`;
- `units_of_measure`, `uom_conversions`, `recipes`, `recipe_versions`, `recipe_lines`, `batch_productions`, `yield_records`, `product_cost_snapshots`.

Head-office definitions are canonical and branch overrides are confirmed. Branch availability, station, price, tax, activation, recipes, suppliers, price lists, tax profiles, and chart-of-account differences belong in explicit effective-dated override tables. Resolution is `active branch override -> tenant master default`; the resolved value is snapshotted onto transactions. Do not clone complete master records per branch.

### Sales, tables, kitchen, and billing

- `floor_areas`, `restaurant_tables`, `table_seats`, `table_sessions`, `table_session_tables`;
- `orders`, `order_lines`, `order_line_modifiers`, `order_status_histories`, `order_amendments`;
- `bills`, `bill_lines`, `bill_allocations`, `applied_discounts`, `applied_promotions`;
- `payments`, `payment_allocations`, `payment_attempts`, `refunds`, `tips`, `service_charges`, `fiscal_receipts`;
- `kitchen_stations`, `station_routing_rules`, `kitchen_tickets`, `kitchen_ticket_lines`, `preparation_events`, `courses`.

`orders` and `order_lines` are the transaction spine. Downstream records reference them instead of duplicating sale facts. Unit price, tax, discount, modifier price, recipe version, and relevant descriptions are snapshotted so historical transactions never change when master data changes.

### Inventory, consumption, purchasing, and waste

- `inventory_items`, `stores`, `stock_batches`, `stock_ledger_entries`;
- `stock_counts`, `stock_count_lines`, `stock_transfers`, `stock_transfer_lines`, `wastages`;
- `production_consumptions`, `production_consumption_lines`;
- `suppliers`, `supplier_items`, `purchase_requisitions`, `purchase_requisition_lines`;
- `requests_for_quotation`, `supplier_quotations`, `purchase_orders`, `purchase_order_lines`;
- `goods_receipts`, `goods_receipt_lines`, `quality_checks`, `supplier_invoices`, `supplier_invoice_lines`, `landed_cost_allocations`, `purchase_returns`.

`stock_ledger_entries` is append-only and authoritative. A balance projection may be maintained transactionally for fast reads, but it is rebuildable and never the legal source. Production is the confirmed consumption trigger. When a kitchen line reaches the configured produced/ready state, its effective recipe version is consumed exactly once. Every stock effect has a unique source reference such as `(source_type, source_id, movement_kind)` to prevent duplicate consumption or reversal.

### Accounting and cash control

- `chart_of_accounts`, `accounts`, `cost_centres`, `accounting_periods`;
- `journal_entries`, `journal_lines`, `posting_rules`, `posting_failures`;
- `receivables`, `receivable_allocations`, `payables`, `payable_allocations`;
- `cash_accounts`, `bank_accounts`, `bank_statement_lines`, `bank_reconciliations`;
- `expenses`, `fixed_assets`, `depreciation_runs`, `budgets`, `budget_lines`;
- `cashier_sessions`, `cash_movements`, `denomination_counts`, `session_variances`, `daily_closes`.

Journal lines must balance per entry through a deferred database constraint or a controlled posting function/service plus commit-time validation. Posted entries cannot be updated or deleted. Operational posting uses a unique `(source_type, source_uuid, posting_rule_code)` key and reversals point to the original entry.

### CRM, delivery, staff, and communications

- `customers`, `customer_contacts`, `customer_consents`, `customer_merge_histories`, `customer_segments`;
- `loyalty_accounts`, `loyalty_ledger_entries`, `loyalty_tiers`, `vouchers`, `gift_cards`, `gift_card_ledger_entries`, `campaigns`, `campaign_recipients`, `feedback`;
- `delivery_zones`, `drivers`, `driver_shifts`, `delivery_orders`, `delivery_events`, `proofs_of_delivery`, `driver_cash_reconciliations`;
- `positions`, `shifts`, `rosters`, `roster_slots`, `attendance_entries`, `leave_requests`, `commission_rules`, `commission_statements`, `tip_allocations`;
- `pay_items`, `statutory_rules`, `statutory_rule_bands`, `employee_payroll_profiles`, `employee_salary_histories`, `employee_recurring_pay_items`;
- `payroll_periods`, `payroll_runs`, `payroll_run_employees`, `payroll_run_lines`, `payroll_run_exports`, `payroll_postings`, and `statutory_submission_exports`;
- `notification_templates`, `notifications`, `notification_deliveries`, `notification_preferences`.

Phone search stores a normalized E.164 value. Customer duplicates are merged, never deleted without trace. Financial history keeps an anonymized party reference when privacy erasure is required.

The CFS payroll project is a useful domain reference. Reuse its separation between setup, effective-dated employee configuration, calculation runs, immutable result snapshots, and exports. Adapt it to RMIS as follows:

- replace UUID foreign keys with internal bigint foreign keys while retaining public UUIDs;
- add branch, department, cost-centre, job/position, and employment-contract snapshots to payroll results;
- add pay frequency, proration, overtime/attendance inputs, leave-without-pay, loans/advances, benefits, bonuses, commissions, tips, and termination/final-pay treatment;
- model approval, posting, payment and reversal states explicitly; an approved/posted payroll is not edited in place;
- post salary expense, employer contributions, employee deductions, payroll payable, tax payable, and bank/cash settlement through documented journal rules;
- keep statutory rules and bands effective-dated and versioned. Do not hard-code legal rates in migrations. Seed an initial reviewed catalogue separately and snapshot the applied rule/rate/basis on each payroll run line.

### Integration and realtime

- `outbox_messages`, `inbox_messages`, `webhook_receipts`, `idempotency_records`;
- `provider_accounts`, `provider_transactions`, `provider_settlements`, `provider_reconciliations`.

External callbacks are stored first with a provider event ID and payload hash, then processed idempotently. A sale commits without waiting for fiscal, messaging, analytics, or aggregator services. Payment confirmation is asynchronous but has an explicit state machine and reconciliation path.

Providers are intentionally not fixed yet and all restaurant payment/aggregator configuration is tenant-local. Activation has two layers:

1. The platform package/entitlement enables a generic feature such as `integrations.mobile_money`, `integrations.card_payments`, or `integrations.aggregators` for the tenant. A platform administrator can grant or revoke an override without seeing tenant credentials or transactions.
2. A tenant administrator with an exact permission such as `integrations.accounts.manage` chooses a supported adapter, supplies that tenant's merchant credentials, selects branches/channels, tests the connection, and activates the account.

An integration is usable only when both the landlord entitlement and the tenant account are active. Entitlements are cached with tenant-specific keys and invalidated on subscription/override changes. Tenant credentials are encrypted or stored through a tenant-scoped secret reference and never copied into the landlord database.

The tenant schema uses:

- `integration_providers`: tenant-local provider/adapter registry with `provider_code`, `adapter_key`, type, name and status;
- `provider_capabilities`: declared abilities such as collect, status-query, partial-refund, webhook, settlement, menu-sync or order-intake;
- `integration_accounts`: merchant/account configuration, optional `branch_id`, environment, secret references, webhook configuration, status and activation audit;
- `payment_attempts`: internal idempotent attempts linked to bill/order and integration account;
- `provider_transactions`: confirmed provider references, amounts, fees and states;
- `webhook_receipts`: signature result, unique external event key, payload hash, processing state and timestamps;
- `refunds`: full/partial reversal workflow linked to the original provider transaction;
- `provider_settlements` and settlement lines: gross, fee, net, settlement date and bank reference;
- `provider_reconciliations`: reconciliation run, discrepancies and resolution state;
- `aggregator_orders`: unique external-to-internal order mapping per integration account;
- `aggregator_order_events`: deduplicated external lifecycle events and processing result.

Supported adapter definitions can be seeded into every tenant database as they are developed. This avoids a shared operational provider table while still keeping adapter code consistent.

Platform subscription billing is a separate landlord concern. Use landlord-side `platform_payment_providers`, `platform_provider_accounts`, `platform_payment_attempts`, `platform_webhook_receipts`, `platform_transactions`, `platform_refunds`, `platform_settlements`, and `platform_reconciliations`. Never mix ELTECH subscription payments with restaurant customer payments stored in tenant databases.

## 7. Real-time design

Use Laravel Reverb with Redis horizontal scaling. The database remains the source of truth; WebSocket events only announce committed changes.

- private channels: `tenant.{tenantUuid}` and `branch.{branchUuid}`;
- narrower channels for `kds.station.{uuid}`, `table-session.{uuid}`, `order.{uuid}`, and `delivery.{uuid}`;
- authorize channels only after the tenant database is selected and the user's branch/permission scope is established;
- broadcast after transaction commit, normally through a queue/outbox;
- include event UUID, aggregate UUID, aggregate version, event type, and occurred-at timestamp;
- clients detect version gaps and refresh through the versioned REST API;
- never broadcast provider secrets, cost data to public channels, or unneeded personal data.

Critical real-time flows are KDS tickets, order amendments/status, table/floor status, waiter-ready alerts, payment status, delivery tracking, and control alerts.

### Terminals, receipt printers and durable print jobs

Human device sessions, POS terminals and printer agents are different identities:

- `user_device_sessions` authenticates the human session and enforces the one-active-device rule;
- `terminals` identifies a registered POS/KDS/manager device in a tenant branch;
- `printer_agents` authenticates branch-local software allowed to reach USB/LAN ESC/POS printers;
- `printers` describes the physical printer and capabilities.

Tenant tables:

- `terminals`: UUID, branch, code, name, type, status, device fingerprint/public key, last seen and configuration version;
- `printer_agents`: UUID, branch, machine identity/public key, status, last seen and rotated credential metadata;
- `printers`: UUID, branch, agent, name, connection type, network/USB identifier, model, paper width, capabilities and status;
- `printer_routes`: branch, optional terminal/station, document type, order channel, printer, priority, copies and fallback route;
- `receipt_documents`: immutable paid-bill/payment/fiscal document snapshot, number, bill/payment references, template version, content hash, storage path and issue/reissue metadata;
- `print_jobs`: UUID, idempotency key, document type/reference, printer/route, requested-by tenant user, status, attempts, payload/content hash and queued/claimed/printed/failed timestamps;
- `print_job_attempts`: agent/printer, attempt number, acknowledgement, error code/message and timing;
- `receipt_reprints`: original receipt, reason, approved/requested-by users, copy number and audit reference.

Paid-bill flow:

1. Confirm and commit the payment exactly once.
2. Generate an immutable receipt document from snapshotted bill/payment/tax values.
3. Create a durable print job after commit using a unique key such as `(receipt_document_id, copy_number, printer_id)`.
4. Notify the target printer agent over a private Reverb channel; the notification is only a wake-up signal.
5. The agent claims the job through an authenticated API, prints ESC/POS bytes and acknowledges the outcome. If the WebSocket event is missed, the durable job remains queryable and retryable.
6. A failed printer never changes a paid bill back to unpaid. Offer another configured printer and a PDF/SMS/email receipt fallback.
7. Every reprint requires `billing.receipts.reprint`, a reason and visible `REPRINT`/copy numbering; it never issues another payment or fiscal transaction.

Cloud Laravel normally cannot reach a tenant's USB or private-LAN printer directly. Use a small branch-local print agent with an outbound TLS connection. Machine credentials and printer access are separate from the cashier's JWT, so logging the cashier out does not impersonate or unregister the printer.

## 8. Integration boundary

Every provider is behind a tenant-aware adapter contract:

- fiscal/TRA;
- mobile money and card acquirers;
- SMS, WhatsApp, email, and push;
- food aggregators;
- PMS/room posting;
- mapping/geocoding;
- S3-compatible object storage.

Provider credentials are tenant-specific and encrypted. Each adapter must support correlation IDs, idempotency keys, timeout, retry/backoff, circuit breaking, raw request/response audit with secret redaction, webhook signature verification, reconciliation, and a manual fallback. External outages must not roll back a valid cash sale.

### Example provider contract

Suppose the first mobile-money provider is M-Pesa and the customer pays TZS 45,000:

1. RMIS creates `payment_attempts.uuid = pay_123`, status `pending`, and sends `pay_123` as the idempotency/reference key.
2. The provider accepts the request and returns its transaction reference. A timeout does not mean failure, so RMIS queries status or safely retries with the same idempotency key instead of creating another payment.
3. The provider calls the RMIS webhook. RMIS verifies its signature, stores the raw event once using the provider event/reference ID, immediately acknowledges it, and processes it from the inbox.
4. A duplicate webhook is acknowledged but cannot post a second payment because the provider event ID and provider transaction reference are unique.
5. The payment is allocated to the bill and the waiter/POS receives a Reverb payment-status event only after commit.
6. The provider settlement file/API later reports gross TZS 45,000, fee, net amount, settlement date, and bank reference. RMIS matches this to the provider transaction and flags differences.
7. A refund uses the provider's original reference. The contract must state whether full and partial refunds are supported, their time limits, statuses, callbacks, fees, and how failed reversals are reconciled.

For an aggregator such as Bolt Food or Glovo, the contract instead covers menu/product mapping, price and availability synchronization, new-order webhook authentication, duplicate order identifiers, acceptance/rejection deadline, cancellation ownership, preparation/ready statuses, commission/tax fields, and payout settlement. RMIS must know which providers are first because these capabilities and payloads are not identical.

## 9. Package strategy for this repository

Recommended core packages/services:

- `spatie/laravel-multitenancy` v4 for landlord/tenant connection switching and tenant-aware queues;
- `spatie/laravel-permission` v8 for branch-team permission-based access control on the tenant-local `TenantUser` projection;
- `php-open-source-saver/jwt-auth` for landlord-stored, tenant-account-bound JWT authentication; there is no exact Composer package named `laravel/jwt` selected by this design;
- `laravel/socialite` for Google OAuth/OIDC flow while persisting the verified Google subject in `user_identities`;
- `spatie/laravel-medialibrary` for tenant documents/images on S3-compatible storage, using a tenant-connection Media model and tenant-prefixed object keys;
- `spatie/laravel-settings` for typed, low-risk tenant/branch presentation settings after the tenant is current;
- Laravel Reverb for WebSockets and Redis as its scale-out backplane;
- Laravel Horizon if Redis queues are selected;
- Laravel Pennant or a small package-feature service for subscription feature gates;
- S3-compatible Laravel filesystem storage;
- OpenTelemetry-compatible tracing and structured logging.

Important limitations:

- Spatie Multitenancy switches the database connection, but tenant database creation, roles, migrations, backups, restore tests, and fleet drift remain application/operations responsibilities.
- PHP remains at 8.3. Spatie Activitylog will not be installed: v5 requires PHP 8.4, while v4 does not allow Laravel 13. Build a custom `Auditable` trait backed by an `AuditService` and append-only `audit_entries`. Each entry includes previous hash, canonical payload hash, actor, action, subject type/UUID, tenant/branch context, request/correlation ID, IP/device context, before/after values with sensitive-field redaction, and an external chain anchor. Explicit service calls capture sensitive reads, login, export, approval, impersonation, and integration actions.
- Media Library manages metadata and conversions; tenant object authorization, private S3 visibility, signed URLs, malware/type validation, retention, and tenant-prefixed paths remain application responsibilities.
- Settings is appropriate for typed settings such as UI preferences, receipt presentation, or notification defaults. Tax profiles, payroll rules, accounting periods, numbering, branch overrides, and integration credentials require normalized/versioned domain tables rather than an unstructured settings store.
- Spatie Backup is not the primary database-per-tenant recovery design. PostgreSQL WAL/PITR, encrypted off-site snapshots, per-tenant restore automation, and recorded restore tests belong to infrastructure. A compatible stable package release may later be used for supplemental application/file dumps only.
- JWT claims contain base identity, immutable tenant UUID and token metadata, but not current branch roles or permissions. Account status, token-version/revocation, branch access and permissions stay server-side.
- Spatie Permission controls grants; branch ownership, department scope, approval thresholds, record state, separation of duties, and value limits still belong in policies/domain services.
- Do not event-source the whole ERP merely because auditability is required. Immutable stock, loyalty, gift-value, and financial ledgers plus audit/outbox records give the required history with lower complexity.

## 10. PostgreSQL performance rules

- Index every foreign key used for joins; PostgreSQL does not create FK indexes automatically.
- Start composite indexes with the mandatory scope, commonly `branch_id`, followed by equality filters and then range/order columns.
- Typical examples: `(branch_id, business_date, status)`, `(branch_id, status, created_at desc)`, `(store_id, inventory_item_id, occurred_at)`, `(order_id, status)`, `(customer_id, occurred_at desc)`.
- Use unique partial indexes for invariants such as one open cashier session per drawer and one active business date per branch.
- Use partial indexes for hot subsets such as open orders, pending outbox messages, unpaid invoices, and active products.
- Use PostgreSQL trigram GIN indexes for contains/typo-tolerant server search. Prefix search such as `m%` can use an appropriate btree/pattern index. Normalize phones and codes into indexed columns.
- Use BRIN selectively for very large append-only time-ordered audit, event, and ledger tables.
- Avoid N+1 reads with eager loading and purpose-built read queries/resources.
- Every collection endpoint is paginated. Default to 15; enforce a maximum such as 100. Prefer cursor/keyset pagination for high-volume orders, ledger entries, audit, and events.
- Reporting queries never scan operational trade tables during peak service. Feed read models or a reporting store from the outbox/CDC pipeline.
- Partition only after measured need. Likely candidates are audit, outbox, stock ledger, journal lines, and high-volume order events by time; do not partition every table from day one.

## 11. Delivery order for schema and implementation

1. Platform foundation: tenancy, provisioning, global/password/Google identity, tenant accounts, single-device sessions, RBAC scopes, audit chain, branch/business-date rules, migrations, backups, localisation, observability.
2. Commercial master data: organisation, menu, prices, tax, recipe/UoM foundations.
3. Transaction spine: POS terminals, printer agents/routes, table sessions, orders/lines, billing, immutable receipts, durable print jobs, cashier control, basic journals, and request/integration idempotency.
4. Kitchen and real time: station routing, tickets/lines, production state, waiter/floor events, printer fallback.
5. Inventory and costing: stock ledger, batches, valuation, recipe consumption, reversals, counts, waste, variance.
6. Procurement and AP: requisition through payment, matching, landed cost, supplier performance.
7. Customer channels: digital ordering, reservations, CRM, loyalty, campaigns, notifications.
8. Delivery, staff and payroll, full accounting, reporting/control, then intelligence.

## 12. Confirmed decisions and remaining external inputs

Confirmed:

1. Laravel 13 replaces the SRS .NET backend and Reverb replaces SignalR.
2. PHP remains 8.3; auditing uses a custom trait/service because no Spatie Activitylog major supports both PHP 8.3 and Laravel 13.
3. `users` is the global human identity and supports password login by either normalized email or normalized E.164 phone, selected explicitly by the user. Google is linked by stable provider subject, not email. `tenant_accounts` enforces exactly one account per global identity per tenant.
4. Every tenant account has a passwordless tenant-local `tenant_users` projection for local foreign keys, audit, employee linkage, and Spatie authorization.
5. `X-Tenant-Id`, verified domain, JWT tenant claim and tenant account must agree. `X-Branch-Id` is then verified against the tenant user's branch assignments.
6. Spatie Teams uses internal bigint `branch_id`; API headers expose only UUIDs. Users assigned to two branches in their tenant see both in the selector, while actions remain permission-checked in the selected branch.
7. The system is online-only. All offline protocol, storage, peer, and provisional offline flows are removed.
8. Reporting storage is isolated per tenant database boundary.
9. Payroll is a required module, using the CFS domain pattern after conversion to bigint foreign keys and immutable accounting-ready snapshots.
10. Inventory consumption is triggered by production.
11. Tenant masters support effective-dated branch overrides.
12. Restaurant providers and aggregators remain entirely tenant-configurable and are not fixed yet. Platform packages/overrides only activate generic integration features; tenant administrators configure tenant accounts and credentials. Landlord subscription payments use a separate generic provider boundary.
13. Each tenant account allows one active human device session at a time, enforced by a landlord session registry and partial unique index with secure self/admin revocation.
14. Registered tenant terminals and printer agents are separate from human sessions. Paid receipts use immutable receipt documents and durable, idempotent print jobs routed to branch ESC/POS printers with audited reprints and digital fallback.

External inputs still required before final DDL for the affected adapters:

- TRA online fiscal specifications, credentials, signing rules, callback/status behavior, and sandbox access;
- each tenant's selected mobile-money provider, card acquirer/gateway, and food aggregator when that tenant enables an integration;
- each selected provider's webhook authentication, settlement format, refund/reversal behavior, idempotency support, timeout/retry rules, sandbox credentials, and certification process;
- payroll statutory rules and outputs required for the first country release, including PAYE, social-security contributions, other deductions, filing formats, and effective dates.

The physical design is now captured in `physical-postgresql-schema-v1.md`: landlord first, then tenant foundation, commerce, operations, inventory/procurement, payroll/finance, and engagement/reporting. After approval, Laravel migrations and their schema tests should begin with the landlord groups and tenant foundation. The WBS should follow the same dependency order rather than treating SRS sections as independent parallel features.
