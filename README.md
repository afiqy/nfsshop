# 🚗 Workshop Invoicing System

A **Laravel-based Progressive Web App (PWA)** designed for **car workshops** and **repair services** to manage customers, vehicles, invoices, inventory, tasks, and appointments efficiently.

---

## 📌 Features
✅ Multi-Tenant Support  
✅ User Roles (Admin, Mechanic, Client)  
✅ Customer & Vehicle Management  
✅ Invoicing System  
✅ Service Appointments with Calendar  
✅ Inventory Management  
✅ Task Tracking for Repairs  
✅ PWA Support with Firebase  

---

## 📌 Installation

### **1️⃣ Clone the Repository**
```sh
git clone https://github.com/afiqy/nfsshop.git
cd nfsshop
```

### **2️⃣ Clone the Repository**
```sh
composer install
npm install
```

### **3️⃣ Configure .env**
```sh
cp .env.example .env
```

### **4️⃣ Run Migrations & Seeders**
```sh
php artisan migrate --seed
```
Then update:
```ini
DB_DATABASE=nfsworkshop
DB_USERNAME=root
DB_PASSWORD=

FIREBASE_API_KEY=your-api-key
FIREBASE_PROJECT_ID=your-project-id
```
✅ Make sure your MySQL database exists (nfsworkshop).

### **5️⃣ Run the Application**
```sh
php artisan serve
```
✅ This will create necessary tables and add an admin account.
✅ Access the app at: http://127.0.0.1:8000

---

## 📌 Admin Account for Testing

Use this account for initial login:

- **Email**: `admin@invsys.com`  
- **Password**: `password123`  

---

## 📌 Commands

- **Run Laravel Server:** `php artisan serve`  
- **Run Vite for Assets:** `npm run dev`  
- **Clear Cache:** `php artisan cache:clear`  
- **Run Seeders:** `php artisan db:seed`

---

## Workshop invoice beta

After deploying to the beta environment, back up the databases, run `php artisan migrate` for central migrations, and run `php artisan tenants:migrate --tenants=YOUR_BETA_TENANT_ID` for each beta workshop with a provisioned tenant database. New workshops get the tables during provisioning.

Before the live pilot, smoke-test on a separate environment with synthetic data. As an active or trialing workshop admin, add a customer under **Customers**, add a vehicle linked to that customer under **Vehicles**, then select both under **Invoices > New invoice**. Check the totals and **Print / Save as PDF**. Sign in as a different workshop admin and verify that none of those records appear.

On the **Dashboard**, check the workshop name and plan, today's scheduled appointments (Malaysia time), open and overdue jobs, and the total invoice balance after recorded payments, refunds and credit notes. The overdue job count links to **Job cards → Overdue only**; the overdue invoice subtotal links to **Invoices → Overdue only**. Quick actions open the forms for new invoices, job cards, appointments, and vehicles. Staff availability and configurable widgets are not included.

On a new invoice, optionally set a payment due date of today or later (Malaysia time). The date appears on the invoice and its printout. An invoice is overdue only from the day after its due date and only while a balance remains; undated invoices, including existing ones, are not flagged. An admin can update the date while editing an unpaid invoice. Reminders are sent only if the workshop opts in as described below. Back up the pilot database and run `php8.4 artisan tenants:migrate --tenants=YOUR_BETA_TENANT_ID` before using this version.

For **MyInvois preparation**, an active workshop admin can open **Invoices → MyInvois workshop tax profile** to save supplier details, then use **MyInvois preparation** on an invoice to save or clear its named buyer details. Both forms are limited to Malaysian addresses and perform local format checks only. Saved details are encrypted in the workshop database using Laravel's application key; retain that key securely for database restores. Tax identifiers are not added to the normal invoice printout. Smoke-test with synthetic details, not real taxpayer identifiers. No document is generated, signed, submitted, or validated, and line tax/classification and consolidated invoicing are not supported. Continue using the workshop's official invoicing process. Apply the tenant migrations before opening the preparation screens.

