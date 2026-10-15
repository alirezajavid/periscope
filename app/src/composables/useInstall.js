import { ref } from 'vue'

// Chrome/Edge/Samsung fire `beforeinstallprompt` once, early, before any page mounts.
// Keep it at module level so the install page can show the browser's own install dialog later.
const deferredPrompt = ref(null)
const installed = ref(false)

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault() // we show our own guide page instead of the mini-infobar
    deferredPrompt.value = event
  })
  window.addEventListener('appinstalled', () => {
    installed.value = true
    deferredPrompt.value = null
  })
}

const SHOWN_KEY = 'periscope.installGuideShown'

/** Running from the home screen (manifest asks for fullscreen, falls back to standalone/minimal-ui). */
export function isStandalone() {
  if (typeof window === 'undefined') return true
  if (window.navigator.standalone) return true // iOS
  return ['standalone', 'fullscreen', 'minimal-ui'].some(
    (mode) => window.matchMedia(`(display-mode: ${mode})`).matches,
  )
}

/** 'ios' | 'android' | 'desktop' */
export function platform() {
  const ua = navigator.userAgent
  // iPadOS reports itself as a Mac; touch support tells them apart.
  if (/iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1))
    return 'ios'
  if (/Android/.test(ua)) return 'android'
  return 'desktop'
}

/** Safari is the reliable way to add to the home screen on iOS; other iOS browsers may lack it. */
export function isIosSafari() {
  return platform() === 'ios' && !/CriOS|FxiOS|EdgiOS|OPiOS|YaBrowser/.test(navigator.userAgent)
}

/**
 * Show the guide automatically once per browser session on phones opened in the browser.
 * Marks it as shown, so "continue in browser" doesn't bring it back until the next visit.
 */
export function shouldAutoShowGuide() {
  if (isStandalone() || platform() === 'desktop') return false
  try {
    if (sessionStorage.getItem(SHOWN_KEY)) return false
    sessionStorage.setItem(SHOWN_KEY, '1')
  } catch {
    return false
  }
  return true
}

export function useInstall() {
  /** Opens the browser's install dialog (Android/desktop Chromium). Resolves true if accepted. */
  async function promptInstall() {
    const event = deferredPrompt.value
    if (!event) return false
    deferredPrompt.value = null // a prompt can be used only once
    event.prompt()
    const { outcome } = await event.userChoice
    if (outcome === 'accepted') installed.value = true
    return outcome === 'accepted'
  }

  return { deferredPrompt, installed, promptInstall }
}
