import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  const sessionId = localStorage.getItem('session_id')
  if (sessionId) config.headers['X-Session-Id'] = sessionId

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
  categories:   ()           => api.get('/categories'),
  dishes:       (params)     => api.get('/dishes', { params }),
  dish:         (slug)       => api.get(`/dishes/${slug}`),
  featured:     ()           => api.get('/dishes/featured'),
  search:       (q)          => api.get('/search', { params: { q } }),
}

// Auth
export const authApi = {
  register:     (data)       => api.post('/auth/register', data),
  login:        (data)       => api.post('/auth/login', data),
  logout:       ()           => api.post('/auth/logout'),
  me:           ()           => api.get('/auth/me'),
}

// Cart
export const cartApi = {
  get:          ()           => api.get('/cart'),
  addItem:      (data)       => api.post('/cart/items', data),
  updateItem:   (id, data)   => api.patch(`/cart/items/${id}`, data),
  removeItem:   (id)         => api.delete(`/cart/items/${id}`),
  applyCoupon:  (code)       => api.post('/cart/coupon', { code }),
  removeCoupon: ()           => api.delete('/cart/coupon'),
}

// Orders
export const orderApi = {
  list:         ()           => api.get('/orders'),
  create:       (data)       => api.post('/orders', data),
  get:          (ulid)       => api.get(`/orders/${ulid}`),
  cancel:       (ulid, data) => api.post(`/orders/${ulid}/cancel`, data),
  track:        (ulid)       => api.get(`/orders/${ulid}/track`),
}

// Reservations
export const reservationApi = {
  availability: (params)     => api.get('/reservations/availability', { params }),
  create:       (data)       => api.post('/reservations', data),
  get:          (id)         => api.get(`/reservations/${id}`),
  update:       (id, data)   => api.patch(`/reservations/${id}`, data),
  cancel:       (id)         => api.delete(`/reservations/${id}`),
}

// Profile
export const profileApi = {
  get:          ()           => api.get('/profile'),
  update:       (data)       => api.put('/profile', data),
  addresses:    ()           => api.get('/addresses'),
  addAddress:   (data)       => api.post('/addresses', data),
  updateAddress:(id, data)   => api.put(`/addresses/${id}`, data),
  deleteAddress:(id)         => api.delete(`/addresses/${id}`),
  favorites:    ()           => api.get('/favorites'),
  addFavorite:  (dishId)     => api.post('/favorites', { dish_id: dishId }),
  removeFavorite:(dishId)    => api.delete(`/favorites/${dishId}`),
  submitReview: (data)       => api.post('/reviews', data),
}

// Payments
export const paymentApi = {
  createIntent:     (orderUlid)          => api.post('/payments/intent', { order_ulid: orderUlid }),
  createChapaIntent:(orderUlid)          => api.post('/payments/chapa', { order_ulid: orderUlid }),
  roomCharge:       (orderUlid, bookingId) => api.post('/payments/room-charge', { order_ulid: orderUlid, booking_id: bookingId }),
}

// Guest QR + Room Booking
export const guestApi = {
  resolveQr:        (token)    => api.get(`/guest/resolve-qr/${token}`),
  banners:          ()         => api.get('/banners'),
  availableRooms:   (params)   => api.get('/rooms/available', { params }),
  bookRoom:         (data)     => api.post('/rooms/book', data),
  getBooking:       (id)       => api.get(`/rooms/bookings/${id}`),
  submitComplaint:  (data)     => api.post('/complaints', data),
  getComplaint:     (ticket)   => api.get(`/complaints/${ticket}`),
}

// Staff
export const staffApi = {
  kitchenTickets:         ()           => api.get('/kitchen/tickets'),
  updateTicket:           (id, status) => api.patch(`/kitchen/tickets/${id}`, { status }),
  waiterTables:           ()           => api.get('/waiter/tables'),
  deliveryOrders:         ()           => api.get('/delivery/orders'),
  updateDelivery:         (id, status) => api.patch(`/delivery/orders/${id}`, { status }),
  // Reception
  receptionDashboard:     ()           => api.get('/reception/dashboard'),
  availableTables:        (params)     => api.get('/reception/tables/available', { params }),
  walkIn:                 (data)       => api.post('/reception/walk-in', data),
  checkIn:                (id)         => api.post(`/reception/reservations/${id}/check-in`),
  noShow:                 (id)         => api.post(`/reception/reservations/${id}/no-show`),
  releaseTable:           (id)         => api.post(`/reception/tables/${id}/release`),
}

