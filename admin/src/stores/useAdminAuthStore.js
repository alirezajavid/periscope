import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Admin session: password step, TOTP step (setup on first login), logout. */
export const useAdminAuthStore = defineStore('adminAuth', () => {
  const user = ref(null)
  const loaded = ref(false)

  const isLoggedIn = computed(() => user.value !== null)

  async function fetchUser() {
    try {
      const { data } = await api.get('/api/admin/user')
      user.value = data.data
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
  }

  /** Step 1. Resolves to 'two_factor_setup_required' or 'two_factor_required'. */
  async function login(payload) {
    const { data } = await api.post('/api/admin/login', payload)
    return data.status
  }

  /** First login: secret, QR (SVG) and recovery codes. */
  async function startSetup() {
    const { data } = await api.post('/api/admin/two-factor/setup')
    return data
  }

  async function confirmSetup(code) {
    const { data } = await api.post('/api/admin/two-factor/confirm', { code })
    user.value = data.data
  }

  async function challenge(payload) {
    const { data } = await api.post('/api/admin/two-factor/challenge', payload)
    user.value = data.data
  }

  async function logout() {
    await api.post('/api/admin/logout')
    user.value = null
  }

  return { user, loaded, isLoggedIn, fetchUser, login, startSetup, confirmSetup, challenge, logout }
})
