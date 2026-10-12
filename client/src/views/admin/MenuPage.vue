<script setup>
import { ref, computed, watch } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { adminApi, menuApi } from '../../services/api'
import { useUiStore } from '../../stores/ui'
import {
  Plus, Trash2, Pencil, X, Search, Upload, Link, ImageOff,
  ChefHat, Tag, DollarSign, Star, Eye, EyeOff, RefreshCw,
} from '@lucide/vue'

const ui = useUiStore()
const qc = useQueryClient()

// ── State ─────────────────────────────────────────────────────────────────────
const search       = ref('')
const categoryFilter = ref('')
const showModal    = ref(false)
const saving       = ref(false)
const editingDish  = ref(null)        // null = create mode, object = edit mode
const imageMode    = ref('url')       // 'url' | 'file'
const imageFile    = ref(null)        // File object
const imagePreview = ref('')          // preview src
const fileInput    = ref(null)        // template ref

const emptyForm = () => ({
  name:         '',
  category_id:  '',
  price:        '',
  description:  '',
  image_url:    '',
  is_available: true,
  is_featured:  false,
  calories:     '',
  prep_time:    '',
})
const form = ref(emptyForm())

// ── Queries ───────────────────────────────────────────────────────────────────
const { data: dishesData, isLoading } = useQuery({
  queryKey: ['admin', 'dishes', search, categoryFilter],
  queryFn:  () => adminApi.dishes({
    search:   search.value   || undefined,
    category: categoryFilter.value || undefined,
  }).then(r => r.data),
  keepPreviousData: true,
})

const { data: categoriesData } = useQuery({
  queryKey: ['categories'],
  queryFn:  () => menuApi.categories().then(r => r.data.data),
  staleTime: 3600000,
})

const dishes     = () => dishesData.value?.data     ?? []
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

// ── Build FormData ─────────────────────────────────────────────────────────────
function buildPayload() {
  // If uploading a file, use multipart; otherwise send JSON
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
  // JSON payload — strip empty optional fields
  const payload = { ...form.value }
  if (!payload.calories)  delete payload.calories
  if (!payload.prep_time) delete payload.prep_time
  if (!payload.image_url) delete payload.image_url
  return payload
}

// ── Modal open/close ─────────────────────────────────────────────────────────
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
    name:         dish.name         ?? '',
    category_id:  dish.category?.id ?? '',
    price:        dish.price        ?? '',
    description:  dish.description  ?? '',
    image_url:    dish.image        ?? '',
    is_available: dish.is_available ?? true,
    is_featured:  dish.is_featured  ?? false,
    calories:     dish.calories     ?? '',
    prep_time:    dish.prep_time    ?? '',
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

// ── Save ──────────────────────────────────────────────────────────────────────
async function save() {
  if (!form.value.name || !form.value.price) {
    ui.toast('Name and price are required', 'error')
    return
  }
  saving.value = true
  try {
    const payload = buildPayload()
    if (editingDish.value) {
      await adminApi.updateDish(editingDish.value.id, payload)
      ui.toast(`"${form.value.name}" updated successfully`, 'success')
    } else {
      await adminApi.createDish(payload)
      ui.toast(`"${form.value.name}" added to menu`, 'success')
    }
    qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
    qc.invalidateQueries({ queryKey: ['dishes', 'featured'] })
    closeModal()
  } catch (e) {
    const errors = e.response?.data?.errors
    ui.toast(errors ? Object.values(errors).flat()[0] : 'Failed to save dish', 'error')
  } finally {
    saving.value = false
  }
}

async function toggleAvail(dish) {
  try {
    await adminApi.updateDish(dish.id, { is_available: !dish.is_available })
    qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
    ui.toast(dish.is_available ? 'Marked unavailable' : 'Marked available', 'success')
  } catch {
    ui.toast('Failed to update availability', 'error')
  }
}

async function deleteDish(dish) {
  if (!confirm(`Remove "${dish.name}" from the menu? This cannot be undone.`)) return
  try {
    await adminApi.deleteDish(dish.id)
    qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })
    ui.toast(`"${dish.name}" removed`, 'success')
  } catch {
    ui.toast('Failed to delete dish', 'error')
  }
}
</script>

