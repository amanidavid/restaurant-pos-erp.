# API Design 10: HR, Staff Profiles, Attendance, Payroll, Loans, Payslips, and Statutory Calculations

Project: Restaurant POS ERP  
Backend: Laravel, PHP 8.3, PostgreSQL, database-per-tenant multitenancy  
API version: v1  
Date: 2026-08-25

## 1. Purpose

This design explains HR and payroll for restaurant staff.

Main flow:

```text
Tenant user is created
Employment details are attached to tenant user
Shift and attendance are recorded
Earnings/deductions/statutory profiles are configured
Payroll period is opened
Payroll run is calculated
Payroll is approved
Payslips are generated
Payroll is posted to accounting
```

Important principle:

Do not create a duplicate `employees` table.

In this system, staff are `tenant_users`. HR/payroll extends `tenant_users` with employment fields and payroll-related tables.

## 2. What this design depends on

This module depends on:

- Design 03: company, branch, departments, job positions, cost centres
- Design 04: tenant users, branch access, permissions
- Design 09: finance/accounting double-entry posting
- Payroll statutory rules for Tanzania

Payroll is tenant-local and report output is per tenant database.

## 3. Route placement

Tenant HR/payroll APIs belong in:

```text
routes/api/v1/web.php
```

Recommended source folders:

```text
app/Http/Controllers/Web/Hr
app/Http/Controllers/Web/Payroll
app/Http/Requests/Web/Hr
app/Http/Requests/Web/Payroll
app/Http/Resources/Web/Hr
app/Http/Resources/Web/Payroll
app/Services/Hr
app/Services/Payroll
```

No `Api/V1` folder is needed. Versioning stays in route files and URL prefixes.

## 4. Tables used

Tenant tables:

- `tenant_users`
- `employment_types`
- `tenant_user_branch_employments`
- `tenant_user_job_histories`
- `shift_definitions`
- `shift_assignments`
- `attendance_records`
- `leave_types`
- `leave_requests`
- `payroll_periods`
- `earning_types`
- `deduction_types`
- `employee_earnings`
- `employee_deductions`
- `statutory_rules`
- `statutory_brackets`
- `employee_statutory_profiles`
- `loan_types`
- `employee_loans`
- `loan_repayments`
- `payroll_runs`
- `payroll_run_employees`
- `payslips`
- `journal_entries`

## 5. Tenant user as staff member

`tenant_users` is the staff record.

Employment fields added to `tenant_users` include:

- company
- employment type
- home branch
- department
- job position
- cost centre
- employee number
- date of birth
- hired date
- confirmed date
- ended date
- employment status
- gender
- national ID number
- tax number
- NSSF number
- NHIF number

Example:

```text
Tenant user: Asha Mohamed
Role: Cashier
Home branch: Mikocheni
Department: Operations
Job position: Cashier
Employment status: active
Employee number: EMP-01
```

This avoids redundancy because login identity, permissions and staff profile remain one record.

## 6. Employment types

`employment_types` defines how a person is employed.

Examples:

- Full-time
- Part-time
- Casual
- Contract
- Intern

`is_salary_based` helps payroll decide whether default salary logic applies.

Example:

```text
Full-time -> salary based
Casual -> not salary based
```

## 7. Branch employment

`tenant_user_branch_employments` handles staff working in more than one branch.

Example:

```text
Asha works at:
  Mikocheni branch - primary
  Masaki branch - active support branch
```

This is separate from permissions. Permissions decide what the user can do. Branch employment tells HR/payroll where the person works.

## 8. Job history

`tenant_user_job_histories` records salary/job changes over time.

Example:

```text
2026-01-01: Waiter, salary 350,000
2026-08-01: Branch Supervisor, salary 550,000
```

Why history is needed:

If salary changes in August, payroll for July must still use the old salary.

Do not overwrite history and lose old payroll context.

## 9. Shift definitions

`shift_definitions` defines work shifts.

Example:

```text
Morning Shift
Starts: 08:00
Ends: 16:00
Grace: 10 minutes
```

Night shift example:

```text
Night Shift
Starts: 18:00
Ends: 02:00
crosses_midnight = true
```

This is HR staff scheduling, not the same as POS `shift_sessions`.

Difference:

```text
shift_definitions = employee work schedule
shift_sessions = POS/cashier business operation session
```

## 10. Shift assignments

`shift_assignments` assigns staff to a work shift for a period.

Example:

```text
Asha assigned to Morning Shift
Valid from: 2026-08-01
Valid to: null
```

It can be branch-specific.

## 11. Attendance records

`attendance_records` stores clock-in/clock-out or manual attendance.

