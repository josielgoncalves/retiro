import { defineStore } from 'pinia';
import { api, fetchCsrfCookie } from '@/boot/axios';

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'operator';
  active: boolean;
}

interface State {
  user: AuthUser | null;
  loading: boolean;
}

export const useAuthStore = defineStore('auth', {
  state: (): State => ({
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => state.user !== null,
    isAdmin: (state) => state.user?.role === 'admin',
  },

  actions: {
    async login(email: string, password: string): Promise<void> {
      await fetchCsrfCookie();
      const response = await api.post('/auth/login', { email, password });
      this.user = response.data.data as AuthUser;
    },

    async logout(): Promise<void> {
      try {
        await api.post('/auth/logout');
      } finally {
        this.user = null;
      }
    },

    async fetchCurrentUser(): Promise<boolean> {
      this.loading = true;
      try {
        const response = await api.get('/auth/me');
        this.user = response.data.data as AuthUser;
        return true;
      } catch {
        this.user = null;
        return false;
      } finally {
        this.loading = false;
      }
    },
  },
});
