import { Quasar } from 'quasar';
import * as quasarComponents from 'quasar';
import { createTestingPinia } from '@pinia/testing';
import { vi } from 'vitest';
import { defineComponent, h } from 'vue';

/** Wraps a QPage-based page component in the QLayout/QPageContainer it
 * needs at runtime (normally supplied by AdminLayout/PortalLayout). */
export function withQLayout(component: unknown) {
  return defineComponent({
    render: () =>
      h(quasarComponents.QLayout, null, {
        default: () => h(quasarComponents.QPageContainer, null, { default: () => h(component as never) }),
      }),
  });
}

export function globalTestPlugins(initialState?: Record<string, unknown>) {
  return {
    plugins: [
      [Quasar, { components: quasarComponents, plugins: { Notify: quasarComponents.Notify, Dialog: quasarComponents.Dialog } }],
      createTestingPinia({ stubActions: false, initialState }),
    ],
  };
}

export { vi };
