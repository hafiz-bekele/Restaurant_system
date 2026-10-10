import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { cartApi } from '../services/api'

export const useCartStore = defineStore('cart', () => {
  const cart   = ref(null)
  const totals = ref({ subtotal: 0, tax: 0, deliveryFee: 0, discount: 0, total: 0 })
  const open   = ref(false)
  const loading = ref(false)

  const itemCount = computed(() => cart.value?.items?.reduce((sum, i) => sum + i.quantity, 0) ?? 0)

  async function fetchCart() {
    loading.value = true
    try {
      const { data } = await cartApi.get()
      cart.value   = data.data.cart
      totals.value = data.data.totals
    } finally {
      loading.value = false
    }
  }

  async function addItem(payload) {
    await cartApi.addItem(payload)
    await fetchCart()
    open.value = true
  }

  async function updateItem(id, quantity) {
    await cartApi.updateItem(id, { quantity })
    await fetchCart()
  }

  async function removeItem(id) {
    await cartApi.removeItem(id)
    await fetchCart()
  }

  async function applyCoupon(code) {
    await cartApi.applyCoupon(code)
    await fetchCart()
  }

  async function removeCoupon() {
    await cartApi.removeCoupon()
    await fetchCart()
  }

  function toggleDrawer() { open.value = !open.value }
  function closeDrawer()  { open.value = false }

  return { cart, totals, open, loading, itemCount, fetchCart, addItem, updateItem, removeItem, applyCoupon, removeCoupon, toggleDrawer, closeDrawer }
})
