import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { globalTestPlugins } from './test-utils';

const mockPush = vi.fn();
vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>();
  return {
    ...actual,
    useRouter: () => ({ push: mockPush }),
    useRoute: () => ({ query: {} }),
  };
});

const mockPost = vi.fn();
vi.mock('@/boot/axios', () => ({
  api: { post: (...args: unknown[]) => mockPost(...args) },
  fetchCsrfCookie: vi.fn().mockResolvedValue(undefined),
}));

import LoginPage from '@/pages/admin/LoginPage.vue';
import { useAuthStore } from '@/stores/auth';

describe('LoginPage', () => {
  beforeEach(() => {
    mockPush.mockReset();
    mockPost.mockReset();
  });

  function mountPage() {
    return mount(LoginPage, {
      global: globalTestPlugins(),
      attachTo: document.body,
    });
  }

  it('submits credentials and redirects to the dashboard on success', async () => {
    mockPost.mockResolvedValue({
      data: { data: { id: 1, name: 'Administrador', email: 'admin@example.com', role: 'admin', active: true } },
    });

    const wrapper = mountPage();

    await wrapper.find('input[type=email]').setValue('admin@example.com');
    await wrapper.find('input[type=password]').setValue('change-me-please');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();

    expect(mockPost).toHaveBeenCalledWith('/auth/login', { email: 'admin@example.com', password: 'change-me-please' });
    expect(mockPush).toHaveBeenCalledWith('/app/dashboard');

    const auth = useAuthStore();
    expect(auth.user?.email).toBe('admin@example.com');
  });

  it('shows an error message and does not redirect on invalid credentials', async () => {
    mockPost.mockRejectedValue({ response: { status: 422 } });

    const wrapper = mountPage();

    await wrapper.find('input[type=email]').setValue('admin@example.com');
    await wrapper.find('input[type=password]').setValue('wrong-password');
    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();

    expect(mockPush).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain('inválidos');
  });
});

function flushPromises() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}
