# API Design 12: Notifications, Communications, Templates, Provider Routing, and Delivery Tracking

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains how the system sends and tracks notifications through:

```text
in-app
SMS
email
WhatsApp
push
```

Main flow:

```text
Business event happens
Notification rule is matched
Template is rendered
Message is created
Recipients are resolved
Deliveries are queued
Provider sends message
Delivery callback updates status
User/customer sees notification result
```

This module must be queue-based and provider-agnostic.

## 2. What this design depends on

This module depends on:

- Design 04: tenant users, customers, permissions
- Design 06: POS orders, payments, refunds
- Design 07: reservations
- Design 08: stock/purchasing alerts
- Design 10: payroll alerts
- Design 11: tax/fiscalization alerts
- Integration provider/account tables
- Settings tables

Notifications are tenant-local. Provider credentials are tenant-specific and can be branch-specific when needed.

## 3. Route placement

Tenant notification APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Notifications
app/Http/Controllers/Web/Communications
app/Http/Requests/Web/Notifications
app/Http/Resources/Web/Notifications
app/Services/Notifications
app/Services/Communications
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `notification_templates`
- `notification_rules`
- `notification_messages`
- `notification_recipients`
- `notification_deliveries`
- `notification_delivery_events`
- `notification_preferences`
- `integration_providers`
- `provider_capabilities`
- `integration_accounts`
- `settings`
- `branch_settings`
- `tenant_user_preferences`
- `webhook_receipts`

Referenced source tables:

- `approval_requests`
- `orders`
- `reservations`
- `payments`
- `refunds`
- `purchase_orders`
- `stock_batches`
- `payroll_runs`
- `fiscal_receipts`
- `tax_filing_submissions`

## 5. Notification template

`notification_templates` stores reusable message content.

Template scope:

```text
company-wide
branch-specific override
```

Channels:

```text
in_app
sms
email
whatsapp
push
```

Example:

```text
event_type = order_ready
channel = sms
body_template = Your order {{ order_number }} is ready for pickup.
```

Branch override example:

```text
Company template says:
Your order is ready.

Branch-specific template says:
Your order at Mikocheni branch is ready.
```

Use company template by default. Use branch template only when the branch needs different wording.

## 6. Notification rule

`notification_rules` decides who receives a notification and through which channel.

Recipient types:

```text
requested_user
assigned_user
customer
branch_managers
permission_holders
accountants
owners
custom
```

Example:

```text
Event: refund_requested
Recipient: branch_managers
Channel: in_app
```

Another example:

```text
Event: order_ready
Recipient: customer
Channel: sms
```

Rules can include conditions.

Example:

```json
{
  "amount_greater_than": "50000.00",
  "branch_uuid": "uuid"
}
```

This allows:

```text
Only alert manager when refund amount is above threshold.
```

## 7. Notification message

`notification_messages` is the message header/body created from an event.

It stores:

- company
- optional branch
- template
- source reference
- event type
- title
- body
- priority
- status
- queued/processed timestamps
- payload

Statuses:

```text
queued
processing
sent
partially_sent
failed
cancelled
```

Priorities:

```text
low
normal
high
urgent
```

Important correction:

System notifications may not have order/payment/refund source.

That is why `system_reference_key` exists.

Examples:

```text
password_expiry_warning
backup_completed
tenant_storage_limit_warning
payroll_job_finished
```

So a notification can be linked to a business source or to a system reference key.

## 8. Notification recipient

`notification_recipients` stores who should receive the message.

Recipient can be:

```text
tenant_user
customer
external email/phone snapshot
```

Snapshots are important.

Example:

If a customer changes phone number later, old delivery record must still show where the message was sent.

Statuses:

```text
pending
sent
failed
read
cancelled
```

## 9. Notification delivery

`notification_deliveries` stores actual channel delivery attempts.

Example:

```text
Message: Order ready
Recipient: Customer A
Channel: SMS
Destination: +255712345678
Provider: Beem/Africa's Talking/custom SMS gateway
Status: delivered
```

One notification message can have many recipients.

One recipient can have many deliveries if multiple channels are enabled.

Example:

```text
Manager receives refund alert:
  in_app
  email
  push
```

## 10. Notification delivery events

`notification_delivery_events` stores provider/status callbacks over time.

Examples:

```text
queued
accepted
delivered
failed
bounced
read
callback_received
```

This creates a timeline.

Example:

```text
12:00 queued
12:00 accepted by provider
12:01 delivered
```

If failed:

```text
12:00 accepted
12:02 failed: invalid phone number
```

## 11. Notification preferences

`notification_preferences` stores user/customer opt-in or opt-out.

Example:

```text
Customer disables marketing WhatsApp
Customer keeps order SMS enabled
```