Important fields:

- staff user
- branch
- shift definition
- attendance date
- clock in/out time
- worked minutes
- late minutes
- overtime minutes
- status
- source

Statuses:

```text
present
absent
leave
off
half_day
```

Sources:

```text
manual
device
import
system
```

Example:

```text
Attendance date: 2026-08-25
Clock in: 08:13
Clock out: 16:30
Late minutes: 3
Overtime minutes: 30
```

Payroll can use attendance for overtime, unpaid absence, lateness penalties or attendance allowances if tenant policy requires.

## 12. Leave

`leave_types` defines leave categories.

Examples:

- Annual leave
- Sick leave
- Maternity leave
- Unpaid leave

`leave_requests` stores staff leave requests and approval.

Example:

```text
Employee: Asha
Leave type: Annual leave
Dates: 2026-09-01 to 2026-09-05
Days: 5
Status: approved
```

Approved leave affects attendance and optionally payroll.

## 13. Payroll periods

`payroll_periods` defines the pay cycle.

Example:

```text
August 2026 Payroll
Start: 2026-08-01
End: 2026-08-31
Pay date: 2026-09-05
Status: open
```

Statuses:

```text
open
processing
closed
```

One company can have many periods, but period date ranges must be unique.

## 14. Earning types

`earning_types` defines money paid to staff.

Examples:

- Basic salary
- Housing allowance
- Transport allowance
- Overtime
- Bonus
- Commission

Calculation types:

```text
fixed
rate
formula
```

Examples:

```text
Basic salary -> fixed
Overtime -> rate
Bonus -> fixed
Commission -> formula
```

Fields:

- taxable or not
- pensionable or not
- system or custom
- status

System earning types should be protected from deletion.

## 15. Deduction types

`deduction_types` defines money removed from gross pay.

Examples:

- PAYE
- NSSF
- NHIF
- Loan repayment
- Salary advance
- Absence deduction
- Penalty

Calculation types:

```text
fixed
rate
formula
```

System statutory deduction types should be protected from deletion.

## 16. Employee earnings and deductions

`employee_earnings` stores recurring or effective earnings for a staff member.

Example:

```text
Employee: Asha
Earning: Transport allowance
Amount: 100,000
Effective from: 2026-08-01
```

`employee_deductions` stores recurring deductions.

Example:

```text
Employee: Asha
Deduction: Staff meal deduction
Amount: 30,000
Effective from: 2026-08-01
```

Use effective dates so payroll can calculate historical periods correctly.

## 17. Statutory rules

`statutory_rules` stores government/statutory calculation rules.

Examples for Tanzania:

- PAYE
- NSSF
- WCF
- SDL
- NHIF if applicable by policy

Rule types:

```text
tax
pension
health
levy
other
```

Important:

Statutory rules change over time. Use effective dates.

Example:

```text
PAYE rule effective from 2026-01-01
PAYE rule effective to null
```

Do not hard-code statutory calculations directly inside controllers.

## 18. Statutory brackets

`statutory_brackets` stores calculation bands.

Example simplified PAYE:

```text
0 - 270,000: 0%
270,001 - 520,000: 8%
520,001 - 760,000: 20%
...
```

Fields:

- sequence
- min amount
- max amount
- employee rate
- employer rate
- fixed amount

Payroll service applies the correct bracket by taxable amount and effective date.

## 19. Employee statutory profiles

`employee_statutory_profiles` links staff to statutory rules.

Example:

```text
Employee: Asha
Rule: NSSF
Reference number: NSSF-12345
Exempt: false
```

If employee is exempt:

```text
is_exempt = true
```

Then payroll skips that statutory rule for the employee.

## 20. Loans and repayments

`employee_loans` stores staff loans or salary advances.

Example:

```text
Loan: Staff advance
Principal: 300,000
Installments: 3
Installment amount: 100,000
Starts: 2026-09-01
Status: active
```

`loan_repayments` records repayments.

Repayment can be created from payroll:

```text
payroll_run_employee_id = current employee payroll calculation
amount = 100,000
```

Loan balance should be calculated from:

```text
principal + interest - posted repayments
```

Do not manually reduce the principal amount.

## 21. Payroll run

`payroll_runs` is the payroll batch.

Example:

```text
PAY-2026-01
Period: August 2026
Company: Kilimanjaro Restaurant
Branch: Mikocheni
Status: draft
```

Payroll can be:

```text
company-wide
branch-specific
```

Numbering rule:

```text
company-wide payroll run: unique(company_id, run_number) where branch_id is null
branch payroll run: unique(branch_id, run_number) where branch_id is not null
```

