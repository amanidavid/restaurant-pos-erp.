# Restaurant POS ERP WBS Skeleton

Start date: 2026-08-25  
Initial 30-day target: 2026-09-23  
Delivery style: API-first, module-wave delivery  
Frontends: separate Tenant Frontend and Admin Frontend  
Backend: Laravel API, PostgreSQL, database-per-tenant multitenancy

## 1. Purpose

This document is a working WBS skeleton for the restaurant POS ERP. It is not a final project plan. It is designed to help identify:

- what must be built per module;
- which tasks are backend API, tenant frontend, admin frontend, or cross-cutting;
- which modules depend on other modules;
- which tasks must be completed first;
- what can realistically be targeted in the first 30 days;
- what should be deferred or implemented as a controlled skeleton.

The schema is large, so the WBS must be dependency-driven. The frontend should not guess API behavior. Backend API contracts should be completed first per module, then frontend follows.

## 2. Priority Definitions

| Priority | Meaning | First 30-day rule |
|---|---|---|
| P0 | Mandatory foundation. Nothing useful works without it. | Must complete. |
| P1 | Required for first usable restaurant operation. | Must complete or have a usable MVP. |
| P2 | Important business module but can be partial in first release. | Implement skeleton or core flow. |
| P3 | Advanced, optimization, provider-specific, or phase 2 feature. | Design now, implement later. |

## 3. Stream Definitions

| Stream | Meaning |
|---|---|
| Backend API | Laravel API, routes, services, requests, resources, policies, migrations, seeders, jobs, events, tests. |
| Tenant Frontend | Business-facing app used by restaurant owners, managers, cashiers, waiters, kitchen, storekeepers, HR, accountants, customers. |
| Admin Frontend | Platform/landlord app used by ELTECH platform owner/support/admin. |
| Cross-cutting | Architecture, DevOps, QA, security, performance, documentation, deployment. |

## 4. Delivery Method

Use API-first wave delivery.

Backend API for a module should reach “API Contract Ready” before frontend begins that module.

API Contract Ready means:

- routes exist;
- request validation exists;
- response resources exist;
- permission names are defined;
- pagination/filter/sort contract is defined;
- example JSON request/response exists;
- basic happy-path endpoint works;
- standard error format is known;
- tenant/branch authorization behavior is known.

The frontend may start when API contract is ready. It does not need to wait for every backend module in the ERP, but it should not start a module whose API behavior is unstable.

## 5. Status Definitions

| Status | Meaning |
|---|---|
| Not started | Task has not begun. |
| API planned | API behavior agreed but not implemented. |
| API in progress | API implementation started. |
| API contract ready | Frontend can safely start. |
| API complete | API feature is implemented and tested. |
| Frontend ready to start | Required API contract exists. |
| Frontend in progress | UI implementation started. |
| QA ready | Full flow can be tested. |
| Done | Accepted by backend, frontend, and QA. |
| Deferred | Not in first 30-day target. |

## 6. 30-Day Wave Plan

| Wave | Dates | Backend API Focus | Tenant Frontend Focus | Admin Frontend Focus | Goal |
|---|---|---|---|---|---|
| Wave 1 | 2026-08-25 to 2026-08-31 | Auth, tenancy, permissions, tenant registration/provisioning, company/branch/user foundation | UI shell only, login shell, branch selector shell | Admin login shell, tenant onboarding shell | Stable access and tenancy foundation |
| Wave 2 | 2026-09-01 to 2026-09-07 | Catalog, menu, inventory master, POS setup API contracts | Auth, company setup, branch setup, users/roles, menu setup | Tenant onboarding, tenant status, packages/features | Business setup can be completed |
| Wave 3 | 2026-09-08 to 2026-09-14 | Orders, kitchen, payments, receipts, print jobs, stock operations | POS, table view, kitchen display, payments, receipts | Provider/device/realtime monitoring basics | Restaurant operation flow works |
| Wave 4 | 2026-09-15 to 2026-09-23 | Finance/cash basics, payroll skeleton, reporting, audit, backup, support, hardening | Cash close, stock operations, basic reports, UAT fixes | Backup, support, report scheduling, health views, UAT fixes | Release candidate |

