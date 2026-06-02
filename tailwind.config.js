/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,ts,vue}',
    ],
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                // Design tokens — VAschools brand maroon (#9A0036)
                brand: {
                    DEFAULT: '#9A0036', // Maroon — Heritage, Authority
                    50: '#fdf2f6',
                    100: '#fad9e4', // sidebar text on the maroon ground
                    200: '#f4b0c8',
                    300: '#ea7da3',
                    400: '#db4e7c',
                    500: '#c12a5b',
                    600: '#9A0036',
                    700: '#810030', // primary-button hover
                    800: '#660026',
                    900: '#4d001d',
                },
                accent: {
                    DEFAULT: '#F6B73C', // Warm Gold — pairs with maroon
                    soft: '#fbd27e',
                },
                success: '#10B981', // Emerald
                warning: '#F59E0B', // Amber
                danger: '#F43F5E',  // Rose
            },
            fontFamily: {
                sans: ['"DM Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
            },
            borderRadius: {
                card: '8px',
                btn: '6px',
                input: '4px',
            },
            boxShadow: {
                'elevation-1': '0 1px 2px 0 rgb(16 30 48 / 0.06)',
                'elevation-2': '0 4px 12px -2px rgb(16 30 48 / 0.10)',
                'elevation-3': '0 12px 32px -8px rgb(16 30 48 / 0.18)',
            },
        },
    },
    plugins: [],
};
