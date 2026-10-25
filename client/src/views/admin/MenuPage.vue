<script setup>
import { ref, computed, watch } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi, menuApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import { useI18n } from 'vue-i18n'
import {
  Plus, Trash2, Pencil, X, Search, Upload, Link, ImageOff,
  ChefHat, Tag, DollarSign, Star, Eye, EyeOff, RefreshCw,
  Flame, Leaf, RotateCcw, AlertTriangle, Sparkles
} from '@lucide/vue'
import DishCardSkeleton from '../../components/ui/DishCardSkeleton.vue'
import StarRating from '../../components/ui/StarRating.vue'
import CircleLoader from '../../components/ui/CircleLoader.vue'
import AppButton from '../../components/ui/AppButton.vue'
import LoadingState from '../../components/ui/LoadingState.vue'

const { t, locale } = useI18n()
const ui = useUiStore()
const qc = useQueryClient()

// ── State ─────────────────────────────────────────────────────────────────────
const search         = ref('')
const categoryFilter = ref('')
const currentStatus  = ref('active') // 'active' | 'trashed'
const showModal      = ref(false)
const saving         = ref(false)
const editingDish    = ref(null)        // null = create mode, object = edit mode
const imageMode      = ref('url')       // 'url' | 'file'
const imageFile      = ref(null)        // File object
const imagePreview   = ref('')          // preview src
const fileInput      = ref(null)        // template ref

const emptyForm = () => ({
  name:           '',
  name_am:        '',
  category_id:    '',
  price:          '',
  description:    '',
  description_am: '',
  image_url:      '',
  is_available:   true,
  is_featured:    false,
  is_vegetarian:  false,
  is_vegan:       false,
  spice_level:    0,
  calories:       '',
  prep_time:      '',
})

const form = ref(emptyForm())

// ── Queries ───────────────────────────────────────────────────────────────────
const { data: dishesData, isLoading, isError, error, refetch } = useQuery({
  queryKey: ['admin', 'dishes', search, categoryFilter, currentStatus],
  queryFn:  () => adminApi.dishes({
    search:   search.value         || undefined,
    category: categoryFilter.value || undefined,
    status:   currentStatus.value,
  }).then(r => r.data),
  keepPreviousData: true,
})

const { data: categoriesData } = useQuery({
  queryKey: ['categories'],
  queryFn:  () => menuApi.categories().then(r => r.data.data),
  staleTime: 3600000,
})

const dishes     = () => dishesData.value?.data ?? []
const categories = () => categoriesData.value ?? []

// ── Image handling ─────────────────────────────────────────────────────────────
watch(() => form.value.image_url, (val) => {
  if (imageMode.value === 'url' && val) imagePreview.value = val
})

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  imageFile.value = file
  imagePreview.value = URL.createObjectURL(file)
}

function triggerFileInput() {
  fileInput.value?.click()
}

function clearImage() {
  imageFile.value    = null
  imagePreview.value = ''
  form.value.image_url = ''
  if (fileInput.value) fileInput.value.value = ''
}

function switchImageMode(mode) {
  imageMode.value = mode
  clearImage()
}

// ── Build FormData / Payload ───────────────────────────────────────────────────
function buildPayload() {
  if (imageMode.value === 'file' && imageFile.value) {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => {
      if (k !== 'image_url' && v !== '' && v !== null && v !== undefined) {
        fd.append(k, String(v))
      }
    })
    fd.append('image', imageFile.value)
    return fd
  }

  const payload = { ...form.value }
  if (imageMode.value === 'url') {
    payload.image = form.value.image_url || null
  }
  delete payload.image_url
  if (!payload.description) delete payload.description
  if (!payload.description_am) delete payload.description_am
  if (!payload.name_am) delete payload.name_am
  if (!payload.calories) delete payload.calories
  if (!payload.prep_time) delete payload.prep_time
  return payload
}

// ── Modal open / close ────────────────────────────────────────────────────────
function openCreate() {
  editingDish.value  = null
  form.value         = emptyForm()
  imageMode.value    = 'url'
  imagePreview.value = ''
  imageFile.value    = null
  showModal.value    = true
}

