<script setup>
import { ref, onMounted, watch } from 'vue'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import {
  Star, Search, CheckCircle, EyeOff, Trash2,
  RefreshCw, MessageSquare, ThumbsUp, Filter, AlertCircle
} from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'

const ui = useUiStore()

const loading = ref(false)
const reviews = ref([])
const stats = ref({
  total_reviews: 0,
  approved_count: 0,
  pending_count: 0,
  avg_rating: 0,
})

const meta = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
})

// Filters
const search = ref('')
const selectedStatus = ref('')
const selectedRating = ref('')
const deletingId = ref(null)
const updatingId = ref(null)

async function fetchReviews(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      search: search.value.trim() || undefined,
      status: selectedStatus.value || undefined,
      rating: selectedRating.value || undefined,
    }
    const { data } = await adminApi.reviews(params)
    reviews.value = data.data || []
    if (data.meta) meta.value = data.meta
    if (data.stats) stats.value = data.stats
  } catch (err) {
    ui.toast('Failed to load customer reviews', 'error')
  } finally {
    loading.value = false
  }
}

async function toggleApproval(review) {
  updatingId.value = review.id
  const targetState = !review.is_approved
  try {
    const { data } = await adminApi.updateReview(review.id, { is_approved: targetState })
    review.is_approved = targetState
    ui.toast(data.message || (targetState ? 'Review approved and published' : 'Review hidden'), 'success')
    // Refresh stats
    fetchReviews(meta.value.current_page)
  } catch (err) {
    ui.toast('Failed to update review status', 'error')
  } finally {
    updatingId.value = null
  }
}

async function deleteReview(review) {
  if (!confirm(`Are you sure you want to delete this review by ${review.user?.name || 'Customer'}? This action cannot be undone.`)) {
    return
  }

  deletingId.value = review.id
  try {
    await adminApi.deleteReview(review.id)
    ui.toast('Review deleted successfully', 'success')
    fetchReviews(meta.value.current_page)
  } catch (err) {
    ui.toast('Failed to delete review', 'error')
  } finally {
    deletingId.value = null
  }
}

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    fetchReviews(1)
  }, 400)
})

watch([selectedStatus, selectedRating], () => {
  fetchReviews(1)
})

onMounted(() => {
  fetchReviews(1)
})

