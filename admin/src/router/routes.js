const routes = [
  {
    path: '/',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: { name: 'posts' } },
      { path: 'posts', name: 'posts', component: () => import('@/pages/PostsPage.vue') },
      { path: 'posts/new', name: 'post-new', component: () => import('@/pages/PostEditPage.vue') },
      { path: 'posts/:id', name: 'post-edit', component: () => import('@/pages/PostEditPage.vue') },
      { path: 'users', name: 'users', component: () => import('@/pages/UsersPage.vue') },
      { path: 'users/:id', name: 'user', component: () => import('@/pages/UserDetailPage.vue') },
      { path: 'payments', name: 'payments', component: () => import('@/pages/PaymentsPage.vue') },
    ],
  },
  {
    path: '/login',
    name: 'login',
    meta: { guestOnly: true },
    component: () => import('@/pages/LoginPage.vue'),
  },
  { path: '/:catchAll(.*)*', component: () => import('@/pages/ErrorNotFound.vue') },
]

export default routes