function openEdit(dish) {
  editingDish.value = dish
  form.value = {
    name:           dish.name           ?? '',
    name_am:        dish.name_am        ?? '',
    category_id:    dish.category_id    ?? '',
    price:          dish.price          ?? '',
    description:    dish.description    ?? '',
    description_am: dish.description_am ?? '',
    image_url:      dish.image          ?? '',
    is_available:   dish.is_available   ?? true,
    is_featured:    dish.is_featured    ?? false,
    is_vegetarian:  Boolean(dish.is_vegetarian),
    is_vegan:       Boolean(dish.is_vegan),
    spice_level:    Number(dish.spice_level) || 0,
    calories:       dish.calories       ?? '',
    prep_time:      dish.prep_time      ?? '',
  }
  imageMode.value    = 'url'
  imagePreview.value = dish.image ?? ''
  imageFile.value    = null
  showModal.value    = true
}

function closeModal() {
  showModal.value = false
  clearImage()
}

// ── Save (create or update) ───────────────────────────────────────────────────
async function save() {
  const name  = form.value.name.trim()
  const price = Number(form.value.price)

  if (!name) {
    ui.toast(t('adminMenu.dishName'), 'error')
    return
  }
  if (form.value.price === '' || form.value.price === null || form.value.price === undefined) {
    ui.toast(t('adminMenu.price'), 'error')
    return
  }
  if (price < 0) {
    ui.toast('Price cannot be negative', 'error')
    return
  }
  saving.value = true
  try {
    const payload = buildPayload()
    if (editingDish.value) {
      await adminApi.updateDish(editingDish.value.id, payload)
      ui.toast(t('adminMenu.toastUpdated', { name: form.value.name }), 'success')
    } else {
      await adminApi.createDish(payload)
      ui.toast(t('adminMenu.toastCreated', { name: form.value.name }), 'success')
    }
    qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
    qc.invalidateQueries({ queryKey: ['dishes', 'featured'] })
    closeModal()
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : t('adminMenu.toastSaveFailed'), 'error')
  } finally {
    saving.value = false
  }
}

async function toggleAvail(dish) {
  try {
    await adminApi.updateDish(dish.id, { is_available: !dish.is_available })
    qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
    ui.toast(dish.is_available ? t('adminMenu.toastMarkedUnavailable') : t('adminMenu.toastMarkedAvailable'), 'success')
  } catch {
    ui.toast(t('adminMenu.toastAvailabilityFailed'), 'error')
  }
}

async function deleteDish(dish) {
  if (currentStatus.value === 'trashed') {
    if (!confirm(t('adminMenu.confirmPermanentDelete', { name: dish.name }))) return
    try {
      await adminApi.deleteDish(dish.id, true)
      qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
      ui.toast(t('adminMenu.toastForceDeleted', { name: dish.name }), 'success')
    } catch {
      ui.toast(t('adminMenu.toastDeleteFailed'), 'error')
    }
    return
  }

  if (!confirm(t('adminMenu.confirmDelete', { name: dish.name }))) return
  try {
    await adminApi.deleteDish(dish.id)
    qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
    ui.toast(t('adminMenu.toastDeleted', { name: dish.name }), 'success')
  } catch {
    ui.toast(t('adminMenu.toastDeleteFailed'), 'error')
  }
}

async function restoreDish(dish) {
  try {
    await adminApi.restoreDish(dish.id)
    qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
    ui.toast(t('adminMenu.toastRestored', { name: dish.name }), 'success')
  } catch {
    ui.toast('Failed to restore dish', 'error')
  }
}
</script>

