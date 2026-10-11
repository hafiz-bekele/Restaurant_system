import axios, { type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'

const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  const sessionId = localStorage.getItem('session_id')
  if (sessionId) {
    config.headers['X-Session-Id'] = sessionId
  }

  return config
})

api.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api

// Menu
export const menuApi = {
  categories:   ()                        => api.get('/categories'),
  dishes:       (params?: Record<string, any>) => api.get('/dishes', { params }),
  dish:         (slug: string)            => api.get(`/dishes/${slug}`),
  featured:     ()                        => api.get('/dishes/featured'),
  search:       (q: string)               => api.get('/search', { params: { q } }),
}

// Auth
export const authApi = {
  register:     (data: Record<string, any>) => api.post('/auth/register', data),
  login:        (data: Record<string, any>) => api.post('/auth/login', data),
  logout:       ()                          => api.post('/auth/logout'),
  me:           ()                          => api.get('/auth/me'),
}

// Cart
export const cartApi = {
  get:          ()                          => api.get('/cart'),
  addItem:      (data: Record<string, any>) => api.post('/cart/items', data),
  updateItem:   (id: number | string, data: Record<string, any>) => api.patch(`/cart/items/${id}`, data),
  removeItem:   (id: number | string)       => api.delete(`/cart/items/${id}`),
  applyCoupon:  (code: string)              => api.post('/cart/coupon', { code }),
  removeCoupon: ()                          => api.delete('/cart/coupon'),
}

// Orders
export const orderApi = {
  list:         ()                          => api.get('/orders'),
  create:       (data: Record<string, any>) => api.post('/orders', data),
  get:          (ulid: string)              => api.get(`/orders/${ulid}`),
  cancel:       (ulid: string, data?: Record<string, any>) => api.post(`/orders/${ulid}/cancel`, data),
  track:        (ulid: string)              => api.get(`/orders/${ulid}/track`),
}

// Reservations
export const reservationApi = {
  availability: (params?: Record<string, any>) => api.get('/reservations/availability', { params }),
  create:       (data: Record<string, any>)    => api.post('/reservations', data),
  get:          (id: number | string)          => api.get(`/reservations/${id}`),
  update:       (id: number | string, data: Record<string, any>) => api.patch(`/reservations/${id}`, data),
  cancel:       (id: number | string)          => api.delete(`/reservations/${id}`),
}

// Profile
export const profileApi = {
  get:          ()                          => api.get('/profile'),
  update:       (data: Record<string, any>) => api.put('/profile', data),
  addresses:    ()                          => api.get('/addresses'),
  addAddress:   (data: Record<string, any>) => api.post('/addresses', data),
  updateAddress:(id: number | string, data: Record<string, any>) => api.put(`/addresses/${id}`, data),
  deleteAddress:(id: number | string)       => api.delete(`/addresses/${id}`),
  favorites:    ()                          => api.get('/favorites'),
  addFavorite:  (dishId: number | string)   => api.post('/favorites', { dish_id: dishId }),
  removeFavorite:(dishId: number | string)  => api.delete(`/favorites/${dishId}`),
  submitReview: (data: Record<string, any>) => api.post('/reviews', data),
}

// Payments
export const paymentApi = {
  createIntent:     (orderUlid: string) => api.post('/payments/intent', { order_ulid: orderUlid }),
  createChapaIntent:(orderUlid: string) => api.post('/payments/chapa', { order_ulid: orderUlid }),
  roomCharge:       (orderUlid: string, bookingId: number | string) => api.post('/payments/room-charge', { order_ulid: orderUlid, booking_id: bookingId }),
}

// Guest QR + Room Booking
export const guestApi = {
  resolveQr:        (token: string)             => api.get(`/guest/resolve-qr/${token}`),
  banners:          ()                          => api.get('/banners'),
  availableRooms:   (params?: Record<string, any>) => api.get('/rooms/available', { params }),
  bookRoom:         (data: Record<string, any>) => api.post('/rooms/book', data),
  getBooking:       (id: number | string)       => api.get(`/rooms/bookings/${id}`),
  submitComplaint:  (data: Record<string, any>) => api.post('/complaints', data),
  getComplaint:     (ticket: string)            => api.get(`/complaints/${ticket}`),
}

// Staff
export const staffApi = {
  kitchenTickets:         ()                    => api.get('/kitchen/tickets'),
  updateTicket:           (id: number | string, status: string) => api.patch(`/kitchen/tickets/${id}`, { status }),
  waiterTables:           ()                    => api.get('/waiter/tables'),
  deliveryOrders:         ()                    => api.get('/delivery/orders'),
  updateDelivery:         (id: number | string, status: string) => api.patch(`/delivery/orders/${id}`, { status }),
  // Reception
  receptionDashboard:     ()                    => api.get('/reception/dashboard'),
  availableTables:        (params?: Record<string, any>) => api.get('/reception/tables/available', { params }),
  walkIn:                 (data: Record<string, any>) => api.post('/reception/walk-in', data),
  checkIn:                (id: number | string) => api.post(`/reception/reservations/${id}/check-in`),
  noShow:                 (id: number | string) => api.post(`/reception/reservations/${id}/no-show`),
  releaseTable:           (id: number | string) => api.post(`/reception/tables/${id}/release`),
}

