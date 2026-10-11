# Restaurant System — Implementation Tasks

## Phase 1: Foundation
- [x] T-001: Install Laravel 13 (done), install spatie/laravel-permission, scramble, laravel-reverb, laravel-horizon, laravel-octane, laravel-scout, meilisearch driver
- [x] T-002: Configure .env for MySQL, Redis, Reverb, Scout, Mail
- [ ] T-003: Set up versioned API routing (/api/v1), base API controller with JSON envelope helper
- [ ] T-004: Vue 3 + Vite + TypeScript + Tailwind CSS scaffolding in /resources/js
- [ ] T-005: Pinia stores scaffold, Vue Router with lazy routes and role guards
- [ ] T-006: TanStack Vue Query, VeeValidate, Zod, vue-i18n, lucide-vue-next setup
- [ ] T-007: Docker Compose (app, mysql, redis, meilisearch, mailpit, reverb)
- [ ] T-008: GitHub Actions CI pipeline

## Phase 2: Database & Models
- [ ] T-009: Migrations for all 35+ tables
- [ ] T-010: Eloquent models with relationships, casts, scopes, preventLazyLoading
- [ ] T-011: Factories for all models
- [ ] T-012: Seeders: 8 categories, 60 dishes, 20 tables, staff per role, 200 customers, sample orders

## Phase 3: Auth & Menu APIs
- [ ] T-013: Auth endpoints (register, login, logout, forgot/reset, verify email, 2FA)
- [ ] T-014: Category and dish API endpoints with filters, cursor pagination, ETags
- [ ] T-015: Search endpoint with Meilisearch
- [ ] T-016: API Resources for all response shapes

## Phase 4: Cart, Orders, Payments
- [ ] T-017: Cart API (guest session + user server cart, merge on login)
- [ ] T-018: Order creation with server-side price recalculation, idempotency keys
- [ ] T-019: Order status tracking endpoints
- [ ] T-020: Payment gateway interface + Stripe implementation
- [ ] T-021: Chapa/Telebirr gateway implementation
- [ ] T-022: Webhook endpoints with signature verification
- [ ] T-023: Refund and cancellation logic

## Phase 5: Reservations & Real-time
- [ ] T-024: Reservation availability check and CRUD
- [ ] T-025: Reverb channels and events (OrderStatusChanged, KitchenTicket, etc.)
- [ ] T-026: Staff APIs (kitchen, waiter, cashier, delivery)
- [ ] T-027: Notification system (email, SMS, in-app, web push)

## Phase 6: Reviews, Loyalty, Admin
- [ ] T-028: Reviews, favorites, loyalty points, address book
- [ ] T-029: Admin CRUD for all entities
- [ ] T-030: Reports with PDF/Excel export
- [ ] T-031: Settings, audit log, activity logging

## Phase 7: Frontend
- [ ] T-032: Design system (CSS variables, tokens, typography)
- [ ] T-033: Base UI components (Button, Input, Modal, Toast, Skeleton, Badge, DataTable)
- [ ] T-034: Layouts (Default, Auth, Admin, Staff)
- [ ] T-035: Home page
- [ ] T-036: Menu page with filters and dish modal
- [ ] T-037: Cart drawer + checkout flow
- [ ] T-038: Order tracking with Echo WebSocket
- [ ] T-039: Reservation page
- [ ] T-040: Auth pages
- [ ] T-041: Profile pages
- [ ] T-042: Static pages
- [ ] T-043: Kitchen Display System
- [ ] T-044: Waiter screen
- [ ] T-045: Cashier POS
- [ ] T-046: Admin dashboard and all admin pages
- [ ] T-047: PWA manifest and service worker
- [ ] T-048: i18n (English + Amharic)
- [ ] T-049: Dark mode

## Phase 8: Performance, Security, Testing
- [ ] T-050: Redis caching (menu, settings), HTTP ETags, cache tags
- [ ] T-051: Octane configuration
- [ ] T-052: Security headers middleware, CORS, rate limiting
- [ ] T-053: Pest feature tests for all API routes
- [ ] T-054: Vitest unit tests for stores and composables
- [ ] T-055: Playwright E2E tests (browse, order, pay, reserve, kitchen flow)
- [ ] T-056: k6 load test scripts (ramp to 50,000 VUs)
- [ ] T-057: Scramble OpenAPI docs
- [ ] T-058: README, architecture doc, deployment guide, runbook
- [ ] T-059: CI emoji check