Another example:

```text
Manager disables low-priority email alerts
Manager keeps urgent push alerts
```

Compliance note:

Do not send marketing messages to customers who opted out.

Operational messages like receipts/order status may have different business rules.

## 12. Provider capabilities

Provider capabilities define what an integration provider/account can do.

Examples:

```text
sms_send
sms_delivery_callback
email_send
email_delivery_callback
whatsapp_send
whatsapp_delivery_callback
push_send
```

This is better than assuming every provider supports everything.

Example:

Provider A:

```text
supports sms_send
supports sms_delivery_callback
does not support whatsapp_send
```

Provider B:

```text
supports whatsapp_send
does not support sms_send
```

The notification service should choose a provider/account only if the required capability is active.

## 13. Meaning of communication capabilities

`sms_send`

```text
The provider can send SMS messages.
```

Example:

```text
Send OTP or order-ready SMS to +255...
```

`sms_delivery_callback`

```text
The provider can notify us later whether SMS was delivered, failed, expired or rejected.
```

Example:

```text
Provider calls our webhook: message delivered.
```

`email_send`

```text
The provider can send email.
```

Example:

```text
Send payroll payslip email or supplier PO email.
```

`email_delivery_callback`

```text
The provider can notify us about email delivery/bounce/open events if supported.
```

Example:

```text
Email bounced because address does not exist.
```

`whatsapp_send`

```text
The provider can send WhatsApp messages.
```

Example:

```text
Send reservation confirmation via WhatsApp.
```

`push_send`

```text
The provider can send push notifications to mobile/app devices.
```

Example:

```text
Notify business owner: daily sales report is ready.
```

Push requires device tokens, which should be handled in auth/device design.

## 14. Where SMS/email/WhatsApp settings live

There are two layers:

### Layer 1: Provider/account credentials

Use:

```text
integration_accounts
```

Examples:

```text
SMS provider API key
Email provider SMTP/API key
WhatsApp business account token
Push provider credentials
```

These should be encrypted.

### Layer 2: Notification behavior settings

Use:

```text
settings
branch_settings
tenant_user_preferences
notification_rules
notification_templates
notification_preferences
```

Examples:

```text
Which events send SMS?
Which branch overrides receipt SMS text?
Which channel is default for order ready?
Which customer opted out?
```

Do not store provider API secrets inside notification templates.

## 15. Tenant vs branch configuration

Default:

```text
company/tenant-level notification setting
```

Branch override only when needed.

Example company-wide:

```text
All branches send receipt by SMS using tenant SMS provider.
```

Example branch-specific:

```text
Airport branch uses its own WhatsApp number.
```

In that case:

```text
branch_settings or branch-linked integration account
```

## 16. Event examples

Operational:

```text
order_ready
reservation_confirmed
payment_completed
refund_requested
refund_approved
purchase_order_approved
stock_expiring
low_stock
payroll_approved
payroll_posted
fiscal_receipt_rejected
tax_return_due
backup_completed
```

Customer-facing:

```text
otp_login
order_received
order_ready
reservation_confirmed
receipt_available
refund_processed
loyalty_points_updated
```

Internal:

```text
approval_required
cash_variance_detected
stock_count_variance
payroll_processing_completed
fiscal_submission_failed
daily_report_ready
```

## 17. Sending flow

Recommended backend flow:

```text
Business event occurs
Transaction writes source record
Transaction writes notification intent/outbox event
Transaction commits
Worker resolves notification rules
Worker renders template
Worker creates notification_message
Worker creates recipients
Worker creates deliveries
Channel workers send messages
Provider callback updates delivery status
```

Do not send SMS/email inside business transaction.

Reason:

If the transaction rolls back but SMS was already sent, customer/staff receives false information.

## 18. Delivery flow example

Order ready SMS:

```text
Kitchen marks order ready
Outbox event: order.ready
Worker creates notification message
Recipient: customer
Delivery: SMS to +255...
SMS provider accepts
Callback confirms delivered
notification_deliveries.status = delivered
notification_recipients.status = sent
notification_messages.status = sent
```

If one recipient fails and another succeeds:

```text
notification_messages.status = partially_sent
```

## 19. Webhook/callback handling

Provider callbacks should use:

```text
webhook_receipts
notification_delivery_events
notification_deliveries
```

Flow:

```text
Receive provider webhook
Store webhook_receipt short-term for idempotency
Find delivery by provider_message_reference
Create notification_delivery_event
Update notification_deliveries status
Update recipient/message aggregate status
Archive/delete raw webhook later
```

Do not keep large callback raw payload forever.

## 20. Recommended endpoints

Templates:

