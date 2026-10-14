import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    auth?: boolean
    guest?: boolean
    staffOnly?: boolean
    role?: string
    customerOnly?: boolean
  }
}

const routes = [
  // Public info pages — anyone can view
  { path: '/',               name: 'home',            component: () => import('../views/HomePage.vue') },
  { path: '/about',          name: 'about',           component: () => import('../views/static/AboutPage.vue') },
  { path: '/contact',        name: 'contact',         component: () => import('../views/static/ContactPage.vue') },

  // Customer ordering & booking — open to everyone, blocked only for staff/admin
  { path: '/menu',           name: 'menu',            component: () => import('../views/menu/MenuPage.vue'),               meta: { customerOnly: true } },
  { path: '/menu/:slug',     name: 'dish',            component: () => import('../views/menu/DishPage.vue'),               meta: { customerOnly: true } },
  { path: '/cart',           name: 'cart',            component: () => import('../views/cart/CartPage.vue'),               meta: { customerOnly: true } },
  { path: '/checkout',       name: 'checkout',        component: () => import('../views/cart/CheckoutPage.vue'),           meta: { customerOnly: true } },
  { path: '/reservations',   name: 'reservations',    component: () => import('../views/reservation/ReservationPage.vue'), meta: { customerOnly: true } },
  { path: '/rooms',          name: 'rooms',           component: () => import('../views/guest/RoomsPage.vue'),             meta: { customerOnly: true } },
  { path: '/complaints/new', name: 'complaint.new',   component: () => import('../views/guest/ComplaintPage.vue'),         meta: { customerOnly: true } },
  { path: '/complaints/:ticket', name: 'complaint.show', component: () => import('../views/guest/ComplaintPage.vue') },
  { path: '/qr/:token',      name: 'qr.landing',      component: () => import('../views/guest/QrLandingPage.vue') },

  // Auth — only staff/admin need to log in; guests browse freely
  // Login/register pages redirect staff/admin to their panels if already logged in
  { path: '/login',     name: 'login',    component: () => import('../views/auth/LoginPage.vue'),    meta: { staffOnly: true } },
  { path: '/register',  name: 'register', component: () => import('../views/auth/RegisterPage.vue') },

  // Order tracking — public
  { path: '/orders/:ulid/track', name: 'order.track', component: () => import('../views/order/TrackingPage.vue') },

  // These pages are useful for logged-in customers but not required
  { path: '/orders',    name: 'orders',    component: () => import('../views/order/OrdersPage.vue') },
  { path: '/profile',   name: 'profile',   component: () => import('../views/profile/ProfilePage.vue'),   meta: { auth: true } },
  { path: '/favorites', name: 'favorites', component: () => import('../views/profile/FavoritesPage.vue'), meta: { auth: true } },

  // Staff
  {
    path: '/staff',
    component: () => import('../layouts/StaffLayout.vue'),
    meta: { auth: true, role: 'staff' },
    children: [
      { path: 'kitchen',   name: 'kitchen',   component: () => import('../views/staff/KitchenPage.vue') },
      { path: 'waiter',    name: 'waiter',    component: () => import('../views/staff/WaiterPage.vue') },
      { path: 'cashier',   name: 'cashier',   component: () => import('../views/staff/CashierPage.vue') },
      { path: 'delivery',  name: 'delivery',  component: () => import('../views/staff/DeliveryPage.vue') },
      { path: 'reception', name: 'reception', component: () => import('../views/staff/ReceptionPage.vue') },
    ],
  },

  // Admin
  {
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { auth: true, role: 'admin' },
    children: [
      { path: '',               name: 'admin',                  component: () => import('../views/admin/DashboardPage.vue') },
      { path: 'menu',           name: 'admin.menu',             component: () => import('../views/admin/MenuPage.vue') },
      { path: 'orders',         name: 'admin.orders',           component: () => import('../views/admin/OrdersPage.vue') },
      { path: 'reviews',        name: 'admin.reviews',          component: () => import('../views/admin/ReviewsPage.vue') },
      { path: 'reports',        name: 'admin.reports',          component: () => import('../views/admin/ReportsPage.vue') },
      { path: 'settings',       name: 'admin.settings',         component: () => import('../views/admin/SettingsPage.vue') },
      { path: 'users',          name: 'admin.users',            component: () => import('../views/admin/UsersPage.vue') },
      { path: 'tables',         name: 'admin.tables',           component: () => import('../views/admin/TablesPage.vue') },
      { path: 'reservations',   name: 'admin.reservations',     component: () => import('../views/admin/ReservationsPage.vue') },
      { path: 'coupons',        name: 'admin.coupons',          component: () => import('../views/admin/CouponsPage.vue') },
      { path: 'inventory',      name: 'admin.inventory',        component: () => import('../views/admin/InventoryPage.vue') },
      { path: 'audit',          name: 'admin.audit',            component: () => import('../views/admin/AuditPage.vue') },
      { path: 'complaints',     name: 'admin.complaints',       component: () => import('../views/admin/ComplaintsPage.vue') },
      { path: 'delivery-zones', name: 'admin.delivery-zones',   component: () => import('../views/admin/DeliveryZonesPage.vue') },
      { path: 'rooms',          name: 'admin.rooms',            component: () => import('../views/admin/RoomsPage.vue') },
      { path: 'hotels',         name: 'admin.hotels',           component: () => import('../views/admin/HotelsPage.vue') },
    ],
  },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../views/static/NotFoundPage.vue') },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: () => ({ top: 0, behavior: 'smooth' }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Always resolve the user before evaluating role guards
  if (auth.token && !auth.user) {
    await auth.fetchUser()
  }

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // Login page — if staff/admin already logged in, redirect to their panel
  if (to.meta.staffOnly && auth.isAuthenticated) {
    if (auth.isAdmin)              return { name: 'admin' }
    if (auth.hasRole('kitchen'))   return { path: '/staff/kitchen' }
    if (auth.hasRole('waiter'))    return { path: '/staff/waiter' }
    if (auth.hasRole('cashier'))   return { path: '/staff/cashier' }
    if (auth.hasRole('reception')) return { path: '/staff/reception' }
    if (auth.hasRole('delivery'))  return { path: '/staff/delivery' }
  }

  // Keep old guest redirect for any remaining guest meta routes
  if (to.meta.guest && auth.isAuthenticated) {
    if (auth.isAdmin)              return { name: 'admin' }
    if (auth.hasRole('kitchen'))   return { path: '/staff/kitchen' }
    if (auth.hasRole('waiter'))    return { path: '/staff/waiter' }
    if (auth.hasRole('cashier'))   return { path: '/staff/cashier' }
    if (auth.hasRole('reception')) return { path: '/staff/reception' }
    if (auth.hasRole('delivery'))  return { path: '/staff/delivery' }
    return { name: 'home' }
  }

  // Admin panel: accessible to super-admin, admin, and manager
  if (to.meta.role === 'admin' && !auth.isAdmin) {
    return auth.isAuthenticated ? { name: 'home' } : { name: 'login', query: { redirect: to.fullPath } }
  }

  // Staff portals: accessible to all staff roles (including manager)
  if (to.meta.role === 'staff' && !auth.isStaff) {
    return auth.isAuthenticated ? { name: 'home' } : { name: 'login', query: { redirect: to.fullPath } }
  }

  // Customer-only pages: block all staff and admin roles
  // Unauthenticated guests (not logged in) are allowed through
  if (to.meta.customerOnly && auth.isAuthenticated && auth.isStaff) {
    // Redirect staff to their correct home
    if (auth.isAdmin)              return { name: 'admin' }
    if (auth.hasRole('kitchen'))   return { path: '/staff/kitchen' }
    if (auth.hasRole('waiter'))    return { path: '/staff/waiter' }
    if (auth.hasRole('cashier'))   return { path: '/staff/cashier' }
    if (auth.hasRole('reception')) return { path: '/staff/reception' }
    if (auth.hasRole('delivery'))  return { path: '/staff/delivery' }
    return { name: 'home' }
  }
})

export default router
