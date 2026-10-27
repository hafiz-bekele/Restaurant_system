<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { adminApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useI18n } from 'vue-i18n'
import {
  Star, Search, CheckCircle, EyeOff, Trash2,
  RefreshCw, MessageSquare, ThumbsUp, Filter, AlertCircle,
  Camera, Image as ImageIcon, Sparkles, X, ShieldCheck, ArrowUpDown,
  CornerDownRight, CheckSquare, Square, ExternalLink, Utensils, BedDouble, Send
} from '@lucide/vue'
import TableSkeleton from '../../components/ui/TableSkeleton.vue'
import AppButton from '../../components/ui/AppButton.vue'

const { t } = useI18n()
const ui = useUiStore()

const loading = ref(false)
const reviews = ref([])
const stats = ref({
  total_reviews: 0,
  approved_count: 0,
  pending_count: 0,
  replied_count: 0,
  reply_rate: 0,
  with_images_count: 0,
  avg_rating: 0,
  five_star_count: 0,
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
const selectedType = ref('')
const selectedReplyStatus = ref('')
const selectedHasImages = ref('')
const sortOrder = ref('latest')

const deletingId = ref(null)
const updatingId = ref(null)

// Bulk selections
const selectedIds = ref([])
const bulkActing = ref(false)

// Reply Modal state
const replyModalOpen = ref(false)
const targetReview = ref(null)
const replyDraft = ref('')
const submittingReply = ref(false)

// Lightbox state
const lightboxImage = ref(null)

const isAllSelected = computed(() => {
  if (!reviews.value.length) return false
  return reviews.value.every(r => selectedIds.value.includes(r.id))
})

async function fetchReviews(page = 1) {
  loading.value = true
  try {
    const params = {
      page,
      search: search.value.trim() || undefined,
      status: selectedStatus.value || undefined,
      rating: selectedRating.value || undefined,
      type: selectedType.value || undefined,
      has_reply: selectedReplyStatus.value || undefined,
      has_images: selectedHasImages.value || undefined,
      sort: sortOrder.value,
    }
    const { data } = await adminApi.reviews(params)
    reviews.value = data.data || []
    if (data.meta) meta.value = data.meta
    if (data.stats) stats.value = data.stats
  } catch (err) {
    ui.toast(t('adminReviews.toastFailed'), 'error')
  } finally {
    loading.value = false
  }
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedIds.value = []
  } else {
    selectedIds.value = reviews.value.map(r => r.id)
  }
}

function toggleSelectReview(id) {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

async function applyBulkAction(action) {
  if (!selectedIds.value.length) return
  if (action === 'delete' && !confirm(t('adminReviews.confirmDelete'))) {
    return
  }

  bulkActing.value = true
  try {
    await adminApi.bulkReviews(action, selectedIds.value)
    ui.toast(t('adminReviews.toastBulkSuccess'), 'success')
    selectedIds.value = []
    fetchReviews(meta.value.current_page)
  } catch {
    ui.toast(t('adminReviews.toastFailed'), 'error')
  } finally {
    bulkActing.value = false
  }
}

async function toggleApproval(review) {
  updatingId.value = review.id
  const targetState = !review.is_approved
  try {
    await adminApi.updateReview(review.id, { is_approved: targetState })
    review.is_approved = targetState
    ui.toast(targetState ? t('adminReviews.toastApproved') : t('adminReviews.toastHidden'), 'success')
    fetchReviews(meta.value.current_page)
  } catch (err) {
    ui.toast(t('adminReviews.toastFailed'), 'error')
  } finally {
    updatingId.value = null
  }
}

async function toggleFeatured(review) {
  updatingId.value = review.id
  const targetState = !review.is_featured
  try {
    await adminApi.updateReview(review.id, { is_featured: targetState })
    review.is_featured = targetState
    ui.toast(targetState ? 'Review featured on homepage showcase' : 'Review removed from showcase', 'success')
  } catch (err) {
    ui.toast(t('adminReviews.toastFailed'), 'error')
  } finally {
    updatingId.value = null
  }
}

function openReplyModal(review) {
  targetReview.value = review
  replyDraft.value = review.admin_reply || ''
  replyModalOpen.value = true
}

function closeReplyModal() {
  replyModalOpen.value = false
  targetReview.value = null
  replyDraft.value = ''
}

function applyTemplate(text) {
  replyDraft.value = text
}

async function submitReply() {
  if (!targetReview.value || !replyDraft.value.trim()) return
  submittingReply.value = true
  try {
    await adminApi.replyReview(targetReview.value.id, replyDraft.value.trim())
    targetReview.value.admin_reply = replyDraft.value.trim()
    targetReview.value.replied_at = new Date().toISOString()
    ui.toast(t('adminReviews.toastReplied'), 'success')
    closeReplyModal()
    fetchReviews(meta.value.current_page)
  } catch {
    ui.toast(t('adminReviews.toastFailed'), 'error')
  } finally {
    submittingReply.value = false
  }
}

async function deleteReply(review) {
  if (!confirm(t('adminReviews.deleteReplyBtn') + '?')) return
  try {
    await adminApi.deleteReviewReply(review.id)
    review.admin_reply = null
    review.replied_at = null
    ui.toast(t('adminReviews.toastReplyDeleted'), 'success')
    if (targetReview.value?.id === review.id) {
      closeReplyModal()
    }
    fetchReviews(meta.value.current_page)
  } catch {
    ui.toast(t('adminReviews.toastFailed'), 'error')
  }
}

async function deleteReview(review) {
  if (!confirm(t('adminReviews.confirmDelete'))) {
    return
  }

  deletingId.value = review.id
  try {
    await adminApi.deleteReview(review.id)
    ui.toast(t('adminReviews.toastDeleted'), 'success')
    fetchReviews(meta.value.current_page)
  } catch (err) {
    ui.toast(t('adminReviews.toastFailed'), 'error')
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

watch([selectedStatus, selectedRating, selectedType, selectedReplyStatus, selectedHasImages, sortOrder], () => {
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
          {{ $t('adminReviews.title') }}
        </h1>
        <p class="text-xs md:text-sm text-warm-500 dark:text-warm-400 mt-1">
          {{ $t('adminReviews.subtitle') }}
        </p>
      </div>

      <button
        @click="fetchReviews(meta.current_page)"
        :disabled="loading"
        class="btn-secondary self-start sm:self-auto gap-2 text-xs py-2 px-4 rounded-xl shadow-sm"
      >
        <RefreshCw :size="15" :class="{ 'animate-spin': loading }" />
        <span>{{ $t('adminReviews.refresh') }}</span>
      </button>
    </div>

    <!-- Enhanced Stats Cards Grid -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3.5">
      <div class="card p-4 border border-warm-200/80 dark:border-warm-800 rounded-2xl bg-white dark:bg-warm-900">
        <p class="text-[11px] uppercase font-semibold text-warm-400 tracking-wider">{{ $t('adminReviews.totalReviews') }}</p>
        <div class="flex items-baseline gap-2 mt-1.5">
          <span class="font-heading text-2xl font-extrabold text-warm-900 dark:text-white">
            {{ stats.total_reviews }}
          </span>
          <MessageSquare :size="15" class="text-warm-400 ml-auto" />
        </div>
      </div>

      <div class="card p-4 border border-amber-500/20 rounded-2xl bg-gradient-to-br from-amber-500/5 to-transparent">
        <p class="text-[11px] uppercase font-semibold text-amber-600 dark:text-amber-400 tracking-wider">{{ $t('adminReviews.avgRating') }}</p>
        <div class="flex items-baseline gap-2 mt-1.5">
          <span class="font-heading text-2xl font-extrabold text-amber-600 dark:text-amber-400">
            {{ Number(stats.avg_rating).toFixed(1) }}★
          </span>
          <Star :size="16" class="text-amber-500 fill-amber-500 ml-auto" />
        </div>
      </div>

      <div class="card p-4 border border-emerald-500/20 rounded-2xl bg-gradient-to-br from-emerald-500/5 to-transparent">
        <p class="text-[11px] uppercase font-semibold text-emerald-600 dark:text-emerald-400 tracking-wider">{{ $t('adminReviews.approvedCount') }}</p>
        <div class="flex items-baseline gap-2 mt-1.5">
          <span class="font-heading text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">
            {{ stats.approved_count }}
          </span>
          <CheckCircle :size="16" class="text-emerald-500 ml-auto" />
        </div>
      </div>

      <div class="card p-4 border border-orange-500/20 rounded-2xl bg-gradient-to-br from-orange-500/5 to-transparent">
        <p class="text-[11px] uppercase font-semibold text-orange-600 dark:text-orange-400 tracking-wider">{{ $t('adminReviews.pendingCount') }}</p>
        <div class="flex items-baseline gap-2 mt-1.5">
          <span class="font-heading text-2xl font-extrabold text-orange-600 dark:text-orange-400">
            {{ stats.pending_count }}
          </span>
          <EyeOff :size="16" class="text-orange-500 ml-auto" />
        </div>
      </div>

      <div class="card p-4 border border-primary-500/20 rounded-2xl bg-gradient-to-br from-primary-500/5 to-transparent">
        <p class="text-[11px] uppercase font-semibold text-primary-600 dark:text-primary-400 tracking-wider">{{ $t('adminReviews.repliedCount') }}</p>
        <div class="flex items-baseline gap-2 mt-1.5">
          <span class="font-heading text-2xl font-extrabold text-primary-600 dark:text-primary-400">
            {{ stats.replied_count }}
          </span>
          <span class="text-[10px] text-warm-400 ml-1">({{ stats.reply_rate }}%)</span>
          <Sparkles :size="15" class="text-primary-500 ml-auto" />
        </div>
      </div>

      <div class="card p-4 border border-indigo-500/20 rounded-2xl bg-gradient-to-br from-indigo-500/5 to-transparent">
        <p class="text-[11px] uppercase font-semibold text-indigo-600 dark:text-indigo-400 tracking-wider">{{ $t('adminReviews.withPhotosCount') }}</p>
        <div class="flex items-baseline gap-2 mt-1.5">
          <span class="font-heading text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
            {{ stats.with_images_count }}
          </span>
          <ImageIcon :size="16" class="text-indigo-500 ml-auto" />
        </div>
      </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card p-4 rounded-2xl border border-warm-200/80 dark:border-warm-800 space-y-3">
      <div class="flex flex-col lg:flex-row gap-3 items-stretch lg:items-center justify-between">
        <!-- Search -->
        <div class="relative flex-1 max-w-md">
          <Search :size="16" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-warm-400" />
          <input
            v-model="search"
            type="text"
            :placeholder="$t('adminReviews.searchPlaceholder')"
            class="input w-full pl-10 pr-4 py-2 text-xs rounded-xl"
          />
        </div>

        <!-- Dropdown Filters Row -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Status Filter -->
          <select
            v-model="selectedStatus"
            class="input text-xs py-2 px-3 rounded-xl bg-warm-50 dark:bg-warm-800"
          >
            <option value="">{{ $t('adminReviews.filterAllStatus') }}</option>
            <option value="approved">{{ $t('adminReviews.filterApproved') }}</option>
            <option value="pending">{{ $t('adminReviews.filterPending') }}</option>
          </select>

          <!-- Rating Filter -->
          <select
            v-model="selectedRating"
            class="input text-xs py-2 px-3 rounded-xl bg-warm-50 dark:bg-warm-800"
          >
            <option value="">{{ $t('adminReviews.filterAllRatings') }}</option>
            <option value="5">{{ $t('adminReviews.filterStars', { count: 5 }) }}</option>
            <option value="4">{{ $t('adminReviews.filterStars', { count: 4 }) }}</option>
            <option value="3">{{ $t('adminReviews.filterStars', { count: 3 }) }}</option>
            <option value="2">{{ $t('adminReviews.filterStars', { count: 2 }) }}</option>
            <option value="1">{{ $t('adminReviews.filterStars', { count: 1 }) }}</option>
          </select>

          <!-- Entity Type Filter -->
          <select
            v-model="selectedType"
            class="input text-xs py-2 px-3 rounded-xl bg-warm-50 dark:bg-warm-800"
          >
            <option value="">{{ $t('adminReviews.filterAllTypes') }}</option>
            <option value="dish">{{ $t('adminReviews.filterDish') }}</option>
            <option value="room">{{ $t('adminReviews.filterRoom') }}</option>
          </select>

          <!-- Reply Filter -->
          <select
            v-model="selectedReplyStatus"
            class="input text-xs py-2 px-3 rounded-xl bg-warm-50 dark:bg-warm-800"
          >
            <option value="">All Reply States</option>
            <option value="yes">{{ $t('adminReviews.filterHasReply') }}</option>
            <option value="no">{{ $t('adminReviews.filterNoReply') }}</option>
          </select>

          <!-- Photos Filter -->
          <select
            v-model="selectedHasImages"
            class="input text-xs py-2 px-3 rounded-xl bg-warm-50 dark:bg-warm-800"
          >
            <option value="">All Media</option>
            <option value="yes">{{ $t('adminReviews.filterWithPhotos') }}</option>
          </select>

          <!-- Sorting -->
          <div class="flex items-center gap-1">
            <ArrowUpDown :size="14" class="text-warm-400 shrink-0" />
            <select
              v-model="sortOrder"
              class="input text-xs py-2 px-2.5 rounded-xl bg-warm-50 dark:bg-warm-800"
            >
              <option value="latest">Latest</option>
              <option value="oldest">Oldest</option>
              <option value="rating_desc">Highest Rated</option>
              <option value="rating_asc">Lowest Rated</option>
              <option value="helpful">Most Helpful</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Bulk Actions Bar (Revealed when items are selected) -->
      <div
        v-if="selectedIds.length > 0"
        class="flex flex-wrap items-center justify-between gap-3 p-3 bg-primary-500/10 dark:bg-primary-950/40 border border-primary-500/30 rounded-xl text-xs"
      >
        <div class="flex items-center gap-2 text-primary-700 dark:text-primary-300 font-semibold">
          <CheckSquare :size="16" />
          <span>{{ $t('adminReviews.selectedCount', { count: selectedIds.length }) }}</span>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="applyBulkAction('approve')"
            :disabled="bulkActing"
            class="btn-primary py-1 px-3 text-xs rounded-lg gap-1.5"
          >
            <CheckCircle :size="14" />
            <span>{{ $t('adminReviews.bulkApprove') }}</span>
          </button>

          <button
            type="button"
            @click="applyBulkAction('hide')"
            :disabled="bulkActing"
            class="btn-secondary py-1 px-3 text-xs rounded-lg gap-1.5"
          >
            <EyeOff :size="14" />
            <span>{{ $t('adminReviews.bulkHide') }}</span>
          </button>

          <button
            type="button"
            @click="applyBulkAction('delete')"
            :disabled="bulkActing"
            class="btn-danger py-1 px-3 text-xs rounded-lg gap-1.5"
          >
            <Trash2 :size="14" />
            <span>{{ $t('adminReviews.bulkDelete') }}</span>
          </button>

          <button
            type="button"
            @click="selectedIds = []"
            class="text-warm-500 hover:text-warm-700 dark:hover:text-warm-300 text-xs px-2"
          >
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Reviews Table -->
    <div class="card overflow-hidden border border-warm-200/80 dark:border-warm-800 rounded-3xl shadow-sm">
      <TableSkeleton v-if="loading && reviews.length === 0" :rows="5" :columns="7" />

      <div v-else-if="reviews.length > 0" class="overflow-x-auto">
        <table class="w-full text-left text-xs text-warm-600 dark:text-warm-300">
          <thead class="bg-warm-50 dark:bg-warm-800/60 uppercase font-semibold text-warm-500 border-b border-warm-200 dark:border-warm-800">
            <tr>
              <th class="py-3.5 px-4 w-10">
                <button
                  type="button"
                  @click="toggleSelectAll"
                  class="text-warm-400 hover:text-warm-600 dark:hover:text-warm-200"
                  aria-label="Select all reviews"
                >
                  <component :is="isAllSelected ? CheckSquare : Square" :size="16" />
                </button>
              </th>
              <th class="py-3.5 px-4">{{ $t('adminReviews.thGuest') }}</th>
              <th class="py-3.5 px-4">{{ $t('adminReviews.thDish') }}</th>
              <th class="py-3.5 px-4">{{ $t('adminReviews.thRating') }}</th>
              <th class="py-3.5 px-4 max-w-sm">{{ $t('adminReviews.thComment') }}</th>
              <th class="py-3.5 px-4">{{ $t('adminReviews.thDate') }}</th>
              <th class="py-3.5 px-4">{{ $t('adminReviews.thStatus') }}</th>
              <th class="py-3.5 px-5 text-right">{{ $t('adminReviews.thActions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-warm-100 dark:divide-warm-800">
            <tr
              v-for="rev in reviews"
              :key="rev.id"
              :class="[
                'hover:bg-warm-50/50 dark:hover:bg-warm-800/40 transition-colors',
                selectedIds.includes(rev.id) ? 'bg-primary-500/5 dark:bg-primary-950/20' : ''
              ]"
            >
              <!-- Checkbox -->
              <td class="py-4 px-4">
                <button
                  type="button"
                  @click="toggleSelectReview(rev.id)"
                  class="text-warm-400 hover:text-warm-600 dark:hover:text-warm-200"
                  :aria-label="`Select review #${rev.id}`"
                >
                  <component :is="selectedIds.includes(rev.id) ? CheckSquare : Square" :size="16" class="text-primary-600 dark:text-primary-400" />
                </button>
              </td>

              <!-- Customer -->
              <td class="py-4 px-4 whitespace-nowrap">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-primary-600 to-amber-500 text-white font-bold flex items-center justify-center shrink-0 text-xs">
                    {{ getInitials(rev.user?.name) }}
                  </div>
                  <div>
                    <p class="font-bold text-warm-900 dark:text-white leading-tight">
                      {{ rev.user?.name || $t('adminReviews.verifiedDiner') }}
                    </p>
                    <p class="text-[11px] text-warm-400">{{ rev.user?.email || '—' }}</p>
                  </div>
                </div>
              </td>

              <!-- Entity (Dish or Room) -->
              <td class="py-4 px-4 whitespace-nowrap">
                <!-- Dish -->
                <div v-if="rev.dish" class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-lg bg-warm-100 dark:bg-warm-800 overflow-hidden shrink-0">
                    <img
                      v-if="rev.dish?.image"
                      :src="rev.dish.image"
                      :alt="rev.dish.name"
                      class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-warm-400">
                      <Utensils :size="15" />
                    </div>
                  </div>
                  <div>
                    <span class="font-semibold text-warm-900 dark:text-white block max-w-[140px] truncate">
                      {{ rev.dish.name }}
                    </span>
                    <span v-if="rev.order?.order_number" class="text-[10px] text-primary-600 dark:text-primary-400 font-mono">
                      Order: {{ rev.order.order_number }}
                    </span>
                  </div>
                </div>

                <!-- Room -->
                <div v-else-if="rev.room" class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <BedDouble :size="16" />
                  </div>
                  <div>
                    <span class="font-semibold text-warm-900 dark:text-white block">
                      Room {{ rev.room.room_number }}
                    </span>
                    <span class="text-[10px] text-warm-400 capitalize">{{ rev.room.type }}</span>
                  </div>
                </div>

                <span v-else class="text-warm-400 italic">Unassigned</span>
              </td>

              <!-- Star Rating & Badges -->
              <td class="py-4 px-4 whitespace-nowrap">
                <div class="space-y-1">
                  <div class="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded-lg w-max border border-amber-300/40 dark:border-amber-800/40">
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

                  <div class="flex items-center gap-1.5 text-[10px]">
                    <span v-if="rev.helpful_count > 0" class="inline-flex items-center gap-1 text-warm-500">
                      <ThumbsUp :size="11" /> {{ rev.helpful_count }}
                    </span>
                    <span v-if="rev.is_featured" class="badge badge-purple text-[9px] py-0 px-1 font-bold">
                      ★ Featured
                    </span>
                  </div>
                </div>
              </td>

              <!-- Title, Comment & Photos -->
              <td class="py-4 px-4 max-w-sm">
                <div class="space-y-1.5">
                  <!-- Title -->
                  <p v-if="rev.title" class="font-bold text-warm-900 dark:text-white text-xs leading-snug">
                    {{ rev.title }}
                  </p>

                  <!-- Comment Text -->
                  <p v-if="rev.comment" class="line-clamp-2 text-warm-700 dark:text-warm-300 text-xs">
                    {{ rev.comment }}
                  </p>
                  <span v-else-if="!rev.title" class="text-warm-400 italic text-[11px]">No written text</span>

                  <!-- Attached Photo Thumbnails -->
                  <div v-if="rev.images && rev.images.length" class="flex items-center gap-1.5 pt-0.5">
                    <button
                      v-for="(img, idx) in rev.images"
                      :key="idx"
                      type="button"
                      @click="lightboxImage = img"
                      class="w-8 h-8 rounded-lg overflow-hidden border border-warm-200 dark:border-warm-700 hover:scale-105 transition-transform"
                      title="Click to enlarge"
                    >
                      <img :src="img" :alt="`Review photo ${idx + 1}`" class="w-full h-full object-cover" />
                    </button>
                    <span class="text-[10px] text-warm-400 font-medium">({{ rev.images.length }} photo<span v-if="rev.images.length > 1">s</span>)</span>
                  </div>

                  <!-- Management Reply Snippet -->
                  <div
                    v-if="rev.admin_reply"
                    class="p-2 rounded-xl bg-warm-100/70 dark:bg-warm-800/60 border border-warm-200 dark:border-warm-700 text-[11px] text-warm-600 dark:text-warm-300 space-y-1"
                  >
                    <div class="flex items-center justify-between text-amber-600 dark:text-amber-400 font-semibold text-[10px]">
                      <span class="flex items-center gap-1">
                        <CornerDownRight :size="11" />
                        {{ $t('adminReviews.replyModalTitle') }}
                      </span>
                      <button
                        type="button"
                        @click="openReplyModal(rev)"
                        class="hover:underline text-[10px]"
                      >
                        {{ $t('adminReviews.editReplyBtn') }}
                      </button>
                    </div>
                    <p class="line-clamp-2 italic">“{{ rev.admin_reply }}”</p>
                  </div>
                </div>
              </td>

              <!-- Date -->
              <td class="py-4 px-4 whitespace-nowrap text-warm-400 text-[11px]">
                {{ formatDate(rev.created_at) }}
              </td>

              <!-- Status Badge -->
              <td class="py-4 px-4 whitespace-nowrap">
                <span :class="['badge text-[10px] font-bold uppercase', rev.is_approved ? 'badge-green' : 'badge-orange']">
                  {{ rev.is_approved ? $t('adminReviews.approvedBadge') : $t('adminReviews.pendingBadge') }}
                </span>
              </td>

              <!-- Actions -->
              <td class="py-4 px-5 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1">
                  <!-- Feature Toggle -->
                  <button
                    type="button"
                    @click="toggleFeatured(rev)"
                    :disabled="updatingId === rev.id"
                    :title="rev.is_featured ? $t('adminReviews.unfeatureBtn') : $t('adminReviews.featureBtn')"
                    :class="[
                      'p-2 rounded-xl text-xs font-semibold transition-colors',
                      rev.is_featured
                        ? 'text-amber-500 bg-amber-500/10'
                        : 'text-warm-400 hover:text-amber-500 hover:bg-warm-100 dark:hover:bg-warm-800'
                    ]"
                  >
                    <Star :size="15" :class="{ 'fill-current': rev.is_featured }" />
                  </button>

                  <!-- Toggle Approve / Hide Button -->
                  <button
                    type="button"
                    @click="toggleApproval(rev)"
                    :disabled="updatingId === rev.id"
                    :title="rev.is_approved ? $t('adminReviews.hideBtn') : $t('adminReviews.approveBtn')"
                    :class="[
                      'p-2 rounded-xl text-xs font-semibold transition-colors',
                      rev.is_approved
                        ? 'text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-950/50'
                        : 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/50'
                    ]"
                  >
                    <component :is="rev.is_approved ? EyeOff : CheckCircle" :size="15" />
                  </button>

                  <!-- Reply Button -->
                  <button
                    type="button"
                    @click="openReplyModal(rev)"
                    :title="rev.admin_reply ? $t('adminReviews.editReplyBtn') : $t('adminReviews.replyBtn')"
                    :class="[
                      'p-2 rounded-xl text-xs font-semibold transition-colors',
                      rev.admin_reply
                        ? 'text-primary-600 bg-primary-500/10'
                        : 'text-warm-500 hover:text-primary-600 hover:bg-warm-100 dark:hover:bg-warm-800'
                    ]"
                  >
                    <MessageSquare :size="15" />
                  </button>

                  <!-- Delete Button -->
                  <button
                    type="button"
                    @click="deleteReview(rev)"
                    :disabled="deletingId === rev.id"
                    :title="$t('adminReviews.deleteBtn')"
                    class="p-2 rounded-xl text-red-500 hover:bg-red-50 dark:hover:bg-red-950/50 transition-colors"
                  >
                    <Trash2 :size="15" />
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
        <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white mb-1">{{ $t('adminReviews.noReviewsFound') }}</h3>
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

    <!-- Official Management Reply Modal -->
    <div
      v-if="replyModalOpen && targetReview"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
      @click.self="closeReplyModal"
    >
      <div class="card w-full max-w-xl p-6 md:p-8 space-y-6 shadow-2xl border border-warm-200 dark:border-warm-700 bg-white dark:bg-warm-900 rounded-3xl animate-in fade-in zoom-in-95 duration-200">
        <!-- Modal Header -->
        <div class="flex items-start justify-between gap-4 pb-4 border-b border-warm-100 dark:border-warm-800">
          <div>
            <h3 class="font-heading text-lg font-bold text-warm-900 dark:text-white flex items-center gap-2">
              <Sparkles :size="20" class="text-amber-500" />
              {{ $t('adminReviews.replyModalTitle') }}
            </h3>
            <p class="text-xs text-warm-500 dark:text-warm-400 mt-1">
              {{ $t('adminReviews.replyModalSubtitle') }}
            </p>
          </div>
          <button
            type="button"
            @click="closeReplyModal"
            class="p-1.5 rounded-full text-warm-400 hover:text-warm-600 dark:hover:text-warm-200 transition-colors"
          >
            <X :size="20" />
          </button>
        </div>

        <!-- Target Review Snapshot -->
        <div class="p-4 rounded-2xl bg-warm-50 dark:bg-warm-800/60 border border-warm-200/80 dark:border-warm-700/60 text-xs space-y-2">
          <div class="flex items-center justify-between">
            <span class="font-bold text-warm-900 dark:text-white">
              {{ targetReview.user?.name || $t('adminReviews.verifiedDiner') }}
            </span>
            <div class="flex items-center gap-1 text-amber-500">
              <Star v-for="s in targetReview.rating" :key="s" :size="12" class="fill-current" />
            </div>
          </div>
          <p v-if="targetReview.title" class="font-semibold text-warm-800 dark:text-warm-200">
            {{ targetReview.title }}
          </p>
          <p v-if="targetReview.comment" class="text-warm-600 dark:text-warm-300 italic">
            “{{ targetReview.comment }}”
          </p>
        </div>

        <!-- Quick Response Templates -->
        <div class="space-y-2">
          <label class="block text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400">
            {{ $t('adminReviews.quickTemplates') }}
          </label>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              @click="applyTemplate($t('adminReviews.templateThankYou'))"
              class="text-xs py-1.5 px-3 rounded-xl bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-amber-500/10 hover:text-amber-600 transition-colors"
            >
              🎉 Thank You
            </button>
            <button
              type="button"
              @click="applyTemplate($t('adminReviews.templateApology'))"
              class="text-xs py-1.5 px-3 rounded-xl bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-amber-500/10 hover:text-amber-600 transition-colors"
            >
              🙏 Sincere Apology
            </button>
            <button
              type="button"
              @click="applyTemplate($t('adminReviews.templateAppreciation'))"
              class="text-xs py-1.5 px-3 rounded-xl bg-warm-100 dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:bg-amber-500/10 hover:text-amber-600 transition-colors"
            >
              ⭐ Warm Appreciation
            </button>
          </div>
        </div>

        <!-- Response Textarea -->
        <div class="space-y-2">
          <label class="block text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400">
            Response Message
          </label>
          <textarea
            v-model="replyDraft"
            rows="4"
            maxlength="2000"
            :placeholder="$t('adminReviews.replyPlaceholder')"
            class="input w-full resize-none text-xs p-3.5 rounded-2xl"
          ></textarea>
          <div class="flex items-center justify-between text-xs text-warm-400">
            <span>Official response will be visible on the public review.</span>
            <span>{{ replyDraft.length }}/2000</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between pt-2">
          <button
            v-if="targetReview.admin_reply"
            type="button"
            @click="deleteReply(targetReview)"
            class="text-xs text-red-500 hover:underline"
          >
            {{ $t('adminReviews.deleteReplyBtn') }}
          </button>
          <div v-else></div>

          <div class="flex items-center gap-2.5">
            <button
              type="button"
              @click="closeReplyModal"
              class="btn-secondary py-2 px-4 rounded-xl text-xs"
            >
              Cancel
            </button>
            <AppButton
              @click="submitReply"
              :loading="submittingReply"
              :disabled="!replyDraft.trim()"
              :icon="Send"
              class="py-2 px-5 rounded-xl text-xs"
            >
              <span>{{ $t('adminReviews.postReply') }}</span>
            </AppButton>
          </div>
        </div>
      </div>
    </div>

    <!-- Admin Photo Lightbox Modal -->
    <div
      v-if="lightboxImage"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
      @click.self="lightboxImage = null"
    >
      <div class="relative max-w-3xl max-h-[90vh] bg-warm-900 rounded-3xl overflow-hidden shadow-2xl border border-warm-700">
        <button
          type="button"
          @click="lightboxImage = null"
          class="absolute top-4 right-4 z-10 p-2 rounded-full bg-black/60 hover:bg-black/90 text-white transition-colors"
          aria-label="Close photo preview"
        >
          <X :size="20" />
        </button>
        <img :src="lightboxImage" alt="Enlarged review photo" class="max-w-full max-h-[85vh] object-contain mx-auto" />
      </div>
    </div>
  </div>
</template>