```text
GET  /api/v1/notification-templates
POST /api/v1/notification-templates
GET  /api/v1/notification-templates/{notification_template_uuid}
PATCH /api/v1/notification-templates/{notification_template_uuid}
```

Rules:

```text
GET  /api/v1/notification-rules
POST /api/v1/notification-rules
PATCH /api/v1/notification-rules/{notification_rule_uuid}
```

Messages:

```text
GET /api/v1/notification-messages
GET /api/v1/notification-messages/{notification_message_uuid}
POST /api/v1/notification-messages/{notification_message_uuid}/cancel
POST /api/v1/notification-messages/{notification_message_uuid}/retry
```

Recipients/deliveries:

```text
GET  /api/v1/notification-recipients
POST /api/v1/notification-recipients/{notification_recipient_uuid}/mark-read

GET  /api/v1/notification-deliveries
GET  /api/v1/notification-deliveries/{notification_delivery_uuid}
POST /api/v1/notification-deliveries/{notification_delivery_uuid}/retry
```

Preferences:

```text
GET  /api/v1/notification-preferences
POST /api/v1/notification-preferences
PATCH /api/v1/notification-preferences/{notification_preference_uuid}
```

Provider callback endpoints:

```text
POST /api/v1/webhooks/communications/{provider_key}
```

Webhook endpoints must validate provider signature where supported.

## 21. Complexity classification

Simple CRUD:

- notification templates
- notification rules
- notification preferences

Medium complexity:

- notification message list/details
- mark in-app notification read
- retry a failed delivery
- provider/account selection

High complexity:

- resolving recipients from permissions/roles/branches
- rendering templates safely
- sending SMS/email/WhatsApp/push
- processing callbacks
- aggregate message status update
- opt-out enforcement

Queue required:

- all external sends
- delivery retry
- webhook normalization
- bulk notifications
- report-ready notifications
- payslip email generation/sending

No queue required:

- in-app read status update
- simple template/rule CRUD
- listing messages

## 22. Transactions and locking

Use transactions for:

- creating notification message with recipients/deliveries
- claiming delivery for sending
- processing provider callback
- retrying delivery

Use locking for:

- delivery row during send/retry
- message row when recalculating aggregate status
- webhook receipt during idempotency handling

Reason:

Two workers must not send the same SMS twice.

## 23. Query and performance rules

All list endpoints use simple pagination.

Frequent filters:

```text
notification_templates.company_id/event_type/channel/is_active
notification_templates.branch_id/event_type/channel/is_active
notification_rules.company_id/event_type/is_active
notification_rules.branch_id/event_type/is_active
notification_messages.company_id/event_type/status/queued_at
notification_messages.branch_id/event_type/status/queued_at
notification_recipients.tenant_user_id/status/read_at
notification_recipients.customer_id/status
notification_deliveries.channel/status/last_attempt_at
notification_deliveries.integration_account_id/status/last_attempt_at
notification_delivery_events.notification_delivery_id/event_at
notification_preferences.tenant_user_id/event_type/is_enabled
notification_preferences.customer_id/event_type/is_enabled
```

Do not scan all tenant messages every minute.

Workers should claim pending deliveries by indexed status/channel/provider filters.

Archive or delete old delivery events based on retention policy.

## 24. Storage and retention

Keep:

- notification message summary
- recipient summary
- final delivery status

Do not keep huge provider response JSON forever.

Recommended:

```text
retain delivery event payloads for configured period
retain provider raw webhook only short-term
archive old communication events
keep business-critical notification summary
```

Example:

Order-ready SMS delivery details may only need 90 days of provider payload, but message summary can remain longer.

## 25. Access control

Recommended permissions:

```text
notification_templates.view
notification_templates.manage

notification_rules.view
notification_rules.manage

notification_messages.view
notification_messages.cancel
notification_messages.retry

notification_deliveries.view
notification_deliveries.retry

notification_preferences.view
notification_preferences.manage

communication_providers.view
communication_providers.manage
```

Users should see their own in-app notifications without needing admin notification permissions.

## 26. Audit logging

Log critical notification events:

- provider/account configuration changed
- notification template changed
- notification rule changed
- bulk notification sent
- delivery manually retried/cancelled
- opt-out/preference changed by staff

Do not log every SMS delivery callback in Spatie activity log. Delivery events already store that operational trail.

## 27. First release recommendation

Implement first:

- notification templates
- notification rules
- notification messages
- notification recipients
- in-app notifications
- SMS delivery skeleton
- email delivery skeleton
- provider capability checks
- callback/webhook intake skeleton
- notification preferences
- queue-based delivery workers

Later:

- WhatsApp provider automation
- mobile push device token management
- marketing campaign module
- advanced segmentation
- delivery cost tracking
- multilingual template management
