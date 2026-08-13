import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    build: {
        reportCompressedSize: false,
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                manualChunks: {
                    // Vue core
                    'vendor-vue': ['vue', '@inertiajs/vue3'],

                    // Rich text editor (heavy ~200KB)
                    'vendor-tiptap': [
                        '@tiptap/vue-3',
                        '@tiptap/starter-kit',
                        '@tiptap/extension-link',
                        '@tiptap/extension-placeholder',
                        '@tiptap/extension-underline',
                    ],

                    // Charts
                    'vendor-chart': ['chart.js', 'vue-chartjs'],

                    // Excel I/O (xlsx-js-style only — avoid duplicate sheetjs)
                    'vendor-excel': ['xlsx-js-style'],

                    // Date picker (FilterDatePicker + date-fns vi locale)
                    'vendor-datepicker': ['@vuepic/vue-datepicker', 'date-fns/locale/vi'],

                    // FullCalendar (project + coaching)
                    'vendor-calendar': [
                        '@fullcalendar/vue3',
                        '@fullcalendar/core',
                        '@fullcalendar/daygrid',
                        '@fullcalendar/timegrid',
                        '@fullcalendar/list',
                        '@fullcalendar/interaction',
                    ],

                    // DOCX preview
                    'vendor-docx': ['docx-preview'],

                    // State management + routing
                    'vendor-utils': ['pinia', 'ziggy-js'],
                },
            },
        },
    },
});
