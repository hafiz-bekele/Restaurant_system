<script setup>
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { menuApi, reviewApi } from '../../services/api'
import { useCartStore } from '../../stores/cart'
import { useUiStore }   from '../../stores/ui'
import { useAuthStore } from '../../stores/auth'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import { Star, Clock, Leaf, Plus, Minus, MessageSquare, ThumbsUp, Send, CheckCircle2 } from '@lucide/vue'

const { t }       = useI18n()
const route       = useRoute()
const router      = useRouter()
const queryClient = useQueryClient()
const cart        = useCartStore()
const ui          = useUiStore()
const auth        = useAuthStore()

const qty         = ref(1)
const loading     = ref(false)
const submittingReview = ref(false)
const showReviewForm   = ref(false)

// Review Form state
const ratingHover = ref(0)
const newRating   = ref(5)
const newComment  = ref('')

const ratingLabels = computed(() => ({
  1: t('dish.rating1'),
  2: t('dish.rating2'),
  3: t('dish.rating3'),
  4: t('dish.rating4'),
  5: t('dish.rating5'),
}))

// Fetch dish details
const { data: dish, isLoading } = useQuery({
  queryKey: ['dish', route.params.slug],
  queryFn:  () => menuApi.dish(route.params.slug).then(r => r.data.data),
})

// Fetch dish reviews & statistics
const reviewsData = ref(null)
const loadingReviews = ref(false)

async function fetchReviews(dishId) {
  if (!dishId) return
  loadingReviews.value = true
  try {
    const { data } = await reviewApi.dishReviews(dishId)
    reviewsData.value = data.data
  } catch (err) {
    console.error('Failed to load reviews', err)
  } finally {
    loadingReviews.value = false
  }
}

watch(() => dish.value?.id, (newId) => {
  if (newId) fetchReviews(newId)
}, { immediate: true })

async function addToCart() {
  if (!dish.value) return
  loading.value = true
  try {
    await cart.addItem({ dish_id: dish.value.id, quantity: qty.value })
    ui.toast(`${dish.value.name} added to cart`)
  } catch {
    ui.toast('Failed to add to cart', 'error')
  } finally {
    loading.value = false
  }
}

async function handleReviewSubmit() {
  if (!auth.isAuthenticated) {
    ui.toast('Please sign in to submit a rating and review', 'warning')
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }

  if (!dish.value?.id) return

  if (newRating.value < 1 || newRating.value > 5) {
    ui.toast('Please select a star rating between 1 and 5', 'error')
    return
  }

  submittingReview.value = true
  try {
    await reviewApi.submit({
      dish_id: dish.value.id,
      rating:  newRating.value,
      comment: newComment.value.trim() || null,
    })

    ui.toast('Thank you! Your review has been submitted successfully.', 'success')
    newComment.value = ''
    showReviewForm.value = false

    // Refetch dish & reviews
    await Promise.all([
      fetchReviews(dish.value.id),
      queryClient.invalidateQueries({ queryKey: ['dish', route.params.slug] }),
    ])
  } catch (err) {
    const msg = err.response?.data?.message || 'Failed to submit review'
    ui.toast(msg, 'error')
  } finally {
    submittingReview.value = false
  }
}

const reviewsList = computed(() => reviewsData.value?.reviews || dish.value?.reviews || [])
const summaryStats = computed(() => {
  const sum = reviewsData.value?.summary
  const total = sum?.total_reviews ?? dish.value?.reviews_count ?? reviewsList.value.length
  const avg = Number(sum?.avg_rating ?? dish.value?.avg_rating ?? 0)
  const breakdown = sum?.breakdown || {
    5: reviewsList.value.filter(r => r.rating === 5).length,
    4: reviewsList.value.filter(r => r.rating === 4).length,
    3: reviewsList.value.filter(r => r.rating === 3).length,
    2: reviewsList.value.filter(r => r.rating === 2).length,
    1: reviewsList.value.filter(r => r.rating === 1).length,
  }
  return { total, avg, breakdown }
})

function formatDate(isoStr) {
  if (!isoStr) return ''
  try {
    const d = new Date(isoStr)
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return ''
  }
}

