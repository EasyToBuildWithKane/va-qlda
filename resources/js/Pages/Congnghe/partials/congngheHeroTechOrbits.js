/**
 * Nhãn công nghệ trên vòng quỹ đạo hero — đồng bộ với nhóm trong config/congnghe.php (tech.groups).
 * Mỗi item có thể là chuỗi hoặc object { label, color } — color là mã brand của công nghệ,
 * dùng cho chấm phát sáng dẫn đầu badge (nhận diện tech stack).
 * @type {{ duration: number, reverse: boolean, inset: string, offsetDeg: number, tone: string, items: ({label:string,color?:string}|string)[] }[]}
 */
export const HERO_TECH_ORBITS = [
    {
        duration: 52,
        reverse: false,
        inset: '0%',
        offsetDeg: 8,
        tone: 'cyan',
        items: [
            { label: 'Laravel 10', color: '#FF2D20' },
            { label: 'Vue 3', color: '#42B883' },
            { label: 'Docker', color: '#2496ED' },
            { label: 'Python', color: '#3776AB' },
            { label: 'GitHub Actions', color: '#2088FF' },
        ],
    },
    {
        duration: 38,
        reverse: true,
        inset: '14%',
        offsetDeg: -22,
        tone: 'violet',
        items: [
            { label: 'MySQL', color: '#4479A1' },
            { label: 'Tailwind CSS', color: '#38BDF8' },
            { label: 'Redis', color: '#DC382D' },
            { label: 'OpenAI', color: '#10A37F' },
            { label: 'Inertia.js', color: '#9553E9' },
        ],
    },
    {
        duration: 28,
        reverse: false,
        inset: '28%',
        offsetDeg: 14,
        tone: 'brand',
        items: [
            { label: 'PHP 8', color: '#777BB4' },
            { label: 'Vite', color: '#646CFF' },
            { label: 'Pinia', color: '#FFD859' },
            { label: 'CI/CD', color: '#22D3EE' },
            { label: 'Nginx', color: '#009639' },
        ],
    },
];

/** Chuẩn hoá item về { label, color } để template dùng đồng nhất (hỗ trợ cả chuỗi cũ). */
export function normalizeOrbitItem(item) {
    if (typeof item === 'string') return { label: item, color: null };
    return { label: item?.label ?? '', color: item?.color ?? null };
}

export const ORBIT_TONE_CLASS = {
    cyan: 'border-cyan-400/25 bg-cyan-500/10 text-cyan-100/90 shadow-[0_0_18px_rgba(34,211,238,0.12)]',
    violet: 'border-violet-400/25 bg-violet-500/10 text-violet-100/90 shadow-[0_0_18px_rgba(167,139,250,0.12)]',
    brand: 'border-brand-300/30 bg-brand/15 text-brand-100/95 shadow-[0_0_18px_rgba(154,0,54,0.25)]',
    emerald: 'border-emerald-400/25 bg-emerald-500/10 text-emerald-100/90 shadow-[0_0_18px_rgba(52,211,153,0.12)]',
};

/** Gradient conic cho cung sáng quét quanh mỗi vòng — theo tông màu. */
export const ORBIT_SWEEP_GRADIENT = {
    cyan: 'conic-gradient(from var(--cn-angle), transparent 0deg, rgba(34,211,238,0.55) 60deg, rgba(34,211,238,0) 150deg, transparent 360deg)',
    violet: 'conic-gradient(from var(--cn-angle), transparent 0deg, rgba(167,139,250,0.5) 60deg, rgba(167,139,250,0) 150deg, transparent 360deg)',
    brand: 'conic-gradient(from var(--cn-angle), transparent 0deg, rgba(255,77,141,0.5) 60deg, rgba(154,0,54,0) 150deg, transparent 360deg)',
    emerald: 'conic-gradient(from var(--cn-angle), transparent 0deg, rgba(52,211,153,0.5) 60deg, rgba(52,211,153,0) 150deg, transparent 360deg)',
};

/** Màu chấm comet đầu mỗi vòng (đầu cung sáng đang quay). */
export const ORBIT_COMET_COLOR = {
    cyan: 'rgba(34,211,238,0.95)',
    violet: 'rgba(167,139,250,0.95)',
    brand: 'rgba(255,77,141,0.95)',
    emerald: 'rgba(52,211,153,0.95)',
};
