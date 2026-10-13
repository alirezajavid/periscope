import { defineBoot } from '#q-app'
import axios from 'axios'
import { createClient } from '@shared/api/client.js'
import { useAdminAuthStore } from '@/stores/useAdminAuthStore'

// Late-bound so the client can exist before the router and store do.
const handlers = { onUnauthenticated: () => {} }

// Shared client. Admin routes use their own session cookie, and the CSRF token comes from the API's JSON.
const api = createClient(axios, {
  baseURL: import.meta.env.API_URL,
  csrfTokenPath: '/api/admin/csrf-token',
  onUnauthenticated: (status) => handlers.onUnauthenticated(status),
})

export default defineBoot(({ router, store }) => {
  // 401/419 while signed in: idle timeout (30 min) or expired session.
  handlers.onUnauthenticated = () => {
    const auth = useAdminAuthStore(store)
    if (!auth.isLoggedIn) return
    auth.user = null
    router.push({ name: 'login', query: { expired: '1' } })
  }
})

export { api }
