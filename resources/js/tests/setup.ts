import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';

// Unmount anything a test rendered, so one test's DOM cannot bleed into the next.
afterEach(() => {
    cleanup();
});

// jsdom implements neither matchMedia nor ResizeObserver, and the sidebar's
// mobile hook and several Radix primitives call them. These stubs are plain
// functions, not vi.fn() - the config's restoreMocks would reset a vi.fn()
// before each test and leave matchMedia() returning undefined mid-suite.
window.matchMedia = (query: string): MediaQueryList =>
    ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
    }) as unknown as MediaQueryList;

window.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
};
