import { reactive, ref } from 'vue'

/**
 * Submit state for a form that talks to the API: loading flag, per-field
 * validation errors (Laravel 422 shape) and a general error message.
 */
export function useApiForm() {
  const loading = ref(false)
  const errors = reactive({})
  const message = ref('')

  async function submit(action) {
    loading.value = true
    message.value = ''
    Object.keys(errors).forEach((key) => delete errors[key])
    try {
      await action()
      return true
    } catch (error) {
      const data = error.response?.data
      if (error.response?.status === 422 && data?.errors) {
        Object.entries(data.errors).forEach(([field, list]) => (errors[field] = list[0]))
      } else {
        message.value = data?.message ?? ''
      }
      return false
    } finally {
      loading.value = false
    }
  }

  return { loading, errors, message, submit }
}
