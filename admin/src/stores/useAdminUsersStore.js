import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Admin API for users: list, detail, account actions and manual subscriptions. */
export const useAdminUsersStore = defineStore('adminUsers', () => {
  const list = (params) => api.get('/api/admin/users', { params }).then((r) => r.data)
  const get = (id) => api.get(`/api/admin/users/${id}`).then((r) => r.data.data)
  const create = (payload) => api.post('/api/admin/users', payload).then((r) => r.data.data)
  const sendPasswordLink = (id) => api.post(`/api/admin/users/${id}/send-password-link`)
  const verifyEmail = (id) => api.post(`/api/admin/users/${id}/verify-email`)
  const setPassword = (id, password) => api.put(`/api/admin/users/${id}/password`, { password })
  const block = (id) => api.post(`/api/admin/users/${id}/block`)
  const unblock = (id) => api.post(`/api/admin/users/${id}/unblock`)
  const logoutAll = (id) => api.post(`/api/admin/users/${id}/logout-all`)
  const grantSubscription = (id, payload) =>
    api.post(`/api/admin/users/${id}/subscriptions`, payload)

  return {
    list,
    get,
    create,
    sendPasswordLink,
    verifyEmail,
    setPassword,
    
    unblock,
    logoutAll,
    grantSubscription,
  }
})