This avoids duplicate branch payroll numbers while also allowing company-wide payroll.

## 22. Payroll run employees

`payroll_run_employees` stores calculated payroll per employee.

Fields:

- gross amount
- taxable amount
- deduction amount
- net amount
- status

Example:

```text
Employee: Asha
Gross: 700,000
Taxable: 650,000
Deductions: 120,000
Net: 580,000
Status: calculated
```

This is the payroll snapshot. If employee salary changes after payroll is calculated, the old payroll run remains explainable.

## 23. Payslips

`payslips` stores generated payslip documents.

Example:

```text
Payslip: PSL-2026-01
Employee: Asha
Generated: 2026-09-01
Published: 2026-09-02
```

Payslip should show:

- employee details
- payroll period
- earnings
- deductions
- statutory deductions
- loan repayments
- gross pay
- net pay

Payslip number can remain tenant-wide/global to avoid duplicating branch/company columns on the payslip table.

## 24. Payroll calculation flow

Backend steps:

```text
Start payroll run
Select eligible tenant users
Load job history effective for payroll period
Load active earnings
Load active deductions
Load attendance records
Load approved leave
Load active loans
Load statutory profiles and rules
Calculate gross amount
Calculate taxable amount
Calculate statutory deductions
Calculate loan repayments
Calculate net amount
Store payroll_run_employees
Generate payslip records when approved/published
```

Payroll calculation is service logic, not CRUD.

Recommended service:

```text
PayrollCalculationService
```

## 25. Calculation example

Employee:

```text
Basic salary: 600,000
Transport allowance: 100,000
Overtime: 50,000
```

Gross:

```text
750,000
```

Deductions:

```text
PAYE: 60,000
NSSF employee: 60,000
Loan repayment: 100,000
```

Net:

```text
750,000 - 220,000 = 530,000
```

Payroll run employee:

```text
gross_amount = 750,000
taxable_amount = according to statutory rule
deduction_amount = 220,000
net_amount = 530,000
```

## 26. Payroll approval and posting

Recommended payroll statuses:

```text
draft
processing
approved
posted
cancelled
```

Flow:

```text
Draft payroll
Calculate payroll
Review exceptions
Approve payroll
Generate/publish payslips
Post payroll journal
Close payroll period
```

Do not post payroll to accounting before approval.

## 27. Payroll accounting posting

Example payroll journal:

```text
Debit: Payroll Expense              750,000
Credit: PAYE Payable                 60,000
Credit: NSSF Payable                 60,000
Credit: Staff Loan Receivable       100,000
Credit: Salaries Payable            530,000
```

When salaries are paid:

```text
Debit: Salaries Payable             530,000
Credit: Bank Account                530,000
```

Payroll posting should use Finance Design 09 posting services.

## 28. Queue usage

Small payroll can be calculated synchronously.

But for a scalable design, payroll calculation should support queue processing.

Recommended:

```text
Create payroll run
Dispatch payroll calculation job
Job connects to tenant DB
Calculate employees in chunks
Store payroll_run_employees
Mark run calculated/processing complete
Notify HR/payroll user
```

Jobs stay in landlord queue tables, but they carry tenant identity.

This prevents HTTP timeout when a tenant has many staff.

## 29. Recommended endpoints

HR setup:

```text
GET  /api/v1/employment-types
POST /api/v1/employment-types
PATCH /api/v1/employment-types/{employment_type_uuid}

PATCH /api/v1/tenant-users/{tenant_user_uuid}/employment
GET   /api/v1/tenant-users/{tenant_user_uuid}/job-history
POST  /api/v1/tenant-users/{tenant_user_uuid}/job-history
```

Attendance and leave:

```text
GET  /api/v1/shift-definitions
POST /api/v1/shift-definitions
POST /api/v1/shift-assignments

GET  /api/v1/attendance-records
POST /api/v1/attendance-records
POST /api/v1/attendance-records/import

GET  /api/v1/leave-types
POST /api/v1/leave-types
GET  /api/v1/leave-requests
POST /api/v1/leave-requests
POST /api/v1/leave-requests/{leave_request_uuid}/approve
POST /api/v1/leave-requests/{leave_request_uuid}/reject
```

Payroll setup:

```text
GET  /api/v1/payroll-periods
POST /api/v1/payroll-periods

GET  /api/v1/earning-types
POST /api/v1/earning-types
GET  /api/v1/deduction-types
POST /api/v1/deduction-types

POST /api/v1/tenant-users/{tenant_user_uuid}/earnings
POST /api/v1/tenant-users/{tenant_user_uuid}/deductions

GET  /api/v1/statutory-rules
POST /api/v1/statutory-rules
POST /api/v1/statutory-rules/{statutory_rule_uuid}/brackets
POST /api/v1/tenant-users/{tenant_user_uuid}/statutory-profiles
```

