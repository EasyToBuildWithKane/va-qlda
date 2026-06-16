/**
 * Nhãn công nghệ trên vòng quỹ đạo hero — đồng bộ với nhóm trong config/congnghe.php (tech.groups).
 * @type {{ duration: number, reverse: boolean, inset: string, offsetDeg: number, tone: string, items: string[] }[]}
 */
export const HERO_TECH_ORBITS = [
    {
        duration: 52,
        reverse: false,
        inset: '0%',
        offsetDeg: 8,
        tone: 'cyan',
        items: ['Laravel 10', 'Vue 3', 'Docker', 'Python', 'GitHub Actions'],
    },
    {
        duration: 38,
        reverse: true,
        inset: '14%',
        offsetDeg: -22,
        tone: 'violet',
        items: ['MySQL', 'Tailwind CSS', 'Redis', 'OpenAI', 'Inertia.js'],
    },
    {
        duration: 28,
        reverse: false,
        inset: '28%',
        offsetDeg: 14,
        tone: 'brand',
        items: ['PHP 8', 'Vite', 'Pinia', 'CI/CD', 'Nginx'],
    },
];

export const ORBIT_TONE_CLASS = {
    cyan: 'border-cyan-400/25 bg-cyan-500/10 text-cyan-100/90 shadow-[0_0_18px_rgba(34,211,238,0.12)]',
    violet: 'border-violet-400/25 bg-violet-500/10 text-violet-100/90 shadow-[0_0_18px_rgba(167,139,250,0.12)]',
    brand: 'border-brand-300/30 bg-brand/15 text-brand-100/95 shadow-[0_0_18px_rgba(154,0,54,0.25)]',
    emerald: 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100/90 shadow-[0_0_18px_rgba(52,211,153,0.12)]',
};
