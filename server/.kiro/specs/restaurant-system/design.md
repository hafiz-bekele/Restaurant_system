# Restaurant System — Design

## 1. Architecture Overview

```
[Browser / PWA]
      |
   [Cloudflare CDN]
      |
   [Nginx Load Balancer]
      |
[Laravel Octane (multiple nodes)]
      |         |          |
  [MySQL 8]  [Redis 7]  [Reverb WS]
  [Replica]  [Cluster]
      |
  [Horizon]  [Scout/Meilisearch]
      |
  [S3 Storage]  [Mail/SMS]
```

## 2. Technology Decisions
| Concern | Decision | Reason |
|---------|----------|--------|
| Auth | Laravel Sanctum (SPA cookie + token) | Native Laravel, simple, secure |
| Roles | spatie/laravel-permission | Industry standard, flexible |
| Queues | Redis + Laravel Horizon | Visibility, autoscaling workers |
| WebSockets | Laravel Reverb | First-party, Redis pub/sub for scale |
| Search | Laravel Scout + Meilisearch | Fast, typo-tolerant, easy |
| Payments | Gateway interface (Stripe + Chapa) | Swappable, config-driven |
| API Docs | Scramble (OpenAPI) | Auto-generated from routes |
| State | Pinia + pinia-plugin-persistedstate | Official, TS-friendly |
| Data Fetching | TanStack Vue Query | Caching, background refresh |
| Forms | VeeValidate + Zod | Type-safe validation |
| i18n | vue-i18n | Official, lazy-loaded locales |

## 3. API Structure

Base: `/api/v1`

Response envelope:
```json
{
  "data": {},
  "meta": { "page": 1, "per_page": 15, "total": 100 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "errors": null
}
```

### Auth
- POST /auth/register
- POST /auth/login
- POST /auth/logout
- POST /auth/forgot-password
- POST /auth/reset-password
- GET  /auth/me
- POST /auth/verify-email/{id}/{hash}
- POST /auth/2fa/enable
- POST /auth/2fa/verify

### Public Menu
- GET /categories
- GET /dishes (filters: category, search, vegetarian, vegan, spicy, min_price, max_price, allergens; sort: popular, price, rating; cursor pagination)
- GET /dishes/{slug}
- GET /search?q=

### Cart
- GET    /cart
- POST   /cart/items
- PATCH  /cart/items/{id}
- DELETE /cart/items/{id}
- POST   /cart/coupon
- DELETE /cart/coupon

### Orders
- POST   /orders
- GET    /orders
- GET    /orders/{ulid}
- POST   /orders/{ulid}/cancel
- GET    /orders/{ulid}/track

### Payments
- POST /payments/intent
- POST /webhooks/stripe
- POST /webhooks/chapa

### Reservations
- GET  /reservations/availability
- POST /reservations
- GET  /reservations/{id}
- PATCH /reservations/{id}
- DELETE /reservations/{id}

### Profile
- GET/PUT  /profile
- CRUD     /addresses
- GET/POST /favorites
- DELETE   /favorites/{dishId}
- POST     /reviews
- GET      /loyalty

### Staff
- GET   /kitchen/tickets
- PATCH /kitchen/tickets/{id}
- GET   /waiter/tables
- POST  /waiter/orders
- PATCH /waiter/orders/{id}
- POST  /cashier/payments
- GET   /delivery/orders
- PATCH /delivery/orders/{id}

### Admin (all under /admin/)
- CRUD /admin/categories
- CRUD /admin/dishes
- CRUD /admin/orders
- CRUD /admin/reservations
- CRUD /admin/tables
- CRUD /admin/customers
- CRUD /admin/staff
- CRUD /admin/coupons
- CRUD /admin/inventory/ingredients
- CRUD /admin/inventory/suppliers
- CRUD /admin/promotions
- GET  /admin/reports/sales
- GET  /admin/reports/tax
- GET  /admin/reports/staff
- CRUD /admin/settings
- GET  /admin/audit-log

## 4. Database ERD (Key Relationships)

```
users ──< addresses
users ──< orders ──< order_items ──> dishes
orders ──< order_status_history
orders ──< payments
users ──< reservations >── tables
users ──< reviews >── dishes
users ──< favorites >── dishes
users ──< loyalty_transactions
users ──< carts ──< cart_items
dishes >── categories
dishes ──< dish_variants
dishes ──< dish_images
dishes >──< addon_groups ──< addons
dishes >──< allergens (pivot)
dishes >──< ingredients (recipe pivot)
coupons ──< coupon_usages >── users
ingredients ──< stock_movements
```

## 5. Frontend Component Tree

```
App
├── layouts/
│   ├── DefaultLayout (Navbar + Footer)
│   ├── AuthLayout
│   ├── AdminLayout (Sidebar + Topbar)
│   └── StaffLayout
├── pages/
│   ├── Home
│   ├── Menu
│   ├── DishDetail
│   ├── Cart
│   ├── Checkout
│   ├── OrderTracking
│   ├── Reservation
│   ├── auth/ (Login, Register, ForgotPassword, VerifyEmail)
│   ├── profile/ (Orders, Addresses, Favorites, Loyalty)
│   ├── static/ (About, Contact, Gallery, FAQ, Privacy, Terms)
│   ├── admin/ (Dashboard, Menu, Orders, Tables, Customers, Staff, Inventory, Reports, Settings)
│   └── staff/ (Kitchen, Waiter, Cashier, Delivery)
└── components/
    ├── ui/ (Button, Input, Modal, Toast, Badge, Skeleton, Tabs, Dropdown)
    ├── common/ (Navbar, Footer, QuantityStepper, EmptyState, DataTable)
    ├── menu/ (DishCard, DishModal, CategoryChips, SearchBar, FilterPanel)
    ├── cart/ (CartDrawer, CartItem, PromoCode)
    ├── checkout/ (OrderTypeSelector, AddressForm, PaymentMethod, OrderSummary)
    ├── admin/ (StatCard, RevenueChart, FloorPlanEditor, InventoryTable)
    └── kitchen/ (KitchenTicket, TicketBoard, StationFilter)
```

## 6. Real-time Channels (Reverb)
- `orders.{ulid}` — customer order tracking
- `kitchen` — all kitchen tickets
- `admin` — admin dashboard alerts
- `table.{id}` — per-table dine-in orders

## 7. Queue Structure (Horizon)
- `default` — general jobs
- `notifications` — emails, SMS, push
- `payments` — payment processing, webhooks
- `reports` — PDF/Excel generation
- `images` — resize/optimize uploads

## 8. Caching Strategy
- Menu categories: Redis tag `menu`, TTL 1 hour
- Dish listings: Redis tag `menu`, TTL 15 min
- Settings: Redis, TTL 24 hours
- Popular dishes: Redis, TTL 30 min
- HTTP ETags on all GET endpoints
- Cloudflare edge cache on public GET endpoints
