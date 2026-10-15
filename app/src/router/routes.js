const routes = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', name: 'home', component: () => import('@/pages/HomePage.vue') },
      { path: 'search', name: 'search', component: () => import('@/pages/SearchPage.vue') },
      { path: 'posts/:id', name: 'post', component: () => import('@/pages/PostPage.vue') },
      { path: 'ask', name: 'ask', component: () => import('@/pages/AskPage.vue') },
      { path: 'account', name: 'account', component: () => import('@/pages/AccountPage.vue') },
      {
        path: 'account/password',
        name: 'change-password',
        component: () => import('@/pages/ChangePasswordPage.vue'),
      },
      { path: 'payments', name: 'payments', component: () => import('@/pages/PaymentsPage.vue') },
      { path: 'support', name: 'support', component: () => import('@/pages/SupportPage.vue') },
    ],
  },
  {
    // Full-screen flows without the tab bar
    path: '/',
    component: () => import('@/layouts/FlatLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: 'plans', name: 'plans', component: () => import('@/pages/PlansPage.vue') },
      { path: 'payment', name: 'payment', component: () => import('@/pages/PaymentPage.vue') },
    ],
  },
  {
    path: '/maintenance',
    name: 'maintenance',
    component: () => import('@/pages/MaintenancePage.vue'),
  },
  {
    path: '/player',
    name: 'player',
    meta: { requiresAuth: true },
    component: () => import('@/pages/PlayerPage.vue'),
  },

  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    children: [
      {
        path: 'login',
        name: 'login',
        meta: { guestOnly: true },
        component: () => import('@/pages/LoginPage.vue'),
      },
      {
        path: 'login/phone',
        name: 'login-phone',
        meta: { guestOnly: true },
        component: () => import('@/pages/LoginPhonePage.vue'),
      },
      {
        path: 'register',
        name: 'register',
        meta: { guestOnly: true },
        component: () => import('@/pages/RegisterPage.vue'),
      },
      {
        path: 'verify-email',
        name: 'verify-email',
        meta: { requiresAuth: true },
        component: () => import('@/pages/VerifyEmailPage.vue'),
      },
      {
        path: 'forgot-password',
        name: 'forgot-password',
        meta: { guestOnly: true },
        component: () => import('@/pages/ForgotPasswordPage.vue'),
      },
      {
        path: 'reset-link-sent',
        name: 'reset-link-sent',
        component: () => import('@/pages/ResetLinkSentPage.vue'),
      },
      {
        path: 'reset-password',
        name: 'reset-password',
        component: () => import('@/pages/ResetPasswordPage.vue'),
      },
      {
        path: 'email-verified',
        name: 'email-verified',
        component: () => import('@/pages/EmailVerifiedPage.vue'),
      },
      {
        path: 'unsubscribed',
        name: 'unsubscribed',
        component: () => import('@/pages/UnsubscribedPage.vue'),
      },
      {
        path: 'terms',
        name: 'terms',
        meta: { setting: 'page_terms', titleKey: 'account.terms' },
        component: () => import('@/pages/StaticPage.vue'),
      },
      {
        path: 'about',
        name: 'about',
        meta: { setting: 'page_about', titleKey: 'account.about' },
        component: () => import('@/pages/StaticPage.vue'),
      },
      {
        path: 'install',
        name: 'install',
        component: () => import('@/pages/InstallPage.vue'),
      },
    ],
  },

  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  },
]

export default routes
