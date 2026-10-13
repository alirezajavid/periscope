// Page side of the silent offline cache (the Service Worker does the storing: src-pwa/sw/custom-sw.js).
//
// Rules (docs/reference.md §5):
//  - the whole personalized file is stored ~30 s after playback starts, without any UI;
//  - before playing from the cache the subscription must still be valid, and we must have
//    checked it online within the last 7 days;
//  - the cache is emptied on logout and when the subscription ends.

const REGISTRY_KEY = 'periscope.offlineAudio' // { [postId]: { fileId, cachedAt, post: { id, title, cover_url, audio_duration, access } } }
const CACHE_AFTER_MS = 30000
export const OFFLINE_GRACE_MS = 7 * 24 * 3600 * 1000

let timer = null
let listening = false

const readRegistry = () => {
  try {
    return JSON.parse(localStorage.getItem(REGISTRY_KEY) || '{}')
  } catch {
    return {}
  }
}
const writeRegistry = (value) => localStorage.setItem(REGISTRY_KEY, JSON.stringify(value))

const fileIdOf = (url) => url.match(/\/api\/audio\/(\d+)/)?.[1] ?? null

async function worker() {
  if (!('serviceWorker' in navigator)) return null
  const registration = await navigator.serviceWorker.ready
  return registration.active
}

function listen() {
  if (listening || !('serviceWorker' in navigator)) return
  listening = true
  navigator.serviceWorker.addEventListener('message', (event) => {
    if (event.data?.type !== 'AUDIO_CACHED') return
    const registry = readRegistry()
    const entry = registry[event.data.postId]
    if (entry) {
      entry.cachedAt = Date.now()
      writeRegistry(registry)
    }
  })
}

/** Ask the browser not to evict our storage (needed on iOS). Called on the first play. */
export function requestPersistentStorage() {
  navigator.storage?.persist?.().catch(() => {})
}

/**
 * Store this episode for offline playback ~30 s from now, if the listener is still on it.
 * `isStillPlaying` is checked when the timer fires.
 */
export function scheduleCache(post, url, isStillPlaying) {
  cancelScheduledCache()
  const postId = post.id
  const fileId = fileIdOf(url)
  if (!fileId || !('serviceWorker' in navigator)) return
  if (readRegistry()[postId]?.cachedAt) return // already stored

  listen()
  timer = setTimeout(async () => {
    if (!isStillPlaying()) return
    const registry = readRegistry()
    registry[postId] = {
      fileId,
      cachedAt: null,
      post: {
        id: post.id,
        title: post.title,
        cover_url: post.cover_url,
        audio_duration: post.audio_duration,
        access: post.access,
      },
    }
    writeRegistry(registry)
    ;(await worker())?.postMessage({ type: 'CACHE_AUDIO', url, postId })
  }, CACHE_AFTER_MS)
}

export function cancelScheduledCache() {
  clearTimeout(timer)
  timer = null
}

/** Why offline playback is refused, or null when it is allowed. */
export function offlineBlocker() {
  const lastCheck = Number(localStorage.getItem('periscope.lastOnlineCheck') || 0)
  const endsAt = Date.parse(localStorage.getItem('periscope.subscriptionEndsAt') || '')
  if (!lastCheck || Date.now() - lastCheck > OFFLINE_GRACE_MS) return 'offline_expired'
  if (!endsAt || endsAt < Date.now()) return 'subscription'
  return null
}

/** URL to play a cached episode from when the network is unavailable, or null if it is not stored. */
export function offlineSource(postId, apiUrl) {
  const entry = readRegistry()[postId]
  return entry?.cachedAt ? `${apiUrl}/api/audio/${entry.fileId}` : null
}

/** Episodes stored for offline playback, newest first (shown on the home screen when the API is unreachable). */
export function storedEpisodes() {
  return Object.values(readRegistry())
    .filter((entry) => entry.cachedAt && entry.post)
    .sort((a, b) => b.cachedAt - a.cachedAt)
    .map((entry) => entry.post)
}

/** Forget every stored episode (logout, subscription ended). */
export async function clearOfflineAudio() {
  cancelScheduledCache()
  localStorage.removeItem(REGISTRY_KEY)
  localStorage.removeItem('periscope.lastOnlineCheck')
  localStorage.removeItem('periscope.subscriptionEndsAt')
  ;(await worker().catch(() => null))?.postMessage({ type: 'CLEAR_AUDIO' })
}