// Admin
export const adminApi = {
  // Dishes
  dishes:       (params?: Record<string, any>)  => api.get('/admin/dishes', { params }),
  createDish:   (data: Record<string, any>)     => api.post('/admin/dishes', data),
  updateDish:   (id: number | string, data: Record<string, any>) => api.put(`/admin/dishes/${id}`, data),
  deleteDish:   (id: number | string)           => api.delete(`/admin/dishes/${id}`),

  // Orders
  orders:       (params?: Record<string, any>)  => api.get('/admin/orders', { params }),
  updateOrder:  (id: number | string, data: Record<string, any>) => api.patch(`/admin/orders/${id}/status`, data),

  // Reports
  salesReport:  (params?: Record<string, any>)  => api.get('/admin/reports/sales', { params }),

  // Settings
  settings:     ()                              => api.get('/admin/settings'),
  saveSettings: (data: Record<string, any>)     => api.put('/admin/settings', { settings: data }),

  // Coupons
  coupons:         (params?: Record<string, any>) => api.get('/admin/coupons', { params }),
  createCoupon:    (data: Record<string, any>)   => api.post('/admin/coupons', data),
  updateCoupon:    (id: number | string, data: Record<string, any>) => api.patch(`/admin/coupons/${id}`, data),
  deleteCoupon:    (id: number | string)         => api.delete(`/admin/coupons/${id}`),

  // Users
  users:           (params?: Record<string, any>) => api.get('/admin/users', { params }),
  createUser:      (data: Record<string, any>)   => api.post('/admin/users', data),
  showUser:        (id: number | string)         => api.get(`/admin/users/${id}`),
  updateUser:      (id: number | string, data: Record<string, any>) => api.patch(`/admin/users/${id}`, data),
  deleteUser:      (id: number | string)         => api.delete(`/admin/users/${id}`),
  roles:           ()                            => api.get('/admin/users/roles'),

  // Tables
  tables:          (params?: Record<string, any>) => api.get('/admin/tables', { params }),
  tableAreas:      ()                            => api.get('/admin/tables/areas'),
  createTable:     (data: Record<string, any>)   => api.post('/admin/tables', data),
  updateTable:     (id: number | string, data: Record<string, any>) => api.patch(`/admin/tables/${id}`, data),
  deleteTable:     (id: number | string)         => api.delete(`/admin/tables/${id}`),
  regenerateQr:    (id: number | string)         => api.post(`/admin/tables/${id}/regenerate-qr`),

  // Admin Reservations
  adminReservations:      (params?: Record<string, any>) => api.get('/admin/reservations', { params }),
  todayReservations:      ()                             => api.get('/admin/reservations/today'),
  showReservation:        (id: number | string)          => api.get(`/admin/reservations/${id}`),
  updateReservation:      (id: number | string, data: Record<string, any>) => api.patch(`/admin/reservations/${id}`, data),
  deleteReservation:      (id: number | string)          => api.delete(`/admin/reservations/${id}`),

  // Inventory
  ingredients:     (params?: Record<string, any>) => api.get('/admin/inventory/ingredients', { params }),
  createIngredient:(data: Record<string, any>)   => api.post('/admin/inventory/ingredients', data),
  updateIngredient:(id: number | string, data: Record<string, any>) => api.patch(`/admin/inventory/ingredients/${id}`, data),
  adjustStock:     (id: number | string, data: Record<string, any>) => api.post(`/admin/inventory/ingredients/${id}/adjust`, data),
  lowStock:        ()                            => api.get('/admin/inventory/low-stock'),
  suppliers:       ()                            => api.get('/admin/inventory/suppliers'),
  createSupplier:  (data: Record<string, any>)   => api.post('/admin/inventory/suppliers', data),

  // Audit log
  auditLog:        (params?: Record<string, any>) => api.get('/admin/audit', { params }),

  // Complaints
  complaints:         (params?: Record<string, any>) => api.get('/admin/complaints', { params }),
  showComplaint:      (id: number | string)          => api.get(`/admin/complaints/${id}`),
  updateComplaint:    (id: number | string, data: Record<string, any>) => api.patch(`/admin/complaints/${id}`, data),

  // Delivery zones
  deliveryZones:      ()                             => api.get('/admin/delivery-zones'),
  createZone:         (data: Record<string, any>)    => api.post('/admin/delivery-zones', data),
  updateZone:         (id: number | string, data: Record<string, any>) => api.patch(`/admin/delivery-zones/${id}`, data),
  deleteZone:         (id: number | string)          => api.delete(`/admin/delivery-zones/${id}`),

  // Rooms + bookings
  adminRooms:         (params?: Record<string, any>) => api.get('/admin/rooms', { params }),
  createRoom:         (data: Record<string, any>)    => api.post('/admin/rooms', data),
  updateRoom:         (id: number | string, data: Record<string, any>) => api.patch(`/admin/rooms/${id}`, data),
  deleteRoom:         (id: number | string)          => api.delete(`/admin/rooms/${id}`),
  roomBookings:       (params?: Record<string, any>) => api.get('/admin/room-bookings', { params }),
  updateBooking:      (id: number | string, data: Record<string, any>) => api.patch(`/admin/room-bookings/${id}`, data),
}
