# Development Checklist

This checklist is based on the documents in [`System Requirement/`](../../System%20Requirement/). A checked item means that the explicitly described **beta scope** is implemented, not that every requirement in that broader module is complete. Unchecked items are remaining work, later-stage functionality, or external dependencies. Confirm each scope against the requirements and deployment environment before using it with live workshop data.

## Workshop beta

- [x] Authentication and access-control baseline: login/logout, role checks, account lockout, session timeout, tenant scoping, subscription-expiry gates.
- [ ] Full authentication: password recovery and password policy, CAPTCHA, MFA, SSO, device management.
- [ ] Profile and account preferences: full contact/profile editing, avatar, language, timezone and notification settings.
- [ ] Staff account administration: safe workshop-scoped invitations, activation/suspension and permission management.
- [ ] Client account and self-service portal.
- [x] Customer records: workshop-scoped list, create and edit.
- [x] Vehicle records: workshop-scoped list, create and edit, customer linkage.
- [ ] Complete vehicle master data: year, colour, mileage, fuel type and owner assignment for unregistered clients.
- [x] Appointment scheduling: workshop calendar, Malaysia-time booking/rescheduling/cancellation, same-vehicle conflict prevention.
- [ ] Client appointment self-service and booking/confirmation/reminder notifications.
- [x] Job cards: create from a vehicle or appointment, track open/in-progress/completed states.
- [x] Job assignment: assign/reassign active same-workshop mechanics; mechanics can access and advance only their assigned jobs.
- [x] Mechanic personal dashboard: today's assigned appointments/jobs and assigned open-job count.
- [x] Job service notes (encrypted) and append-only progress history for job opening and status changes (recorded from deployment onward).
- [ ] Job deadlines and parts-used recording, including inventory integration.
- [ ] Mechanic vehicle updates: mileage/condition and service notes linked to vehicle history.
- [x] Inventory beta: items, stock movements and low-stock threshold, subject to plan entitlement.
- [ ] Inventory suppliers, purchase orders, stock usage linked to jobs/invoices and alerts.
- [x] Invoices: create, view, print/save as PDF, MYR line-item totals, optional vehicle.
- [x] Invoice payment tracking: manual partial/final payments, balance and payment status; no payment processing or reversal.
- [x] Printable acknowledgments for recorded payments; not MyInvois-validated receipts.
- [x] Optional invoice due date, Malaysia-time overdue detection for unpaid balances, overdue filter/dashboard total.
- [ ] Invoice editing/voids, refunds/credit notes, sending/emailing and payment reminders.
- [x] Vehicle service history: linked appointments, job cards and invoices.
- [x] Workshop dashboard: workshop/plan, today's appointments, open jobs, balances, overdue balances and quick actions.
- [x] Per-admin payment preferences: up to two gateways plus offline methods.
- [x] Stripe, toyyibPay and Billplz hosted sandbox demos. Mocked provider tests pass; live sandbox browser/webhook tests remain outstanding.
- [ ] Live payment processing, settlement/reconciliation and production gateway webhooks.
- [ ] Workshop services, price lists, discounts and tax settings.
- [ ] Workshop reports and analytics: revenue by service, staff performance, client spend, monthly/annual summaries.
- [ ] Client engagement: promotions, loyalty/rewards and service/renewal reminders.

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

## Next recommended module

- [ ] Job deadlines and parts-used recording on job cards, with stock movements linked to inventory.

