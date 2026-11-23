# Development Checklist

This checklist is based on the documents in [`System Requirement/`](../../System%20Requirement/). A checked item means that the explicitly described **beta scope** is implemented, not that every requirement in that broader module is complete. Unchecked items are remaining work, later-stage functionality, or external dependencies. Confirm each scope against the requirements and deployment environment before using it with live workshop data.

## Workshop beta

- [x] Authentication and access-control baseline: login/logout, role checks, account lockout, session timeout, tenant scoping, subscription-expiry gates.
- [x] Password recovery and password policy: email reset links, consistent password validation, and web-session invalidation after password changes.
- [x] Login CAPTCHA after repeated failures, optional authenticator verification for selected high-privilege actions, and web device-session management.
- [ ] Full authentication: Google/Microsoft SSO and linked identity management.
- [x] Profile beta: personal name, phone, bio and avatar editing; verified account email changes; saved language, timezone and communication preferences; link to existing client reminder consent.
- [ ] Complete profile and account preferences: workshop logo uploads, applied language/timezone preferences and remaining notification settings.
- [x] Staff account administration beta: workshop-scoped email invitations for mechanics or full workshop admins; activation/suspension, role management and optional read-only customer/vehicle lists.
- [ ] Additional staff permissions (including invoice and payment recording), existing-account invitations and self-registration approval.
- [x] Client account and self-service portal: workshop-admin-confirmed email linking, client-owned vehicle records (five self-service vehicles), approval requests for additional vehicles, and read-only service history.
- [x] Customer records: workshop-scoped list, create and edit, short-name aliases and search.
- [x] Vehicle records: workshop-scoped list, create and edit, customer linkage.
- [x] Vehicle master data: year, colour, mileage and fuel type, with assignment to registered or workshop-only customer records; portal history is customer-scoped.
- [x] Appointment scheduling: workshop calendar, Malaysia-time booking/rescheduling/cancellation, same-vehicle conflict prevention.
- [x] Registered clients can request a service package or custom service selection; workshop admins confirm or reject requests.
- [ ] Client appointment cancellation/rescheduling and booking/confirmation/reminder notifications.
- [x] Job cards: create from a vehicle or appointment, track open/in-progress/completed states.
- [x] Job assignment: assign/reassign active same-workshop mechanics; mechanics can access and advance only their assigned jobs.
- [x] Mechanic personal dashboard: today's assigned appointments/jobs and assigned open-job count.
- [x] Job service notes (encrypted) and append-only progress history for job opening and status changes (recorded from deployment onward).
- [x] Optional job deadlines with Malaysia-time overdue detection and filtering; job parts usage/returns with linked inventory stock movements and mechanic/admin authorization.
- [x] Assigned mechanics can append mileage, vehicle condition and service notes to an active job; updates appear in vehicle service history.
- [x] Inventory beta: items, stock movements and low-stock threshold, subject to plan entitlement.
- [x] Supplier records and purchase orders, with partial/complete receiving added to audited stock movements.
- [x] Optional invoice-line inventory linkage deducts stock atomically with invoice creation; eligible workshop dashboards show tenant-scoped low-stock alerts.
- [x] Invoices: create, view, print/save as PDF, MYR line-item totals, optional vehicle.
- [x] Invoice payment tracking: manual partial/final payments, balance and payment status; no payment processing or reversal.
- [x] Workshop bank/e-wallet QR payment options, customer-submitted proof with admin approval, and admin-uploaded proof for payments already received.
- [x] Printable acknowledgments for recorded payments; not MyInvois-validated receipts.
- [x] Optional invoice due date, Malaysia-time overdue detection for unpaid balances, overdue filter/dashboard total.
- [x] Edit or void unpaid invoices with reason tracking and atomic inventory stock reconciliation; invoices with payments cannot be changed or voided.
- [x] Partial/full refunds and credit notes for invoices with recorded payments; immutable records preserve original payments/invoices and track customer credit.
- [x] Email invoice sending through configured transport, with manual retries and tenant-scoped per-attempt audit history.
- [x] Opt-in email payment reminders on the due date and 7/14 days overdue, with pending-proof pauses, manual retries and per-attempt history.
- [x] Vehicle service history: linked appointments, job cards and invoices.
- [x] Workshop dashboard: workshop/plan, today's appointments, open and overdue jobs, balances, overdue balances and quick actions.
- [x] Per-admin payment preferences: up to two gateways plus offline methods.
- [x] Stripe, toyyibPay and Billplz hosted sandbox demos. Mocked provider tests pass; live sandbox browser/webhook tests remain outstanding.
- [ ] Live payment processing, settlement/reconciliation and production gateway webhooks.
- [x] Workshop service and package price lists, optional invoice-wide percentage discounts, and invoice-specific inclusive/exclusive tax calculations (beta estimates; not MyInvois compliance).
- [x] Workshop reporting beta: monthly/annual invoice and cash movements, customer spend, assigned-mechanic completed-job counts, and tagged gross service line totals.
- [ ] Net revenue by service, including allocation of invoice-wide discounts, tax and credit notes and a strategy for historical untagged lines.
- [x] Client engagement beta: dated portal promotions, manual loyalty points and redemption history, and consent-based manual vehicle service/renewal reminder emails.
- [ ] Automatic visit/spend-based loyalty and configurable rewards; invoice-linked reward discounts.
- [ ] Basic-tier loyalty add-on provisioning and entitlement from Qysoft Main.
- [ ] Automated service/renewal reminder schedules and additional notification channels.

## LHDN MyInvois

