import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  // Public
  { path: '/',           name: 'home',        component: () => import('../pages/HomePage.vue') },
  { path: '/menu',       name: 'menu',        component: () => import('../pages/menu/MenuPage.vue') },
  { path: '/menu/:slug', name: 'dish',        component: () => import('../pages/menu/DishPage.vue') },
  { path: '/cart',       name: 'cart',        component: () => import('../pages/cart/CartPage.vue') },
  { path: '/checkout',   name: 'checkout',    component: () => import('../pages/cart/CheckoutPage.vue') },
  { path: '/reservations', name: 'reservations', component: () => import('../pages/reservation/ReservationPage.vue') },
  { path: '/about',      name: 'about',       component: () => import('../pages/static/AboutPage.vue') },
  { path: '/contact',    name: 'contact',     component: () => import('../pages/static/ContactPage.vue') },

  // Auth
  { path: '/login',    name: 'login',    component: () => import('../pages/auth/LoginPage.vue'),    meta: { guest: true } },
  { path: '/register', name: 'register', component: () => import('../pages/auth/RegisterPage.vue'), meta: { guest: true } },

  // Order tracking (public with ulid)
  { path: '/orders/:ulid/track', name: 'order.track', component: () => import('../pages/order/TrackingPage.vue') },

  // Auth-required
  { path: '/orders',       name: 'orders',   component: () => import('../pages/order/OrdersPage.vue'),  meta: { auth: true } },
  { path: '/profile',      name: 'profile',  component: () => import('../pages/profile/ProfilePage.vue'), meta: { auth: true } },
  { path: '/favorites',    name: 'favorites',component: () => import('../pages/profile/FavoritesPage.vue'), meta: { auth: true } },

  // Staff
  {
    path: '/staff',
    component: () => import('../layouts/StaffLayout.vue'),
    meta: { auth: true, role: 'staff' },
    children: [
      { path: 'kitchen',  name: 'kitchen',  component: () => import('../pages/staff/KitchenPage.vue') },
      { path: 'waiter',   name: 'waiter',   component: () => import('../pages/staff/WaiterPage.vue') },
      { path: 'cashier',  name: 'cashier',  component: () => import('../pages/staff/CashierPage.vue') },
      { path: 'delivery', name: 'delivery', component: () => import('../pages/staff/DeliveryPage.vue') },
    ],
  },

  // Admin
  {
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    meta: { auth: true, role: 'admin' },
    children: [
      { path: '',            name: 'admin',                component: () => import('../pages/admin/DashboardPage.vue') },
      { path: 'menu',        name: 'admin.menu',           component: () => import('../pages/admin/MenuPage.vue') },
      { path: 'orders',      name: 'admin.orders',         component: () => import('../pages/admin/OrdersPage.vue') },
      { path: 'reports',     name: 'admin.reports',        component: () => import('../pages/admin/ReportsPage.vue') },
      { path: 'settings',    name: 'admin.settings',       component: () => import('../pages/admin/SettingsPage.vue') },
      { path: 'users',       name: 'admin.users',          component: () => import('../pages/admin/UsersPage.vue') },
      { path: 'tables',      name: 'admin.tables',         component: () => import('../pages/admin/TablesPage.vue') },
      { path: 'reservations',name: 'admin.reservations',   component: () => import('../pages/admin/ReservationsPage.vue') },
      { path: 'coupons',     name: 'admin.coupons',        component: () => import('../pages/admin/CouponsPage.vue') },
      { path: 'inventory',   name: 'admin.inventory',      component: () => import('../pages/admin/InventoryPage.vue') },
      { path: 'audit',       name: 'admin.audit',          component: () => import('../pages/admin/AuditPage.vue') },
    ],
  },

  { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('../pages/static/NotFoundPage.vue') },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0, behavior: 'smooth' }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.user && auth.token) {
    await auth.fetchUser()
  }

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'home' }
  }

  if (to.meta.role === 'admin' && !auth.isAdmin) {
    return { name: 'home' }
  }

  if (to.meta.role === 'staff' && !auth.isStaff) {
    return { name: 'home' }
  }
})

export default router
