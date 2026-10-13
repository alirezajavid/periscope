const routes = [
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [{ path: '', name: 'home', component: () => import('@/pages/IndexPage.vue') }],
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
        path: 'register',
        name: 'register',
        meta: { guestOnly: true },
        component: () => import('@/pages/RegisterPage.vue'),
      },
      {
        path: 'forgot-password',
        name: 'forgot-password',
        meta: { guestOnly: true },
        component: () => import('@/pages/ForgotPasswordPage.vue'),
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
    ],
  },

  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  },
]

export default routes