## 7. Master Dependency Map

```text
Project foundation
  -> Auth and identity
  -> Tenant provisioning
  -> Access control
  -> Company and branch setup
  -> Staff/user setup
  -> Devices and print setup
  -> Catalog/menu setup
  -> Inventory master setup
  -> POS ordering
  -> Kitchen/realtime
  -> Payments/receipts/printing
  -> Stock operations
  -> Finance/cash control
  -> Payroll
  -> Tax/fiscalization
  -> Reports/dashboard
  -> Audit/backup/retention/support
```

## 8. Module WBS Skeleton

### 8.1 Project Foundation

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 1.1 | Laravel project structure, module folder standards, API versioning conventions | Backend API | P0 | None | Aug 25 | Standard structure ready |
| 1.2 | Global API response format, exception handling, validation error format | Backend API | P0 | 1.1 | Aug 25-Aug 26 | Consistent API responses |
| 1.3 | Database connection strategy: landlord + tenant connection switching | Backend API | P0 | 1.1 | Aug 25-Aug 26 | Tenant DB switching works |
| 1.4 | Queue, cache, jobs, failed jobs, scheduler foundation on landlord | Backend API | P0 | 1.1 | Aug 26 | Queue foundation ready |
| 1.5 | Base seeders: core roles, permissions, packages, stock movement types, number sequences | Backend API | P0 | 1.1 | Aug 26-Aug 27 | Initial seed data ready |
| 1.6 | Testing foundation: feature tests, tenant test helper, API auth helper | Backend API | P0 | 1.1 | Aug 27-Aug 28 | Test pattern ready |
| 1.7 | Tenant frontend application shell | Tenant Frontend | P0 | 1.2 | Aug 29-Sep 1 | Layout, routing, auth wrapper shell |
| 1.8 | Admin frontend application shell | Admin Frontend | P0 | 1.2 | Aug 29-Sep 1 | Admin layout and routing shell |

### 8.2 Authentication, Identity, Device Session

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 2.1 | Tenant user login API by phone/email with JWT | Backend API | P0 | 1.3 | Aug 25-Aug 27 | Tenant login works |
| 2.2 | Admin/platform user login API | Backend API | P0 | 1.3 | Aug 25-Aug 27 | Admin login works |
| 2.3 | JWT refresh/logout/token version/session invalidation | Backend API | P0 | 2.1 | Aug 27 | Secure token lifecycle |
| 2.4 | Tenant UUID header verification against authenticated account | Backend API | P0 | 2.1 | Aug 27-Aug 28 | Tenant header cannot be spoofed |
| 2.5 | Branch selection and current access payload API | Backend API | P0 | 2.4 | Aug 28-Aug 29 | User sees allowed branches |
| 2.6 | Single active device/session rule with force logout flow | Backend API | P0 | 2.1 | Aug 29-Aug 30 | Second login controlled |
| 2.7 | Tenant login, logout, branch selection UI | Tenant Frontend | P0 | 2.1-2.5 API contract ready | Sep 1-Sep 3 | Tenant can sign in and select branch |
| 2.8 | Admin login/logout UI | Admin Frontend | P0 | 2.2 API contract ready | Sep 1-Sep 2 | Admin can sign in |

### 8.3 Landlord Tenant Onboarding and Provisioning

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 3.1 | Tenant registration API with minimum details | Backend API | P0 | 2.2 | Aug 28-Aug 29 | Tenant account can be requested |
| 3.2 | Tenant database provisioning API/job | Backend API | P0 | 3.1, 1.4 | Aug 29-Aug 31 | Tenant DB can be generated |
| 3.3 | Provisioning run/step status API | Backend API | P0 | 3.2 | Aug 30-Aug 31 | Admin can see provisioning progress |
| 3.4 | Tenant domains and default hosted-domain logic | Backend API | P1 | 3.1 | Aug 31-Sep 1 | Default domain stored; custom domain prepared |
| 3.5 | Tenant package/subscription/feature assignment API | Backend API | P1 | 3.1 | Sep 1-Sep 2 | Tenant feature access controlled |
| 3.6 | Registration/start setup UI | Tenant Frontend | P0 | 3.1 API contract ready | Sep 3-Sep 4 | Tenant can submit registration |
| 3.7 | Tenant onboarding dashboard | Admin Frontend | P0 | 3.1-3.3 API contract ready | Sep 3-Sep 5 | Admin can manage tenant onboarding |
| 3.8 | Package/features/subscription UI | Admin Frontend | P1 | 3.5 API contract ready | Sep 5-Sep 7 | Admin can assign package/features |

