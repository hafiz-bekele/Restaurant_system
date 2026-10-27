<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { profileApi, reviewApi } from '../../services/api'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import ThemeToggle from '../../components/ui/ThemeToggle.vue'
import AppButton from '../../components/ui/AppButton.vue'
import { APP_NAME } from '../../config'
import {
  Sparkles, Moon, Sun, User, Star, Utensils,
  BedDouble, Trash2, Edit3, X, MessageSquare, ArrowRight, CornerDownRight
} from '@lucide/vue'

const { t } = useI18n()
const auth   = useAuthStore()
const ui     = useUiStore()

const activeTab = ref('profile') // 'profile' | 'reviews'
const form   = ref({ name: auth.user?.name ?? '', phone: auth.user?.phone ?? '' })
const saving = ref(false)

// Customer Reviews State
const loadingReviews = ref(false)
const myReviews = ref([])
const editingReview = ref(null)
const editForm = ref({ rating: 5, title: '', comment: '' })
const savingEdit = ref(false)
const deletingReviewId = ref(null)

async function save() {
  saving.value = true
  try {
    await profileApi.update(form.value)
    await auth.fetchUser()
    ui.toast(t('profilePage.profileUpdated'), 'success')
  } catch {
    ui.toast(t('profilePage.updateFailed'), 'error')
  } finally {
    saving.value = false
  }
}

async function fetchMyReviews() {
  loadingReviews.value = true
  try {
    const { data } = await reviewApi.myReviews()
    myReviews.value = data.data || []
  } catch {
    ui.toast('Failed to load your reviews', 'error')
  } finally {
    loadingReviews.value = false
  }
}

function openEditReview(rev) {
  editingReview.value = rev
  editForm.value = {
    rating: rev.rating,
    title: rev.title || '',
    comment: rev.comment || '',
  }
}

function closeEditReview() {
  editingReview.value = null
}

async function saveReviewUpdate() {
  if (!editingReview.value) return
  savingEdit.value = true
  try {
    const { data } = await reviewApi.update(editingReview.value.id, editForm.value)
    // Update local list
    const idx = myReviews.value.findIndex(r => r.id === editingReview.value.id)
    if (idx !== -1) {
      myReviews.value[idx] = { ...myReviews.value[idx], ...data.data }
    }
    ui.toast(t('profilePage.reviewUpdated'), 'success')
    closeEditReview()
  } catch {
    ui.toast('Failed to update review', 'error')
  } finally {
    savingEdit.value = false
  }
}

async function deleteReview(rev) {
  if (!confirm(t('profilePage.confirmDeleteReview'))) return
  deletingReviewId.value = rev.id
  try {
    await reviewApi.delete(rev.id)
    myReviews.value = myReviews.value.filter(r => r.id !== rev.id)
    ui.toast(t('profilePage.reviewDeleted'), 'success')
  } catch {
    ui.toast('Failed to delete review', 'error')
  } finally {
    deletingReviewId.value = null
  }
}

function formatDate(isoStr) {
  if (!isoStr) return ''
  try {
    const d = new Date(isoStr)
    return d.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    })
  } catch {
    return ''
  }
}