For the live pilot, use a dedicated workshop account (not the example credentials), HTTPS, `APP_DEBUG=false`, and backups with a tested restore. Keep real customer records out of development databases and bug reports. Confirm the workshop's entitlement covers the pilot before admitting real data. Customer and vehicle deletion, customer appointment self-service and online customer payments are out of scope. Admins can edit only invoices with no recorded payments and can void only unpaid invoices. Voiding preserves the invoice and reason and restores linked stock atomically; invoices with payments remain unchanged and use separate refund/credit-note records. Invoices and credit notes are not submitted to LHDN MyInvois; do not use this beta as the sole system for official invoices.

Workshop admins can upload up to ten active bank or e-wallet QR images under **Payment settings → Manage workshop payment QR codes**. Active QRs appear on invoice printouts and in the registered customer's portal. A portal customer can submit a partial or full payment amount, transfer reference and proof; the amount is reserved against other pending submissions, but is not recorded as paid until a workshop admin verifies and approves it. Admins can instead upload proof when recording a QR payment they have already received; that payment is recorded immediately. Proof files are stored privately and encrypted with Laravel's application key, while QR images are served only through authenticated, workshop-scoped routes. Apply the tenant migrations and back up the private storage files as well as databases; preserve the application key for proof recovery. This is manual proof review, not online payment processing, and no customer notifications are sent.

For an invoice with at least one recorded payment and no pending payment proofs, an admin can record a partial or full external refund against that payment or issue a partial/full credit note. A refund may not exceed the unrefunded payment amount, and after a credit note it is also capped by the remaining customer-credit balance. Credit notes reduce the amount due without changing the original invoice or moving money; any resulting overpayment appears as customer credit, which can be refunded through a separate external refund record. Both adjustments require a reason, remain in an append-only history and have printable beta documents; no money is transferred by the app and these documents are not MyInvois-validated. Apply the tenant migrations before using this workflow.

Workshop admins can email an itemized invoice to the email address currently saved on its customer record. Configure Laravel's mail transport (`MAIL_MAILER`, the transport-specific `MAIL_*` settings, and `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME`) in the deployment environment before sending. Sending is synchronous; a failed attempt is recorded with a safe error message and can be manually retried, with every attempt saved in the tenant's invoice email history. A successful send means the configured transport accepted the message, not that it reached the recipient's inbox. The email includes the current invoice balance and is not a PDF or a MyInvois-validated invoice; use the invoice page's print/save as PDF action for a printable document. Apply the tenant migrations before using this workflow.

For payment reminders, apply the tenant migrations and configure a real mail transport; log/array mailers do not deliver email. Under **Invoices → Payment reminders**, each workshop admin can opt in or turn reminders off; they are off by default. For workshops with a usable subscription, the scheduler checks hourly in Malaysia time and sends once on the due date, seven days overdue and fourteen days overdue, only for issued invoices with a positive adjusted balance and no pending payment proofs. It uses the customer's current email, so confirm the address before enabling. If payment proof is pending, the stage remains paused and can run later that same day after review. Missed stages are not backfilled after their scheduled day; future stages still run. Set up Laravel's scheduler to run `php artisan schedule:run` every minute on the application server; `php artisan invoices:send-payment-reminders` can also be run manually. The invoice's reminder history records attempts, transport acceptance or failure, and the admin can retry the latest failed attempt while the invoice remains eligible and reminders are enabled. Acceptance does not guarantee inbox delivery. A stuck **Sending / outcome unknown** attempt is not automatically retried: check the mail provider before taking further action to avoid duplicate reminders. Reminders do not process payments or constitute MyInvois-validated invoices.

The registered-client portal is available at **My vehicles & history** after a workshop admin confirms the account against a customer record with the same email address. A client can add up to five vehicles directly; each additional vehicle is added only after workshop-admin approval. Existing walk-in/non-registered customer records stay private to the workshop unless the admin explicitly confirms a matching portal account. Apply tenant migrations before enabling the portal, and verify the link/approval flow with synthetic accounts before using live customer data. Portal vehicle history shows only appointments, service records and invoices associated with the current customer's record; after a vehicle changes customer, the new customer cannot see the previous customer's records, while the previous customer retains access to their own invoices. Internal job notes are not exposed.