Loans:

```text
GET  /api/v1/loan-types
POST /api/v1/loan-types
GET  /api/v1/employee-loans
POST /api/v1/employee-loans
POST /api/v1/employee-loans/{employee_loan_uuid}/cancel
```

Payroll processing:

```text
GET  /api/v1/payroll-runs
POST /api/v1/payroll-runs
GET  /api/v1/payroll-runs/{payroll_run_uuid}
POST /api/v1/payroll-runs/{payroll_run_uuid}/calculate
POST /api/v1/payroll-runs/{payroll_run_uuid}/approve
POST /api/v1/payroll-runs/{payroll_run_uuid}/post
POST /api/v1/payroll-runs/{payroll_run_uuid}/cancel

GET  /api/v1/payroll-runs/{payroll_run_uuid}/employees
GET  /api/v1/payslips
GET  /api/v1/payslips/{payslip_uuid}
POST /api/v1/payslips/{payslip_uuid}/publish
```

## 30. Complexity classification

Simple CRUD:

- employment types
- shift definitions
- leave types
- earning types
- deduction types
- statutory rules/brackets setup
- loan types

Medium complexity:

- tenant user employment update
- job history create
- attendance create/import validation
- leave request approval
- employee earning/deduction effective dating
- employee loan setup

High complexity:

- payroll calculation
- statutory calculation
- loan repayment generation
- payslip generation
- payroll approval
- payroll accounting posting
- payroll reversal/correction

Queue required:

- payroll calculation for many staff
- attendance import
- payslip PDF generation
- payroll export
- statutory report generation
- payroll journal posting if many employees

No queue required:

- simple setup list/create
- one attendance manual record
- one leave approval

## 31. Transactions and locking

Use transactions for:

- creating payroll run
- calculating payroll run employees
- approving payroll
- posting payroll
- generating loan repayments
- publishing payslips
- cancelling payroll run

Use locking for:

- payroll run during calculate/approve/post
- employee loan when generating repayment
- payroll period when closing
- tenant user when updating employment history

Reason:

Two users must not calculate or post the same payroll twice.

## 32. Query and performance rules

All lists use simple pagination.

Frequent filters:

```text
tenant_users.company_id/employment_status
tenant_users.home_branch_id/employment_status
tenant_user_branch_employments.branch_id/status
attendance_records.branch_id/attendance_date
attendance_records.attendance_date/status
payroll_periods.company_id/status
payroll_runs.company_id/status
payroll_runs.branch_id/status
payroll_runs.payroll_period_id/status
payroll_run_employees.tenant_user_id/status
employee_loans.tenant_user_id/status
loan_repayments.employee_loan_id/repayment_date
```

Do not scan attendance for all tenants. Tenant DB is already isolated, and within tenant use date/branch filters.

Do not recalculate payroll every time the payslip screen opens. Store payroll run employee results as the approved snapshot.

## 33. Access control

Recommended permissions:

```text
hr.staff.view
hr.staff.update_employment
hr.job_history.view
hr.job_history.create

attendance.view
attendance.create
attendance.import
attendance.adjust

leave.view
leave.create
leave.approve
leave.reject

payroll_periods.view
payroll_periods.manage

payroll_setup.view
payroll_setup.manage

employee_loans.view
employee_loans.create
employee_loans.cancel

payroll_runs.view
payroll_runs.create
payroll_runs.calculate
payroll_runs.approve
payroll_runs.post
payroll_runs.cancel

payslips.view
payslips.publish
```

Staff should see only their own payslip unless they have payroll permission.

## 34. Audit logging

Log critical events:

- employment status changed
- salary/job history changed
- attendance manually adjusted
- leave approved/rejected
- statutory rule changed
- employee loan created/cancelled
- payroll calculated
- payroll approved
- payroll posted
- payroll cancelled
- payslip published

Do not log huge before/after JSON for every payroll line. Payroll run employee and payslip records are the business snapshot.

## 35. First release recommendation

Implement first:

- employment fields on tenant users
- employment types
- job history
- shift definitions
- attendance records
- leave types/requests
- earning and deduction types
- employee recurring earnings/deductions
- statutory rules/brackets
- employee statutory profiles
- employee loans and repayments
- payroll periods
- payroll run calculate/approve/post
- payslips
- payroll journal posting skeleton

Later:

- biometric attendance integration
- advanced rostering
- leave accrual balances
- payroll bank payment file
- statutory filing exports
- employee self-service portal
- advanced overtime rules
