<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { profileApi }   from '../../services/api'
import DefaultLayout from '../../layouts/DefaultLayout.vue'
import ThemeToggle from '../../components/ui/ThemeToggle.vue'
import AppButton from '../../components/ui/AppButton.vue'
import { APP_NAME } from '../../config'
import { Sparkles, Moon, Sun } from '@lucide/vue'

const { t } = useI18n()
const auth   = useAuthStore()
const ui     = useUiStore()
const form   = ref({ name: auth.user?.name ?? '', phone: auth.user?.phone ?? '' })
const saving = ref(false)

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
</script>

<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-xl space-y-6">
      <div>
        <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white">{{ $t('profilePage.title') }}</h1>
        <p class="text-sm text-warm-500 dark:text-warm-400 mt-1">{{ $t('profilePage.subtitle') }}</p>
      </div>

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
  </DefaultLayout>
</template>
