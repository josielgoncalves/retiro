import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { globalTestPlugins, withQLayout } from './test-utils';

vi.mock('vue-router', async (importOriginal) => {
  const actual = await importOriginal<typeof import('vue-router')>();
  return { ...actual, useRoute: () => ({ params: { token: 'test-token' } }) };
});

const mockGet = vi.fn();
vi.mock('axios', () => ({
  default: { get: (...args: unknown[]) => mockGet(...args) },
}));

import PortalPage from '@/pages/portal/PortalPage.vue';

describe('PortalPage', () => {
  beforeEach(() => {
    mockGet.mockReset();
  });

  it('shows the invalid-link screen when the token is revoked/expired/unknown', async () => {
    mockGet.mockRejectedValue({ response: { status: 404 } });

    const wrapper = mount(withQLayout(PortalPage), { global: globalTestPlugins() });
    await flushPromises();

    expect(wrapper.text()).toContain('Link indisponível');
    expect(wrapper.text()).not.toContain('Olá');
  });

  it('renders the enrollment summary and published avisos for a valid token', async () => {
    mockGet.mockImplementation((url: string) => {
      if (url.includes('/announcements')) {
        return Promise.resolve({
          data: { data: [{ title: 'Bem-vindos!', content: 'Tragam roupas confortáveis.', published_at: '2027-01-01T00:00:00Z' }] },
        });
      }

      return Promise.resolve({
        data: {
          data: {
            couple: { partner_one_name: 'Carlos', partner_two_name: 'Patrícia' },
            retreat: { name: 'Retiro de Primavera', start_date: '2027-03-10', end_date: '2027-03-12', location: 'Vale da Paz' },
            payment_mode: 'flexible',
            total_amount_cents: 50000,
            total_paid_cents: 20000,
            balance_cents: 30000,
            percent_paid: 40,
            next_due_installment: null,
            installments: null,
            payments: [{ amount_cents: 20000, paid_at: '2027-01-05T00:00:00Z', payment_method: 'pix' }],
          },
        },
      });
    });

    const wrapper = mount(withQLayout(PortalPage), { global: globalTestPlugins() });
    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain('Carlos');
    expect(text).toContain('Patrícia');
    expect(text).toContain('Retiro de Primavera');
    expect(text).toContain('Bem-vindos!');
    expect(text).toContain('Tragam roupas confortáveis.');
    // No internal/administrative fields ever reach the template.
    expect(text).not.toContain('notes');
  });
});