### 8.4 Access Control and User Administration

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 4.1 | Tenant permission catalog seed and protected default roles | Backend API | P0 | 1.5 | Aug 29-Aug 31 | Default roles cannot be deleted/renamed |
| 4.2 | Tenant user CRUD API with branch scope and status | Backend API | P0 | 2.4, 4.1 | Aug 31-Sep 2 | Tenant users managed |
| 4.3 | Role/permission assignment API with Spatie teams per branch | Backend API | P0 | 4.1, 4.2 | Sep 1-Sep 3 | Branch-scoped permissions work |
| 4.4 | Platform admin role/permission API | Backend API | P0 | 2.2 | Sep 1-Sep 2 | Admin access controlled |
| 4.5 | Tenant users/roles/permissions UI | Tenant Frontend | P0 | 4.2-4.3 API contract ready | Sep 5-Sep 8 | Business owner manages users |
| 4.6 | Platform admin users/roles UI | Admin Frontend | P1 | 4.4 API contract ready | Sep 5-Sep 7 | Platform owner manages admins |

### 8.5 Company, Branch, Staff Foundation

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 5.1 | Company profile API | Backend API | P0 | 3.2 | Aug 31-Sep 1 | Company profile saved |
| 5.2 | Branch CRUD API with contacts, business dates, default settings | Backend API | P0 | 5.1 | Sep 1-Sep 3 | Branch setup works |
| 5.3 | Department, position, employment type APIs | Backend API | P1 | 5.1 | Sep 2-Sep 4 | Staff structure ready |
| 5.4 | Staff profile extension API using tenant_users | Backend API | P1 | 4.2, 5.3 | Sep 3-Sep 5 | Staff details managed without employee duplication |
| 5.5 | Company and branch setup UI | Tenant Frontend | P0 | 5.1-5.2 API contract ready | Sep 5-Sep 8 | Tenant completes business setup |
| 5.6 | Staff/position setup UI | Tenant Frontend | P1 | 5.3-5.4 API contract ready | Sep 8-Sep 10 | Staff structure configured |
| 5.7 | Admin tenant profile read-only view | Admin Frontend | P1 | 5.1-5.2 API contract ready | Sep 7-Sep 9 | Admin sees tenant status, not private finance details |

### 8.6 Devices, Printing, Receipt Infrastructure

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 6.1 | Branch device registration and capability API | Backend API | P1 | 5.2 | Sep 4-Sep 6 | Branch devices registered |
| 6.2 | Print devices/templates/jobs API | Backend API | P1 | 5.2 | Sep 6-Sep 8 | Thermal print workflow prepared |
| 6.3 | Device pairing/session/security API | Backend API | P1 | 6.1, 2.6 | Sep 7-Sep 9 | Devices controlled securely |
| 6.4 | Tenant device/printer setup UI | Tenant Frontend | P1 | 6.1-6.3 API contract ready | Sep 12-Sep 14 | Branch configures printers/devices |
| 6.5 | Admin device/realtime health view | Admin Frontend | P2 | 6.1 API contract ready | Sep 15-Sep 17 | Platform sees health, not private content |

