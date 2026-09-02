# API Design 23: Packages, Features, Tenant Subscriptions, Feature Overrides, and Platform Billing

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains platform-side package/subscription control.

It answers:

```text
Which features does a tenant have?
Which package is tenant subscribed to?
Is tenant active/trial/suspended?
Can admin temporarily enable/disable a feature?
How are subscription invoices tracked?
```

This is landlord-side because feature access and billing are platform concerns.

## 2. Route placement

Admin/platform APIs:

```text
routes/api/v1/admin.php
```

Recommended folders:

```text
app/Http/Controllers/Admin/Billing
app/Http/Controllers/Admin/Features
app/Http/Controllers/Admin/Subscriptions
app/Services/Admin/Billing
app/Services/Admin/FeatureAccess
```

Tenant-side API can expose read-only current package/features:

```text
GET /api/v1/my-subscription
GET /api/v1/my-features
```

## 3. Tables used

Landlord tables:

- `features`
- `packages`
- `package_features`
- `tenant_subscriptions`
- `tenant_feature_overrides`
- `subscription_invoices`
- `tenants`

## 4. Features

`features` defines platform capabilities.

Examples:

```text
pos
kitchen_display
inventory
payroll
finance
tax
reports
multi_branch
realtime
customer_qr_ordering
backup_restore
```

Fields:

```text
code
name
category
is_billable
status
```

Feature codes must be stable because code checks use them.

## 5. Packages

`packages` groups features into a sellable plan.

Examples:

```text
Starter
Professional
Enterprise
Custom
```

Billing intervals:

```text
monthly
yearly
one_time
```

Statuses:

```text
draft
active
retired
```

Do not delete old packages used by subscriptions. Retire them.

## 6. Package features

`package_features` says which features are included in a package.

It can define limits.

Examples:

```text
multi_branch enabled, limit 3 branches
users enabled, limit 20 users
reports enabled
payroll disabled
```

Limit period examples:

```text
month
year
lifetime
```

## 7. Tenant subscriptions

`tenant_subscriptions` is the active tenant subscription record.

Statuses:

```text
draft
trial
active
past_due
suspended
expired
cancelled
```

Only one current subscription is allowed per tenant:

```text
trial
active
past_due
suspended
```

Important fields:

```text
starts_at
trial_ends_at
current_period_starts_at
current_period_ends_at
auto_renew
price_snapshot
currency_code
```

Price snapshot is important because package price can change later.

## 8. Tenant feature overrides

`tenant_feature_overrides` allows admin to override one feature for one tenant.

Types:

```text
enable
disable
limit
```

Examples:

```text
Enable payroll for tenant trial for 30 days.
Disable QR ordering for tenant due to unpaid invoice.
Increase branch limit from 3 to 5 temporarily.
```

Only one open override per tenant/feature is allowed.

Use reason:

```text
reason = Trial extension approved by platform owner
```

## 9. Subscription invoices

`subscription_invoices` stores platform invoice to tenant.

Statuses:

```text
draft
issued
paid
void
overdue
```

Fields:

```text
billing period
subtotal
tax
discount
total
paid
issued/due/paid timestamps
```

This is platform billing, not tenant internal finance.

## 10. Feature access resolution

When backend checks a feature:

```text
1. Find tenant current subscription.
2. Load package features.
3. Apply active tenant feature overrides.
4. Return enabled/disabled and limits.
```

Example:

```text
Package disables payroll.
Override enables payroll until 2026-09-30.
Effective payroll = enabled until override ends.
```

Cache effective features per tenant, but invalidate cache when subscription/package/override changes.

## 11. Tenant status impact

Tenant status and subscription status should cooperate.

Example:

```text
subscription = past_due
tenant remains active but warning shown
```

Example:

```text
subscription = suspended
tenant.status = suspended
login may be limited to owner/billing screen
```

Do not delete tenant DB for billing issue.

## 12. API endpoints

Features:

```text
GET  /api/v1/admin/features
POST /api/v1/admin/features
PATCH /api/v1/admin/features/{feature_uuid}
```

Packages:

```text
GET  /api/v1/admin/packages
POST /api/v1/admin/packages
GET  /api/v1/admin/packages/{package_uuid}
PATCH /api/v1/admin/packages/{package_uuid}
POST /api/v1/admin/packages/{package_uuid}/features
```

Subscriptions:

```text
GET  /api/v1/admin/tenant-subscriptions
POST /api/v1/admin/tenants/{tenant_uuid}/subscriptions
PATCH /api/v1/admin/tenant-subscriptions/{subscription_uuid}
POST /api/v1/admin/tenant-subscriptions/{subscription_uuid}/cancel
POST /api/v1/admin/tenant-subscriptions/{subscription_uuid}/suspend
POST /api/v1/admin/tenant-subscriptions/{subscription_uuid}/reactivate
```

Overrides:

```text
GET  /api/v1/admin/tenant-feature-overrides
POST /api/v1/admin/tenants/{tenant_uuid}/feature-overrides
POST /api/v1/admin/tenant-feature-overrides/{override_uuid}/end
```

Invoices:

```text
GET  /api/v1/admin/subscription-invoices
POST /api/v1/admin/subscription-invoices
POST /api/v1/admin/subscription-invoices/{invoice_uuid}/issue
POST /api/v1/admin/subscription-invoices/{invoice_uuid}/mark-paid
POST /api/v1/admin/subscription-invoices/{invoice_uuid}/void
```

Tenant read-only:

```text
GET /api/v1/my-subscription
GET /api/v1/my-features
```

## 13. Complexity

Simple:

- features
- packages
- package features

Medium:

- create subscription
- issue invoice
- feature override

High:

- effective feature resolution
- subscription renewal
- tenant suspension/reactivation
- payment provider integration for platform invoices

Queue required:

- invoice generation
- subscription renewal checks
- overdue/suspension notifications
- feature cache invalidation broadcast if needed

## 14. Security

Only platform admin can manage:

```text
features
packages
subscriptions
overrides
subscription invoices
```

Tenant can only view its own subscription/features.

Changing package/override must be audited.

## 15. First release

Implement:

- features
- packages
- package features
- tenant subscription create/update
- tenant feature overrides
- subscription invoices
- effective feature resolver
- feature middleware/helper

Later:

- online platform payment collection
- automatic renewals
- dunning
- tax invoice integration
- usage-based billing
