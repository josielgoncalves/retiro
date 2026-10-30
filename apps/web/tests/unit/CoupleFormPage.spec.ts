import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { globalTestPlugins, withQLayout } from './test-utils';

const mockPush = vi.fn();
vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>();
  return {
    ...actual,
    useRouter: () => ({ push: mockPush }),
    useRoute: () => ({ params: {} }),
  };
});

const mockPost = vi.fn();
const mockGet = vi.fn();
vi.mock('@/boot/axios', () => ({
  api: {
    post: (...args: unknown[]) => mockPost(...args),
    get: (...args: unknown[]) => mockGet(...args),
    patch: vi.fn(),
  },
}));

import CoupleFormPage from '@/pages/admin/CoupleFormPage.vue';

describe('CoupleFormPage — cadastro de casal', () => {
  beforeEach(() => {
    mockPush.mockReset();
    mockPost.mockReset();
  });

  it('creates a couple and navigates to its detail page', async () => {
    mockPost.mockResolvedValue({ data: { data: { id: 42 } } });

    const wrapper = mount(withQLayout(CoupleFormPage), {
      global: globalTestPlugins(),
      attachTo: document.body,
    });

    const inputs = wrapper.findAll('input');
    await inputs[0]!.setValue('Carlos Mendes');
    await inputs[1]!.setValue('Patrícia Mendes');
    await inputs[2]!.setValue('+55 11 91234-5678');

    await wrapper.find('form').trigger('submit.prevent');
    await flushPromises();

    expect(mockPost).toHaveBeenCalledWith(
      '/couples',
      expect.objectContaining({
        partner_one_name: 'Carlos Mendes',
        partner_two_name: 'Patrícia Mendes',
        phone: '+55 11 91234-5678',
      }),
    );
    expect(mockPush).toHaveBeenCalledWith('/app/couples/42');
  });
});
