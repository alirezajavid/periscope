import { ref, computed } from 'vue'
import { defineStore, acceptHMRUpdate } from 'pinia'
import { api } from '@/boot/axios'
import { OFFLINE_GRACE_MS, clearOfflineAudio } from '@/services/offlineAudio'
import { usePlayerStore } from '@/stores/usePlayerStore'
import { useSubscriptionStore } from '@/stores/useSubscriptionStore'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loaded = ref(false)

  const isLoggedIn = computed(() => user.value !== null)
  const emailVerified = computed(() => user.value?.email_verified === true)

  const USER_KEY = 'periscope.user'

  function remember(value) {
    user.value = value
    localStorage.setItem(USER_KEY, JSON.stringify(value))
  }

  // No connection at all: keep the last known user for the offline grace period so stored episodes stay reachable.
  function rememberedUser() {
    const lastCheck = Number(localStorage.getItem('periscope.lastOnlineCheck') || 0)
    if (!lastCheck || Date.now() - lastCheck > OFFLINE_GRACE_MS) return null
    try {
      return JSON.parse(localStorage.getItem(USER_KEY))
    } catch {
      return null
    }
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/api/user')
      remember(data.data)
    } catch (e) {
      user.value = e.response ? null : rememberedUser()
    } finally {
      loaded.value = true
    }
  }

  async function submitAndStore(request) {
    const { data } = await request
    remember(data.data)
  }

  const register = (payload) => submitAndStore(api.post('/api/register', payload))
  const login = (payload) => submitAndStore(api.post('/api/login', payload))
  const otpLogin = (payload) => submitAndStore(api.post('/api/otp/login', payload))
  const updateEmail = (email) => submitAndStore(api.put('/api/email', { email }))
  const updateProfile = (payload) => submitAndStore(api.put('/api/profile', payload))

  const sendOtp = (phone) => api.post('/api/otp/send', { phone })
  const forgotPassword = (payload) => api.post('/api/forgot-password', payload)
  const resetPassword = (payload) => api.post('/api/reset-password', payload)
  const resendVerification = () => api.post('/api/email/resend')

  // Whenever the session ends: stop playing and forget per-user state (offline audio is cleared in usePlayerStore.stop)
  function clearSession() {
    usePlayerStore().stop()
    clearOfflineAudio()
    useSubscriptionStore().reset()
    localStorage.removeItem(USER_KEY)
    user.value = null
  }

  async function logout() {
    await api.post('/api/logout')
    clearSession()
  }

  async function logoutAll() {
    await api.post('/api/logout-all')
    clearSession()
  }

  async function changePassword(payload) {
    await api.put('/api/password', payload)
    clearSession()
  }

  return {
    user,
    loaded,
    isLoggedIn,
    emailVerified,
    fetchUser,
    register,
    login,
    otpLogin,
    updateEmail,
    updateProfile,
    sendOtp,
    forgotPassword,
    resetPassword,
    resendVerification,
    logout,
    logoutAll,
    changePassword,
    clearSession,
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot))
}
