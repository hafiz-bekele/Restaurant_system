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
  createIntent: (orderUlid)  => api.post('/payments/intent', { order_ulid: orderUlid }),
}

// Staff
export const staffApi = {
  kitchenTickets:   ()           => api.get('/kitchen/tickets'),
  updateTicket:     (id, status) => api.patch(`/kitchen/tickets/${id}`, { status }),
  waiterTables:     ()           => api.get('/waiter/tables'),
  deliveryOrders:   ()           => api.get('/delivery/orders'),
  updateDelivery:   (id, status) => api.patch(`/delivery/orders/${id}`, { status }),
}

// Admin
export const adminApi = {
  dishes:       (params)     => api.get('/admin/dishes', { params }),
  createDish:   (data)       => api.post('/admin/dishes', data),
  updateDish:   (id, data)   => api.put(`/admin/dishes/${id}`, data),
  deleteDish:   (id)         => api.delete(`/admin/dishes/${id}`),
  orders:       (params)     => api.get('/admin/orders', { params }),
  updateOrder:  (id, data)   => api.patch(`/admin/orders/${id}/status`, data),
  salesReport:  (params)     => api.get('/admin/reports/sales', { params }),
  settings:     ()           => api.get('/admin/settings'),
  saveSettings: (data)       => api.put('/admin/settings', { settings: data }),
}