### 8.7 Catalog, Menu, Recipes, Modifiers

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 7.1 | Units of measure and tax codes API | Backend API | P1 | 5.1 | Sep 1-Sep 2 | Units and tax setup ready |
| 7.2 | Menu category and menu item API | Backend API | P1 | 7.1 | Sep 2-Sep 4 | Sellable items managed |
| 7.3 | Branch menu item price/availability/kitchen routing API | Backend API | P1 | 7.2, 5.2 | Sep 4-Sep 5 | Branch menu configured |
| 7.4 | Modifiers/options/combo API | Backend API | P1 | 7.2 | Sep 5-Sep 6 | Item customization ready |
| 7.5 | Ingredient and recipe API | Backend API | P1 | 7.1, 7.2 | Sep 5-Sep 7 | Recipe links menu item to ingredients |
| 7.6 | Menu/category/item setup UI | Tenant Frontend | P1 | 7.2 API contract ready | Sep 8-Sep 11 | Tenant manages menu |
| 7.7 | Branch pricing/availability/kitchen route UI | Tenant Frontend | P1 | 7.3 API contract ready | Sep 11-Sep 13 | Branch menu ready for POS |
| 7.8 | Recipe/modifier setup UI | Tenant Frontend | P1 | 7.4-7.5 API contract ready | Sep 12-Sep 14 | Recipes/modifiers configured |

### 8.8 Inventory Master and Suppliers

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 8.1 | Warehouses, locations, stock units API | Backend API | P1 | 5.2, 7.1 | Sep 4-Sep 6 | Stock locations ready |
| 8.2 | Supplier categories, suppliers, contacts API | Backend API | P1 | 5.1 | Sep 5-Sep 6 | Suppliers managed |
| 8.3 | Stock item settings API | Backend API | P1 | 7.5, 8.1, 8.2 | Sep 6-Sep 7 | Reorder/perishable/batch settings ready |
| 8.4 | Warehouse/location setup UI | Tenant Frontend | P1 | 8.1 API contract ready | Sep 12-Sep 14 | Stock stores configured |
| 8.5 | Supplier setup UI | Tenant Frontend | P1 | 8.2 API contract ready | Sep 12-Sep 14 | Suppliers configured |
| 8.6 | Stock item settings UI | Tenant Frontend | P1 | 8.3 API contract ready | Sep 14-Sep 15 | Ingredient stock settings configured |

### 8.9 POS Ordering, Tables, Service Flow

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 9.1 | Dining areas/tables/counters/service types/sales channels API | Backend API | P1 | 5.2 | Sep 7-Sep 8 | POS location setup ready |
| 9.2 | Order create/update/cancel/submit API | Backend API | P1 | 7.3, 9.1 | Sep 8-Sep 10 | Order can be created |
| 9.3 | Order item/modifier API | Backend API | P1 | 9.2, 7.4 | Sep 9-Sep 10 | Order items and modifiers work |
| 9.4 | Discount/void approval trigger API | Backend API | P1 | 9.2, approval skeleton | Sep 10-Sep 11 | Controlled override flow |
| 9.5 | Order status log and business date/shift link API | Backend API | P1 | 9.2, cash/shift foundation | Sep 10-Sep 11 | Order traceability ready |
| 9.6 | POS table/floor screen | Tenant Frontend | P1 | 9.1 API contract ready | Sep 14-Sep 16 | Cashier sees tables/counters |
| 9.7 | POS order entry screen | Tenant Frontend | P1 | 9.2-9.3 API contract ready | Sep 16-Sep 19 | Cashier creates orders |
| 9.8 | Discount/void UI with approval state | Tenant Frontend | P1 | 9.4 API contract ready | Sep 19-Sep 20 | Overrides controlled |

### 8.10 Kitchen and Realtime

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 10.1 | Kitchen section and ticket API | Backend API | P1 | 7.3, 9.2 | Sep 10-Sep 12 | Kitchen tickets generated |
| 10.2 | Realtime outbox API/job for order/kitchen events | Backend API | P1 | 1.4, 10.1 | Sep 11-Sep 13 | Events broadcast outside transactions |
| 10.3 | Reverb channel policies and auth endpoint | Backend API | P1 | 2.5, 10.2 | Sep 12-Sep 13 | Branch-scoped channels secure |
| 10.4 | Kitchen display screen | Tenant Frontend | P1 | 10.1-10.3 API contract ready | Sep 18-Sep 21 | Kitchen sees live ticket status |
| 10.5 | Admin realtime worker health | Admin Frontend | P2 | landlord realtime monitor API | Sep 20-Sep 22 | Admin monitors delivery health |

