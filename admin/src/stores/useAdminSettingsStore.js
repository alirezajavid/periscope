import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Admin API for the key-value settings (links, fixed pages, maintenance mode). */
export const useAdminSettingsStore = defineStore('adminSettings', () => {
  const load = () => api.get('/api/admin/settings').then((r) => r.data.data)
  const save = (payload) => api.put('/api/admin/settings', payload).then((r) => r.data.data)

  return { load, save }
})
