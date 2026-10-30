// jsdom doesn't implement ResizeObserver, which Quasar's QLayout/QPage use
// for size calculations; a no-op stub is enough for component tests.
class ResizeObserverStub {
  observe() {}
  unobserve() {}
  disconnect() {}
}

// @ts-expect-error -- jsdom has no ResizeObserver
globalThis.ResizeObserver ??= ResizeObserverStub;
