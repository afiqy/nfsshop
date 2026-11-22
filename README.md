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

After deploying to the beta environment, back up the databases and run `php artisan tenants:migrate --tenants=YOUR_BETA_TENANT_ID` for each beta workshop with a provisioned tenant database. New workshops get the tables during provisioning. No seeding or new central migration is required.

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

To run `InvoiceTest`, configure PHPUnit with a disposable MySQL database and credentials that can create and drop tenant databases. The central migrations use MySQL-specific SQL; do not run the test suite against a real workshop database.

Under **Calendar**, book a service for an existing vehicle, change its time, and cancel it. The weekly calendar uses Malaysia time. Overlapping bookings for the same vehicle are blocked, while different vehicles can be booked in parallel. Cancellations stay visible and release the slot. There are no customer notifications or self-service bookings yet.

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
