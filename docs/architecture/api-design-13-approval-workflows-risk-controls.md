# API Design 13: Approval Workflows, Multi-Step Reviews, Risk Controls, and Approval Events

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how the system controls risky actions through configurable approval workflows.

Examples of actions that may require approval:

```text
refund
order void
receipt void
discount override
purchase order approval
stock adjustment
stock transfer
payroll approval
journal posting
supplier bill approval
expense claim approval
fiscal receipt cancellation
tax filing submission
```

The purpose is not to slow the restaurant down. The purpose is to protect money, stock, payroll, tax records and audit accountability.

## 2. What this design depends on

This module depends on:

- Design 04: tenant users, roles, permissions
- Design 06: orders, payments, refunds
- Design 08: purchasing, stock adjustments, transfers
- Design 09: finance and journals
- Design 10: payroll
- Design 11: tax/fiscalization
- Design 12: notifications

Approvals are tenant-local and branch-aware.

## 3. Route placement

Tenant approval APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Approvals
app/Http/Requests/Web/Approvals
app/Http/Resources/Web/Approvals
app/Services/Approvals
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `approval_workflows`
- `approval_workflow_steps`
- `approval_requests`
- `approval_request_steps`
- `approval_decisions`
- `approval_request_events`
- `notification_messages`
- `notification_recipients`
- `tenant_users`
- `permissions`

Source tables:

- `orders`
- `receipts`
- `refunds`
- `discount_applications`
- `purchase_orders`
- `stock_adjustments`
- `stock_transfers`
- `payroll_runs`
- `journal_entries`
- `supplier_bills`
- `expense_claims`
- `fiscal_receipts`
- `tax_filing_submissions`

## 5. Approval workflow

`approval_workflows` is the rule header.

It answers:

```text
Which workflow should control this type of request?
```

Important fields:

- company
- optional branch
- name
- workflow area
- minimum amount
- maximum amount
- active flag

Supported workflow areas:

```text
order_void
receipt_void
discount_override
refund
purchase_order
stock_adjustment
stock_transfer
payroll
journal_posting
supplier_bill
expense_claim
fiscal_cancellation
tax_filing
```

Example:

```text
Workflow: Refund 0 - 50,000
Area: refund
Minimum amount: 0
Maximum amount: 50,000
Branch: null
```

Another example:

```text
Workflow: Mikocheni High Value Refund
Area: refund
Minimum amount: 500,000
Branch: Mikocheni
```

Branch workflow overrides company workflow when branch-specific control is needed.

## 6. Approval workflow steps

`approval_workflow_steps` defines the configured steps.

Important fields:

- workflow
- permission required
- step number
- step name
- required approval count
- can self approve
- active flag

Example:

```text
Step 1: Branch Manager Review
Permission: refunds.approve.branch
Required approvals: 1
Self approval: false
```

Example:

```text
Step 2: Accountant Review
Permission: refunds.approve.accounting
Required approvals: 1
```

Example:

```text
Step 3: Owner Final Approval
Permission: refunds.approve.owner
Required approvals: 1
```

## 7. Approval request

`approval_requests` is the actual submitted request.

It links to exactly one source document.

Examples:

```text
refund_id
purchase_order_id
stock_adjustment_id
payroll_run_id
expense_claim_id
```

The schema enforces:

```text
one approval request = one source document
```

Important fields:

- workflow
- company
- branch
- requested by
- source document
- title
- amount
- currency
- status
- current step
- reason
- submitted/completed time

Statuses:

```text
pending
in_review
approved
rejected
cancelled
expired
```

## 8. Approval request steps

`approval_request_steps` stores a snapshot of the workflow steps at the time of request.

Why snapshot?

If admin changes the workflow tomorrow, old requests must still show the steps that applied when they were submitted.

Example:

```text
Request: Refund 200,000
Step 1 snapshot: Branch Manager Review
Step 2 snapshot: Accountant Review
Step 3 snapshot: Owner Final Approval
```

Statuses:

```text
pending
open
approved
rejected
skipped
cancelled
```

Only one step should normally be open at a time unless parallel approval is intentionally supported later.

## 9. Approval decisions

`approval_decisions` stores each approver's decision.

Fields:

- request
- request step
- decided by
- decision
- comments
- decided time

Decisions:

```text
approved
rejected
```

The schema prevents the same user approving the same step twice:

```text
unique(approval_request_step_id, decided_by_tenant_user_id)
```

## 10. Approval request events