onMounted(() => {
  fetchMyReviews()
})
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-2xl space-y-6">
      <div>
        <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white">{{ $t('profilePage.title') }}</h1>
        <p class="text-sm text-warm-500 dark:text-warm-400 mt-1">{{ $t('profilePage.subtitle') }}</p>
      </div>

      <!-- Navigation Tabs -->
      <div class="flex items-center gap-2 p-1.5 bg-warm-100 dark:bg-warm-800/80 rounded-2xl w-max">
        <button
          type="button"
          @click="activeTab = 'profile'"
          :class="[
            'px-5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all',
            activeTab === 'profile'
              ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm'
              : 'text-warm-500 hover:text-warm-800 dark:hover:text-warm-200'
          ]"
        >
          <User :size="15" />
          <span>{{ $t('profilePage.tabProfile') }}</span>
        </button>

        <button
          type="button"
          @click="activeTab = 'reviews'"
          :class="[
            'px-5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all',
            activeTab === 'reviews'
              ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-sm'
              : 'text-warm-500 hover:text-warm-800 dark:hover:text-warm-200'
          ]"
        >
          <Star :size="15" />
          <span>{{ $t('profilePage.tabMyReviews') }}</span>
          <span
            v-if="myReviews.length > 0"
            class="ml-1 px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400"
          >
            {{ myReviews.length }}
          </span>
        </button>
      </div>

      <!-- TAB 1: Profile Information & Settings -->
      <div v-if="activeTab === 'profile'" class="space-y-6">
        <!-- Account Info Card -->
        <div class="card p-8 space-y-5">
          <div class="flex items-center gap-4 pb-5 border-b border-warm-100 dark:border-warm-800">
            <div class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-2xl font-bold text-primary-600 dark:text-primary-400">
              {{ auth.user?.name?.[0]?.toUpperCase() }}
            </div>
            <div>
              <p class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ auth.user?.name }}</p>
              <p class="text-sm text-warm-500">{{ auth.user?.email }}</p>
              <p class="text-xs text-primary-600 dark:text-primary-400 mt-1 flex items-center gap-1 font-semibold">
                <Sparkles :size="13" /> {{ $t('profilePage.loyaltyPoints', { count: auth.user?.loyalty_points ?? 0 }) }}
              </p>
            </div>
          </div>
          <form @submit.prevent="save" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('profilePage.fullName') }}</label>
              <input v-model="form.name" type="text" required class="input" />
            </div>
            <div>
              <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('profilePage.phone') }}</label>
              <input v-model="form.phone" type="tel" class="input" />
            </div>
            <AppButton
              type="submit"
              :loading="saving"
              :loading-text="$t('profilePage.saving')"
              variant="primary"
            >
              {{ $t('profilePage.saveChanges') }}
            </AppButton>
          </form>
        </div>

        <!-- Appearance & Preferences Card -->
        <div class="card p-8 space-y-4">
          <div>
            <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('profilePage.appearanceTitle') }}</h2>
            <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('profilePage.appearanceSubtitle') }}</p>
          </div>

          <div class="flex items-center justify-between p-4 rounded-2xl bg-warm-50 dark:bg-warm-800/50 border border-warm-100 dark:border-warm-700/60">
            <div class="space-y-0.5">
              <p class="text-sm font-semibold text-warm-900 dark:text-white flex items-center gap-2">
                <Sun v-if="!ui.darkMode" :size="16" class="text-amber-500" />
                <Moon v-else :size="16" class="text-primary-400" />
                {{ $t('profilePage.themeMode') }}
              </p>
              <p class="text-xs text-warm-500 dark:text-warm-400">
                {{ ui.darkMode ? $t('profilePage.darkActive') : $t('profilePage.lightActive') }}
              </p>
            </div>
            <ThemeToggle variant="segmented" />
          </div>
        </div>
      </div>

      <!-- TAB 2: My Reviews & Ratings -->
      <div v-else class="space-y-6">
        <div>
          <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ $t('profilePage.myReviewsTitle') }}</h2>
          <p class="text-xs text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('profilePage.myReviewsSubtitle') }}</p>
        </div>

        <!-- Loading Skeleton -->
        <div v-if="loadingReviews" class="space-y-4">
          <div v-for="i in 3" :key="i" class="card p-6 space-y-3">
            <div class="skeleton h-5 w-40"></div>
            <div class="skeleton h-4 w-full"></div>
            <div class="skeleton h-4 w-2/3"></div>
          </div>
        </div>

        <!-- Reviews List -->
        <div v-else-if="myReviews.length > 0" class="space-y-4">
          <div
            v-for="rev in myReviews"
            :key="rev.id"
            class="card p-6 border border-warm-200/80 dark:border-warm-800 rounded-3xl space-y-4 hover:border-amber-400/30 transition-colors"
          >
            <!-- Target Item & Rating Header -->
            <div class="flex items-start justify-between gap-4">
              <!-- Dish or Room Info -->
              <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-warm-100 dark:bg-warm-800 overflow-hidden shrink-0">
                  <img
                    v-if="rev.dish?.image"
                    :src="rev.dish.image"
                    :alt="rev.dish.name"
                    class="w-full h-full object-cover"
                  />
                  <div v-else-if="rev.room" class="w-full h-full flex items-center justify-center text-indigo-500 bg-indigo-50 dark:bg-indigo-950/40">
                    <BedDouble :size="20" />
                  </div>
                  <div v-else class="w-full h-full flex items-center justify-center text-warm-400">
                    <Utensils :size="20" />
                  </div>
                </div>

                <div>
                  <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white">
                    <router-link
                      v-if="rev.dish?.slug"
                      :to="`/menu/${rev.dish.slug}`"
                      class="hover:text-primary-600 transition-colors"
                    >
                      {{ rev.dish.name }}
                    </router-link>
                    <span v-else-if="rev.room">
                      Room {{ rev.room.room_number }}
                    </span>
                    <span v-else>Review #{{ rev.id }}</span>
                  </h3>
                  <span class="text-xs text-warm-400">{{ formatDate(rev.created_at) }}</span>
                </div>
              </div>

              <!-- Rating Badge -->
              <div class="flex items-center gap-1 bg-amber-50 dark:bg-amber-950/30 px-3 py-1 rounded-xl">
                <Star
                  v-for="s in 5"
                  :key="s"
                  :size="14"
                  :class="s <= rev.rating ? 'text-amber-400 fill-amber-400' : 'text-warm-300 dark:text-warm-700'"
                />
                <span class="text-xs font-bold text-amber-700 dark:text-amber-400 ml-1">{{ rev.rating }}.0</span>
              </div>
            </div>

            <!-- Title & Comment -->
            <div class="space-y-1.5">
              <h4 v-if="rev.title" class="font-bold text-sm text-warm-900 dark:text-white">
                {{ rev.title }}
              </h4>
              <p v-if="rev.comment" class="text-xs text-warm-700 dark:text-warm-300 leading-relaxed">
                {{ rev.comment }}
              </p>
              <p v-else class="text-xs text-warm-400 italic">Rated without written comment</p>
            </div>

            <!-- Attached Photos -->
            <div v-if="rev.images && rev.images.length" class="flex flex-wrap gap-2 pt-1">
              <div
                v-for="(img, idx) in rev.images"
                :key="idx"
                class="w-16 h-16 rounded-xl overflow-hidden border border-warm-200 dark:border-warm-700 shadow-sm"
              >
                <img :src="img" :alt="`Review photo ${idx + 1}`" class="w-full h-full object-cover" />
              </div>
            </div>

            <!-- Official Management Response -->
            <div
              v-if="rev.admin_reply"
              class="p-4 rounded-2xl bg-gradient-to-r from-amber-500/5 to-primary-500/5 border border-amber-500/20 text-xs space-y-1.5"
            >
              <div class="flex items-center justify-between font-semibold text-amber-700 dark:text-amber-400">
                <span class="flex items-center gap-1.5">
                  <Sparkles :size="13" />
                  {{ $t('profilePage.officialReply') }}
                </span>
                <span v-if="rev.replied_at" class="text-[11px] font-normal text-warm-400">
                  {{ formatDate(rev.replied_at) }}
                </span>
              </div>
              <p class="text-warm-700 dark:text-warm-300 leading-relaxed">
                {{ rev.admin_reply }}
              </p>
            </div>

            <!-- Review Actions (Edit / Delete) -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
              <button
                type="button"
                @click="openEditReview(rev)"
                class="btn-secondary py-1.5 px-3 text-xs rounded-xl gap-1.5"
              >
                <Edit3 :size="13" />
                <span>{{ $t('profilePage.editReview') }}</span>
              </button>
              <button
                type="button"
                @click="deleteReview(rev)"
                :disabled="deletingReviewId === rev.id"
                class="btn-danger py-1.5 px-3 text-xs rounded-xl gap-1.5"
              >
                <Trash2 :size="13" />
                <span>{{ $t('profilePage.deleteReview') }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-else
          class="card p-12 text-center border-dashed border-2 border-warm-200 dark:border-warm-800 rounded-3xl space-y-4"
        >
          <div class="w-14 h-14 rounded-full bg-amber-100 dark:bg-amber-950/40 text-amber-500 flex items-center justify-center mx-auto">
            <Star :size="28" />
          </div>
          <div>
            <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white">
              {{ $t('profilePage.noMyReviews') }}
            </h3>
            <p class="text-xs text-warm-500 dark:text-warm-400 mt-1 max-w-sm mx-auto">
              {{ $t('profilePage.noMyReviewsPrompt') }}
            </p>
          </div>
          <router-link to="/menu" class="btn-primary py-2.5 px-5 text-xs rounded-2xl inline-flex items-center gap-2">
            <span>{{ $t('profilePage.exploreMenu') }}</span>
            <ArrowRight :size="14" />
          </router-link>
        </div>
      </div>

      <!-- Edit Review Modal -->
      <div
        v-if="editingReview"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
        @click.self="closeEditReview"
      >
        <div class="card w-full max-w-lg p-6 space-y-5 bg-white dark:bg-warm-900 rounded-3xl shadow-2xl border border-warm-200 dark:border-warm-700">
          <div class="flex items-center justify-between pb-3 border-b border-warm-100 dark:border-warm-800">
            <h3 class="font-heading font-bold text-base text-warm-900 dark:text-white flex items-center gap-2">
              <Edit3 :size="16" class="text-amber-500" />
              {{ $t('profilePage.editReview') }}
            </h3>
            <button
              type="button"
              @click="closeEditReview"
              class="p-1 rounded-full text-warm-400 hover:text-warm-600 dark:hover:text-warm-200"
            >
              <X :size="18" />
            </button>
          </div>

          <!-- Star Picker -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-2">
              {{ $t('dish.overallRating') }}
            </label>
            <div class="flex items-center gap-1.5">
              <button
                v-for="s in 5"
                :key="s"
                type="button"
                @click="editForm.rating = s"
                class="p-1 rounded-xl transition-transform hover:scale-110 focus:outline-none"
              >
                <Star
                  :size="24"
                  :class="s <= editForm.rating ? 'text-amber-400 fill-amber-400' : 'text-warm-300 dark:text-warm-700'"
                />
              </button>
              <span class="text-xs font-bold text-amber-600 dark:text-amber-400 ml-2">{{ editForm.rating }} Stars</span>
            </div>
          </div>

          <!-- Headline / Title -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-1.5">
              {{ $t('dish.reviewTitle') }}
            </label>
            <input
              v-model="editForm.title"
              type="text"
              maxlength="120"
              class="input text-xs"
            />
          </div>

          <!-- Comment -->
          <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-warm-500 dark:text-warm-400 mb-1.5">
              {{ $t('dish.reviewNotes') }}
            </label>
            <textarea
              v-model="editForm.comment"
              rows="3"
              maxlength="1000"
              class="input w-full resize-none text-xs p-3 rounded-xl"
            ></textarea>
          </div>

          <!-- Modal Actions -->
          <div class="flex items-center justify-end gap-2.5 pt-2">
            <button
              type="button"
              @click="closeEditReview"
              class="btn-secondary py-2 px-4 rounded-xl text-xs"
            >
              Cancel
            </button>
            <AppButton
              @click="saveReviewUpdate"
              :loading="savingEdit"
              :loading-text="$t('profilePage.savingReview')"
              class="py-2 px-5 rounded-xl text-xs"
            >
              {{ $t('profilePage.saveReview') }}
            </AppButton>
          </div>
        </div>
      </div>
    </div>
  </DefaultLayout>
</template>
