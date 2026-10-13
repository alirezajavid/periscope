import { defineBoot } from '#q-app'
import axios from 'axios'
import { createClient } from '@shared/api/client.js'

// Shared client: Sanctum SPA cookies + CSRF. Import `api` wherever a request is needed.
const api = createClient(axios, { baseURL: process.env.API_URL })

export default defineBoot(({ app }) => {
  app.config.globalProperties.$api = api
})

export { api }
