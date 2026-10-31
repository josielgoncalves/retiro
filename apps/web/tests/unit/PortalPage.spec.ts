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
            retreat: {
              name: 'Retiro de Primavera',
              start_date: '2027-03-10',
              end_date: '2027-03-12',
              location: 'Vale da Paz',
              cover_image_url: '/api/v1/public/retreats/1/cover',
              pix_key_type: 'email',
              pix_key: 'pix@example.com',
              pix_beneficiary_name: 'Comunidade Retiro',
              receipt_whatsapp: '+55 63 99999-0000',
            },
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
    expect(text).toContain('pix@example.com');
    expect(text).toContain('Copiar');
    expect(text).toContain('Enviar comprovante pelo WhatsApp');
    const cover = wrapper.find('img');
    expect(wrapper.findComponent({ name: 'QImg' }).props('ratio')).toBe(16 / 9);
    expect(wrapper.findComponent({ name: 'QImg' }).props('fit')).toBe('contain');
    expect(cover.attributes('src')).toBe('/api/v1/public/retreats/1/cover');
    expect(cover.attributes('alt')).toBe('Capa do Retiro de Primavera');
    // No internal/administrative fields ever reach the template.
    expect(text).not.toContain('notes');
  });

  it('hides a cover that fails to load without breaking the portal', async () => {
    mockGet.mockImplementation((url: string) => Promise.resolve({
      data: url.includes('/announcements')
        ? { data: [] }
        : {
            data: {
              couple: { partner_one_name: 'Ana', partner_two_name: 'João' },
              retreat: {
                name: 'Retiro 2027', start_date: null, end_date: null, location: null,
                cover_image_url: 'https://retiro.example/cover.jpg', pix_key_type: null,
                pix_key: null, pix_beneficiary_name: null, receipt_whatsapp: null,
              },
              payment_mode: 'flexible', total_amount_cents: 10000, total_paid_cents: 0,
              balance_cents: 10000, percent_paid: 0, next_due_installment: null,
              installments: null, payments: [],
            },
          },
    }));
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const wrapper = mount(withQLayout(PortalPage), { global: globalTestPlugins() });
    await flushPromises();

    await wrapper.findComponent({ name: 'QImg' }).vm.$emit('error', new Event('error'));
    await wrapper.vm.$nextTick();

    expect(wrapper.findComponent({ name: 'QImg' }).exists()).toBe(false);
    expect(wrapper.text()).toContain('Olá, Ana & João!');
    expect(warn).toHaveBeenCalled();
    warn.mockRestore();
  });
});
