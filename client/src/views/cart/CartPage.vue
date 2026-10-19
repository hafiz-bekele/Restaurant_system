<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { useCartStore } from '../../stores/cart'
import { useUiStore } from '../../stores/ui'
import { ShoppingBag, Trash2, Plus, Minus, Tag, ShieldCheck, Sparkles, ArrowRight } from '@lucide/vue'

const { t } = useI18n()
const cart = useCartStore()
const ui = useUiStore()
const couponInput = ref('')
const applyingCoupon = ref(false)

onMounted(() => cart.fetchCart())

async function handleApplyCoupon(code) {
  const targetCode = code || couponInput.value.trim()
  if (!targetCode) return
  applyingCoupon.value = true
  try {
    await cart.applyCoupon(targetCode)
    ui.toast(t('cartPage.couponApplied', { code: targetCode.toUpperCase() }), 'success')
    couponInput.value = ''
  } catch (err) {
    ui.toast(err.response?.data?.message || t('cartPage.invalidCoupon'), 'error')
  } finally {
    applyingCoupon.value = false
  }
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-10">
      <!-- Top Guest Banner -->
      <div class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-primary-500/10 via-amber-500/10 to-transparent border border-primary-500/20 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-primary-500/15 text-primary-600 dark:text-primary-400 flex items-center justify-center shrink-0">
            <Sparkles :size="20" />
          </div>
          <div>
            <h4 class="font-bold text-sm text-warm-900 dark:text-white">{{ $t('cartPage.guestBannerTitle') }}</h4>
            <p class="text-xs text-warm-600 dark:text-warm-400">{{ $t('cartPage.guestBannerDesc') }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-3 py-1.5 rounded-xl border border-emerald-300 dark:border-emerald-800">
          <ShieldCheck :size="16" />
          <span>{{ $t('cartPage.supportedPayments') }}</span>
        </div>
      </div>

      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8">{{ $t('cartPage.title') }}</h1>

      <!-- Loading skeleton -->
      <div v-if="cart.loading && !cart.cart?.items?.length" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
          <div v-for="i in 3" :key="i" class="card p-4 flex gap-4 border border-warm-100 dark:border-warm-800">
            <div class="skeleton w-20 h-20 rounded-xl shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="skeleton h-5 w-40 rounded"></div>
              <div class="skeleton h-4 w-20 rounded"></div>
              <div class="flex items-center gap-2 pt-2">
                <div class="skeleton h-8 w-8 rounded-lg"></div>
                <div class="skeleton h-8 w-8 rounded-lg"></div>
                <div class="skeleton h-8 w-8 rounded-lg"></div>
              </div>
            </div>
          </div>
        </div>
        <div class="card p-6 h-fit space-y-4 border border-warm-100 dark:border-warm-800">
          <div class="skeleton h-6 w-36 rounded-lg"></div>
          <div class="space-y-3">
            <div class="skeleton h-4 w-full rounded"></div>
            <div class="skeleton h-4 w-full rounded"></div>
            <div class="skeleton h-4 w-full rounded"></div>
          </div>
          <div class="skeleton h-11 w-full rounded-xl"></div>
        </div>
      </div>

      <!-- Empty Cart -->
      <div v-else-if="!cart.cart?.items?.length" class="text-center py-20 card border border-warm-100 dark:border-warm-800">
        <ShoppingBag :size="56" class="mx-auto text-warm-300 dark:text-warm-700 mb-4" />
        <h2 class="font-heading text-xl font-bold text-warm-900 dark:text-white mb-2">{{ $t('cartPage.emptyTitle') }}</h2>
        <p class="text-warm-500 dark:text-warm-400 mb-6 text-sm">{{ $t('cartPage.emptyDesc') }}</p>
        <RouterLink to="/menu" class="btn-primary">{{ $t('cartPage.browseMenu') }}</RouterLink>
      </div>

      <!-- Cart Items & Summary -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-4">
          <div v-for="item in cart.cart.items" :key="item.id" class="card p-4 flex gap-4 border border-warm-100 dark:border-warm-800">
            <img v-if="item.dish?.image" :src="item.dish.image" :alt="item.dish.name" class="w-20 h-20 rounded-xl object-cover shrink-0" />
            <div v-else class="w-20 h-20 rounded-xl bg-warm-100 dark:bg-warm-800 shrink-0"></div>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-warm-900 dark:text-white truncate">{{ item.dish?.name }}</p>
              <p class="text-sm text-primary-600 dark:text-primary-400 font-bold font-mono">ETB {{ Number(item.line_total).toFixed(2) }}</p>
              <div class="flex items-center gap-2 mt-2">
                <button
                  @click="cart.updateItem(item.id, item.quantity - 1)"
                  class="w-8 h-8 rounded-lg bg-warm-100 dark:bg-warm-800 hover:bg-warm-200 dark:hover:bg-warm-700 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="$t('cartPage.decreaseAria', { name: item.dish?.name })"
                >
                  <Minus :size="14" />
                </button>
                <span class="w-8 text-center text-sm font-semibold select-none">{{ item.quantity }}</span>
                <button
                  @click="cart.updateItem(item.id, item.quantity + 1)"
                  class="w-8 h-8 rounded-lg bg-warm-100 dark:bg-warm-800 hover:bg-warm-200 dark:hover:bg-warm-700 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="$t('cartPage.increaseAria', { name: item.dish?.name })"
                >
                  <Plus :size="14" />
                </button>
                <button
                  @click="cart.removeItem(item.id)"
                  class="ml-auto w-8 h-8 rounded-lg text-red-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center justify-center transition-colors active:scale-95"
                  :aria-label="$t('cartPage.removeAria', { name: item.dish?.name })"
                >
                  <Trash2 :size="16" />
                </button>
              </div>
            </div>
          </div>

          <!-- Coupon Promotion Box -->
          <div class="card p-4 border border-warm-200 dark:border-warm-700 bg-warm-50/50 dark:bg-warm-800/40 space-y-3">
            <div class="flex items-center justify-between">
              <label class="text-xs font-bold uppercase tracking-wider text-warm-600 dark:text-warm-300 flex items-center gap-1.5">
                <Tag :size="14" class="text-primary-500" /> {{ $t('cartPage.havePromo') }}
              </label>
              <button
                type="button"
                @click="handleApplyCoupon('WELCOME10')"
                class="text-xs text-primary-600 dark:text-primary-400 font-semibold hover:underline"
              >
                {{ $t('cartPage.usePromoCode') }}
              </button>
            </div>
            <div class="flex gap-2">
              <input
                v-model="couponInput"
                type="text"
                :placeholder="$t('cartPage.couponPlaceholder')"
                class="input text-xs uppercase font-mono tracking-wider flex-1"
                @keydown.enter.prevent="handleApplyCoupon()"
              />
              <button
                type="button"
                @click="handleApplyCoupon()"
                :disabled="applyingCoupon"
                class="btn-secondary text-xs px-4"
              >
                {{ applyingCoupon ? $t('cartPage.applying') : $t('cartPage.apply') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Order Summary Card -->
        <div class="card p-6 h-fit space-y-4 border border-warm-100 dark:border-warm-800">
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('cartPage.orderSummary') }}</h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-warm-600 dark:text-warm-400">
              <span>{{ $t('cartPage.subtotal') }}</span>
              <span class="font-mono">ETB {{ Number(cart.totals.subtotal).toFixed(2) }}</span>
            </div>
            <div v-if="cart.totals.discount > 0" class="flex justify-between text-emerald-600 font-semibold">
              <span>{{ $t('cartPage.promoDiscount') }}</span>
              <span class="font-mono">-ETB {{ Number(cart.totals.discount).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between text-warm-600 dark:text-warm-400">
              <span>{{ $t('cartPage.vat') }}</span>
              <span class="font-mono">ETB {{ Number(cart.totals.tax).toFixed(2) }}</span>
            </div>
            <div class="flex justify-between font-bold text-warm-900 dark:text-white pt-2 border-t border-warm-100 dark:border-warm-800 text-base">
              <span>{{ $t('cartPage.grandTotal') }}</span>
              <span class="font-mono text-primary-600 text-lg">ETB {{ Number(cart.totals.total).toFixed(2) }}</span>
            </div>
          </div>

          <RouterLink
            to="/checkout"
            class="btn-primary w-full justify-center py-3.5 text-sm font-bold gap-2 shadow-lg shadow-primary-500/20"
          >
            <span>{{ $t('cartPage.proceedToGuestCheckout') }}</span>
            <ArrowRight :size="16" />
          </RouterLink>

          <p class="text-[11px] text-center text-warm-500">
            {{ $t('cartPage.instantCheckoutNote') }}
          </p>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
