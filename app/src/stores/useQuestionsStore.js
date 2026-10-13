import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

export const useQuestionsStore = defineStore('questions', () => {
  const items = ref([])

  async function fetch() {
    const { data } = await api.get('/api/questions')
    items.value = data.data
  }

  async function ask(body) {
    const { data } = await api.post('/api/questions', { body })
    items.value.unshift(data.data)
  }

  return { items, fetch, ask }
})