- [x] Preparation-only workshop supplier profile and per-invoice buyer details; sensitive tax details encrypted at rest and omitted from normal invoice output.
- [ ] Complete taxpayer/business fields, buyer TIN validation, B2B/B2C and consolidated-invoice rules.
- [ ] Tax types/rates, item classification, discounts, payment terms and full MyInvois document validation.
- [ ] Generate compliant UBL/MyInvois JSON/XML and apply the required digital signature.
- [ ] Credential management and explicit sandbox integration; no credentials or submission endpoints are configured in this beta.
- [ ] Submit documents, poll/handle validation, IRN/QR display, rejection handling and safe resubmission.
- [ ] Cancellation, credit/debit/refund notes and immutable lifecycle/audit history.
- [ ] Signed XML/PDF archive, seven-year retention, client download/email and operational restore controls.
- [ ] Compliance environment controls and live submission, following successful sandbox verification and legal/accountant review.

## Accounts, onboarding and subscriptions

- [x] Account subscription and package-allowance enforcement in this app; package creation/pricing belongs to Qysoft Main.
- [x] Basic/Pro/Enterprise allowance data model and single-workshop Pro/Enterprise operational entitlement checks.
- [x] Inactive/expired access handling for workshop admins and staff.
- [ ] Confirm and complete Basic/Pro registration, trial eligibility, duplicate/fraud safeguards and onboarding emails.
- [ ] Free beta entitlement for the pilot workshop and the agreed post-beta 50%-off Pro year (requires explicit provisioning).
- [ ] Qysoft Main API contract and verified account/subscription provisioning and synchronization.
- [ ] Multi-workshop parent-account provisioning, linked-admin workflows, workshop allowance edge cases and Enterprise add-on approvals.
- [ ] Paid billing, renewal, cancellation, auto-renewal, payment receipts and billing notifications from Qysoft Main.
- [ ] Complete external registration API with equivalent web-flow validation and ownership guarantees.
- [ ] Fully validate trial-run pricing/expiry/renewal behavior against Qysoft Main; this app must not invent or charge package prices.

## Platform administration

- [x] System-admin audit log baseline with filtering/export.
- [x] System-admin owner/workshop oversight and support impersonation baseline.
- [ ] Full audit coverage, tamper-evident retention and linked-workshop-scoped parent account audits.
- [ ] Cross-workshop analytics, platform usage/growth/churn and global vehicle analytics with strict account scoping.
- [ ] Global services/catalogue, localization/tax settings, templates and integrations.
- [ ] Support tickets, knowledge base, feedback, announcements and internal messaging.
- [ ] System backup/restore controls and automated retention policy.

## Shared platform and client features

- [ ] In-app/email/SMS/WhatsApp notifications, delivery tracking, user preferences and escalation/quiet hours.
- [ ] Global search and saved filters across customers, vehicles, jobs, invoices, payments and documents.
- [ ] Permission-checked CSV/PDF export for operational records and reports.
- [ ] Document/media library with safe upload validation, malware scanning, versioning, quotas and retention.
- [ ] Localization, multi-currency/tax rules and per-workshop regional settings beyond Malaysia-time scheduling and MYR.
- [ ] Mobile/PWA/offline workflows, push notifications, camera/barcode uploads and signature capture.
- [ ] Optional client dashboard, online booking/payment, receipts, vehicle timeline and loyalty.
- [ ] External accounting, ERP and messaging integrations.

## Recently completed

- [x] Mechanic dashboard for today's assigned jobs and pending assigned-job count.
- [x] Encrypted job service notes and progress history; notes appear in vehicle service history (pre-deployment history is not backfilled).
- [x] Optional job deadlines with Malaysia-time overdue detection/filtering, and inventory-linked parts usage/returns with append-only stock movements.
- [x] Assigned mechanic vehicle updates for mileage, condition and service notes; sensitive text is encrypted and entries appear in vehicle history.
- [x] Supplier management and purchase orders with partial/complete receiving recorded in linked inventory movements.
- [x] Invoice-linked stock deductions and inventory-entitled workshop dashboard low-stock alerts.
- [x] Registered customer portal with admin-confirmed customer linking, a five-vehicle self-service limit, approved additional-vehicle requests and private service history.
- [x] Unpaid invoice editing and voiding with inventory reconciliation; invoices with payments remain immutable and use separate adjustment records.
- [x] Workshop QR payment setup and payment-proof workflow; customer proofs reserve invoice balance and require admin approval, while admin-uploaded proofs record received payments immediately.
- [x] Partial/full refunds and credit notes for recorded payments, with external-transfer recording, printable documents and customer-credit balance tracking.
- [x] Workshop-opted-in email payment reminders with Malaysia-time scheduling and tenant-scoped delivery history.
- [x] Vehicle master mileage and fuel type for workshop and client workflows, with customer-scoped portal history after ownership changes.
- [x] Staff email invitations, role and suspension management, and optional mechanic access to read-only customer and vehicle lists.
- [x] Workshop services and packages, customer appointment requests with admin confirmation, and invoice percentage discounts and inclusive/exclusive tax snapshots.
- [x] Workshop reporting beta with Malaysia-time monthly/annual figures, customer cash spend, assigned-mechanic completed jobs and tagged gross service lines.
- [x] Client engagement beta with portal promotions, manual points and consent-based manual vehicle reminders.
- [x] Email password recovery and consistent password validation, with web-session invalidation on password changes.
- [x] Google reCAPTCHA after repeated login failures, optional authenticator verification for selected privileged actions, and device-session revocation.
- [x] Profile editing with private avatar uploads, verified account email changes and saved personal preferences; existing customer reminder consent remains in the portal.

## Next recommended module

- [ ] Additional staff permissions (including invoice and payment recording), existing-account invitations and self-registration approval.

