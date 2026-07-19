/// <reference types="vitest/config" />
import { defineConfig } from 'vitest/config';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Vitest config lives here too — Vitest reads vite.config.js, so the
    // bundler and the test runner share one source of truth.
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        setupFiles: ['tests/js/setup.js'],
        globals: false,
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html', 'lcov'],
            reportsDirectory: 'tests/js-coverage',
            include: ['resources/js/**/*.js'],
            exclude: ['resources/js/bootstrap.js', 'resources/js/app.js'],
        },
    },
});
