<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useUiStore }   from '../../stores/ui'
import { profileApi }   from '../../services/api'
import DefaultLayout from '../../layouts/DefaultLayout.vue'

const auth   = useAuthStore()
const ui     = useUiStore()
const form   = ref({ name: auth.user?.name ?? '', phone: auth.user?.phone ?? '' })
const saving = ref(false)

async function save() {
  saving.value = true
  try {
    await profileApi.update(form.value)
    await auth.fetchUser()
    ui.toast('Profile updated', 'success')
  } catch { ui.toast('Failed to update profile', 'error') }
  finally { saving.value = false }
}
</script>
<template>
  <DefaultLayout>
    <div class="page-container py-10 max-w-xl">
      <h1 class="font-heading text-3xl font-bold text-warm-900 dark:text-white mb-8">My Profile</h1>
      <div class="card p-8 space-y-5">
        <div class="flex items-center gap-4 pb-5 border-b border-warm-100 dark:border-warm-800">
          <div class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-2xl font-bold text-primary-600 dark:text-primary-400">
            {{ auth.user?.name?.[0]?.toUpperCase() }}
          </div>
          <div>
            <p class="font-heading font-bold text-lg text-warm-900 dark:text-white">{{ auth.user?.name }}</p>
            <p class="text-sm text-warm-500">{{ auth.user?.email }}</p>
            <p class="text-xs text-primary-600 dark:text-primary-400 mt-0.5">{{ auth.user?.loyalty_points }} loyalty points</p>
          </div>
        </div>
        <form @submit.prevent="save" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Full Name</label>
            <input v-model="form.name" type="text" required class="input" />
          </div>
          <div>
            <label class="block text-sm font-medium text-warm-700 dark:text-warm-300 mb-1.5">Phone</label>
            <input v-model="form.phone" type="tel" class="input" />
          </div>
          <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
        </form>
      </div>
    </div>
  </DefaultLayout>
</template>
