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
  const cart            = ref<any>(null)
  const totals          = ref<CartTotals>({ subtotal: 0, tax: 0, deliveryFee: 0, discount: 0, total: 0 })
  const open            = ref<boolean>(false)
  const loading         = ref<boolean>(false)
  const updatingItemId  = ref<number | string | null>(null)
  const isCouponLoading = ref<boolean>(false)

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
    loading.value = true
    try {
      await cartApi.addItem(payload)
      await fetchCart()
      open.value = true
    } finally {
      loading.value = false
    }
  }

  async function updateItem(id: number | string, quantity: number) {
    updatingItemId.value = id
    try {
      await cartApi.updateItem(id, { quantity })
      await fetchCart()
    } finally {
      updatingItemId.value = null
    }
  }

  async function removeItem(id: number | string) {
    updatingItemId.value = id
    try {
      await cartApi.removeItem(id)
      await fetchCart()
    } finally {
      updatingItemId.value = null
    }
  }

  async function applyCoupon(code: string) {
    isCouponLoading.value = true
    try {
      await cartApi.applyCoupon(code)
      await fetchCart()
    } finally {
      isCouponLoading.value = false
    }
  }

  async function removeCoupon() {
    isCouponLoading.value = true
    try {
      await cartApi.removeCoupon()
      await fetchCart()
    } finally {
      isCouponLoading.value = false
    }
  }

  function toggleDrawer() { open.value = !open.value }
  function closeDrawer()  { open.value = false }

  return {
    cart,
    totals,
    open,
    loading,
    updatingItemId,
    isCouponLoading,
    itemCount,
    fetchCart,
    addItem,
    updateItem,
    removeItem,
    applyCoupon,
    removeCoupon,
    toggleDrawer,
    closeDrawer
  }
})
