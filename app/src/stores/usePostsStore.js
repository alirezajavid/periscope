import { ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

/** Episode and article lists (paginated) and single posts. */
export const usePostsStore = defineStore('posts', () => {
  const items = ref([])
  const total = ref(0)
  const page = ref(0)
  const lastPage = ref(1)
  const loading = ref(false)
  const error = ref('')
  const filters = ref({ q: '', tag: '' })

  const hasMore = () => page.value < lastPage.value

  /** Load the first page for the given filters, replacing the list. */
  async function load(newFilters = {}) {
    filters.value = { q: '', tag: '', ...newFilters }
    items.value = []
    page.value = 0
    lastPage.value = 1
    await loadMore()
  }

  async function loadMore() {
    if (loading.value || !hasMore()) return
    loading.value = true
    error.value = ''
    try {
      const params = { page: page.value + 1 }
      if (filters.value.q) params.q = filters.value.q
      if (filters.value.tag) params.tag = filters.value.tag
      const { data } = await api.get('/api/posts', { params })
      items.value.push(...data.data)
      page.value = data.meta.current_page
      lastPage.value = data.meta.last_page
      total.value = data.meta.total
    } catch (e) {
      error.value = e.response?.data?.message || ''
    } finally {
      loading.value = false
    }
  }

  async function fetchPost(id) {
    const { data } = await api.get(`/api/posts/${id}`)
    return data.data
  }

  async function fetchTags() {
    const { data } = await api.get('/api/tags')
    return data.data
  }

  async function fetchContinue() {
    const { data } = await api.get('/api/continue')
    return data.data
  }

  return {
    items,
    total,
    loading,
    error,
    filters,
    hasMore,
    load,
    loadMore,
    fetchPost,
    fetchTags,
    fetchContinue,
  }
})