<template>
  <div class="space-y-6">

    <!-- ── Header ─────────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="font-heading text-2xl font-bold text-warm-900 dark:text-white flex items-center gap-2">
          <ChefHat :size="24" class="text-primary-500" /> Menu Management
        </h1>
        <p class="text-sm text-warm-500 dark:text-warm-400 mt-0.5">Add, edit, and manage all dishes and availability</p>
      </div>
      <button @click="openCreate" class="btn-primary gap-2 self-start sm:self-auto shadow-sm">
        <Plus :size="16" /> Add Dish
      </button>
    </div>

    <!-- ── Filters ─────────────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[200px] max-w-xs">
        <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
        <input v-model="search" type="search" placeholder="Search dishes…" class="input pl-9 text-sm" />
      </div>
      <select v-model="categoryFilter" class="input w-auto text-sm py-2.5">
        <option value="">All Categories</option>
        <option v-for="c in categories()" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <button @click="qc.invalidateQueries({ queryKey: ['admin', 'dishes'] })" class="btn-ghost text-xs gap-1.5 py-2 px-3">
        <RefreshCw :size="13" /> Refresh
      </button>
      <p class="text-xs text-warm-500 ml-auto">{{ dishes().length }} dishes</p>
    </div>

    <!-- ── Loading Skeleton ─────────────────────────────────────────────────── -->
    <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
      <div v-for="i in 8" :key="i" class="card overflow-hidden">
        <div class="skeleton aspect-[4/3]"></div>
        <div class="p-4 space-y-2">
          <div class="skeleton h-4 w-3/4 rounded"></div>
          <div class="skeleton h-3 w-full rounded"></div>
          <div class="skeleton h-3 w-1/3 rounded"></div>
        </div>
      </div>
    </div>

    <!-- ── Dish Grid ────────────────────────────────────────────────────────── -->
    <div v-else-if="dishes().length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
      <div
        v-for="dish in dishes()"
        :key="dish.id"
        class="card overflow-hidden group hover:shadow-float hover:-translate-y-0.5 transition-all duration-200"
      >
        <!-- Image -->
        <div class="aspect-[4/3] bg-warm-100 dark:bg-warm-800 relative overflow-hidden">
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
            <span v-if="dish.is_featured" class="badge badge-orange text-[10px]">
              <Star :size="9" class="mr-0.5" /> Featured
            </span>
            <span :class="['badge text-[10px]', dish.is_available ? 'badge-green' : 'badge-red']">
              {{ dish.is_available ? 'Available' : 'Unavailable' }}
            </span>
          </div>
        </div>

        <!-- Body -->
        <div class="p-4">
          <div class="flex items-start justify-between gap-2 mb-1">
            <h3 class="font-semibold text-sm text-warm-900 dark:text-white leading-tight line-clamp-1">{{ dish.name }}</h3>
            <span class="font-bold text-primary-600 dark:text-primary-400 text-sm shrink-0">${{ Number(dish.price).toFixed(2) }}</span>
          </div>
          <p v-if="dish.category" class="text-[11px] text-warm-400 mb-2 flex items-center gap-1">
            <Tag :size="10" /> {{ dish.category.name }}
          </p>
          <p v-if="dish.description" class="text-xs text-warm-500 dark:text-warm-400 line-clamp-2 mb-3">{{ dish.description }}</p>

          <!-- Actions -->
          <div class="flex items-center gap-2 pt-2 border-t border-warm-100 dark:border-warm-800">
            <!-- Availability toggle -->
            <button
              @click="toggleAvail(dish)"
              :class="['flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-lg transition-colors', dish.is_available ? 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-warm-400 hover:bg-warm-100 dark:hover:bg-warm-800']"
              :title="dish.is_available ? 'Mark unavailable' : 'Mark available'"
            >
              <Eye v-if="dish.is_available" :size="13" />
              <EyeOff v-else :size="13" />
              {{ dish.is_available ? 'Live' : 'Hidden' }}
            </button>

            <div class="flex items-center gap-1 ml-auto">
              <button @click="openEdit(dish)" class="p-1.5 rounded-lg text-warm-400 hover:text-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-colors" title="Edit dish">
                <Pencil :size="14" />
              </button>
              <button @click="deleteDish(dish)" class="p-1.5 rounded-lg text-warm-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" :title="`Delete ${dish.name}`">
                <Trash2 :size="14" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Empty state ───────────────────────────────────────────────────────── -->
    <div v-else class="card p-16 text-center">
      <ChefHat :size="48" class="mx-auto text-warm-300 dark:text-warm-600 mb-4" />
      <p class="font-semibold text-warm-700 dark:text-warm-300 mb-1">No dishes found</p>
      <p class="text-sm text-warm-400 mb-5">{{ search || categoryFilter ? 'Try adjusting your filters.' : 'Add your first dish to get started.' }}</p>
      <button v-if="!search && !categoryFilter" @click="openCreate" class="btn-primary mx-auto">
        <Plus :size="16" /> Add First Dish
      </button>
    </div>

    <!-- ══ ADD / EDIT MODAL ════════════════════════════════════════════════ -->
    <Teleport to="body">
      <Transition
        enter-from-class="opacity-0"
        enter-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
        leave-active-class="transition-opacity duration-150"
      >
        <div v-if="showModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4" @click.self="closeModal">
          <div class="card w-full max-w-2xl shadow-float max-h-[92vh] flex flex-col">

            <!-- Modal header -->
            <div class="flex items-center justify-between p-6 pb-4 border-b border-warm-100 dark:border-warm-800 shrink-0">
              <div>
                <h2 class="font-heading font-bold text-lg text-warm-900 dark:text-white">
                  {{ editingDish ? 'Edit Dish' : 'Add New Dish' }}
                </h2>
                <p class="text-xs text-warm-400 mt-0.5">
                  {{ editingDish ? `Editing "${editingDish.name}"` : 'Fill in the details below to add a dish to your menu' }}
                </p>
              </div>
              <button @click="closeModal" class="btn-ghost p-1.5 rounded-lg shrink-0">
                <X :size="18" />
              </button>
            </div>

            <!-- Modal body (scrollable) -->
            <div class="overflow-y-auto p-6 space-y-5 flex-1">

              <!-- Image section -->
              <div>
                <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-2">Dish Image</label>

                <!-- Mode toggle -->
                <div class="flex gap-1 p-1 bg-warm-100 dark:bg-warm-800 rounded-xl w-fit mb-3">
                  <button
                    @click="switchImageMode('url')"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all', imageMode === 'url' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
                  >
                    <Link :size="13" /> Image URL
                  </button>
                  <button
                    @click="switchImageMode('file')"
                    :class="['flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all', imageMode === 'file' ? 'bg-white dark:bg-gray-900 shadow text-warm-900 dark:text-white' : 'text-warm-500 hover:text-warm-700 dark:hover:text-warm-300']"
                  >
                    <Upload :size="13" /> Upload File
                  </button>
                </div>

                <!-- URL input -->
                <div v-if="imageMode === 'url'" class="flex gap-2">
                  <input
                    v-model="form.image_url"
                    type="url"
                    class="input text-sm flex-1"
                    placeholder="https://example.com/image.jpg"
                  />
                </div>

                <!-- File upload -->
                <div v-else>
                  <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFileChange" />
                  <div
                    @click="triggerFileInput"
                    class="border-2 border-dashed border-warm-300 dark:border-warm-700 rounded-xl p-6 text-center cursor-pointer hover:border-primary-400 hover:bg-primary-50/40 dark:hover:bg-primary-900/10 transition-all duration-200"
                  >
                    <Upload :size="28" class="mx-auto text-warm-400 mb-2" />
                    <p class="text-sm font-medium text-warm-600 dark:text-warm-300">Click to choose a file</p>
                    <p class="text-xs text-warm-400 mt-1">PNG, JPG, WEBP — max 5 MB</p>
                  </div>
                </div>

                <!-- Preview -->
                <div v-if="imagePreview" class="mt-3 relative inline-block">
                  <img :src="imagePreview" alt="Preview" class="h-32 w-48 object-cover rounded-xl border border-warm-200 dark:border-warm-700 shadow-sm" />
                  <button
                    @click="clearImage"
                    class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors shadow-md"
                    title="Remove image"
                  >
                    <X :size="12" />
                  </button>
                </div>
              </div>

              <!-- Name + Category -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                    Dish Name <span class="text-red-500">*</span>
                  </label>
                  <input v-model="form.name" type="text" required class="input text-sm" placeholder="e.g. Grilled Salmon" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Category</label>
                  <select v-model="form.category_id" class="input text-sm">
                    <option value="">— Select category —</option>
                    <option v-for="c in categories()" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </select>
                </div>
              </div>

              <!-- Price + Calories + Prep time -->
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">
                    Price ($) <span class="text-red-500">*</span>
                  </label>
                  <div class="relative">
                    <DollarSign :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-warm-400 pointer-events-none" />
                    <input v-model="form.price" type="number" min="0" step="0.01" required class="input pl-8 text-sm" placeholder="0.00" />
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Calories (kcal)</label>
                  <input v-model="form.calories" type="number" min="0" class="input text-sm" placeholder="e.g. 450" />
                </div>
                <div>
                  <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Prep Time (min)</label>
                  <input v-model="form.prep_time" type="number" min="0" class="input text-sm" placeholder="e.g. 20" />
                </div>
              </div>

              <!-- Description -->
              <div>
                <label class="block text-sm font-semibold text-warm-700 dark:text-warm-300 mb-1.5">Description</label>
                <textarea v-model="form.description" rows="3" class="input text-sm resize-none" placeholder="Brief description of the dish…"></textarea>
              </div>

              <!-- Toggles -->
              <div class="grid grid-cols-2 gap-4 p-4 bg-warm-50 dark:bg-warm-900/50 rounded-xl border border-warm-100 dark:border-warm-800">
                <label class="flex items-center gap-3 cursor-pointer select-none">
                  <div
                    @click="form.is_available = !form.is_available"
                    :class="['relative w-10 h-5 rounded-full transition-colors duration-200 flex-shrink-0', form.is_available ? 'bg-green-500' : 'bg-warm-300 dark:bg-warm-600']"
                  >
                    <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200', form.is_available ? 'translate-x-5' : 'translate-x-0.5']"></span>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-warm-800 dark:text-warm-200">Available</p>
                    <p class="text-xs text-warm-400">Show on customer menu</p>
                  </div>
                </label>
                <label class="flex items-center gap-3 cursor-pointer select-none">
                  <div
                    @click="form.is_featured = !form.is_featured"
                    :class="['relative w-10 h-5 rounded-full transition-colors duration-200 flex-shrink-0', form.is_featured ? 'bg-primary-500' : 'bg-warm-300 dark:bg-warm-600']"
                  >
                    <span :class="['absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200', form.is_featured ? 'translate-x-5' : 'translate-x-0.5']"></span>
                  </div>
                  <div>
                    <p class="text-sm font-semibold text-warm-800 dark:text-warm-200">Featured</p>
                    <p class="text-xs text-warm-400">Show on homepage</p>
                  </div>
                </label>
              </div>

            </div>

            <!-- Modal footer -->
            <div class="p-6 pt-4 border-t border-warm-100 dark:border-warm-800 shrink-0 flex items-center justify-between gap-3">
              <p class="text-xs text-warm-400">
                {{ editingDish ? 'Changes save immediately.' : 'Dish will appear on the menu after saving.' }}
              </p>
              <div class="flex gap-2">
                <button @click="closeModal" class="btn-outline text-sm py-2 px-4">Cancel</button>
                <button @click="save" :disabled="saving" class="btn-primary text-sm py-2 px-5 shadow-md">
                  {{ saving ? 'Saving…' : editingDish ? 'Save Changes' : 'Add Dish' }}
                </button>
              </div>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>
