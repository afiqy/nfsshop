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

For the live pilot, use a dedicated workshop account (not the example credentials), HTTPS, `APP_DEBUG=false`, and backups with a tested restore. Keep real customer records out of development databases and bug reports. Confirm the workshop's entitlement covers the pilot before admitting real data. Customer and vehicle deletion and client self-service are out of scope. The beta cannot edit, void, or email invoices, collect payments, or submit invoices to LHDN MyInvois; do not use it as the sole system for official invoices.

To run `InvoiceTest`, configure PHPUnit with a disposable MySQL database and credentials that can create and drop tenant databases. The central migrations use MySQL-specific SQL; do not run the test suite against a real workshop database.

Under **Calendar**, book a service for an existing vehicle, change its time, and cancel it. The weekly calendar uses Malaysia time. Overlapping bookings for the same vehicle are blocked, while different vehicles can be booked in parallel. Cancellations stay visible and release the slot. There are no customer notifications or self-service bookings yet.

Open a **Job card** for an existing vehicle, or use **Open job card** on a scheduled appointment (one card per appointment). Start work, then mark the job completed. Mechanic assignment and automatic invoicing are not part of this pilot.

Under **Inventory**, eligible Pro and Enterprise workshop admins and active trial workshops can add an item, record its opening balance as stock in, and record later stock in/out with a reason. Check that the movement history shows the resulting balance and that the item is marked low stock at or below its threshold. Suppliers, purchase orders, and automatic stock changes from invoices or jobs are not included.

On an invoice, use **Record payment** for money already received outside the app. In the smoke test, record a partial payment and check the remaining balance, then record the rest and confirm the invoice shows **Paid**. Existing invoices have no recorded payments until you enter them. This is an internal history, not a payment processor or receipt. Entries cannot be edited, deleted, or reversed in the pilot.

Under **Vehicles**, select **History** to find that vehicle's appointments, job cards, and invoices. Only records linked to the vehicle appear; invoices created without a vehicle are not included. No new migration is needed for service history.

---

## 📌 Repository
🔗 **GitHub:** [nfsshop](https://github.com/afiqy/nfsshop.git)

---

📌 License
MIT License - Feel free to use and modify.
