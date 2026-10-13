import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/boot/axios'
import { clearOfflineAudio } from '@/services/offlineAudio'

/** The signed-in user's subscription state, plans, payment methods and payments. */
export const useSubscriptionStore = defineStore('subscription', () => {
  const status = ref(null) // { active, ends_at, subscriptions }
  const plans = ref([])
  const methods = ref([])
  const payments = ref([])

  const active = computed(() => status.value?.active === true)
  const endsAt = computed(() => status.value?.ends_at ?? null)

  async function fetchStatus() {
    const { data } = await api.get('/api/subscription')
    status.value = data
    // Remember when we last confirmed the subscription online: offline playback relies on it.
    localStorage.setItem('periscope.lastOnlineCheck', String(Date.now()))
    localStorage.setItem('periscope.subscriptionEndsAt', data.ends_at ?? '')
    // The subscription is over: stored episodes must not stay playable offline.
    if (!data.active) clearOfflineAudio()
    return data
  }

  async function fetchPlans() {
    const { data } = await api.get('/api/plans')
    plans.value = data.data
  }

  async function fetchMethods() {
    const { data } = await api.get('/api/payment-methods')
    methods.value = data.data
  }

  async function fetchPayments() {
    const { data } = await api.get('/api/payments')
    payments.value = data.data
  }

  async function submitPayment(form) {
    const body = new FormData()
    Object.entries(form).forEach(([key, value]) => body.append(key, value))
    const { data } = await api.post('/api/payments', body)
    await fetchPayments()
    return data.data
  }

  function reset() {
    status.value = null
    payments.value = []
  }

  return {
    status,
    plans,
    methods,
    payments,
    active,
    endsAt,
    fetchStatus,
    fetchPlans,
    fetchMethods,
    fetchPayments,
    submitPayment,
    reset,
  }
})
