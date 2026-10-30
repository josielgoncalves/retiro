import type { RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    component: () => import('@/pages/admin/LoginPage.vue'),
    meta: { guestOnly: true },
  },

  {
    path: '/c/:token',
    component: () => import('@/layouts/PortalLayout.vue'),
    children: [
      { path: '', component: () => import('@/pages/portal/PortalPage.vue') },
    ],
  },

  {
    path: '/app',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/app/dashboard' },
      { path: 'dashboard', component: () => import('@/pages/admin/DashboardPage.vue') },

      { path: 'retreats', component: () => import('@/pages/admin/RetreatsListPage.vue') },
      { path: 'retreats/new', component: () => import('@/pages/admin/RetreatFormPage.vue') },
      { path: 'retreats/:id', component: () => import('@/pages/admin/RetreatFormPage.vue') },

      { path: 'couples', component: () => import('@/pages/admin/CouplesListPage.vue') },
      { path: 'couples/new', component: () => import('@/pages/admin/CoupleFormPage.vue') },
      { path: 'couples/:id', component: () => import('@/pages/admin/CoupleFormPage.vue') },

      { path: 'enrollments/new', component: () => import('@/pages/admin/EnrollmentFormPage.vue') },
      { path: 'enrollments/:id', component: () => import('@/pages/admin/EnrollmentDetailPage.vue') },

      { path: 'announcements', component: () => import('@/pages/admin/AnnouncementsPage.vue') },
      { path: 'reports', component: () => import('@/pages/admin/ReportsPage.vue') },
      { path: 'users', component: () => import('@/pages/admin/UsersPage.vue'), meta: { requiresAdmin: true } },
    ],
  },

  { path: '/', redirect: '/app' },

  {
    path: '/:catchAll(.*)*',
    component: () => import('@/pages/ErrorNotFound.vue'),
  },
];

export default routes;