**Client engagement** is available to Pro/Enterprise workshops and active trials. After tenant migrations, an admin can publish dated, informational promotions for linked customers in the portal, and manually award or redeem points against any workshop customer with a reason. The ledger cannot be edited and redemptions cannot exceed the balance; points do not change invoices, and there is no automatic earning or reward valuation. Reasons are visible to the linked customer, so do not enter private notes. Basic-tier add-on provisioning is not implemented.

Admins can also add service, registration and insurance reminders for a vehicle and manually send individual emails. No reminders are scheduled automatically. Obtain the customer's explicit consent before enabling reminder emails on their engagement page; linked customers can change the preference on their account page, even if the workshop later loses engagement access. Consent defaults off and is cleared when the customer email changes. Reminders belong to the customer at creation and cannot be sent or viewed in the portal while the vehicle belongs to a different customer. Close an incorrect reminder and create a replacement. Configure a real mail transport; log/array mailers do not deliver. Each send records an attempt, and transport acceptance is not proof of inbox delivery. If an attempt remains **sending**, check the provider before marking its outcome unknown or sending again; doing so may duplicate a message. Verify these workflows with synthetic accounts and a real test transport before live use.

Under **Staff access**, workshop admins can invite a mechanic or a full workshop admin by email. A full admin can manage staff and workshop financial settings, so grant this role only to trusted staff. Invitations expire after seven days, are single-use, can be revoked, and require a new, globally unique email; resending invalidates the previous link. Configure a real mail transport and HTTPS before sending. A transport acceptance does not guarantee delivery. Staff can be suspended, reactivated or assigned a role; the signed-in account and workshop owner are protected. Suspension blocks sign-in and permanently invalidates existing web sessions and remember-me cookies, including after reactivation. The optional mechanic permission exposes read-only customer and vehicle lists, not editing, vehicle history, invoices or payments. Existing self-registration requests awaiting owner approval are not processed by this invitation-only workflow; existing accounts cannot accept an invitation. Apply the central migration and test the email and access flows with synthetic accounts before deployment.

To run `InvoiceTest` or `StaffAdministrationTest`, configure PHPUnit with a disposable MySQL database and credentials that can create and drop tenant databases. The central migrations use MySQL-specific SQL; do not run the test suite against a real workshop database.

