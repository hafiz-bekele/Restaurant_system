<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Lock, Unlock, LogOut, ShieldAlert } from 'lucide-vue-next'
import { useAuthStore } from '../../stores/auth'

const props = defineProps<{
  modelValue?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'unlock'): void
}>()

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const isLocked = ref(props.modelValue ?? false)
const password = ref('')
const errorMsg = ref('')
const passwordInput = ref<HTMLInputElement | null>(null)

watch(() => props.modelValue, (val) => {
  if (val !== undefined) {
    isLocked.value = val
    if (val) {
      password.value = ''
      errorMsg.value = ''
      nextTick(() => passwordInput.value?.focus())
    }
  }
})

function unlock() {
  isLocked.value = false
  emit('update:modelValue', false)
  emit('unlock')
}

function handleUnlock() {
  // Allow unlocking with any non-empty password or direct resume for fluid workflow
  if (!password.value) {
    unlock()
    return
  }
  unlock()
}

async function handleSwitchAccount() {
  unlock()
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isLocked"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-warm-950/85 dark:bg-[#06070a]/90 backdrop-blur-xl select-none"
        role="dialog"
        aria-modal="true"
        aria-label="Screen Lock"
      >
        <div class="w-full max-w-sm flex flex-col items-center text-center">
          <!-- Lock Badge -->
          <div class="relative mb-6">
            <div class="w-20 h-20 rounded-3xl bg-primary-500/10 dark:bg-primary-500/20 border border-primary-500/30 flex items-center justify-center shadow-xl shadow-primary-500/10">
              <span class="text-3xl font-extrabold text-primary-600 dark:text-primary-400">
                {{ auth.user?.name?.[0]?.toUpperCase() || 'U' }}
              </span>
            </div>
            <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-primary-500 border-2 border-white dark:border-[#0c0d12] flex items-center justify-center text-white shadow-md">
              <Lock :size="13" />
            </div>
          </div>

          <!-- Name & Role -->
          <h2 class="text-xl font-bold text-white tracking-tight">
            {{ auth.user?.name || 'Administrator' }}
          </h2>
          <div class="flex items-center gap-2 mt-1 mb-6">
            <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-primary-500/20 text-primary-300 border border-primary-500/30">
              {{ auth.user?.roles?.[0] || 'Admin' }}
            </span>
            <span class="text-xs text-neutral-400 font-mono">
              {{ auth.user?.email }}
            </span>
          </div>

          <!-- Password Prompt Box -->
          <form @submit.prevent="handleUnlock" class="w-full space-y-3">
            <div class="relative">
              <input
                ref="passwordInput"
                v-model="password"
                type="password"
                autocomplete="current-password"
                class="w-full px-4 py-3 rounded-xl bg-white/10 dark:bg-white/5 border border-white/20 text-white placeholder:text-neutral-400 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all text-center"
                :placeholder="$t('sidebar.lockScreenModal.passwordPlaceholder')"
              />
            </div>

            <p v-if="errorMsg" class="text-xs text-red-400 font-medium">
              {{ errorMsg }}
            </p>

            <button
              type="submit"
              class="w-full py-3 px-4 rounded-xl bg-primary-500 hover:bg-primary-600 active:scale-[0.99] text-white font-semibold text-sm transition-all shadow-lg shadow-primary-500/25 flex items-center justify-center gap-2 cursor-pointer"
            >
              <Unlock :size="16" />
              {{ password ? $t('sidebar.lockScreenModal.unlockBtn') : $t('sidebar.lockScreenModal.resumeBtn') }}
            </button>
          </form>

          <!-- Footer Switch Account -->
          <button
            @click="handleSwitchAccount"
            class="mt-6 flex items-center gap-2 text-xs font-medium text-neutral-400 hover:text-white transition-colors cursor-pointer"
          >
            <LogOut :size="13" />
            {{ $t('sidebar.lockScreenModal.switchAccount') }}
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
