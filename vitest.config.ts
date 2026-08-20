import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';
import { defineConfig } from 'vitest/config';

// Kept separate from vite.config.js: the build config loads the Laravel and
// Tailwind plugins, which assume a running PHP/asset context Vitest does not
// have. This config carries only what the component tests need - React, jsdom,
// and the @/* path alias the app uses.
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': resolve(__dirname, './resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/tests/setup.ts'],
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        css: false,
        restoreMocks: true,
    },
});