Password recovery is available from **Forgot Password?** on the login page for all account roles. Apply central migrations, set `APP_URL` to the correct public HTTPS origin, and configure a real mail transport before use. Recovery links expire after 60 minutes and are single-use; requests are rate limited and return the same message whether or not an account exists. Mail failures are logged without tokens or addresses; log/array mailers do not deliver. Recovering a password does not unlock or reactivate a suspended account. New passwords across registration, invitations, recovery, profile changes and owner administration require at least eight characters and at most 72 bytes (bcrypt's limit); existing passwords remain valid until changed. Password changes invalidate outstanding recovery links, remember-me cookies and existing web sessions on their next request. The current profile session remains signed in; API tokens are not revoked. A system-admin-generated temporary password must be changed on the profile page before further use. Verify recovery with a test mailbox before live deployment.

After three failed sign-in attempts for the same email and IP, Google reCAPTCHA v2 must be completed before another password attempt; the existing five-failure account lockout still applies. Configure `RECAPTCHA_SITE_KEY`, `RECAPTCHA_SECRET_KEY`, the public HTTPS `APP_URL` and a matching reCAPTCHA domain before deploying. Verification contacts Google, checks the returned hostname against `APP_URL`, and fails closed if configuration or the service is unavailable. Use an appropriately shared rate-limit cache for multi-server deployments. Test with provider test keys in a separate environment.

Under **Profile → Authenticator**, system admins and active Enterprise account/workshop admins can optionally enroll an authenticator app by entering the displayed secret manually and confirming a six-digit code. Save the one-use recovery codes securely; they are shown only once. An enrolled account must verify before staff-access changes, payment/refund/credit-note operations, payment-setting/QR changes, system-admin owner/workshop management and impersonation, and account workshop changes or sandbox checkout actions. Verification lasts five minutes; if it expires before submission the action is not performed, so verify and submit it again. Disabling requires the current password and an unused authenticator or recovery code. Verification remains required for an already-enrolled account after a plan change. MFA is for these selected actions, not a second factor at login. There is no self-service recovery if both the authenticator and all recovery codes are lost; establish a secure support recovery procedure before enabling it. Preserve `APP_KEY` securely for restoring encrypted authenticator secrets. SSO remains unimplemented.

Under **Profile → Manage device sessions**, users can inspect and revoke their own tracked web sessions. Revocation takes effect on the device's next request and invalidates remember-me cookies on all devices; it does not revoke API tokens. Existing sessions are tracked on their next authenticated request; older legacy session-history entries are not treated as active devices. Device management and authenticator enrollment/disablement are unavailable while impersonating.

Under **Profile**, users can edit their name, phone, bio and avatar. New JPEG, PNG or WebP avatars (maximum 2 MB) are stored privately; existing legacy public avatars remain public until replaced or removed. Back up `storage/app/profile-avatars` along with the central database. Account email changes require the current password and a one-hour, single-use confirmation link sent to the new address; the requesting account must be signed in to confirm. A new request replaces the previous link, and password or account-email changes invalidate it. Configure a real mail transport and the public HTTPS `APP_URL`, and test with a real mailbox before deployment. Account email is separate from the workshop's customer email; changing it does not change the linked customer record or reminder consent. Email changes are unavailable during impersonation.

Language, timezone and preferred contact method are stored preferences only: the UI remains English, workshop workflows continue to use Malaysia time, and SMS/WhatsApp delivery is not implemented. Linked customers can manage existing vehicle-reminder email consent in their customer portal; invoice emails and payment reminders are controlled by the workshop. Other notification settings and applied localization remain pending.

Under **Calendar**, book a service for an existing vehicle, change its time, and cancel it. The weekly calendar uses Malaysia time. Overlapping bookings for the same vehicle are blocked, while different vehicles can be booked in parallel. Cancellations stay visible and release the slot. Customers can request appointments but cannot book them directly; there are no customer notifications.

Under **Services & pricing**, workshop admins maintain active/inactive services and MYR prices. They can create packages from active services, use the displayed sum or set a different package price. A package retains the service names and prices captured when it is saved; later service changes do not change it until it is edited and saved again. An active package remains available even if a component is later made inactive. The workshop can enter its own default invoice tax rate; consult an accountant rather than assuming a statutory rate.

Linked, registered customers can use **Request an appointment** to select a package or one or more active services, add notes and request a future time for their own vehicle. The workshop sees pending requests under **Calendar → Appointment requests** and must review capacity and confirm or reject each one. Confirmation checks same-vehicle conflicts, not workshop capacity; no notifications are sent. The portal shows the result and the current time or cancellation status of confirmed appointments, but customers must contact the workshop to change or cancel them. Workshop admins can also choose services for a booking or a walk-in job, or continue entering a free-text description. Prices shown for requests and bookings are estimates, not payments or automatically created invoices. Service selections and estimate prices are snapshotted when requested or booked.

Invoices support an optional invoice-wide percentage discount and a user-entered percentage tax rate, with inclusive or exclusive tax selected per invoice. The discount is rounded to the nearest sen first; tax is then calculated on the discounted amount and rounded to the nearest sen. Inclusive tax is part of the discounted total, while exclusive tax is added. Rates and amounts are saved on each invoice, so later settings changes do not alter it; unpaid invoices may still be edited. The default tax rate prefills the invoice form, and existing invoices are unchanged until edited. These calculations are a beta convenience, not legal or MyInvois compliance; verify rates, exemptions, rounding and document requirements with a qualified adviser. Apply the tenant migrations before using this functionality.

**Reports** are available to workshop admins on usable Pro/Enterprise subscriptions and during an active trial. Select a Malaysia-time month (`YYYY-MM`) or year (`YYYY`, 2000–2099). The invoice total includes currently issued invoices created in that period, including their invoice discount and tax; credit notes, payments and refunds are counted separately in the periods when they were recorded, including for older invoices. Voided invoices are excluded based on their current status, so historical figures can change after a void or an unpaid-invoice edit. Customer spend is payments minus refunds recorded in the period, not the value of invoices issued. Completed jobs are counted for the assigned mechanic at completion, with unassigned and unavailable former staff shown separately; they do not measure quality or hours. Amounts are operational figures, not accounting statements.

On an invoice line, select an optional service tag to include that line in gross service reporting. Enter its description and price separately; a line cannot be tagged both as a service and as inventory. Tags snapshot the service name when added, and existing inactive tags can remain on an edited invoice. Historical untagged lines are not guessed or backfilled. The service report excludes invoice-wide discounts, credit notes, payments and refunds. For inclusive tax, it extracts the pre-discount tax separately from each line and rounds to the nearest sen; added tax is excluded. It is not net service revenue, and line-level rounding need not match the invoice tax. No new migration is needed for report tags, which are stored with invoice line snapshots.

Open a **Job card** for an existing vehicle, or use **Open job card** on a scheduled appointment (one card per appointment). On the card, use **Change assignment** to assign an active mechanic from the same workshop or leave it unassigned. Mechanics can open **My jobs** to view and advance only jobs assigned to them. Admins can change or clear the assignment until the job is completed. Existing jobs start unassigned. Assignment does not send notifications or generate invoices. Back up the database and apply the tenant migrations before deploying.

Sign in as an active mechanic to check **My dashboard**: it shows your open/in-progress and overdue assigned job counts and your assigned jobs with scheduled appointments today in Malaysia time. Cancelled and completed jobs, other mechanics' jobs, and unassigned jobs are excluded. Use **My jobs** to see all assigned cards or filter to overdue ones.

When opening a job card, set an optional target completion date. A job is overdue from the day after that date while it remains open or in progress; undated and completed jobs are not overdue. The date is displayed on the job card and in job lists, with overdue counts on workshop-admin and mechanic dashboards. Dates are interpreted in Malaysia time.

On an open or in-progress job, an eligible workshop admin or the assigned mechanic can record inventory parts used. Stock is deducted and an append-only inventory movement and job-parts history are recorded together. Unused quantities can be returned in partial amounts before the job is completed; over-returns and stock-limit violations are rejected. Only eligible Pro/Enterprise or trial workshops can use inventory, and mechanics can only change parts for jobs assigned to them. Part history keeps the item name and SKU as recorded. This does not automatically add parts to invoices or change invoice totals. Back up the pilot database and apply the tenant migrations, including the job deadline/parts migration, before deployment.

On a job card, its assigned mechanic or a workshop admin can add service notes. The timeline records who opened the card, status changes, and each note; notes are encrypted using Laravel's application key, append-only, and also appear in **Vehicles → History**. Preserve that key for database restores. This history starts at deployment; existing job cards are not backfilled. Mechanics can only see or add notes to their assigned jobs. Avoid entering passwords, payment-card details, or other unnecessary sensitive information in notes. Back up the pilot database and apply the tenant migrations before deployment.

On an active assigned job, a mechanic can append an odometer reading, vehicle condition, or service note. At least one value is required; condition and note text are encrypted at rest. The update appears in the job timeline and **Vehicles → History** without changing the vehicle's master record. Completed jobs cannot receive further vehicle updates.

Under **Inventory**, eligible Pro and Enterprise workshop admins and active trial workshops can manage suppliers and create purchase orders for inventory items. Orders do not change stock until a receipt is recorded; partial receipts are supported, and each received quantity adds a linked inventory movement. An unreceived order can be cancelled, while an order with any received stock stays in the ledger. Suppliers can be marked inactive without removing purchase history. The purchase-order flow does not record supplier payments or costs. Inventory items also support manual stock in/out and show low-stock status at or below their threshold. On an invoice, select an inventory item on any applicable line to deduct its quantity when the invoice is created. Deductions are atomic with invoice creation, cannot overdraw stock, and are not reversed by payment. Editing an unpaid invoice atomically adjusts linked stock; voiding one restores it in an append-only movement. Stock is not deducted automatically for unlinked lines. Eligible workshop admins see up to five low-stock items and the total count on the dashboard; inventory screens, invoice stock deductions and alerts require an eligible Pro/Enterprise subscription or active trial. Job parts are deducted only when manually recorded on the job card.

Back up the workshop databases and apply the tenant migrations before deploying invoice editing/voiding. Invoice line items retain the selected SKU for reference, and each stock movement links to the invoice for audit.

On an invoice, use **Record payment** for money already received outside the app. In the smoke test, record a partial payment and check the remaining balance, then record the rest and confirm the invoice shows **Paid**. In the payment history, select **View receipt** to print or save an acknowledgment for an individual payment. It is not a MyInvois-validated document and does not show internal references or the current balance. Existing invoices have no recorded payments until you enter them. The app does not process payments; recorded payments remain immutable and are never deleted or reversed in place. Record a separate refund after money is returned externally.

Under **Vehicles**, select **History** to find that vehicle's appointments, job cards, and invoices. Only records linked to the vehicle appear; invoices created without a vehicle are not included. No new migration is needed for service history.

Workshop admins and portal clients can record a vehicle's optional year, colour, mileage in kilometres and fuel type. Admins must link a vehicle to a workshop customer record; the customer need not have a portal account. Additional-vehicle requests retain these details for approval. Master mileage is an independently entered value: mileage recorded on a job stays in its service history and does not automatically update the master record. Existing vehicles have no master mileage or fuel type until entered. Back up the tenant databases and apply the vehicle master-details migration before using these fields.

Under **Payment settings**, each workshop admin can save up to two gateway preferences and select offline methods separately. The Malaysia-focused catalog includes [PayNet FPX merchant banks and acquirers](https://paynet.my/business-solutions/fpx/contact-your-preferred-banks-tpa.html) and additional providers; choose **Other provider** if yours is missing. Listings are not endorsements or a guarantee of merchant eligibility. Saving preferences does not collect credentials, change invoices, or activate live checkout.

To try Stripe on staging, set `STRIPE_SANDBOX_ENABLED=true`, provide a Stripe test secret as `STRIPE_TEST_SECRET` in the deployment environment, and verify `APP_URL` points to the staging site. Refresh Laravel's config cache if used. Save **Stripe Malaysia** in **Payment settings**, then choose **Open Stripe test checkout** and use [Stripe test card details](https://docs.stripe.com/testing) only. The demo sends a fixed RM 10.00 charge request in test mode with no invoice or customer data. Returning in the same browser session verifies the result directly with Stripe; it does not write to the payment ledger. No new migration is needed. Leave this disabled on the live pilot; live checkout and webhook reconciliation are not implemented.

To try toyyibPay on staging, create a category in your [toyyibPay sandbox account](https://dev.toyyibpay.com). Set `TOYYIBPAY_SANDBOX_ENABLED=true`, `TOYYIBPAY_TEST_SECRET`, and `TOYYIBPAY_TEST_CATEGORY` using that sandbox account. Use the same `APP_URL` and config-cache setup as above. Save **toyyibPay** under **Payment settings**, open the test checkout, and use the FPX bank simulator. Return in the same browser session to verify the RM 10.00 demo payment. The app contacts only the sandbox API and does not send customer or invoice data, process callbacks, or record the payment. Leave it disabled on the live pilot. No new migration is needed.

To try Billplz, create a collection in a [Billplz sandbox account](https://www.billplz-sandbox.com/) and [enable its X Signature key](https://support.billplz.com/guide/enable-x-signature-key). On staging, set `BILLPLZ_SANDBOX_ENABLED=true` and supply `BILLPLZ_TEST_SECRET`, `BILLPLZ_TEST_COLLECTION`, and `BILLPLZ_TEST_SIGNATURE_KEY` from that sandbox account. Set `APP_URL` to the public HTTPS staging URL and refresh the config cache if used. Billplz must be able to POST to `/api/sandbox/billplz/callback` without a login. Save **Billplz** under **Payment settings**, select **Open Billplz test checkout**, and use the [sandbox payment simulator](https://support.billplz.com/guide/sandbox-testing). Return in the same signed-in browser session to check the RM 10.00 demo result. The app acknowledges signed callbacks and checks the bill with the sandbox API on return. It sends no real customer or invoice data and records no payment. No migration is needed; leave this disabled on the live pilot.

---

## 📌 Repository
🔗 **GitHub:** [nfsshop](https://github.com/afiqy/nfsshop.git)

---

📌 License
MIT License - Feel free to use and modify.
