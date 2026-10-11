import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { cartApi } from '../services/api'

export interface CartTotals {
  subtotal: number
  tax: number
  deliveryFee: number
  discount: number
  total: number
}

export const useCartStore = defineStore('cart', () => {
  const cart   = ref<any>(null)
  const totals = ref<CartTotals>({ subtotal: 0, tax: 0, deliveryFee: 0, discount: 0, total: 0 })
  const open   = ref<boolean>(false)
  const loading = ref<boolean>(false)

  const itemCount = computed(() => cart.value?.items?.reduce((sum: number, i: any) => sum + i.quantity, 0) ?? 0)

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

  async function addItem(payload: Record<string, any>) {
    await cartApi.addItem(payload)
    await fetchCart()
    open.value = true
  }

  async function updateItem(id: number | string, quantity: number) {
    await cartApi.updateItem(id, { quantity })
    await fetchCart()
  }

  async function removeItem(id: number | string) {
    await cartApi.removeItem(id)
    await fetchCart()
  }

  async function applyCoupon(code: string) {
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
