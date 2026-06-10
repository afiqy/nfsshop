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

## 📌 Repository
🔗 **GitHub:** [nfsshop](https://github.com/afiqy/nfsshop.git)

---

📌 License
MIT License - Feel free to use and modify.
