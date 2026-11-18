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

After deploying to the beta environment, back up the databases and run `php artisan tenants:migrate --tenants=YOUR_BETA_TENANT_ID` for each beta workshop with a provisioned tenant database. New workshops get the table during provisioning. No invoice seeder or new central migration is required.

Use an active or trialing workshop-admin account and a workshop that already has a test customer (and optionally a test vehicle). Do not use the example credentials above on a shared beta. Go to **Invoices > New invoice**. Select the customer, add a few MYR line items, save, confirm the totals, and try **Print / Save as PDF**. Sign in as a different workshop admin and confirm the invoice is not in that workshop's list. Use synthetic data only: this beta cannot edit, void, email, collect payments, or submit invoices to LHDN MyInvois.

To run `InvoiceTest`, configure PHPUnit with a disposable MySQL database and credentials that can create and drop tenant databases. The central migrations use MySQL-specific SQL; do not run the test suite against a real workshop database.

---

## 📌 Repository
🔗 **GitHub:** [nfsshop](https://github.com/afiqy/nfsshop.git)

---

📌 License
MIT License - Feel free to use and modify.
