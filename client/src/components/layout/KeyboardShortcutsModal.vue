<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { HelpCircle, X, Compass, Keyboard } from 'lucide-vue-next'

const props = defineProps<{
  modelValue?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'close'): void
}>()

const { t } = useI18n()
const router = useRouter()
const isOpen = ref(props.modelValue ?? false)

watch(() => props.modelValue, (val) => {
  if (val !== undefined) isOpen.value = val
})

function close() {
  isOpen.value = false
  emit('update:modelValue', false)
  emit('close')
}

function open() {
  isOpen.value = true
  emit('update:modelValue', true)
}

defineExpose({ open, close })

// Sequence shortcut listener: "G then X"
let gPressedTime = 0

function isInputActive(): boolean {
  const active = document.activeElement
  if (!active) return false
  const tag = active.tagName.toLowerCase()
  return tag === 'input' || tag === 'textarea' || tag === 'select' || (active as HTMLElement).isContentEditable
}

function handleKeyDown(e: KeyboardEvent) {
  if (isInputActive()) return

  if (e.key === '?' && !e.metaKey && !e.ctrlKey) {
    e.preventDefault()
    if (isOpen.value) close()
    else open()
    return
  }

  if (e.key === 'Escape' && isOpen.value) {
    e.preventDefault()
    close()
    return
  }

  // Sequence detection: G then key
  const now = Date.now()
  if (e.key.toLowerCase() === 'g' && !e.metaKey && !e.ctrlKey && !e.altKey) {
    gPressedTime = now
    return
  }

  if (gPressedTime > 0 && now - gPressedTime < 1500 && !e.metaKey && !e.ctrlKey && !e.altKey) {
    const k = e.key.toLowerCase()
    gPressedTime = 0
    switch (k) {
      case 'd': router.push('/admin'); break
      case 'o': router.push('/admin/orders'); break
      case 'p': router.push('/staff/cashier'); break
      case 'k': router.push('/staff/kitchen'); break
      case 'r': router.push('/admin/reservations'); break
      case 't': router.push('/admin/tables'); break
      case 'm': router.push('/admin/menu'); break
      case 'i': router.push('/admin/inventory'); break
      case 's': router.push('/admin/settings'); break
    }
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-100 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isOpen"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @click.self="close"
        role="dialog"
        aria-modal="true"
        :aria-label="$t('sidebar.shortcutsModal.title')"
      >
        <div
          class="relative w-full max-w-xl bg-white dark:bg-[#0f1117] rounded-2xl border border-warm-200/90 dark:border-white/10 shadow-2xl overflow-hidden flex flex-col max-h-[85vh] transition-all"
        >
          <!-- Modal Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-warm-200/80 dark:border-white/10">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400 flex items-center justify-center">
                <Keyboard :size="20" />
              </div>
              <div>
                <h3 class="text-base font-bold text-warm-900 dark:text-white leading-tight">
                  {{ $t('sidebar.shortcutsModal.title') }}
                </h3>
                <p class="text-xs text-warm-500 dark:text-neutral-400 mt-0.5">
                  {{ $t('sidebar.shortcutsModal.subtitle') }}
                </p>
              </div>
            </div>

            <button
              @click="close"
              class="p-1.5 rounded-lg text-warm-400 hover:text-warm-700 dark:text-neutral-400 dark:hover:text-white hover:bg-warm-100 dark:hover:bg-neutral-800 transition-colors"
              aria-label="Close dialog"
            >
              <X :size="18" />
            </button>
          </div>

          <!-- Shortcuts list -->
          <div class="p-6 space-y-6 overflow-y-auto">
            <!-- General Shortcuts -->
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-warm-500 dark:text-neutral-400 mb-3 flex items-center gap-2">
                <Compass :size="14" class="text-primary-500" />
                {{ $t('sidebar.shortcutsModal.general') }}
              </h4>
              <div class="space-y-2">
                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">
                    {{ $t('sidebar.shortcutsModal.toggleSidebar') }}
                  </span>
                  <kbd class="px-2 py-1 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded-md shadow-xs">
                    [
                  </kbd>
                </div>
                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">
                    {{ $t('sidebar.shortcutsModal.openPalette') }}
                  </span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-2 py-1 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded-md shadow-xs">
                      ⌘K / Ctrl+K
                    </kbd>
                  </div>
                </div>
                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">
                    {{ $t('sidebar.shortcutsModal.showCheatSheet') }}
                  </span>
                  <kbd class="px-2 py-1 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded-md shadow-xs">
                    ?
                  </kbd>
                </div>
                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">
                    {{ $t('sidebar.shortcutsModal.closeModal') }}
                  </span>
                  <kbd class="px-2 py-1 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded-md shadow-xs">
                    Esc
                  </kbd>
                </div>
              </div>
            </div>

            <!-- Sequence Jump Navigation -->
            <div>
              <h4 class="text-xs font-bold uppercase tracking-wider text-warm-500 dark:text-neutral-400 mb-3 flex items-center gap-2">
                <Keyboard :size="14" class="text-primary-500" />
                {{ $t('sidebar.shortcutsModal.navigation') }}
              </h4>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.dashboardKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">D</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.ordersKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">O</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.posKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">P</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.kitchenKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">K</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.reservationsKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">R</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.tablesKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">T</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.menuKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">M</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.inventoryKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">I</kbd>
                  </div>
                </div>

                <div class="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-warm-50 dark:hover:bg-neutral-900/60 transition-colors">
                  <span class="text-sm text-warm-700 dark:text-neutral-300 font-medium">{{ $t('sidebar.shortcutsModal.settingsKey') }}</span>
                  <div class="flex items-center gap-1">
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">G</kbd>
                    <span class="text-warm-400 text-xs">then</span>
                    <kbd class="px-1.5 py-0.5 text-xs font-mono font-semibold bg-warm-100 dark:bg-neutral-800 text-warm-700 dark:text-neutral-300 border border-warm-200 dark:border-white/10 rounded shadow-xs">S</kbd>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-3 border-t border-warm-200/80 dark:border-white/10 bg-warm-50/50 dark:bg-neutral-900/40 flex items-center justify-end">
            <button
              @click="close"
              class="px-4 py-2 text-sm font-semibold text-warm-700 dark:text-neutral-300 hover:bg-warm-100 dark:hover:bg-neutral-800 rounded-xl transition-colors"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