### 8.11 Payments, Providers, Receipts, Printing

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 11.1 | Payment methods API | Backend API | P1 | 5.1 | Sep 10 | Payment methods configured |
| 11.2 | Integration providers/accounts/capabilities API | Backend API | P1 | 5.1 | Sep 10-Sep 12 | Tenant provider settings prepared |
| 11.3 | Payment attempts/payments/allocation API | Backend API | P1 | 9.2, 11.1 | Sep 12-Sep 14 | Order can be paid |
| 11.4 | Receipt generation and reprint API | Backend API | P1 | 11.3, 6.2 | Sep 13-Sep 14 | Receipt generated |
| 11.5 | Webhook receipt/idempotency skeleton | Backend API | P1 | 11.2 | Sep 14-Sep 15 | Webhooks stored and deduplicated |
| 11.6 | Refund API with approval hook | Backend API | P1 | 11.3, approval skeleton | Sep 14-Sep 16 | Refund controlled |
| 11.7 | Payment screen | Tenant Frontend | P1 | 11.3 API contract ready | Sep 19-Sep 21 | Cashier records payment |
| 11.8 | Receipt preview/reprint UI | Tenant Frontend | P1 | 11.4 API contract ready | Sep 21-Sep 22 | Receipt handled |
| 11.9 | Provider config UI | Tenant Frontend | P2 | 11.2 API contract ready | Sep 21-Sep 23 | Tenant configures providers |
| 11.10 | Admin provider capability/status view | Admin Frontend | P2 | 11.2 API contract ready | Sep 20-Sep 23 | Admin sees integration status |

### 8.12 Stock Operations

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 12.1 | Purchase order API | Backend API | P1 | 8.2, 8.3 | Sep 14-Sep 15 | PO created |
| 12.2 | Goods receipt and stock batch API | Backend API | P1 | 12.1 | Sep 15-Sep 16 | Stock received |
| 12.3 | Stock movement posting service | Backend API | P1 | 12.2, stock movement types | Sep 15-Sep 17 | Immutable movement ledger works |
| 12.4 | Stock adjustment API | Backend API | P1 | 12.3 | Sep 17 | Adjustment works with approval |
| 12.5 | Stock transfer API | Backend API | P1 | 12.3 | Sep 17-Sep 18 | Branch/warehouse transfer works |
| 12.6 | Stock count and waste API | Backend API | P2 | 12.3 | Sep 18-Sep 19 | Count and waste captured |
| 12.7 | Purchase order/goods receipt UI | Tenant Frontend | P1 | 12.1-12.2 API contract ready | Sep 20-Sep 22 | Storekeeper receives stock |
| 12.8 | Transfer/adjust/count UI | Tenant Frontend | P2 | 12.4-12.6 API contract ready | Sep 22-Sep 23 | Basic stock operations available |

### 8.13 Finance, Cash Control, Petty Cash

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 13.1 | Account class/category/subcategory/chart account API | Backend API | P1 | 5.1 | Sep 15-Sep 17 | COA configured |
| 13.2 | Financial years/periods/journal source API | Backend API | P1 | 13.1 | Sep 17 | Accounting periods ready |
| 13.3 | Journal entry and line API/service | Backend API | P1 | 13.1, 13.2 | Sep 17-Sep 19 | Double-entry posting skeleton works |
| 13.4 | Cash account API including petty cash type | Backend API | P1 | 13.1, 5.2 | Sep 18 | Cash and petty cash accounts ready |
| 13.5 | Cash movement API linked to payments/refunds/expenses | Backend API | P1 | 11.3, 13.4 | Sep 18-Sep 20 | Cash movement traceable |
| 13.6 | Business day and shift cash reconciliation API | Backend API | P1 | 9.5, 13.5 | Sep 19-Sep 21 | Cash close works |
| 13.7 | Expense claim/payment API | Backend API | P2 | 13.4, approval skeleton | Sep 20-Sep 21 | Expense payments recorded |
| 13.8 | Supplier bill/payment API | Backend API | P2 | 12.1, 13.4 | Sep 21-Sep 22 | Supplier liabilities tracked |
| 13.9 | Cash close UI | Tenant Frontend | P1 | 13.6 API contract ready | Sep 22-Sep 23 | Manager closes shift/day |
| 13.10 | Basic finance setup UI | Tenant Frontend | P2 | 13.1-13.4 API contract ready | Sep 22-Sep 23 | COA/cash accounts managed |
| 13.11 | Admin finance status summary | Admin Frontend | P3 | Aggregated/limited APIs | Deferred | Admin sees health, not tenant finance details |

