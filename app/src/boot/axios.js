import { defineBoot } from '#q-app'
import axios from 'axios'
import { createClient } from '@shared/api/client.js'
import { useAuthStore } from '@/stores/useAuthStore'

// Late-bound so the client can exist before the router and store do.
const handlers = { onUnauthenticated: () => {} }

// Shared client: Sanctum SPA cookies + CSRF. Import `api` wherever a request is needed.
const api = createClient(axios, {
  baseURL: import.meta.env.API_URL,
  onUnauthenticated: (status) => handlers.onUnauthenticated(status),
})

export default defineBoot(({ app, router, store }) => {
  app.config.globalProperties.$api = api

  // 401/419 while signed in: the session ended (expired, other browser/OS, password change).
  handlers.onUnauthenticated = () => {
    const auth = useAuthStore(store)
    if (!auth.isLoggedIn) return
    auth.user = null
    router.push({ name: 'login', query: { expired: '1' } })
  }
})

export { api }
