/*
 * This file (which will be your service worker)
 * is picked up by the build system ONLY if
 * quasar.config file > pwa > workboxMode is set to "InjectManifest"
 */

import { clientsClaim } from 'workbox-core'
import { createPartialResponse } from 'workbox-range-requests'
import { NavigationRoute, registerRoute } from 'workbox-routing'
import {
  cleanupOutdatedCaches,
  createHandlerBoundToURL,
  precacheAndRoute,
} from 'workbox-precaching'

self.skipWaiting()
clientsClaim()

// Use with precache injection
precacheAndRoute(self.__WB_MANIFEST)

cleanupOutdatedCaches()

if (import.meta.env.QUASAR_PROD) {
  // Non-SSR/SSG fallbacks to index.html
  // Production SSR/SSG fallbacks to offline.html (except for dev)
  registerRoute(
    new NavigationRoute(createHandlerBoundToURL(import.meta.env.QUASAR_PWA_FALLBACK_HTML), {
      denylist: [new RegExp(import.meta.env.QUASAR_PWA_SERVICE_WORKER_REGEX), /workbox-(.)*\.js$/],
    }),
  )
}

// ---------------------------------------------------------------------------
// Silent offline audio (docs/reference.md §5)
//
// The page asks us to store the whole personalized episode ~30 s after playback starts.
// It is kept in Cache Storage under a key that ignores the signed query string, and served
// with Range support so seeking works offline. Nothing else (no API response) is cached here.
// ---------------------------------------------------------------------------
const AUDIO_CACHE = 'periscope-audio-v1'
const AUDIO_PATH = /\/api\/audio\/(\d+)$/

/** The signed URL changes every time; the cache key is the file URL without it. */
const audioKey = (url) => {
  const { origin, pathname } = new URL(url)
  return `${origin}${pathname}`
}

registerRoute(
  ({ url }) => AUDIO_PATH.test(url.pathname),
  async ({ request }) => {
    const cache = await caches.open(AUDIO_CACHE)
    const stored = await cache.match(audioKey(request.url))
    if (stored) {
      return request.headers.has('range') ? createPartialResponse(request, stored) : stored
    }
    return fetch(request)
  },
)

async function cacheAudio(url) {
  const cache = await caches.open(AUDIO_CACHE)
  const key = audioKey(url)
  if (await cache.match(key)) return true

  // Low priority so it never competes with what the listener is hearing; signed URL needs no cookies.
  const response = await fetch(url, { mode: 'cors', credentials: 'omit', priority: 'low' })
  if (response.status !== 200) return false

  await cache.put(key, response)
  return true
}

self.addEventListener('message', (event) => {
  const { type, url, postId } = event.data ?? {}

  if (type === 'CACHE_AUDIO') {
    event.waitUntil(
      cacheAudio(url)
        .then((ok) =>
          event.source?.postMessage({
            type: ok ? 'AUDIO_CACHED' : 'AUDIO_CACHE_FAILED',
            postId,
            key: audioKey(url),
          }),
        )
        .catch(() => event.source?.postMessage({ type: 'AUDIO_CACHE_FAILED', postId })),
    )
  }

  if (type === 'CLEAR_AUDIO') {
    event.waitUntil(caches.delete(AUDIO_CACHE))
  }
})