<template>
  <div class="space-y-6">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <ChefHat :size="24" class="text-primary-500" /> {{ $t('adminMenu.title') }}
        </h1>
        <p class="text-sm text-warm-500 dark:text-warm-400 mt-0.5">{{ $t('adminMenu.subtitle') }}</p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Status Tabs: Active vs Trashed -->
        <div class="flex p-1 bg-warm-100 dark:bg-warm-800 rounded-xl">
          <button
            type="button"
            @click="currentStatus = 'active'"
            :class="[
              'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all',
              currentStatus === 'active'
                ? 'bg-white dark:bg-warm-900 text-warm-900 dark:text-white shadow-xs'
                : 'text-warm-600 dark:text-warm-400 hover:text-warm-900 dark:hover:text-white'
            ]"
          >
            {{ $t('adminMenu.activeTab') }}
          </button>
          <button
            type="button"
            @click="currentStatus = 'trashed'"
            :class="[
              'px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5',
              currentStatus === 'trashed'
                ? 'bg-red-500 text-white shadow-xs'
                : 'text-warm-600 dark:text-warm-400 hover:text-red-500'
            ]"
          >
            <Trash2 :size="12" />
            <span>{{ $t('adminMenu.trashTab') }}</span>
          </button>
        </div>

        <button @click="openCreate" class="btn-primary gap-2 shadow-sm">
          <Plus :size="16" /> {{ $t('adminMenu.addDish') }}
        </button>
      </div>
    </div>

    <!-- ── Filter Bar ──────────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[220px]">
        <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
        <input
          v-model="search"
          type="search"
          class="input pl-9 text-sm w-full"
          :placeholder="$t('adminMenu.searchPlaceholder')"
        />
      </div>

      <select v-model="categoryFilter" class="input text-sm w-auto">
        <option value="">{{ $t('adminMenu.allCategories') }}</option>
        <option v-for="c in categories()" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>

      <button @click="qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })" class="btn-ghost text-xs gap-1.5 py-2 px-3">
        <RefreshCw :size="13" /> {{ $t('adminMenu.refresh') }}
      </button>

      <p class="text-xs text-warm-500 ml-auto font-medium">
        {{ $t('adminMenu.dishesCount', { count: dishes().length }) }}
      </p>
    </div>

    <!-- ── Dishes LoadingState Container ───────────────────────────────────── -->
    <LoadingState
      :loading="isLoading"
      :error="isError ? error : null"
      :empty="!dishes().length && !isLoading"
      :empty-title="currentStatus === 'trashed' ? 'No dishes in the trash' : $t('adminMenu.noDishesFound')"
      :empty-description="currentStatus === 'trashed' ? 'Deleted dishes will appear here where you can restore or permanently remove them.' : $t('adminMenu.adjustFilterTip')"
      skeleton-type="dishes"
      :skeleton-count="8"
      @retry="refetch"
    >
      <template #empty-action>
        <button v-if="!search && !categoryFilter && currentStatus === 'active'" @click="openCreate" class="btn-primary mx-auto mt-2">
          <Plus :size="16" /> {{ $t('adminMenu.addFirstDish') }}
        </button>
      </template>

      <!-- ── Dish Grid ────────────────────────────────────────────────────────── -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <div
          v-for="dish in dishes()"
          :key="dish.id"
        :class="[
          'card overflow-hidden group hover:shadow-float hover:-translate-y-0.5 transition-all duration-200 flex flex-col',
          dish.is_deleted ? 'border-red-300 dark:border-red-900/60 bg-red-50/20 dark:bg-red-950/10' : ''
        ]"
      >
        <!-- Image -->
        <div class="aspect-[4/3] bg-warm-100 dark:bg-warm-800 relative overflow-hidden shrink-0">
          <img
            v-if="dish.image"
            :src="dish.image"
            :alt="dish.name"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
          />
          <div v-else class="w-full h-full flex items-center justify-center">
            <ImageOff :size="32" class="text-warm-300 dark:text-warm-600" />
          </div>

          <!-- Badges overlay -->
          <div class="absolute top-2 left-2 flex flex-col gap-1">
            <span v-if="dish.is_deleted" class="badge badge-red text-[10px] shadow-sm">
              {{ $t('adminMenu.trashTab') }}
            </span>
            <span v-if="dish.is_featured && !dish.is_deleted" class="badge badge-orange text-[10px] shadow-sm">
              <Star :size="9" class="mr-0.5 fill-current" /> {{ $t('adminMenu.featuredBadge') }}
            </span>
            <span v-if="!dish.is_deleted" :class="['badge text-[10px] shadow-sm', dish.is_available ? 'badge-green' : 'badge-red']">
              {{ dish.is_available ? $t('adminMenu.availableBadge') : $t('adminMenu.unavailableBadge') }}
            </span>
          </div>

          <!-- Dietary & Spice Badges overlay (top-right) -->
          <div class="absolute top-2 right-2 flex flex-col items-end gap-1">
            <span v-if="dish.is_vegetarian" class="badge badge-green text-[10px] shadow-sm">
              🌱 Veg
            </span>
            <span v-if="dish.is_vegan" class="badge badge-green text-[10px] shadow-sm">
              🌿 Vegan
            </span>
            <span v-if="dish.spice_level > 0" class="badge bg-red-500 text-white text-[10px] shadow-sm font-bold">
              {{ dish.spice_level === 1 ? '🌶️ Mild' : dish.spice_level === 2 ? '🌶️🌶️ Med' : '🌶️🌶️🌶️ Hot' }}
            </span>
          </div>
        </div>

        <!-- Body -->
        <div class="p-4 flex flex-col flex-1">
          <!-- Title & Bilingual Display -->
          <div class="flex items-start justify-between gap-2 mb-1">
            <div class="min-w-0">
              <h3 class="font-semibold text-sm text-warm-900 dark:text-white leading-tight line-clamp-1">
                {{ dish.name }}
              </h3>
              <p v-if="dish.name_am" class="text-xs text-primary-600 dark:text-primary-400 font-medium line-clamp-1 mt-0.5">
                {{ dish.name_am }}
              </p>
            </div>
            <span class="font-bold text-primary-600 dark:text-primary-400 text-sm shrink-0">
              ETB {{ Number(dish.price).toFixed(2) }}
            </span>
          </div>

          <p v-if="dish.category" class="text-[11px] text-warm-400 mb-2 flex items-center gap-1">
            <Tag :size="10" /> {{ dish.category.name }}
          </p>

          <p v-if="dish.description || dish.description_am" class="text-xs text-warm-500 dark:text-warm-400 line-clamp-2 mb-3 leading-relaxed">
            {{ (locale === 'am' && dish.description_am) ? dish.description_am : (dish.description || dish.description_am) }}
          </p>

          <!-- Rating + Info Bar -->
          <div class="flex items-center justify-between gap-2 text-xs text-warm-400 mb-3 mt-auto">
            <StarRating
              :rating="Number(dish.avg_rating) || 0"
              :reviews-count="Number(dish.reviews_count) || 0"
              :interactive="false"
              size="xs"
            />
            <div class="flex items-center gap-2 text-[11px]">
              <span v-if="dish.prep_time">{{ dish.prep_time }}m</span>
              <span v-if="dish.calories">{{ dish.calories }} kcal</span>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
            <!-- Active mode: Live/Hidden Toggle -->
            <button
              v-if="!dish.is_deleted"
              @click="toggleAvail(dish)"
              :class="['flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg transition-colors', dish.is_available ? 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-warm-400 hover:bg-warm-100 dark:hover:bg-warm-800']"
              :title="dish.is_available ? $t('adminMenu.markUnavailable') : $t('adminMenu.markAvailable')"
            >
              <Eye v-if="dish.is_available" :size="13" />
              <EyeOff v-else :size="13" />
              {{ dish.is_available ? $t('adminMenu.liveToggle') : $t('adminMenu.hiddenToggle') }}
            </button>

            <!-- Trashed mode: Restore action -->
            <button
              v-else
              @click="restoreDish(dish)"
              class="flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors"
              :title="$t('adminMenu.restoreDish')"
            >
              <RotateCcw :size="13" />
              <span>{{ $t('adminMenu.restoreDish') }}</span>
            </button>

            <!-- Edit and Delete Actions -->
            <div class="flex items-center gap-1 ml-auto">
              <button
                v-if="!dish.is_deleted"
                @click="openEdit(dish)"
                class="p-1.5 rounded-lg text-warm-400 hover:text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors"
                :title="$t('adminMenu.editDish')"
              >
                <Pencil :size="14" />
              </button>

              <button
                @click="deleteDish(dish)"
                :class="[
                  'p-1.5 rounded-lg transition-colors',
                  dish.is_deleted
                    ? 'text-red-500 hover:bg-red-500 hover:text-white'
                    : 'text-warm-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20'
                ]"
                :title="dish.is_deleted ? $t('adminMenu.permanentDelete') : `${$t('adminMenu.deleteDish')} ${dish.name}`"
              >
                <Trash2 :size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    </LoadingState>

    <!-- ══ ADVANCED ADD / EDIT DISH MODAL ═════════════════════════════════════ -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="closeModal">
          <div class="card w-full max-w-3xl shadow-2xl max-h-[92vh] flex flex-col overflow-hidden rounded-3xl border border-warm-200 dark:border-warm-800 bg-white dark:bg-warm-900">

            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 pb-4 border-b border-warm-100 dark:border-warm-800 shrink-0 bg-warm-50/50 dark:bg-warm-900/50">
              <div>
                <h2 class="font-heading font-bold text-xl text-warm-900 dark:text-white flex items-center gap-2">
                  <ChefHat :size="20" class="text-primary-500" />
                  <span>{{ editingDish ? $t('adminMenu.modalEditTitle') : $t('adminMenu.modalAddTitle') }}</span>
                </h2>
                <p class="text-xs text-warm-400 mt-0.5">
                  {{ editingDish ? $t('adminMenu.modalEditSubtitle', { name: editingDish.name }) : $t('adminMenu.modalAddSubtitle') }}
                </p>
              </div>
              <button @click="closeModal" class="btn-ghost p-2 rounded-xl shrink-0">
                <X :size="18" />
              </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="overflow-y-auto p-6 space-y-6 flex-1">

              <!-- SECTION 1: Dish Names (English & Amharic) -->
              <div class="space-y-4">
                <h3 class="text-xs font-bold text-warm-400 uppercase tracking-wider flex items-center gap-1.5">
                  <Tag :size="12" /> Dish Names & Localization (የምግቡ ስም በሁለቱም ቋንቋ)
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <!-- English Name -->
                  <div>
                    <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                      {{ $t('adminMenu.dishName') }} <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="form.name"
                      type="text"
                      required
                      class="input text-sm"
                      placeholder="e.g. Doro Wat / Prime Steak"
                    />
                  </div>

                  <!-- Amharic Name (የምግቡ ስም በዐማርኛ) -->
                  <div>
                    <label class="block text-sm font-semibold text-primary-600 dark:text-primary-400 mb-1.5 flex items-center gap-1">
                      <span>{{ $t('adminMenu.dishNameAm') }}</span>
                    </label>
                    <input
                      v-model="form.name_am"
                      type="text"
                      class="input text-sm border-primary-200 dark:border-primary-900/50 focus:border-primary-500"
                      :placeholder="$t('adminMenu.dishNameAmPlaceholder')"
                    />
                  </div>
                </div>
              </div>

              <!-- SECTION 2: Category, Price & Metrics -->
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                    {{ $t('adminMenu.dishCategory') }} <span class="text-red-500">*</span>
                  </label>
                  <select v-model="form.category_id" class="input text-sm" required>
                    <option value="">— {{ $t('adminMenu.selectCategory') }} —</option>
                    <option v-for="c in categories()" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                    {{ $t('adminMenu.price') }} <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-warm-400 pointer-events-none">ETB</span>
                    <input
                      v-model.number="form.price"
                      type="number"
                      min="0"
                      step="0.01"
                      required
                      class="input pl-11 text-sm font-semibold"
                      placeholder="0.00"
                    />
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminMenu.prepTime') }}</label>
                    <input v-model="form.prep_time" type="number" min="0" class="input text-sm" placeholder="20m" />
                  </div>
                  <div>
                    <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-1.5">{{ $t('adminMenu.calories') }}</label>
                    <input v-model="form.calories" type="number" min="0" class="input text-sm" placeholder="450" />
                  </div>
                </div>
              </div>

              <!-- SECTION 3: Descriptions (English & Amharic) -->
              <div class="space-y-4">
                <h3 class="text-xs font-bold text-warm-400 uppercase tracking-wider flex items-center gap-1.5">
                  <Tag :size="12" /> Descriptions (ዝርዝር መግለጫ)
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <!-- English Description -->
                  <div>
                    <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                      {{ $t('adminMenu.description') }} (English)
                    </label>
                    <textarea
                      v-model="form.description"
                      rows="3"
                      class="input text-sm resize-none"
                      :placeholder="$t('adminMenu.descPlaceholder')"
                    ></textarea>
                  </div>

                  <!-- Amharic Description (የምግቡ ዝርዝር መግለጫ በዐማርኛ) -->
                  <div>
                    <label class="block text-sm font-semibold text-primary-600 dark:text-primary-400 mb-1.5">
                      {{ $t('adminMenu.descAm') }}
                    </label>
                    <textarea
                      v-model="form.description_am"
                      rows="3"
                      class="input text-sm resize-none border-primary-200 dark:border-primary-900/50 focus:border-primary-500"
                      :placeholder="$t('adminMenu.descAmPlaceholder')"
                    ></textarea>
                  </div>
                </div>
              </div>

              <!-- SECTION 4: Dietary Profile & Spice Level -->
              <div class="space-y-3 p-4 bg-warm-50/70 dark:bg-warm-900/40 rounded-2xl border border-warm-100 dark:border-warm-800">
                <h3 class="text-xs font-bold text-warm-500 uppercase tracking-wider flex items-center gap-1.5">
                  <Flame :size="13" class="text-red-500" /> Dietary & Spice Profile (የፆም ምግብና የቃሪያ መጠን)
                </h3>

                <!-- Spice Level Selector -->
                <div>
                  <label class="block text-xs font-semibold text-warm-700 dark:text-warm-300 mb-2">
                    {{ $t('adminMenu.spiceLevel') }}
                  </label>
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <button
                      v-for="(labelKey, level) in ['spice0', 'spice1', 'spice2', 'spice3']"
                      :key="level"
                      type="button"
                      @click="form.spice_level = level"
                      :class="[
                        'py-2 px-3 rounded-xl text-xs font-semibold border transition-all text-center',
                        form.spice_level === level
                          ? 'border-red-500 bg-red-500 text-white shadow-xs font-bold'
                          : 'border-warm-200 dark:border-warm-700 bg-white dark:bg-warm-800 text-warm-700 dark:text-warm-300 hover:border-red-300'
                      ]"
                    >
                      {{ $t(`adminMenu.${labelKey}`) }}
                    </button>
                  </div>
                </div>

                <!-- Dietary Badges Checkboxes -->
                <div class="grid grid-cols-2 gap-3 pt-2">
                  <label class="flex items-center gap-2.5 p-2.5 bg-white dark:bg-warm-800 rounded-xl border border-warm-200 dark:border-warm-700 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      v-model="form.is_vegetarian"
                      class="w-4 h-4 rounded text-green-600 focus:ring-green-500"
                    />
                    <span class="text-xs font-semibold text-warm-800 dark:text-warm-200">
                      {{ $t('adminMenu.vegetarian') }}
                    </span>
                  </label>

                  <label class="flex items-center gap-2.5 p-2.5 bg-white dark:bg-warm-800 rounded-xl border border-warm-200 dark:border-warm-700 cursor-pointer select-none">
                    <input
                      type="checkbox"
                      v-model="form.is_vegan"
                      class="w-4 h-4 rounded text-green-600 focus:ring-green-500"
                    />
                    <span class="text-xs font-semibold text-warm-800 dark:text-warm-200">
                      {{ $t('adminMenu.vegan') }}
                    </span>
                  </label>
                </div>
              </div>

              <!-- SECTION 5: Image Upload & Presentation -->
              <div>
                <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-2">{{ $t('adminMenu.dishImage') }}</label>

                <!-- Mode Toggle -->
                <div class="flex gap-1 p-1 bg-warm-100 dark:bg-warm-800 rounded-xl w-fit mb-3">
                  <button
                    type="button"
                    @click="switchImageMode('url')"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all', imageMode === 'url' ? 'bg-white dark:bg-gray-900 shadow-xs text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
                  >
                    <Link :size="13" /> {{ $t('adminMenu.imageUrlTab') }}
                  </button>
                  <button
                    type="button"
                    @click="switchImageMode('file')"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all', imageMode === 'file' ? 'bg-white dark:bg-gray-900 shadow-xs text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
                  >
                    <Upload :size="13" /> {{ $t('adminMenu.uploadFileTab') }}
                  </button>
                </div>

                <!-- URL input -->
                <div v-if="imageMode === 'url'" class="flex gap-2">
                  <input
                    v-model="form.image_url"
                    type="url"
                    class="input text-sm flex-1"
                    :placeholder="$t('adminMenu.urlPlaceholder')"
                  />
                </div>

                <!-- File upload from device -->
                <div v-else>
                  <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChange" />
                  <div
                    @click="triggerFileInput"
                    class="border-2 border-dashed border-warm-300 dark:border-warm-700 rounded-2xl p-6 text-center cursor-pointer hover:border-primary-500 hover:bg-primary-50/40 dark:hover:bg-primary-900/10 transition-all duration-200"
                  >
                    <Upload :size="28" class="mx-auto text-primary-500 mb-2" />
                    <p class="text-sm font-semibold text-warm-800 dark:text-warm-200">{{ $t('adminMenu.dropzoneText') }}</p>
                    <p class="text-xs text-warm-400 mt-1">{{ $t('adminMenu.dropzoneHint') }}</p>
                  </div>
                </div>

                <!-- Preview -->
                <div v-if="imagePreview" class="mt-3 relative inline-block">
                  <img :src="imagePreview" alt="Preview" class="h-32 w-48 object-cover rounded-2xl border border-warm-200 dark:border-warm-700 shadow-md" />
                  <button
                    type="button"
                    @click="clearImage"
                    class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-md"
                    title="Remove image"
                  >
                    <X :size="12" />
                  </button>
                </div>
              </div>

              <!-- SECTION 6: Visibility & Promotion Toggles -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-warm-50 dark:bg-warm-900/50 rounded-2xl border border-warm-100 dark:border-warm-800">
                <label class="flex items-center gap-3 cursor-pointer select-none">
                  <div
                    @click="form.is_available = !form.is_available"
                    :class="['relative w-11 h-6 rounded-full transition-colors duration-200 shrink-0', form.is_available ? 'bg-green-500' : 'bg-warm-300 dark:bg-warm-600']"
                  >
                    <span :class="['absolute top-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-200', form.is_available ? 'translate-x-5' : 'translate-x-0.5']"></span>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-warm-800 dark:text-warm-200">{{ $t('adminMenu.availableOnMenu') }}</p>
                    <p class="text-xs text-warm-400">{{ $t('adminMenu.availableHint') }}</p>
                  </div>
                </label>

                <label class="flex items-center gap-3 cursor-pointer select-none">
                  <div
                    @click="form.is_featured = !form.is_featured"
                    :class="['relative w-11 h-6 rounded-full transition-colors duration-200 shrink-0', form.is_featured ? 'bg-primary-500' : 'bg-warm-300 dark:bg-warm-600']"
                  >
                    <span :class="['absolute top-0.5 w-5 h-5 bg-white rounded-full shadow-md transition-transform duration-200', form.is_featured ? 'translate-x-5' : 'translate-x-0.5']"></span>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-warm-800 dark:text-warm-200 flex items-center gap-1">
                      <span>{{ $t('adminMenu.markFeatured') }}</span>
                      <Sparkles :size="13" class="text-amber-500" />
                    </p>
                    <p class="text-xs text-warm-400">{{ $t('adminMenu.featuredHint') }}</p>
                  </div>
                </label>
              </div>

            </div>

            <!-- Modal Footer -->
            <div class="p-6 pt-4 border-t border-warm-100 dark:border-warm-800 shrink-0 flex items-center justify-between gap-3 bg-warm-50/50 dark:bg-warm-900/50">
              <p class="text-xs text-warm-400">
                {{ editingDish ? 'Changes will apply immediately across all client views.' : 'Dish will appear on the menu immediately after saving.' }}
              </p>
              <div class="flex items-center gap-2">
                <button type="button" @click="closeModal" class="btn-outline text-sm py-2 px-4 rounded-xl cursor-pointer">{{ $t('adminMenu.cancel') }}</button>
                <AppButton
                  type="button"
                  @click="save"
                  :loading="saving"
                  :loading-text="$t('adminMenu.saving')"
                  variant="primary"
                  size="md"
                >
                  {{ editingDish ? $t('adminMenu.saveChanges') : $t('adminMenu.createDish') }}
                </AppButton>
              </div>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>
