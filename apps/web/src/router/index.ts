import { defineRouter } from '#q-app';
import {
  createMemoryHistory,
  createRouter,
  createWebHashHistory,
  createWebHistory,
} from 'vue-router';

import routes from './routes';
import { useAuthStore } from '@/stores/auth';

export default defineRouter((/* { store, ssrContext } */) => {
  const createHistory = import.meta.env.QUASAR_SERVER
    ? createMemoryHistory
    : (import.meta.env.QUASAR_VUE_ROUTER_MODE === 'history' ? createWebHistory : createWebHashHistory);

  const Router = createRouter({
    scrollBehavior: () => ({ left: 0, top: 0 }),
    routes,
    history: createHistory(import.meta.env.QUASAR_VUE_ROUTER_BASE)
  });

  Router.beforeEach(async (to) => {
    const auth = useAuthStore();

    const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
    const requiresAdmin = to.matched.some((record) => record.meta.requiresAdmin);
    const guestOnly = to.matched.some((record) => record.meta.guestOnly);

    if ((requiresAuth || guestOnly) && auth.user === null && !auth.loading) {
      await auth.fetchCurrentUser();
    }

    if (requiresAuth && !auth.isAuthenticated) {
      return { path: '/login', query: { redirect: to.fullPath } };
    }

    if (requiresAdmin && !auth.isAdmin) {
      return { path: '/app/dashboard' };
    }

    if (guestOnly && auth.isAuthenticated) {
      return { path: '/app' };
    }

    return true;
  });

  return Router;
});