### 8.14 Payroll and HR Processing

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 14.1 | Attendance/shift assignment API | Backend API | P2 | 5.4 | Sep 15-Sep 17 | Attendance captured |
| 14.2 | Leave types/requests API | Backend API | P2 | 5.4, approval skeleton | Sep 17-Sep 18 | Leave workflow skeleton |
| 14.3 | Payroll periods, earning/deduction types API | Backend API | P2 | 5.4 | Sep 18-Sep 19 | Payroll configuration ready |
| 14.4 | Statutory rules/brackets/profile API | Backend API | P2 | 14.3 | Sep 19-Sep 21 | Statutory rules captured |
| 14.5 | Employee loans/repayments API | Backend API | P2 | 14.3 | Sep 21 | Loans tracked |
| 14.6 | Payroll run/payslip skeleton API | Backend API | P2 | 14.3, 14.4 | Sep 21-Sep 23 | Payroll run can be prepared |
| 14.7 | Payroll UI skeleton | Tenant Frontend | P2 | 14.3, 14.6 API contract ready | Deferred or Sep 23+ | Payroll screens begin |

### 8.15 Tax Compliance and Fiscalization

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 15.1 | Tax authorities/registrations API | Backend API | P2 | 5.1, 5.2 | Sep 18-Sep 20 | Tax identity captured |
| 15.2 | Tax code accounts and tax transaction API | Backend API | P2 | 7.1, 13.1 | Sep 20-Sep 22 | Tax postings traceable |
| 15.3 | Fiscal device/receipt API skeleton | Backend API | P2 | 6.2, 11.4 | Sep 22-Sep 23 | Fiscalization ready for integration |
| 15.4 | Tax return/filing submission skeleton | Backend API | P3 | 15.2 | Deferred | Tax return prepared |
| 15.5 | Tenant tax setup UI | Tenant Frontend | P3 | 15.1 API contract ready | Deferred | Tenant configures tax registrations |

### 8.16 Approval Workflow

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 16.1 | Approval workflow and step API | Backend API | P1 | 4.1, 5.1 | Sep 10-Sep 12 | Approval rules configured |
| 16.2 | Approval request API for refund/discount/stock/payroll/finance | Backend API | P1 | target module source exists | Sep 12-Sep 16 | Request can be opened |
| 16.3 | Approval decision and event API | Backend API | P1 | 16.2 | Sep 13-Sep 16 | Multi-step approval works |
| 16.4 | Approval inbox UI | Tenant Frontend | P1 | 16.2-16.3 API contract ready | Sep 20-Sep 22 | Manager can approve/reject |
| 16.5 | Approval configuration UI | Tenant Frontend | P2 | 16.1 API contract ready | Sep 22-Sep 23 | Owner configures workflow |

### 8.17 Notifications and Communication

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 17.1 | Notification templates/rules API | Backend API | P2 | 5.1, 4.1 | Sep 18-Sep 20 | Messaging rules configured |
| 17.2 | Notification message/recipient/delivery API | Backend API | P2 | 17.1, integration accounts | Sep 20-Sep 22 | Messages queued and sent |
| 17.3 | Delivery callback event API | Backend API | P2 | 17.2 | Sep 22-Sep 23 | Provider callbacks captured |
| 17.4 | Notification preferences API | Backend API | P2 | 17.2 | Sep 22 | Preferences controlled |
| 17.5 | Tenant notification inbox UI | Tenant Frontend | P2 | 17.2 API contract ready | Deferred or Sep 23+ | Users see notifications |
| 17.6 | Admin communication provider status UI | Admin Frontend | P3 | 17.2 | Deferred | Admin sees provider health |

