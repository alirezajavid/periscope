import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Admin API for the payment approval queue plus the plans and payment methods it refers to. */
export const useAdminPaymentsStore = defineStore('adminPayments', () => {
  const list = (params) => api.get('/api/admin/payments', { params }).then((r) => r.data)
  const approve = (id, startsAt) =>
    api.post(`/api/admin/payments/${id}/approve`, { starts_at: startsAt || undefined })
  const reject = (id, reason) => api.post(`/api/admin/payments/${id}/reject`, { reason })

  /** The receipt needs the admin session cookie, so it is fetched through the client as a blob. */
  async function receiptUrl(id) {
    const { data } = await api.get(`/api/admin/payments/${id}/receipt`, { responseType: 'blob' })
    return URL.createObjectURL(data)
  }

  const plans = () => api.get('/api/admin/plans').then((r) => r.data.data)
  const savePlan = (id, payload) =>
    id ? api.put(`/api/admin/plans/${id}`, payload) : api.post('/api/admin/plans', payload)

  const methods = () => api.get('/api/admin/payment-methods').then((r) => r.data.data)
  const saveMethod = (id, payload) =>
    id
      ? api.put(`/api/admin/payment-methods/${id}`, payload)
      : api.post('/api/admin/payment-methods', payload)

  return { list, approve, reject, receiptUrl, plans, savePlan, methods, saveMethod }
})
