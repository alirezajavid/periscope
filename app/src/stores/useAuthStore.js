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

  async function submitAndStore(request) {
    const { data } = await request
    user.value = data.data
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

  // Logging out also empties anything cached for offline playback (see usePlayerStore)
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
  }
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(useAuthStore, import.meta.hot))
}
