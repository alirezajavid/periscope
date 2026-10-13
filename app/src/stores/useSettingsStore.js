import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Public content managed from the admin panel: links, fixed pages, the maintenance switch. */
export const useSettingsStore = defineStore('settings', () => {
  const data = ref({})
  const loaded = ref(false)

  async function fetch() {
    try {
      const response = await api.get('/api/settings')
      data.value = response.data.data
    } finally {
      loaded.value = true
    }
  }

  return { data, loaded, fetch }
})