// Admin
export const adminApi = {
  // Dishes
  dishes:       (params)     => api.get('/admin/dishes', { params }),
  createDish:   (data)       => api.post('/admin/dishes', data),
  updateDish:   (id, data)   => api.put(`/admin/dishes/${id}`, data),
  deleteDish:   (id)         => api.delete(`/admin/dishes/${id}`),

  // Orders
  orders:       (params)     => api.get('/admin/orders', { params }),
  updateOrder:  (id, data)   => api.patch(`/admin/orders/${id}/status`, data),

  // Reports
  salesReport:  (params)     => api.get('/admin/reports/sales', { params }),

  // Settings
  settings:     ()           => api.get('/admin/settings'),
  saveSettings: (data)       => api.put('/admin/settings', { settings: data }),

  // Coupons
  coupons:         (params)       => api.get('/admin/coupons', { params }),
  createCoupon:    (data)         => api.post('/admin/coupons', data),
  updateCoupon:    (id, data)     => api.patch(`/admin/coupons/${id}`, data),
  deleteCoupon:    (id)           => api.delete(`/admin/coupons/${id}`),

  // Users
  users:           (params)       => api.get('/admin/users', { params }),
  createUser:      (data)         => api.post('/admin/users', data),
  showUser:        (id)           => api.get(`/admin/users/${id}`),
  updateUser:      (id, data)     => api.patch(`/admin/users/${id}`, data),
  deleteUser:      (id)           => api.delete(`/admin/users/${id}`),
  roles:           ()             => api.get('/admin/users/roles'),

  // Tables
  tables:          (params)       => api.get('/admin/tables', { params }),
  tableAreas:      ()             => api.get('/admin/tables/areas'),
  createTable:     (data)         => api.post('/admin/tables', data),
  updateTable:     (id, data)     => api.patch(`/admin/tables/${id}`, data),
  deleteTable:     (id)           => api.delete(`/admin/tables/${id}`),
  regenerateQr:    (id)           => api.post(`/admin/tables/${id}/regenerate-qr`),

  // Admin Reservations
  adminReservations:      (params)       => api.get('/admin/reservations', { params }),
  todayReservations:      ()             => api.get('/admin/reservations/today'),
  showReservation:        (id)           => api.get(`/admin/reservations/${id}`),
  updateReservation:      (id, data)     => api.patch(`/admin/reservations/${id}`, data),
  deleteReservation:      (id)           => api.delete(`/admin/reservations/${id}`),

  // Inventory
  ingredients:     (params)       => api.get('/admin/inventory/ingredients', { params }),
  createIngredient:(data)         => api.post('/admin/inventory/ingredients', data),
  updateIngredient:(id, data)     => api.patch(`/admin/inventory/ingredients/${id}`, data),
  adjustStock:     (id, data)     => api.post(`/admin/inventory/ingredients/${id}/adjust`, data),
  lowStock:        ()             => api.get('/admin/inventory/low-stock'),
  suppliers:       ()             => api.get('/admin/inventory/suppliers'),
  createSupplier:  (data)         => api.post('/admin/inventory/suppliers', data),

  // Audit log
  auditLog:        (params)       => api.get('/admin/audit', { params }),

  // Complaints (REQ-11.2)
  complaints:         (params)       => api.get('/admin/complaints', { params }),
  showComplaint:      (id)           => api.get(`/admin/complaints/${id}`),
  updateComplaint:    (id, data)     => api.patch(`/admin/complaints/${id}`, data),

  // Delivery zones
  deliveryZones:      ()             => api.get('/admin/delivery-zones'),
  createZone:         (data)         => api.post('/admin/delivery-zones', data),
  updateZone:         (id, data)     => api.patch(`/admin/delivery-zones/${id}`, data),
  deleteZone:         (id)           => api.delete(`/admin/delivery-zones/${id}`),

  // Rooms + bookings
  adminRooms:         (params)       => api.get('/admin/rooms', { params }),
  createRoom:         (data)         => api.post('/admin/rooms', data),
  updateRoom:         (id, data)     => api.patch(`/admin/rooms/${id}`, data),
  deleteRoom:         (id)           => api.delete(`/admin/rooms/${id}`),
  roomBookings:       (params)       => api.get('/admin/room-bookings', { params }),
  updateBooking:      (id, data)     => api.patch(`/admin/room-bookings/${id}`, data),
}