function formatDate(isoStr) {
  if (!isoStr) return ''
  try {
    const d = new Date(isoStr)
    return d.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
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
  <div class="space-y-8">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="font-heading text-2xl md:text-3xl font-bold text-warm-900 dark:text-white flex items-center gap-3">
          <Star :size="28" class="text-amber-500 fill-amber-500" />
          Reviews & Rating Moderation
        </h1>
        <p class="text-xs md:text-sm text-warm-500 dark:text-warm-400 mt-1">
          Monitor customer feedback, manage published reviews, and maintain restaurant quality standards.
        </p>
      </div>

      <button
        @click="fetchReviews(meta.current_page)"
        :disabled="loading"
        class="btn-secondary self-start sm:self-auto gap-2 text-xs py-2 px-4 rounded-xl shadow-sm"
      >
        <RefreshCw :size="15" :class="{ 'animate-spin': loading }" />
        <span>Refresh</span>
      </button>
    </div>

    <!-- Stats Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="card p-5 border border-warm-200/80 dark:border-warm-800 rounded-2xl bg-white dark:bg-warm-900">
        <p class="text-xs uppercase font-semibold text-warm-400 tracking-wider">Total Reviews</p>
        <div class="flex items-baseline gap-2 mt-2">
          <span class="font-heading text-3xl font-extrabold text-warm-900 dark:text-white">
            {{ stats.total_reviews }}
          </span>
          <MessageSquare :size="16" class="text-warm-400 ml-auto" />
        </div>
      </div>

      <div class="card p-5 border border-amber-500/20 rounded-2xl bg-gradient-to-br from-amber-500/5 to-transparent">
        <p class="text-xs uppercase font-semibold text-amber-600 dark:text-amber-400 tracking-wider">Average Rating</p>
        <div class="flex items-baseline gap-2 mt-2">
          <span class="font-heading text-3xl font-extrabold text-amber-600 dark:text-amber-400">
            {{ Number(stats.avg_rating).toFixed(1) }}★
          </span>
          <Star :size="18" class="text-amber-500 fill-amber-500 ml-auto" />
        </div>
      </div>

      <div class="card p-5 border border-emerald-500/20 rounded-2xl bg-gradient-to-br from-emerald-500/5 to-transparent">
        <p class="text-xs uppercase font-semibold text-emerald-600 dark:text-emerald-400 tracking-wider">Approved & Live</p>
        <div class="flex items-baseline gap-2 mt-2">
          <span class="font-heading text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
            {{ stats.approved_count }}
          </span>
          <CheckCircle :size="18" class="text-emerald-500 ml-auto" />
        </div>
      </div>

      <div class="card p-5 border border-orange-500/20 rounded-2xl bg-gradient-to-br from-orange-500/5 to-transparent">
        <p class="text-xs uppercase font-semibold text-orange-600 dark:text-orange-400 tracking-wider">Hidden / Pending</p>
        <div class="flex items-baseline gap-2 mt-2">
          <span class="font-heading text-3xl font-extrabold text-orange-600 dark:text-orange-400">
            {{ stats.pending_count }}
          </span>
          <EyeOff :size="18" class="text-orange-500 ml-auto" />
        </div>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card p-4 rounded-2xl border border-warm-200/80 dark:border-warm-800 flex flex-col md:flex-row gap-3 items-center justify-between">
      <!-- Search -->
      <div class="relative w-full md:w-80">
        <Search :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-warm-400" />
        <input
          v-model="search"
          type="text"
          placeholder="Search customer, dish, comment..."
          class="input w-full pl-10 pr-4 py-2 text-xs rounded-xl"
        />
      </div>

      <!-- Dropdown Filters -->
      <div class="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
        <!-- Status Filter -->
        <select
          v-model="selectedStatus"
          class="input text-xs py-2 px-3 rounded-xl"
        >
          <option value="">All Statuses</option>
          <option value="approved">Approved Only</option>
          <option value="pending">Hidden / Pending Only</option>
        </select>

        <!-- Rating Filter -->
        <select
          v-model="selectedRating"
          class="input text-xs py-2 px-3 rounded-xl"
        >
          <option value="">All Ratings</option>
          <option value="5">5 Stars</option>
          <option value="4">4 Stars</option>
          <option value="3">3 Stars</option>
          <option value="2">2 Stars</option>
          <option value="1">1 Star</option>
        </select>
      </div>
    </div>

    <!-- Reviews Table / List -->
    <div class="card overflow-hidden border border-warm-200/80 dark:border-warm-800 rounded-3xl shadow-sm">
      <TableSkeleton v-if="loading && reviews.length === 0" :rows="5" :columns="7" />

      <div v-else-if="reviews.length > 0" class="overflow-x-auto">
        <table class="w-full text-left text-xs text-warm-600 dark:text-warm-300">
          <thead class="bg-warm-50 dark:bg-warm-800/60 uppercase font-semibold text-warm-500 border-b border-warm-200 dark:border-warm-800">
            <tr>
              <th class="py-3.5 px-5">Customer</th>
              <th class="py-3.5 px-4">Dish</th>
              <th class="py-3.5 px-4">Rating</th>
              <th class="py-3.5 px-4">Comment</th>
              <th class="py-3.5 px-4">Date</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr
              v-for="rev in reviews"
              :key="rev.id"
              class="hover:bg-warm-50/50 dark:hover:bg-warm-800/40 transition-colors"
            >
              <!-- Customer -->
              <td class="py-4 px-5">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-primary-600 to-amber-500 text-white font-bold flex items-center justify-center shrink-0 text-xs">
                    {{ getInitials(rev.user?.name) }}
                  </div>
                  <div>
                    <p class="font-bold text-warm-900 dark:text-white leading-tight">
                      {{ rev.user?.name || 'Customer' }}
                    </p>
                    <p class="text-[11px] text-warm-400">{{ rev.user?.email || '—' }}</p>
                  </div>
                </div>
              </td>

              <!-- Dish -->
              <td class="py-4 px-4">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-lg bg-warm-100 dark:bg-warm-800 overflow-hidden shrink-0">
                    <img
                      v-if="rev.dish?.image"
                      :src="rev.dish.image"
                      :alt="rev.dish.name"
                      class="w-full h-full object-cover"
                    />
                  </div>
                  <div>
                    <span class="font-semibold text-warm-900 dark:text-white block">
                      {{ rev.dish?.name || 'Dish #' + rev.dish_id }}
                    </span>
                    <span v-if="rev.order?.order_number" class="text-[10px] text-primary-600 dark:text-primary-400 font-mono">
                      Order: {{ rev.order.order_number }}
                    </span>
                  </div>
                </div>
              </td>

              <!-- Star Rating -->
              <td class="py-4 px-4 whitespace-nowrap">
                <div class="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/40 px-2 py-1 rounded-lg w-max border border-amber-300/40 dark:border-amber-800/40">
                  <Star
                    v-for="s in 5"
                    :key="s"
                    :size="13"
                    :class="s <= rev.rating ? 'text-amber-400 fill-amber-400' : 'text-warm-300 dark:text-warm-700'"
                  />
                  <span class="text-xs font-bold text-amber-700 dark:text-amber-300 ml-1">
                    {{ rev.rating }}
                  </span>
                </div>
              </td>

              <!-- Comment -->
              <td class="py-4 px-4 max-w-xs">
                <p v-if="rev.comment" class="line-clamp-2 text-warm-700 dark:text-warm-300 text-xs">
                  {{ rev.comment }}
                </p>
                <span v-else class="text-warm-400 italic text-[11px]">No written comment</span>
              </td>

              <!-- Date -->
              <td class="py-4 px-4 whitespace-nowrap text-warm-400 text-[11px]">
                {{ formatDate(rev.created_at) }}
              </td>

              <!-- Status Badge -->
              <td class="py-4 px-4 whitespace-nowrap">
                <span :class="['badge text-[11px] font-bold uppercase', rev.is_approved ? 'badge-green' : 'badge-orange']">
                  {{ rev.is_approved ? 'Approved' : 'Hidden' }}
                </span>
              </td>

              <!-- Actions -->
              <td class="py-4 px-5 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <!-- Toggle Approve / Hide Button -->
                  <button
                    @click="toggleApproval(rev)"
                    :disabled="updatingId === rev.id"
                    :title="rev.is_approved ? 'Hide from public view' : 'Approve for public view'"
                    :class="[
                      'p-2 rounded-xl text-xs font-semibold transition-colors',
                      rev.is_approved
                        ? 'text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-950/50'
                        : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50'
                    ]"
                  >
                    <component :is="rev.is_approved ? EyeOff : CheckCircle" :size="16" />
                  </button>

                  <!-- Delete Button -->
                  <button
                    @click="deleteReview(rev)"
                    :disabled="deletingId === rev.id"
                    title="Delete review permanently"
                    class="p-2 rounded-xl text-red-500 hover:bg-red-50 dark:hover:bg-red-950/50 transition-colors"
                  >
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Empty State -->
      <div v-else class="p-12 text-center">
        <div class="w-14 h-14 rounded-full bg-warm-100 dark:bg-warm-800 text-warm-400 flex items-center justify-center mx-auto mb-3">
          <Star :size="28" />
        </div>
        <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white mb-1">No reviews found</h3>
        <p class="text-xs text-warm-400 max-w-sm mx-auto">
          No customer reviews match your active filter criteria. Try clearing your search or status filters.
        </p>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="meta.last_page > 1"
        class="p-4 border-t border-warm-200 dark:border-warm-800 flex items-center justify-between text-xs text-warm-500"
      >
        <span>Page {{ meta.current_page }} of {{ meta.last_page }} ({{ meta.total }} total)</span>
        <div class="flex gap-2">
          <button
            @click="fetchReviews(meta.current_page - 1)"
            :disabled="meta.current_page <= 1"
            class="btn-secondary py-1.5 px-3 rounded-lg text-xs"
          >
            Previous
          </button>
          <button
            @click="fetchReviews(meta.current_page + 1)"
            :disabled="meta.current_page >= meta.last_page"
            class="btn-secondary py-1.5 px-3 rounded-lg text-xs"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
