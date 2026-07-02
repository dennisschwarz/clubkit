import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

/**
 * Vite configuration for ClubKit.
 *
 * All module JS files are registered as entry points so Vite provides
 * fingerprinting, tree-shaking, source maps and minification.
 *
 * All modules have been migrated to resources/js/modules/ and use @vite().
 * Legacy files in public/js/modules/ have been permanently removed (S7–S15).
 */
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/modules/appearance-modal.js',
                'resources/js/modules/custom-fields-modal.js',
                'resources/js/modules/events-detail.js',
                'resources/js/modules/events-modal.js',
                'resources/js/modules/import-preview.js',
                'resources/js/modules/management-modal.js',
                'resources/js/modules/member-teams.js',
                'resources/js/modules/members-modal.js',
                'resources/js/modules/roles-modal.js',
                'resources/js/modules/teams-modal.js',
                'resources/js/modules/treasury-modal.js',
                'resources/js/modules/users-modal.js',
                'resources/js/modules/youth-club-mode.js',
            ],
            refresh: [
                'resources/views/**',
                'modules/**/Resources/Views/**',
            ],
        }),
    ],
});
