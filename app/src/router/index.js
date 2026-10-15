import { defineRouter } from '#q-app'
import {
  createMemoryHistory,
  createRouter,
  createWebHashHistory,
  createWebHistory,
} from 'vue-router'

import routes from './routes.js'
import { useAuthStore } from '@/stores/useAuthStore'
import { useSettingsStore } from '@/stores/useSettingsStore'
import { shouldAutoShowGuide } from '@/composables/useInstall'

// Pages opened from email links go straight through, without the install guide first.
const SKIP_INSTALL_GUIDE = ['install', 'reset-password', 'email-verified', 'unsubscribed']

/*
 * If not building with SSR mode, you can
 * directly export the Router instantiation;
 *
 * The function below can be async too; either use
 * async/await or return a Promise which resolves
 * with the Router instance.
 */

export default defineRouter(({ store }) => {
  const createHistory = import.meta.env.QUASAR_SERVER
    ? createMemoryHistory
    : import.meta.env.QUASAR_VUE_ROUTER_MODE === 'history'
      ? createWebHistory
      : createWebHashHistory

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,

    // Leave this as is and make changes in quasar.conf.js instead!
    // quasar.conf.js -> build -> vueRouterMode
    // quasar.conf.js -> build -> publicPath
    history: createHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE),
  })

  Router.beforeEach(async (to) => {
    const auth = useAuthStore(store)
    const settings = useSettingsStore(store)
    if (!auth.loaded) await auth.fetchUser()
    if (!settings.loaded) await settings.fetch().catch(() => {})

    // Maintenance mode: everything except the maintenance page itself
    if (settings.data.maintenance && to.name !== 'maintenance') return { name: 'maintenance' }
    if (!settings.data.maintenance && to.name === 'maintenance') return { name: 'home' }

    // Opened in a phone browser (not from the home screen): show how to install the app first, once per visit.
    if (!SKIP_INSTALL_GUIDE.includes(to.name) && shouldAutoShowGuide())
      return { name: 'install', query: { redirect: to.fullPath } }

    if (to.meta.requiresAuth && !auth.isLoggedIn) return { name: 'login' }
    if (to.meta.guestOnly && auth.isLoggedIn) return { name: 'home' }
  })

  return Router
})
