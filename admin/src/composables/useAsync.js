import { ref } from 'vue'

/**
 * Wraps an async loader with loading / error state for StatusPanel.
 * `run` never throws: failures end up in `error`.
 */
export function useAsync(loader, { fallbackMessage = '' } = {}) {
  const loading = ref(false)
  const error = ref('')

  async function run(...args) {
    loading.value = true
    error.value = ''
    try {
      return await loader(...args)
    } catch (e) {
      error.value = e.response?.data?.message || fallbackMessage
    } finally {
      loading.value = false
    }
  }

  return { loading, error, run }
}
