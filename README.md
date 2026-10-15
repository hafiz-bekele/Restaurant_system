# 🍷 Taste & Stay Bistro & Boutique Suites

> Modern, full-stack restaurant management, dining ordering system, and boutique hotel guest experience platform.

---

## 🌟 Key Capabilities

### 🍽️ Customer & Guest Dining Experience
- **Frictionless Guest Checkout**: Order and pay in under 60 seconds without login or account registration.
- **Local & International Payment Gateways**: Full support for Chapa (Telebirr, CBE Birr, Ethiopian Banks), Stripe credit/debit cards, and Cash on Arrival.
- **Live Order Tracking**: Real-time multi-stage order tracking (Pending ➔ Confirmed ➔ Preparing ➔ Ready ➔ Delivered).
- **Printable Tax Invoices & Guest Receipts**: Itemized receipts with VAT (15%), service charges (10%), QR expedite verification, and clean `@media print` styling.
- **Interactive Landing Page**:
  - Quick Table Reservation Bar with instant prefill.
  - 4-Way Experience Switcher (Fine Dining, Boutique Suites, Sky Terrace, QR Express).
  - Filterable culinary showcase with spice meters and chef badges.
  - Ethiopian Coffee Ceremony (*Buna*) featurette and Executive Chef spotlight.
  - Interactive photo gallery with lightbox modal and customer review carousel.

### 👨‍🍳 Operational Staff Portals
- **Kitchen Display System (KDS)** (`/staff/kitchen`):
  - 3-column Kanban queue (New Orders, Cooking, Ready).
  - Real-time SLA aging timers with color-coded warnings.
  - Web Audio API acoustic chime ding when new tickets arrive.
  - Guest customer name, phone number, and special preparation notes displayed on tickets.
  - 86 Stock Availability manager to mark sold-out items instantly.
- **Waiter & Floor Service Portal** (`/staff/waiter`):
  - Interactive floor plan with table statuses (Available, Occupied, Reserved).
  - Kitchen dispatch ready alerts with pleasant triangle chime synthesis.
  - 1-click "Mark Delivered & Served" action.
- **Cashier POS & Bill Settlement** (`/staff/cashier`): Split billing, cash reconciliation, and invoice generation.
- **Front Desk Reception** (`/staff/reception`): Room service QR ordering and suite bookings.

---

## 🛠️ Technology Stack

| Layer | Technologies |
|---|---|
| **Frontend** | Vue 3 (Composition API), Vite, Pinia, Tailwind CSS, Lucide Icons, Web Audio API |
| **Backend** | Laravel 11, PHP 8.3, Sanctum, Spatie Permission, Eloquent ORM |
| **Database** | MySQL / SQLite (with ULID primary route keys and soft deletes) |
| **Payments** | Chapa API (Telebirr, CBE Birr, Local Cards), Stripe, Cash On Delivery |

---

## 👥 Demo Staff & Test Accounts

All demo accounts share the default development password: `password`

| Role | Email | Access Scope |
|---|---|---|
| **Super Admin** | `superadmin@restaurant.com` | Full multi-tenant system administration |
| **Hotel Admin** | `admin@restaurant.com` | Property management, staff accounts, analytics |
| **Restaurant Manager** | `manager@restaurant.com` | Menu catalog, shifts, and inventory control |
| **Head Chef** | `kitchen@restaurant.com` | Kitchen Display System (`/staff/kitchen`) |
| **Floor Server** | `waiter@restaurant.com` | Waiter floor plan & ready alerts (`/staff/waiter`) |
| **Cashier** | `cashier@restaurant.com` | Billing, payments, POS settlement (`/staff/cashier`) |
| **Front Desk** | `reception@restaurant.com` | Boutique hotel suites & reservations (`/staff/reception`) |
| **Sample Customer**| `customer@restaurant.com` | Registered diner with saved order history |

---

## 🚀 Getting Started

### Prerequisites
- PHP >= 8.2 with Composer
- Node.js >= 18 with npm
- MySQL or SQLite

### 1. Backend Setup (Laravel)
```bash
cd server

# Install PHP dependencies
composer install

# Copy environment configuration
cp .env.example .env

# Generate application encryption key
php artisan key:generate

# Run migrations and seed sample menu & accounts
php artisan migrate:fresh --seed

# Start Laravel development server (runs on http://127.0.0.1:8000)
php artisan serve
```

### 2. Frontend Setup (Vue 3 + Vite)
```bash
cd client

# Install JavaScript packages
npm install

# Start Vite hot-reloading dev server (runs on http://localhost:5173)
npm run dev
```

---

## 📄 License
This project is open-source software licensed under the MIT license.