`approval_request_events` stores timeline events.

Examples:

```text
submitted
step_opened
approved
rejected
cancelled
expired
commented
reassigned
```

This gives the audit timeline without storing huge JSON before/after snapshots.

Example:

```text
09:00 submitted by cashier
09:02 step opened: Branch Manager Review
09:05 approved by manager
09:06 step opened: Accountant Review
09:15 approved by accountant
09:20 approved by owner
09:20 request completed approved
```

## 11. Three-step refund example

Scenario:

```text
Customer requests refund of 300,000 TZS
Cashier cannot approve this amount
Refund requires 3 steps
```

Workflow:

```text
Area: refund
Amount range: 100,000 - 500,000
```

Steps:

```text
Step 1: Branch Manager Review
Step 2: Accountant Review
Step 3: Business Owner Final Approval
```

Flow:

```text
Cashier creates refund request
System creates refund record with status pending_approval
System creates approval_request
System snapshots 3 approval_request_steps
Step 1 becomes open
Branch manager receives notification
Branch manager approves
Step 1 becomes approved
Step 2 becomes open
Accountant receives notification
Accountant approves
Step 2 becomes approved
Step 3 becomes open
Owner receives notification
Owner approves
Approval request becomes approved
Refund service completes refund
Payment/refund accounting posting happens
Customer notification can be sent
```

If accountant rejects at step 2:

```text
Step 2 becomes rejected
Approval request becomes rejected
Refund remains rejected/cancelled
No money is returned
Reason is stored
Requester is notified
```

## 12. Approval workflow selection

When a request is submitted, the service should find the best matching workflow.

Selection inputs:

```text
company_id
branch_id
workflow_area
amount
is_active
```

Recommended selection order:

```text
1. branch-specific workflow matching area and amount
2. company-wide workflow matching area and amount
3. default no-approval rule if action can proceed without approval
4. block request if approval is mandatory but no workflow exists
```

Do not choose workflow by name from the frontend.

Frontend submits the action. Backend resolves workflow.

## 13. Self-approval rule

`can_self_approve` controls whether requester can approve their own request.

Default:

```text
false
```

Example:

Cashier requested refund. Cashier should not approve own refund.

Exception:

For low-risk admin workflows, self approval may be allowed, but it must be explicit.

## 14. Required approval count

`required_approval_count` allows more than one person to approve the same step.

Example:

```text
Step: Owner/Director Approval
Required approvals: 2
```

Meaning:

Two different users with the required permission must approve before the step passes.

This is useful for high-value actions.

## 15. Permission-based approvers

Workflow steps use `permission_id`, not hard-coded roles.

Good:

```text
Permission: refunds.approve.owner
```

Avoid:

```text
Role name: Owner
```

Reason:

Roles are dynamic. A tenant may rename roles or create custom roles. Permissions are the stable control point.

## 16. Source document status coordination

Approval request status and source document status must move together.

Example refund:

```text
refund.status = pending_approval
approval_request.status = in_review
```

When approved:

```text
approval_request.status = approved
refund.status = approved or processing
```

When rejected:

```text
approval_request.status = rejected
refund.status = rejected
```

Do not approve the workflow but forget to update the source document.

This must happen inside a transaction.

## 17. Active duplicate protection

The schema has partial unique indexes to prevent duplicate active approval requests for the same source.

Example:

```text
Only one pending/in_review approval request can exist for the same refund.
```

This prevents:

```text
Cashier submits same refund approval three times
Manager sees duplicate requests
```

## 18. Notifications

Approval workflow should trigger notifications through Design 12.

Events:

```text
approval.submitted
approval.step_opened
approval.approved
approval.rejected
approval.cancelled
approval.expired
```

Recipients:

```text
requester
users with step permission
branch managers
accountants
owners
```

Do not send notifications inside the approval transaction.

Use outbox/queue:

```text
Approval transaction commits
Worker sends notifications
```

## 19. Recommended endpoints

Workflow setup:

```text
GET  /api/v1/approval-workflows
POST /api/v1/approval-workflows
GET  /api/v1/approval-workflows/{approval_workflow_uuid}
PATCH /api/v1/approval-workflows/{approval_workflow_uuid}
POST /api/v1/approval-workflows/{approval_workflow_uuid}/steps
PATCH /api/v1/approval-workflows/{approval_workflow_uuid}/steps/{step_uuid}
```

Approval requests:

```text
GET  /api/v1/approval-requests
GET  /api/v1/approval-requests/{approval_request_uuid}
POST /api/v1/approval-requests/{approval_request_uuid}/approve
POST /api/v1/approval-requests/{approval_request_uuid}/reject
POST /api/v1/approval-requests/{approval_request_uuid}/cancel
POST /api/v1/approval-requests/{approval_request_uuid}/comment
```

Source-specific endpoints should create approval requests internally.

Example:

```text
POST /api/v1/refunds/{refund_uuid}/submit-approval
```

or better:

```text
POST /api/v1/payments/{payment_uuid}/refunds
```

If approval is required, backend creates approval automatically.

## 20. Request examples

Create workflow:

```json
{
  "branch_uuid": null,
  "name": "Refund above 100,000",
  "workflow_area": "refund",
  "minimum_amount": "100000.00",
  "maximum_amount": null,
  "is_active": true,
  "steps": [
    {
      "step_number": 1,
      "name": "Branch Manager Review",
      "permission": "refunds.approve.branch",
      "required_approval_count": 1,
      "can_self_approve": false
    },
    {
      "step_number": 2,
      "name": "Accountant Review",
      "permission": "refunds.approve.accounting",
      "required_approval_count": 1,
      "can_self_approve": false
    },
    {
      "step_number": 3,
      "name": "Owner Final Approval",
      "permission": "refunds.approve.owner",
      "required_approval_count": 1,
      "can_self_approve": false
    }
  ]
}
```

Approve request:

```json
{
  "comments": "Documents checked and amount is valid."
}
```

Reject request:

```json
{
  "comments": "Refund reason is not supported by receipt/payment evidence."
}
```

## 21. Complexity classification

Simple CRUD:

- approval workflow list
- workflow create/update
- workflow step create/update

Medium complexity:

- approval request list/details
- comment on approval request
- cancel pending request

High complexity:

- submit approval request
- workflow resolution
- approve current step
- reject current step
- required approval count handling
- source document status update
- notification dispatch
- expiry handling

Queue required:

- approval notifications
- approval expiry reminders
- escalation reminders
- report/export of approval history

No queue required:

- approving one current step
- rejecting one current step
- listing requests

## 22. Transactions and locking

Use transactions for:

- submitting approval request
- approving a step
- rejecting a step
- cancelling a request
- completing request and updating source document

Use row locking for:

- approval request
- current approval request step
- source document

Reason:

Two managers must not both complete the same step in a way that produces inconsistent state.

## 23. Query and performance rules

All list endpoints use simple pagination.

Frequent filters:

```text
approval_workflows.company_id/workflow_area/is_active
approval_workflows.branch_id/workflow_area/is_active
approval_requests.company_id/status/submitted_at
approval_requests.branch_id/status/submitted_at
approval_requests.requested_by_tenant_user_id/submitted_at
approval_request_steps.approval_request_id/status
approval_decisions.approval_request_id/decision/decided_at
approval_decisions.decided_by_tenant_user_id/decided_at
approval_request_events.approval_request_id/occurred_at
```

Do not scan all tenant approvals for each user.

For “my approvals”:

```text
find open steps
match permission_id against current user's permissions
filter branch scope
paginate
```

## 24. Audit logging

Approval tables are already an audit trail.

Use Spatie activity log only for critical configuration/control events:

- workflow created/changed/deactivated
- approval request force-cancelled
- approval request manually reassigned
- suspicious repeated rejection/override

Do not store huge before/after JSON for every approval decision.

## 25. Access control

Recommended permissions:

```text
approval_workflows.view
approval_workflows.manage

approval_requests.view
approval_requests.view_own
approval_requests.approve
approval_requests.reject
approval_requests.cancel
approval_requests.comment

refunds.approve.branch
refunds.approve.accounting
refunds.approve.owner

purchase_orders.approve
stock_adjustments.approve
stock_transfers.approve
payroll_runs.approve
journal_entries.approve_posting
expenses.approve
supplier_bills.approve
fiscal_receipts.approve_cancellation
tax_filing.approve_submission
```

Approvers must also pass branch scope checks.

## 26. First release recommendation

Implement first:

- approval workflow setup
- workflow steps
- approval request creation
- approve/reject current step
- multi-step sequential approvals
- required approval count
- self-approval prevention
- source status synchronization
- approval notifications through outbox
- approval request list/details

Later:

- delegation/reassignment
- escalation after time limit
- SLA timers
- parallel approval groups
- approval analytics
- mobile push approvals
