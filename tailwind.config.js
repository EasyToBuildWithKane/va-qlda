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
            // ── Landing /congnghe — premium AI/SaaS motion tokens (prefix cn-) ──
            keyframes: {
                'cn-aurora': {
                    '0%,100%': { transform: 'translate3d(0,0,0) scale(1)', opacity: '0.55' },
                    '33%': { transform: 'translate3d(6%,-4%,0) scale(1.15)', opacity: '0.8' },
                    '66%': { transform: 'translate3d(-5%,5%,0) scale(0.95)', opacity: '0.5' },
                },
                'cn-float': {
                    '0%,100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-12px)' },
                },
                'cn-float-x': {
                    '0%,100%': { transform: 'translateX(0)' },
                    '50%': { transform: 'translateX(10px)' },
                },
                'cn-glow': {
                    '0%,100%': { opacity: '0.4' },
                    '50%': { opacity: '1' },
                },
                'cn-spin-slow': {
                    from: { transform: 'rotate(0deg)' },
                    to: { transform: 'rotate(360deg)' },
                },
                'cn-shimmer': {
                    '0%': { backgroundPosition: '200% 0' },
                    '100%': { backgroundPosition: '-200% 0' },
                },
                'cn-grid-pan': {
                    '0%': { backgroundPosition: '0 0' },
                    '100%': { backgroundPosition: '40px 40px' },
                },
                'cn-rise': {
                    from: { opacity: '0', transform: 'translateY(24px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'cn-ping-ring': {
                    '0%': { transform: 'scale(0.9)', opacity: '0.6' },
                    '80%,100%': { transform: 'scale(2.2)', opacity: '0' },
                },
                'cn-assistant-bob': {
                    '0%,100%': { transform: 'translateY(0) rotate(-1deg)' },
                    '25%': { transform: 'translateY(-6px) rotate(1deg)' },
                    '50%': { transform: 'translateY(-10px) rotate(-0.5deg)' },
                    '75%': { transform: 'translateY(-4px) rotate(1.5deg)' },
                },
                // ── upgrade: hiệu ứng AI/tech đan xen ──
                'cn-scan-line': {
                    '0%': { transform: 'translateY(-120%)', opacity: '0' },
                    '10%,90%': { opacity: '1' },
                    '100%': { transform: 'translateY(220%)', opacity: '0' },
                },
                'cn-data-stream': {
                    '0%': { backgroundPosition: '0 0' },
                    '100%': { backgroundPosition: '0 -200%' },
                },
                'cn-text-shimmer': {
                    '0%': { backgroundPosition: '200% center' },
                    '100%': { backgroundPosition: '-200% center' },
                },
                'cn-border-rotate': {
                    to: { '--cn-angle': '360deg' },
                },
                'cn-hologram': {
                    '0%,100%': { opacity: '1', transform: 'translateX(0)' },
                    '92%': { opacity: '1', transform: 'translateX(0)' },
                    '94%': { opacity: '0.72', transform: 'translateX(0.6px)' },
                    '96%': { opacity: '0.92', transform: 'translateX(-0.6px)' },
                    '98%': { opacity: '0.8', transform: 'translateX(0.3px)' },
                },
                'cn-pulse-grid': {
                    '0%,100%': { opacity: '0.18' },
                    '50%': { opacity: '0.5' },
                },
                'cn-reveal-up': {
                    from: { opacity: '0', transform: 'translateY(28px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                'cn-reveal-left': {
                    from: { opacity: '0', transform: 'translateX(-28px)' },
                    to: { opacity: '1', transform: 'translateX(0)' },
                },
                'cn-reveal-right': {
                    from: { opacity: '0', transform: 'translateX(28px)' },
                    to: { opacity: '1', transform: 'translateX(0)' },
                },
                'cn-ripple': {
                    from: { transform: 'scale(0)', opacity: '0.35' },
                    to: { transform: 'scale(4)', opacity: '0' },
                },
                'cn-caret-blink': {
                    '0%,49%': { opacity: '1' },
                    '50%,100%': { opacity: '0' },
                },
                'cn-ticker-x': {
                    from: { transform: 'translateX(0)' },
                    to: { transform: 'translateX(-50%)' },
                },
            },
            animation: {
                'cn-aurora': 'cn-aurora 18s ease-in-out infinite',
                'cn-aurora-slow': 'cn-aurora 26s ease-in-out infinite',
                'cn-float': 'cn-float 6s ease-in-out infinite',
                'cn-float-x': 'cn-float-x 7s ease-in-out infinite',
                'cn-glow': 'cn-glow 4s ease-in-out infinite',
                'cn-spin-slow': 'cn-spin-slow 28s linear infinite',
                'cn-shimmer': 'cn-shimmer 6s linear infinite',
                'cn-grid-pan': 'cn-grid-pan 8s linear infinite',
                'cn-ping-ring': 'cn-ping-ring 3s ease-out infinite',
                'cn-assistant-bob': 'cn-assistant-bob 4.5s ease-in-out infinite',
                // ── upgrade: hiệu ứng AI/tech đan xen ──
                'cn-scan-line': 'cn-scan-line 2.6s ease-in-out infinite',
                'cn-data-stream': 'cn-data-stream 12s linear infinite',
                'cn-text-shimmer': 'cn-text-shimmer 7s linear infinite',
                'cn-border-rotate': 'cn-border-rotate 6s linear infinite',
                'cn-hologram': 'cn-hologram 5s ease-in-out infinite',
                'cn-pulse-grid': 'cn-pulse-grid 4.5s ease-in-out infinite',
                'cn-reveal-up': 'cn-reveal-up 0.7s cubic-bezier(0.22,1,0.36,1) both',
                'cn-reveal-left': 'cn-reveal-left 0.7s cubic-bezier(0.22,1,0.36,1) both',
                'cn-reveal-right': 'cn-reveal-right 0.7s cubic-bezier(0.22,1,0.36,1) both',
                'cn-ripple': 'cn-ripple 0.65s ease-out forwards',
                'cn-caret-blink': 'cn-caret-blink 1s step-end infinite',
            },
        },
    },
    plugins: [],
};