function getInitials(name) {
  if (!name) return 'U'
  return name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase()
}
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-5xl">
      <!-- Loading Skeleton -->
      <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-2 gap-10">
        <div class="space-y-4">
          <div class="skeleton aspect-square !rounded-3xl"></div>
          <div class="grid grid-cols-4 gap-3">
            <div v-for="i in 4" :key="i" class="skeleton aspect-square !rounded-xl"></div>
          </div>
        </div>
        <div class="space-y-6">
          <div class="space-y-3">
            <div class="skeleton h-9 w-4/5 rounded-xl"></div>
            <div class="flex items-center gap-3">
              <div class="skeleton h-5 w-20 !rounded-full"></div>
              <div class="skeleton h-5 w-16 !rounded-full"></div>
            </div>
            <div class="skeleton h-8 w-24 rounded-lg pt-2"></div>
          </div>
          <div class="space-y-2 py-2">
            <div class="skeleton h-4 w-full rounded"></div>
            <div class="skeleton h-4 w-5/6 rounded"></div>
            <div class="skeleton h-4 w-3/4 rounded"></div>
          </div>
          <div class="space-y-3 pt-4 border-t border-warm-100 dark:border-warm-800">
            <div class="skeleton h-4 w-28 rounded"></div>
            <div class="flex gap-2">
              <div v-for="i in 3" :key="i" class="skeleton h-10 w-24 !rounded-xl"></div>
            </div>
          </div>
          <div class="flex gap-4 pt-4">
            <div class="skeleton h-12 w-32 !rounded-xl"></div>
            <div class="skeleton h-12 flex-1 !rounded-xl"></div>
          </div>
        </div>
      </div>

      <!-- Dish Details Card -->
      <div v-else-if="dish" class="space-y-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 items-start">
          <!-- Dish Image Gallery -->
          <div class="aspect-square rounded-3xl overflow-hidden bg-warm-100 dark:bg-warm-800 shadow-xl border border-warm-200/50 dark:border-warm-700/50">
            <img v-if="dish.image" :src="dish.image" :alt="dish.name" class="w-full h-full object-cover transition-transform duration-500 hover:scale-105" />
            <div v-else class="w-full h-full flex items-center justify-center text-warm-400">
              <Leaf :size="48" />
            </div>
          </div>

          <!-- Dish Info -->
          <div>
            <div class="flex flex-wrap gap-2 mb-3">
              <span v-if="dish.category?.name" class="badge badge-orange font-semibold">{{ dish.category.name }}</span>
              <span v-if="dish.is_vegetarian" class="badge badge-green font-semibold">{{ $t('menu.veg') }}</span>
              <span v-if="dish.is_vegan"      class="badge badge-green font-semibold">{{ $t('menu.vegan') }}</span>
              <span v-if="dish.spice_level > 0" class="badge badge-red font-semibold">{{ $t('dish.spiceLevel', { level: dish.spice_level }) }}</span>
            </div>

            <h1 class="font-heading text-3xl md:text-4xl font-bold text-warm-900 dark:text-white mb-3">{{ dish.name }}</h1>
            <p class="text-warm-600 dark:text-warm-300 text-base leading-relaxed mb-6">{{ dish.description }}</p>

            <!-- Quick Meta Badges -->
            <div class="flex items-center gap-5 text-sm text-warm-500 dark:text-warm-400 mb-6 pb-6 border-b border-warm-100 dark:border-warm-800">
              <div class="flex items-center gap-1.5 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 px-3 py-1.5 rounded-xl font-medium">
                <Star :size="16" class="text-amber-500 fill-amber-500" />
                <span class="font-bold">{{ summaryStats.avg > 0 ? summaryStats.avg.toFixed(1) : $t('dish.newBadge') }}</span>
                <span v-if="summaryStats.total > 0" class="text-xs text-amber-600/80 dark:text-amber-400/80">{{ $t('dish.reviewsCount', { count: summaryStats.total }) }}</span>
              </div>
              <span v-if="dish.prep_time" class="flex items-center gap-1.5"><Clock :size="16" class="text-warm-400" />{{ $t('dish.prepTime', { time: dish.prep_time }) }}</span>
              <span v-if="dish.calories" class="flex items-center gap-1.5"><Leaf :size="16" class="text-warm-400" />{{ $t('dish.calories', { calories: dish.calories }) }}</span>
            </div>

            <!-- Price & Order Actions -->
            <div class="flex items-baseline gap-3 mb-6">
              <span class="font-heading text-4xl font-extrabold text-primary-600 dark:text-primary-400">
                ${{ Number(dish.price).toFixed(2) }}
              </span>
              <span class="text-xs uppercase tracking-wider text-warm-400 font-semibold">{{ $t('dish.perServing') }}</span>
            </div>

            <div class="flex items-center gap-4 mb-6">
              <div class="flex items-center gap-2 bg-warm-100 dark:bg-warm-800 rounded-2xl p-1.5 border border-warm-200 dark:border-warm-700">
                <button
                  @click="qty = Math.max(1, qty - 1)"
                  class="p-2 rounded-xl text-warm-600 hover:bg-white dark:hover:bg-warm-700 dark:text-warm-300 transition-colors"
                  aria-label="Decrease quantity"
                >
                  <Minus :size="16" />
                </button>
                <span class="w-10 text-center font-bold text-warm-900 dark:text-white">{{ qty }}</span>
                <button
                  @click="qty++"
                  class="p-2 rounded-xl text-warm-600 hover:bg-white dark:hover:bg-warm-700 dark:text-warm-300 transition-colors"
                  aria-label="Increase quantity"
                >
                  <Plus :size="16" />
                </button>
              </div>

              <button
                @click="addToCart"
                :disabled="loading"
                class="btn-primary flex-1 justify-center py-3.5 text-base font-semibold shadow-lg shadow-primary-500/20"
              >
                {{ loading ? $t('dish.adding') : $t('dish.addToCartWithPrice', { price: (Number(dish.price) * qty).toFixed(2) }) }}
              </button>
            </div>

            <!-- Allergens Note -->
            <div v-if="dish.allergens?.length" class="p-4 bg-amber-50 dark:bg-amber-950/20 rounded-2xl border border-amber-200/50 dark:border-amber-900/40">
              <p class="text-xs font-bold text-amber-800 dark:text-amber-400 mb-1 flex items-center gap-1.5">
                <Leaf :size="14" /> {{ $t('dish.dietaryAllergens') }}
              </p>
              <p class="text-xs text-amber-700 dark:text-amber-300 leading-relaxed">
                {{ $t('dish.contains') }} {{ dish.allergens.map(a => a.name).join(', ') }}
              </p>
            </div>
          </div>
        </div>

        <!-- ──────────────── Ratings & Reviews Section ──────────────── -->
        <section class="mt-16 pt-12 border-t border-warm-200 dark:border-warm-800">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
              <h2 class="font-heading text-2xl md:text-3xl font-bold text-warm-900 dark:text-white flex items-center gap-3">
                <Star :size="28" class="text-amber-400 fill-amber-400" />
                {{ $t('dish.ratingsAndReviews') }}
              </h2>
              <p class="text-warm-500 dark:text-warm-400 text-sm mt-1">
                {{ $t('dish.ratingsSubtitle') }}
              </p>
            </div>

            <button
              @click="showReviewForm = !showReviewForm"
              class="btn-secondary gap-2 self-start md:self-auto py-2.5 px-5 rounded-2xl"
            >
              <MessageSquare :size="16" />
              <span>{{ showReviewForm ? $t('dish.cancelReview') : $t('dish.writeReview') }}</span>
            </button>
          </div>

          <!-- Review Form Card -->
          <div
            v-if="showReviewForm"
            class="card p-6 md:p-8 mb-10 bg-gradient-to-br from-amber-500/5 via-primary-500/5 to-transparent border border-amber-500/20 rounded-3xl"
          >
            <h3 class="font-heading text-lg font-bold text-warm-900 dark:text-white mb-2">
              {{ $t('dish.rateExperience', { name: dish.name }) }}
            </h3>
            <p class="text-xs text-warm-500 dark:text-warm-400 mb-6">
              {{ $t('dish.ratePrompt') }}
            </p>

            <form @submit.prevent="handleReviewSubmit" class="space-y-6">
              <!-- Interactive 5-Star Picker -->
              <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-2">
                  {{ $t('dish.overallRating') }}
                </label>
                <div class="flex items-center gap-2">
                  <div class="flex items-center gap-1.5">
                    <button
                      v-for="starIndex in 5"
                      :key="starIndex"
                      type="button"
                      @mouseenter="ratingHover = starIndex"
                      @mouseleave="ratingHover = 0"
                      @click="newRating = starIndex"
                      class="p-1 rounded-xl transition-transform hover:scale-110 focus:outline-none"
                    >
                      <Star
                        :size="30"
                        :class="[
                          'transition-colors duration-150',
                          starIndex <= (ratingHover || newRating)
                            ? 'text-amber-400 fill-amber-400 drop-shadow'
                            : 'text-warm-300 dark:text-warm-700'
                        ]"
                      />
                    </button>
                  </div>
                  <span class="text-sm font-semibold text-amber-600 dark:text-amber-400 ml-3">
                    {{ ratingLabels[ratingHover || newRating] }}
                  </span>
                </div>
              </div>

              <!-- Comment Field -->
              <div>
                <label for="review-comment" class="block text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-2">
                  {{ $t('dish.reviewNotes') }}
                </label>
                <textarea
                  id="review-comment"
                  v-model="newComment"
                  rows="3"
                  maxlength="1000"
                  :placeholder="$t('dish.reviewPlaceholder')"
                  class="input w-full resize-none text-sm p-3.5 rounded-2xl"
                ></textarea>
                <div class="text-right text-xs text-warm-400 mt-1">
                  {{ newComment.length }}/1000 {{ $t('dish.characters') }}
                </div>
              </div>

              <!-- Submit Button -->
              <div class="flex items-center justify-between pt-2">
                <p v-if="!auth.isAuthenticated" class="text-xs text-amber-600 dark:text-amber-400">
                  {{ $t('dish.loginPrompt') }}
                </p>
                <div v-else class="text-xs text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 font-medium">
                  <CheckCircle2 :size="14" />
                  {{ $t('dish.postingAs', { name: auth.user?.name }) }}
                </div>

                <button
                  type="submit"
                  :disabled="submittingReview"
                  class="btn-primary gap-2 py-2.5 px-6 rounded-2xl shadow-md"
                >
                  <Send :size="16" />
                  <span>{{ submittingReview ? $t('dish.publishing') : $t('dish.submitReview') }}</span>
                </button>
              </div>
            </form>
          </div>

          <!-- Overall Breakdown & Metrics Grid -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6 md:p-8 bg-warm-50/80 dark:bg-warm-900/50 rounded-3xl border border-warm-200/60 dark:border-warm-800 mb-8">
            <!-- Overall Score Block -->
            <div class="flex flex-col items-center justify-center text-center p-4 border-b md:border-b-0 md:border-r border-warm-200 dark:border-warm-800">
              <span class="font-heading text-5xl md:text-6xl font-black text-warm-900 dark:text-white tracking-tight">
                {{ summaryStats.avg > 0 ? summaryStats.avg.toFixed(1) : '—' }}
              </span>
              <div class="flex items-center gap-1 my-2">
                <Star
                  v-for="s in 5"
                  :key="s"
                  :size="20"
                  :class="[
                    'transition-colors',
                    s <= Math.round(summaryStats.avg)
                      ? 'text-amber-400 fill-amber-400'
                      : 'text-warm-300 dark:text-warm-700'
                  ]"
                />
              </div>
              <p class="text-xs text-warm-500 font-medium">
                {{ summaryStats.total }} {{ $t('dish.verifiedRatings') }}
              </p>
            </div>

            <!-- 5-Star Breakdown Progress Bars -->
            <div class="md:col-span-2 flex flex-col justify-center gap-2.5 px-2">
              <div
                v-for="starVal in [5, 4, 3, 2, 1]"
                :key="starVal"
                class="flex items-center gap-3 text-xs"
              >
                <div class="flex items-center gap-1 w-12 text-warm-600 dark:text-warm-300 font-medium shrink-0">
                  <span>{{ starVal }}</span>
                  <Star :size="13" class="text-amber-400 fill-amber-400 shrink-0" />
                </div>
                <div class="flex-1 h-2.5 bg-warm-200 dark:bg-warm-800 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-amber-400 rounded-full transition-all duration-500"
                    :style="{
                      width: `${summaryStats.total > 0 ? ((summaryStats.breakdown[starVal] || 0) / summaryStats.total) * 100 : 0}%`
                    }"
                  ></div>
                </div>
                <span class="w-8 text-right text-warm-400 font-medium shrink-0">
                  {{ summaryStats.breakdown[starVal] || 0 }}
                </span>
              </div>
            </div>
          </div>

          <!-- Reviews List -->
          <div v-if="loadingReviews" class="space-y-4">
            <div v-for="i in 3" :key="i" class="card p-5 space-y-3">
              <div class="flex items-center gap-3">
                <div class="skeleton w-10 h-10 rounded-full"></div>
                <div class="space-y-1">
                  <div class="skeleton h-4 w-28"></div>
                  <div class="skeleton h-3 w-16"></div>
                </div>
              </div>
              <div class="skeleton h-4 w-full"></div>
            </div>
          </div>

          <div v-else-if="reviewsList.length > 0" class="space-y-4">
            <div
              v-for="rev in reviewsList"
              :key="rev.id"
              class="card p-6 border border-warm-200/70 dark:border-warm-800 rounded-2xl hover:border-amber-400/30 transition-colors"
            >
              <div class="flex items-start justify-between gap-4 mb-3">
                <!-- User Profile Header -->
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-amber-500 text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-sm">
                    {{ getInitials(rev.user?.name) }}
                  </div>
                  <div>
                    <h4 class="font-heading font-semibold text-sm text-warm-900 dark:text-white">
                      {{ rev.user?.name || $t('dish.verifiedDiner') }}
                    </h4>
                    <span class="text-xs text-warm-400">
                      {{ formatDate(rev.created_at) }}
                    </span>
                  </div>
                </div>

                <!-- Stars Display -->
                <div class="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/30 px-2.5 py-1 rounded-xl">
                  <Star
                    v-for="s in 5"
                    :key="s"
                    :size="14"
                    :class="s <= rev.rating ? 'text-amber-400 fill-amber-400' : 'text-warm-300 dark:text-warm-700'"
                  />
                  <span class="text-xs font-bold text-amber-700 dark:text-amber-400 ml-1">{{ rev.rating }}.0</span>
                </div>
              </div>

              <!-- Comment Body -->
              <p v-if="rev.comment" class="text-sm text-warm-700 dark:text-warm-300 leading-relaxed pl-13">
                {{ rev.comment }}
              </p>
              <p v-else class="text-xs text-warm-400 italic pl-13">
                {{ $t('dish.ratedWithoutComment', { rating: rev.rating }) }}
              </p>
            </div>
          </div>

          <!-- Empty Reviews State -->
          <div
            v-else
            class="text-center py-12 px-6 card border-dashed border-2 border-warm-200 dark:border-warm-800 rounded-3xl"
          >
            <div class="w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-950/40 text-amber-500 flex items-center justify-center mx-auto mb-4">
              <Star :size="32" class="fill-amber-400/30" />
            </div>
            <h3 class="font-heading text-lg font-bold text-warm-900 dark:text-white mb-1">
              {{ $t('dish.noReviewsYet', { name: dish.name }) }}
            </h3>
            <p class="text-xs text-warm-500 dark:text-warm-400 max-w-md mx-auto mb-6">
              {{ $t('dish.beFirstReview') }}
            </p>
            <button
              @click="showReviewForm = true"
              class="btn-primary py-2.5 px-6 rounded-2xl text-xs gap-2"
            >
              <Star :size="14" class="fill-current" />
              <span>{{ $t('dish.writeFirstReview') }}</span>
            </button>
          </div>
        </section>
      </div>
    </div>

    <!-- Sticky Mobile Floating Add-to-Cart Bar -->
    <div
      v-if="dish"
      class="md:hidden fixed bottom-14 left-0 right-0 z-30 bg-white/95 dark:bg-gray-900/95 backdrop-blur-xl border-t border-warm-200 dark:border-warm-800 p-3 shadow-2xl flex items-center justify-between gap-3"
    >
      <div class="min-w-0">
        <p class="text-xs text-warm-500 font-medium truncate">{{ dish.name }}</p>
        <p class="font-heading font-extrabold text-base text-primary-600 dark:text-primary-400">
          ${{ (Number(dish.price) * qty).toFixed(2) }}
        </p>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <div class="flex items-center bg-warm-100 dark:bg-warm-800 rounded-xl p-1 border border-warm-200 dark:border-warm-700">
          <button
            type="button"
            @click="qty = Math.max(1, qty - 1)"
            class="p-1 rounded-lg text-warm-600 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700 transition-colors"
            aria-label="Decrease quantity"
          >
            <Minus :size="14" />
          </button>
          <span class="w-6 text-center text-xs font-bold text-warm-900 dark:text-white">{{ qty }}</span>
          <button
            type="button"
            @click="qty++"
            class="p-1 rounded-lg text-warm-600 dark:text-warm-300 hover:bg-warm-200 dark:hover:bg-warm-700 transition-colors"
            aria-label="Increase quantity"
          >
            <Plus :size="14" />
          </button>
        </div>

        <button
          @click="addToCart"
          :disabled="loading"
          class="btn-primary py-2.5 px-4 text-xs font-semibold shadow-md whitespace-nowrap"
        >
          {{ loading ? '...' : $t('dish.addToCart') }}
        </button>
      </div>
    </div>
  </DefaultLayout>
</template>
