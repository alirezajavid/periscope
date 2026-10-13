import { ref, computed } from 'vue'
import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from '@/boot/axios'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loaded = ref(false)

  const isLoggedIn = computed(() => user.value !== null)
  const emailVerified = computed(() => user.value?.email_verified === true)

  async function fetchUser() {
    try {
      const { data } = await api.get('/api/user')
      user.value = data.data
    } catch {
      user.value = null
    } finally {
      loaded.value = true
    }
  }

  async function register(payload) {
    const { data } = await api.post('/api/register', payload)
    user.value = data.data
  }

  async function login(payload) {
    const { data } = await api.post('/api/login', payload)
    user.value = data.data
  }

  async function logout() {
    await api.post('/api/logout')
    user.value = null
  }

  async function logoutAll() {
    await api.post('/api/logout-all')
    user.value = null
  }

  async function changePassword(payload) {
    await api.put('/api/password', payload)
    user.value = null
  }

  const forgotPassword = (payload) => api.post('/api/forgot-password', payload)
  const resetPassword = (payload) => api.post('/api/reset-password', payload)
  const resendVerification = () => api.post('/api/email/resend')

  return {
    user,
    loaded,
    isLoggedIn,
    emailVerified,
    fetchUser,
    register,
    login,
    logout,
    logoutAll,
    changePassword,
    forgotPassword,
    resetPassword,
    resendVerification,
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot))
}
