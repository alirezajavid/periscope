import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { api } from '@/boot/axios'

const SKIP_SECONDS = 15
const REPORT_EVERY_MS = 15000
const PREPARING_POLL_MS = 3000
const PREPARING_MAX_TRIES = 60
export const SPEEDS = [1, 1.25, 1.5, 1.75, 2]
export const SLEEP_OPTIONS = [15, 30, 45, 60] // minutes

/** Stable id of this browser; the API lets only one device play at a time per account. */
function deviceId() {
  let id = localStorage.getItem('periscope.deviceId')
  if (!id) {
    id = crypto.randomUUID()
    localStorage.setItem('periscope.deviceId', id)
  }
  return id
}

// One <audio> element for the whole app, so playback survives page changes.
let audio = null
let reportTimer = null
let sleepTimer = null

export const usePlayerStore = defineStore('player', () => {
  const current = ref(null) // { id, title, cover_url, access }
  const status = ref('idle') // idle | loading | preparing | paused | playing | error
  const error = ref('') // playing_elsewhere | subscription | network | unavailable
  const position = ref(0)
  const duration = ref(0)
  const speed = ref(1)
  const sleepEndsAt = ref(null)
  const source = ref('') // url currently loaded (used by the offline cache)

  const isPlaying = computed(() => status.value === 'playing')
  const hasTrack = computed(() => current.value !== null)

  function element() {
    if (audio) return audio
    audio = new Audio()
    audio.preload = 'auto'
    audio.addEventListener('timeupdate', () => (position.value = audio.currentTime))
    audio.addEventListener('loadedmetadata', () => {
      if (Number.isFinite(audio.duration)) duration.value = Math.floor(audio.duration)
    })
    audio.addEventListener('play', () => {
      status.value = 'playing'
      startReporting()
      updateMediaSession()
    })
    audio.addEventListener('pause', () => {
      if (!audio.ended && status.value === 'playing') status.value = 'paused'
      stopReporting()
    })
    audio.addEventListener('ended', () => {
      status.value = 'paused'
      stopReporting()
      report()
    })
    audio.addEventListener('error', () => {
      if (status.value === 'loading' || status.value === 'playing') fail('network')
    })
    return audio
  }

  function fail(code) {
    error.value = code
    status.value = 'error'
    stopReporting()
  }

  /** Ask the API where to stream from; waits while the personalized copy is being built. */
  async function resolveSource(postId) {
    for (let attempt = 0; attempt < PREPARING_MAX_TRIES; attempt++) {
      const response = await api.get(`/api/posts/${postId}/audio`, {
        params: { device: deviceId() },
        validateStatus: (code) => code < 500,
      })
      if (response.status === 200) return response.data.url
      if (response.status === 202) {
        status.value = 'preparing'
        await new Promise((resolve) => setTimeout(resolve, PREPARING_POLL_MS))
        continue
      }
      if (response.status === 409)
        throw Object.assign(new Error('elsewhere'), { code: 'playing_elsewhere' })
      if (response.status === 403)
        throw Object.assign(new Error('subscription'), { code: 'subscription' })
      throw Object.assign(new Error('unavailable'), { code: 'unavailable' })
    }
    throw Object.assign(new Error('unavailable'), { code: 'unavailable' })
  }

  async function resumePosition(postId) {
    try {
      const { data } = await api.get(`/api/posts/${postId}/progress`)
      return data.completed ? 0 : data.position
    } catch {
      return 0
    }
  }

  /** Start (or resume) an episode. `post` is a post resource from the API. */
  async function play(post) {
    if (current.value?.id === post.id && audio?.src) {
      if (audio.paused) await audio.play().catch(() => {})
      return
    }

    await report() // save where the previous episode stopped
    element().pause()
    current.value = {
      id: post.id,
      title: post.title,
      cover_url: post.cover_url,
      access: post.access,
    }
    error.value = ''
    status.value = 'loading'
    position.value = 0
    duration.value = post.audio_duration || 0

    try {
      const url = await resolveSource(post.id)
      const start = await resumePosition(post.id)
      source.value = url
      const el = element()
      el.src = url
      el.playbackRate = speed.value
      el.currentTime = start
      position.value = start
      await el.play()
      setupMediaSession()
    } catch (e) {
      if (e.name === 'NotAllowedError')
        status.value = 'paused' // the browser wants a tap first
      else fail(e.code || 'network')
    }
  }

  function toggle() {
    const el = element()
    if (!current.value) return
    if (el.paused) el.play().catch(() => {})
    else el.pause()
  }

  function seek(seconds) {
    const el = element()
    el.currentTime = Math.min(Math.max(0, seconds), duration.value || seconds)
    position.value = el.currentTime
  }

  const skip = (delta) => seek(position.value + delta)
  const back15 = () => skip(-SKIP_SECONDS)
  const forward15 = () => skip(SKIP_SECONDS)

  function setSpeed(value) {
    speed.value = value
    if (audio) audio.playbackRate = value
  }

  /** Pause after `minutes`; null turns the timer off. */
  function setSleep(minutes) {
    clearTimeout(sleepTimer)
    sleepEndsAt.value = null
    if (!minutes) return
    sleepEndsAt.value = Date.now() + minutes * 60000
    sleepTimer = setTimeout(() => {
      element().pause()
      sleepEndsAt.value = null
    }, minutes * 60000)
  }

  /** Report the position; the same call keeps the one-device lock alive. */
  async function report() {
    if (!current.value || !audio?.src) return
    try {
      await api.put(`/api/posts/${current.value.id}/progress`, {
        position: Math.floor(audio.currentTime || 0),
        device: deviceId(),
      })
    } catch (e) {
      if (e.response?.status === 409) {
        audio.pause()
        fail('playing_elsewhere')
      }
    }
  }

  function startReporting() {
    stopReporting()
    report()
    reportTimer = setInterval(report, REPORT_EVERY_MS)
  }

  async function stopReporting() {
    clearInterval(reportTimer)
    reportTimer = null
    if (!audio?.paused) return
    await report()
    api.post('/api/playback/release', { device: deviceId() }).catch(() => {})
  }

  /** Unload everything (on logout). */
  function stop() {
    stopReporting()
    setSleep(null)
    if (audio) {
      audio.pause()
      audio.removeAttribute('src')
      audio.load()
    }
    current.value = null
    status.value = 'idle'
    position.value = 0
    duration.value = 0
    source.value = ''
    if ('mediaSession' in navigator) navigator.mediaSession.metadata = null
  }

  // Lock screen / headset controls
  function setupMediaSession() {
    if (!('mediaSession' in navigator)) return
    const session = navigator.mediaSession
    session.setActionHandler('play', () => element().play())
    session.setActionHandler('pause', () => element().pause())
    session.setActionHandler('seekbackward', back15)
    session.setActionHandler('seekforward', forward15)
    session.setActionHandler('seekto', (details) => seek(details.seekTime))
    updateMediaSession()
  }

  function updateMediaSession() {
    if (!('mediaSession' in navigator) || !current.value) return
    navigator.mediaSession.metadata = new MediaMetadata({
      title: current.value.title,
      artist: 'پادکست پریسکوپ',
      artwork: current.value.cover_url ? [{ src: current.value.cover_url, sizes: '512x512' }] : [],
    })
  }

  return {
    current,
    status,
    error,
    position,
    duration,
    speed,
    sleepEndsAt,
    source,
    isPlaying,
    hasTrack,
    play,
    toggle,
    seek,
    back15,
    forward15,
    setSpeed,
    setSleep,
    stop,
    report,
  }
})