### 8.18 Documents, Media, Imports

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 18.1 | Media library storage configuration | Backend API | P1 | filesystem/storage config | Sep 5-Sep 7 | Uploads work |
| 18.2 | Document category/requirement API | Backend API | P2 | 5.1 | Sep 17-Sep 18 | Document types configured |
| 18.3 | Business document/version/review/event API | Backend API | P2 | 18.1, 18.2 | Sep 18-Sep 20 | Documents versioned |
| 18.4 | Data import batch/error API | Backend API | P2 | 18.1 | Sep 20-Sep 21 | Excel import tracked |
| 18.5 | Menu/logo/document upload UI | Tenant Frontend | P1 | 18.1 API contract ready | Sep 12-Sep 16 | Logos/menu images upload |
| 18.6 | Import UI | Tenant Frontend | P2 | 18.4 API contract ready | Deferred or Sep 23+ | Excel import starts |

### 8.19 Reporting, Dashboards, Scheduled Reports

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 19.1 | Tenant report definitions and run API | Backend API | P1 | POS/payment/stock data | Sep 19-Sep 21 | Basic reports run |
| 19.2 | Dashboard definitions/widgets/KPI snapshot API | Backend API | P2 | report definitions | Sep 21-Sep 22 | Dashboard configured |
| 19.3 | Landlord report schedule template/assignment API | Backend API | P1 | tenant report definitions | Sep 20-Sep 22 | Admin schedules report without scanning all tenant DBs every minute |
| 19.4 | Report delivery log API | Backend API | P2 | 19.3, notifications | Sep 22-Sep 23 | Scheduled delivery tracked |
| 19.5 | Tenant dashboard and report UI | Tenant Frontend | P1 | 19.1 API contract ready | Sep 22-Sep 23 | Tenant sees daily figures |
| 19.6 | Admin scheduled report UI | Admin Frontend | P1 | 19.3 API contract ready | Sep 22-Sep 23 | Admin controls schedules |

### 8.20 Audit, Realtime Outbox, Retention, Backup

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 20.1 | Targeted audit policy/event API | Backend API | P1 | core modules | Sep 18-Sep 20 | Critical actions logged only |
| 20.2 | Realtime outbox worker and publish attempts | Backend API | P1 | Reverb, queue | Sep 11-Sep 14 | Reliable broadcasting |
| 20.3 | Tenant retention cleanup run/table/archive API | Backend API | P2 | audit/events/reports | Sep 21-Sep 23 | Heavy data can be archived |
| 20.4 | Landlord retention policy/dispatch API | Backend API | P2 | 20.3 | Sep 21-Sep 23 | Cleanup scheduled centrally |
| 20.5 | Backup storage/policy/run/artifact API | Backend API | P1 | tenant registry, queue | Sep 18-Sep 22 | Per-tenant backup tracked |
| 20.6 | Restore request/run API | Backend API | P2 | backup artifacts | Deferred or Sep 23+ | Restore controlled |
| 20.7 | Admin backup dashboard | Admin Frontend | P1 | 20.5 API contract ready | Sep 22-Sep 23 | Admin sees tenant backup status |
| 20.8 | Admin audit/retention dashboard | Admin Frontend | P2 | 20.1, 20.4 | Deferred or Sep 23+ | Admin monitors retention/audit |

### 8.21 Support Ticketing

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 21.1 | Support ticket category/ticket/message API | Backend API | P1 | tenant identity, admin identity | Sep 18-Sep 20 | Tenant can raise ticket |
| 21.2 | Support ticket attachment/event API | Backend API | P2 | 21.1, media | Sep 20-Sep 21 | Ticket history and attachments work |
| 21.3 | Tenant support ticket UI | Tenant Frontend | P1 | 21.1 API contract ready | Sep 22-Sep 23 | Tenant sends issue to admin |
| 21.4 | Admin support center UI | Admin Frontend | P1 | 21.1 API contract ready | Sep 22-Sep 23 | Admin responds to tickets |

