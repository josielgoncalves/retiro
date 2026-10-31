import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SystemFooter from '@/components/SystemFooter.vue';

describe('SystemFooter', () => {
  it('shows the developer credit with a safe external link', () => {
    const wrapper = mount(SystemFooter);
    const link = wrapper.get('a');

    expect(wrapper.text()).toContain('Sistema desenvolvido por: LumenCode');
    expect(link.attributes('href')).toBe('https://lumencode.com.br');
    expect(link.attributes('target')).toBe('_blank');
    expect(link.attributes('rel')).toBe('noopener noreferrer');
  });
});
