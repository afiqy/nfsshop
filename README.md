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

On the **Dashboard**, check the workshop name and plan, today's scheduled appointments (Malaysia time), open and overdue jobs, and the total invoice balance after recorded payments. The overdue job count links to **Job cards → Overdue only**; the overdue invoice subtotal links to **Invoices → Overdue only**. Quick actions open the forms for new invoices, job cards, appointments, and vehicles. Staff availability and configurable widgets are not included.

On a new invoice, optionally set a payment due date of today or later (Malaysia time). The date appears on the invoice and its printout. An invoice is overdue only from the day after its due date and only while a balance remains; undated invoices, including existing ones, are not flagged. Due dates are not editable after creation and do not send reminders. Back up the pilot database and run `php8.4 artisan tenants:migrate --tenants=YOUR_BETA_TENANT_ID` before using this version.

For **MyInvois preparation**, an active workshop admin can open **Invoices → MyInvois workshop tax profile** to save supplier details, then use **MyInvois preparation** on an invoice to save or clear its named buyer details. Both forms are limited to Malaysian addresses and perform local format checks only. Saved details are encrypted in the workshop database using Laravel's application key; retain that key securely for database restores. Tax identifiers are not added to the normal invoice printout. Smoke-test with synthetic details, not real taxpayer identifiers. No document is generated, signed, submitted, or validated, and line tax/classification and consolidated invoicing are not supported. Continue using the workshop's official invoicing process. Apply the tenant migrations before opening the preparation screens.

For the live pilot, use a dedicated workshop account (not the example credentials), HTTPS, `APP_DEBUG=false`, and backups with a tested restore. Keep real customer records out of development databases and bug reports. Confirm the workshop's entitlement covers the pilot before admitting real data. Customer and vehicle deletion and client self-service are out of scope. The beta cannot edit, void, or email invoices, collect payments, or submit invoices to LHDN MyInvois; do not use it as the sole system for official invoices.

To run `InvoiceTest`, configure PHPUnit with a disposable MySQL database and credentials that can create and drop tenant databases. The central migrations use MySQL-specific SQL; do not run the test suite against a real workshop database.

Under **Calendar**, book a service for an existing vehicle, change its time, and cancel it. The weekly calendar uses Malaysia time. Overlapping bookings for the same vehicle are blocked, while different vehicles can be booked in parallel. Cancellations stay visible and release the slot. There are no customer notifications or self-service bookings yet.

Open a **Job card** for an existing vehicle, or use **Open job card** on a scheduled appointment (one card per appointment). On the card, use **Change assignment** to assign an active mechanic from the same workshop or leave it unassigned. Mechanics can open **My jobs** to view and advance only jobs assigned to them. Admins can change or clear the assignment until the job is completed. Existing jobs start unassigned. Assignment does not send notifications or generate invoices. Back up the database and apply the tenant migrations before deploying.

Sign in as an active mechanic to check **My dashboard**: it shows your open/in-progress and overdue assigned job counts and your assigned jobs with scheduled appointments today in Malaysia time. Cancelled and completed jobs, other mechanics' jobs, and unassigned jobs are excluded. Use **My jobs** to see all assigned cards or filter to overdue ones.

When opening a job card, set an optional target completion date. A job is overdue from the day after that date while it remains open or in progress; undated and completed jobs are not overdue. The date is displayed on the job card and in job lists, with overdue counts on workshop-admin and mechanic dashboards. Dates are interpreted in Malaysia time.

On an open or in-progress job, an eligible workshop admin or the assigned mechanic can record inventory parts used. Stock is deducted and an append-only inventory movement and job-parts history are recorded together. Unused quantities can be returned in partial amounts before the job is completed; over-returns and stock-limit violations are rejected. Only eligible Pro/Enterprise or trial workshops can use inventory, and mechanics can only change parts for jobs assigned to them. Part history keeps the item name and SKU as recorded. This does not automatically add parts to invoices or change invoice totals. Back up the pilot database and apply the tenant migrations, including the job deadline/parts migration, before deployment.

On a job card, its assigned mechanic or a workshop admin can add service notes. The timeline records who opened the card, status changes, and each note; notes are encrypted using Laravel's application key, append-only, and also appear in **Vehicles → History**. Preserve that key for database restores. This history starts at deployment; existing job cards are not backfilled. Mechanics can only see or add notes to their assigned jobs. Avoid entering passwords, payment-card details, or other unnecessary sensitive information in notes. Back up the pilot database and apply the tenant migrations before deployment.

On an active assigned job, a mechanic can append an odometer reading, vehicle condition, or service note. At least one value is required; condition and note text are encrypted at rest. The update appears in the job timeline and **Vehicles → History** without changing the vehicle's master record. Completed jobs cannot receive further vehicle updates.

Under **Inventory**, eligible Pro and Enterprise workshop admins and active trial workshops can manage suppliers and create purchase orders for inventory items. Orders do not change stock until a receipt is recorded; partial receipts are supported, and each received quantity adds a linked inventory movement. An unreceived order can be cancelled, while an order with any received stock stays in the ledger. Suppliers can be marked inactive without removing purchase history. The purchase-order flow does not record supplier payments or costs. Inventory items also support manual stock in/out and show low-stock status at or below their threshold. Invoice-linked stock deduction and dashboard low-stock alerts are not included; job parts are deducted only when manually recorded on the job card.

On an invoice, use **Record payment** for money already received outside the app. In the smoke test, record a partial payment and check the remaining balance, then record the rest and confirm the invoice shows **Paid**. In the payment history, select **View receipt** to print or save an acknowledgment for an individual payment. It is not a MyInvois-validated document and does not show internal references or the current balance. Existing invoices have no recorded payments until you enter them. The app does not process payments, and entries cannot be edited, deleted, or reversed in the pilot.

Under **Vehicles**, select **History** to find that vehicle's appointments, job cards, and invoices. Only records linked to the vehicle appear; invoices created without a vehicle are not included. No new migration is needed for service history.

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
