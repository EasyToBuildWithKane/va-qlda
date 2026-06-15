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

                    // Excel I/O
                    'vendor-excel': ['xlsx', 'xlsx-js-style'],

                    // Gantt (heavy, used only on Gantt view)
                    'vendor-gantt': ['frappe-gantt'],

                    // WebGL (chỉ dùng ở landing /congnghe)
                    'vendor-ogl': ['ogl'],

                    // State management + routing
                    'vendor-utils': ['pinia', 'ziggy-js'],
                },
            },
        },
    },
});