### 8.22 QA, UAT, Performance, Release

| WBS | Task | Stream | Priority | Depends on | Target window | Deliverable |
|---|---|---|---|---|---|---|
| 22.1 | API feature tests for P0/P1 modules | Backend API | P0 | Each P0/P1 API | Continuous | Core flow tested |
| 22.2 | Tenant full-flow UAT: setup to paid order | Cross-cutting | P0 | POS/payment/receipt | Sep 21-Sep 23 | Demo order paid and receipted |
| 22.3 | Admin UAT: tenant onboarding, backup, support | Cross-cutting | P0 | landlord APIs | Sep 21-Sep 23 | Admin can operate platform |
| 22.4 | Performance review: indexes, pagination, N+1, queues | Cross-cutting | P0 | API implementation | Continuous | No obvious scaling mistakes |
| 22.5 | Security review: tenant isolation, branch scope, permissions | Cross-cutting | P0 | Auth/access APIs | Continuous | No privilege escalation |
| 22.6 | Deployment checklist and environment documentation | Cross-cutting | P0 | app config | Sep 21-Sep 23 | Release candidate ready |

## 9. First 30-Day MVP Acceptance

The first 30-day target should be accepted only if the following work in a demo environment:

- Admin can log in.
- Tenant can register or be created.
- Tenant database can be provisioned.
- Tenant user can log in with phone/email.
- Tenant UUID header and authentication are verified.
- Branch selection and branch-scoped permissions work.
- Company and branch setup work.
- Users, roles, and protected default roles work.
- Menu categories/items/branch prices are configured.
- Ingredients and basic recipes are configured.
- Cashier can create an order.
- Kitchen can receive ticket updates.
- Payment can be recorded.
- Receipt and print job can be generated.
- Supplier and stock receiving basic flow works.
- Cash account and shift/day close skeleton works.
- Basic daily report/dashboard works.
- Backup policy/run visibility exists.
- Support ticket can be raised by tenant and seen by admin.
- Critical audit events are recorded selectively.
- Realtime events are broadcast through outbox/worker, not inside transaction.

## 10. Deferred or Skeleton-Only in First 30 Days

These should be designed but may remain partial:

- full statutory payroll calculations;
- complete fiscal authority integration;
- full tax filing submission to external providers;
- full supplier AP lifecycle with advanced reconciliation;
- advanced BI dashboards;
- advanced archive storage and restore drills;
- full customer loyalty/reservation self-service;
- advanced offline protocol;
- all payment providers and aggregators;
- full document compliance workflows.

## 11. Non-Negotiable Engineering Rules

- Do not scan all tenant databases every minute.
- Do not log everything.
- Do not duplicate report data unnecessarily.
- Do not broadcast inside database transactions.
- Do not store huge before/after JSON for every update.
- Use landlord registries for cross-tenant scheduling.
- Use queues for heavy work.
- Use indexes on every frequent filter.
- Archive heavy logs/events.
- Backup per tenant.
- Use pagination for lists likely to exceed 15 records.
- Avoid N+1 queries.
- Use tenant UUID/header plus authenticated membership validation.
- Use branch-scoped permission checks where branch access matters.
- Keep models only under `app/Models/Landlord` and `app/Models/Tenant`.

## 12. Suggested WBS Tracking Columns

When converting this skeleton into a project tracker, use these columns:

| Column | Purpose |
|---|---|
| WBS code | Stable task number. |
| Module | Auth, POS, inventory, etc. |
| Task name | Short task title. |
| Stream | Backend API, Tenant Frontend, Admin Frontend, Cross-cutting. |
| Priority | P0/P1/P2/P3. |
| Dependency WBS | Which tasks must finish first. |
| API contract date | Date API contract should be ready. |
| Start date | Planned start. |
| End date | Planned finish. |
| Status | Not started, API contract ready, Done, etc. |
| Deliverable | What can be demonstrated. |
| Acceptance criteria | How to prove it works. |
| Risk | Complexity/blocker note. |
| Owner | Person/team responsible. |

