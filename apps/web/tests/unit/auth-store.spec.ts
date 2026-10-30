import { describe, it, expect, vi, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

const mockPost = vi.fn();
const mockGet = vi.fn();
const mockFetchCsrf = vi.fn();

vi.mock('@/boot/axios', () => ({
  api: { post: (...args: unknown[]) => mockPost(...args), get: (...args: unknown[]) => mockGet(...args) },
  fetchCsrfCookie: () => mockFetchCsrf(),
}));

import { useAuthStore } from '@/stores/auth';

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    mockPost.mockReset();
    mockGet.mockReset();
    mockFetchCsrf.mockReset();
  });

  it('login fetches the CSRF cookie then authenticates and stores the user', async () => {
    mockFetchCsrf.mockResolvedValue(undefined);
    mockPost.mockResolvedValue({
      data: { data: { id: 1, name: 'Administrador', email: 'admin@example.com', role: 'admin', active: true } },
    });

    const auth = useAuthStore();
    await auth.login('admin@example.com', 'secret123');

    expect(mockFetchCsrf).toHaveBeenCalledOnce();
    expect(mockPost).toHaveBeenCalledWith('/auth/login', { email: 'admin@example.com', password: 'secret123' });
    expect(auth.isAuthenticated).toBe(true);
    expect(auth.user?.email).toBe('admin@example.com');
  });

  it('login failure leaves the store unauthenticated and propagates the error', async () => {
    mockFetchCsrf.mockResolvedValue(undefined);
    mockPost.mockRejectedValue(new Error('invalid credentials'));

    const auth = useAuthStore();
    await expect(auth.login('admin@example.com', 'wrong')).rejects.toThrow();
    expect(auth.isAuthenticated).toBe(false);
  });

  it('logout clears the user even if the API call fails', async () => {
    const auth = useAuthStore();
    auth.user = { id: 1, name: 'Administrador', email: 'admin@example.com', role: 'admin', active: true };

    mockPost.mockRejectedValue(new Error('network error'));

    await expect(auth.logout()).rejects.toThrow();
    expect(auth.user).toBeNull();
  });

  it('isAdmin getter reflects the role', () => {
    const auth = useAuthStore();
    auth.user = { id: 1, name: 'Op', email: 'op@example.com', role: 'operator', active: true };
    expect(auth.isAdmin).toBe(false);

    auth.user.role = 'admin';
    expect(auth.isAdmin).toBe(true);
  });
});
