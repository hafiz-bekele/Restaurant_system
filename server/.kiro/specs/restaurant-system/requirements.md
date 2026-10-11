# Restaurant System — Requirements

## 1. Project Purpose
A production-ready, sellable restaurant web platform for online ordering, table reservation, kitchen operations, payments, and admin management.

## 2. User Roles
| Role | Capabilities |
|------|-------------|
| Guest | Browse menu, add to cart (local), view info |
| Customer | Orders, reservations, addresses, favorites, reviews, loyalty |
| Waiter | Create/edit dine-in orders, manage tables |
| Kitchen | View/update ticket preparation status |
| Cashier | Take payments, print receipts, close orders |
| Delivery | View assignments, update delivery status |
| Manager | Reports, staff, inventory, promotions |
| Super Admin | Everything including settings and role management |

## 3. Functional Requirements

### 3.1 Customer Website
- US-001: As a guest I can browse the menu by category without logging in
- US-002: As a customer I can search dishes with instant suggestions
- US-003: As a customer I can filter dishes by vegetarian, vegan, spicy, price, allergens, calories
- US-004: As a customer I can view dish details with gallery, variants, add-ons, reviews
- US-005: As a customer I can add items to a persistent cart (local guest / server user)
- US-006: As a customer I can checkout with order type: delivery, pickup, or dine-in QR
- US-007: As a customer I can apply coupons and see real-time price recalculation
- US-008: As a customer I can pay with card (Stripe), mobile money (Chapa/Telebirr), or cash
- US-009: As a customer I can track my order live with real-time status steps via WebSocket
- US-010: As a customer I can reserve a table with date, time, party size, and availability check
- US-011: As a customer I can scan a QR code at a table and order directly (dine-in QR flow)
- US-012: As a customer I can register, login, use social login (Google), and verify email
- US-013: As a customer I can manage profile, addresses, order history, favorites, loyalty points
- US-014: As a customer I can leave verified-purchase reviews and ratings
- US-015: As a customer I can use coupons, loyalty rewards, and gift cards
- US-016: As a guest I can checkout without registering
- US-017: As a customer I can use the app in English or Amharic
- US-018: As a customer I can toggle light/dark mode
- US-019: As a customer I can install the PWA and browse menu offline

### 3.2 Staff Applications
- US-020: As a waiter I can view the table map, open/close tables, send orders to kitchen
- US-021: As a waiter I can split bills and transfer tables
- US-022: As a kitchen staff I see live ticket board (new/preparing/ready) with timers
- US-023: As a kitchen staff I receive audio alerts for new and delayed tickets
- US-024: As a cashier I can take orders, process payments, print receipts, close day
- US-025: As a delivery driver I can view assigned orders, update status, log proof of delivery

### 3.3 Admin Dashboard
- US-026: As an admin I can view today's sales, orders, revenue chart, peak hours heatmap
- US-027: As an admin I can manage menu (categories, dishes, variants, add-ons, images)
- US-028: As an admin I can manage orders (filter, change status, refund, cancel, print)
- US-029: As an admin I can manage the floor plan (tables, areas, positions)
- US-030: As an admin I can manage customers (list, profile, block/unblock)
- US-031: As an admin I can manage staff (create accounts, assign roles, shifts, activity log)
- US-032: As an admin I can manage inventory (ingredients, stock, recipes, suppliers, alerts)
- US-033: As an admin I can manage promotions (coupons, banners, loyalty rules, gift cards)
- US-034: As an admin I can view reports (sales, tax, staff) and export to PDF/Excel
- US-035: As an admin I can configure settings (hours, delivery zones, payments, theme, SEO)
- US-036: As an admin I can view the audit log of all admin actions

### 3.4 Notifications
- US-037: System sends order confirmation and status change notifications via email, SMS, in-app, and web push
- US-038: System sends reservation confirmation and reminders
- US-039: Staff receives new order alerts with sound and toast

## 4. Non-Functional Requirements
- NFR-001: API p95 response < 500 ms under 50,000 concurrent users
- NFR-002: Lighthouse score >= 90 on Home, Menu, Checkout pages
- NFR-003: Zero emoji in any part of the repository
- NFR-004: WCAG 2.1 AA accessibility compliance
- NFR-005: OWASP Top 10 protections in place
- NFR-006: Backend test coverage >= 80%
- NFR-007: Zero-downtime deployment
- NFR-008: Uptime target 99.9%

## 5. Acceptance Criteria
- All functional requirements work end-to-end on desktop and mobile
- k6 load test passes at 50,000 virtual users: p95 < 500 ms, error rate < 1%
- Lighthouse 90+ on home, menu, checkout
- No emoji in repository (CI check enforced)
- Security checklist passed
- Tests green
- Working demo with seed data
