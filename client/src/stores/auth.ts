import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '../services/api'

export interface User {
  id: number | string
  name: string
  email: string
  phone?: string
  roles?: string[]
  permissions?: string[]
  hotel_id?: number | null
  section?: string | null
  on_duty?: boolean
  [key: string]: any
}

export const useAuthStore = defineStore('auth', () => {
  const user  = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('auth_token'))

  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin         = computed(() => user.value?.roles?.includes('super-admin') || user.value?.roles?.includes('admin') || user.value?.roles?.includes('manager'))
  const isStaff         = computed(() => ['super-admin', 'admin', 'manager', 'waiter', 'kitchen', 'cashier', 'reception', 'delivery'].some(r => user.value?.roles?.includes(r)))
  const hasRole         = (role: string) => user.value?.roles?.includes(role) ?? false

  const hasPermission = (permission: string | string[]) => {
    if (!user.value) return false
    if (user.value.roles?.includes('super-admin')) return true
    const userPerms = user.value.permissions || []
    if (Array.isArray(permission)) {
      return permission.every(p => userPerms.includes(p))
    }
    return userPerms.includes(permission)
  }

  const hasAnyPermission = (permissions: string[]) => {
    if (!user.value) return false
    if (user.value.roles?.includes('super-admin')) return true
    const userPerms = user.value.permissions || []
    return permissions.some(p => userPerms.includes(p))
  }

  async function login(credentials: Record<string, any>) {
    const { data } = await authApi.login(credentials)
    token.value = data.data.token
    user.value  = data.data.user
    localStorage.setItem('auth_token', token.value!)
    return data.data.user
  }

  async function register(payload: Record<string, any>) {
    const { data } = await authApi.register(payload)
    token.value = data.data.token
    user.value  = data.data.user
    localStorage.setItem('auth_token', token.value!)
    return data.data.user
  }

  async function logout() {
    try { await authApi.logout() } catch {}
    token.value = null
    user.value  = null
    localStorage.removeItem('auth_token')
  }

  async function fetchUser() {
    if (!token.value) return
    try {
      const { data } = await authApi.me()
      user.value = data.data
    } catch {
      token.value = null
      localStorage.removeItem('auth_token')
    }
  }

  return { user, token, isAuthenticated, isAdmin, isStaff, hasRole, hasPermission, hasAnyPermission, login, register, logout, fetchUser }
}, { persist: { pick: ['token'] } })
