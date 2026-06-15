const TONES = ['brand', 'sky', 'violet', 'teal', 'amber', 'emerald', 'rose', 'cyan'];

const BAR_CLASS = {
    brand: 'bg-brand',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    teal: 'bg-teal-500',
    amber: 'bg-amber-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    cyan: 'bg-cyan-500',
};

const RING_CLASS = {
    brand: 'ring-brand/20',
    sky: 'ring-sky-100',
    violet: 'ring-violet-100',
    teal: 'ring-teal-100',
    amber: 'ring-amber-100',
    emerald: 'ring-emerald-100',
    rose: 'ring-rose-100',
    cyan: 'ring-cyan-100',
};

function hashKey(key) {
    let h = 0;
    const s = String(key ?? '');
    for (let i = 0; i < s.length; i += 1) {
        h = (h << 5) - h + s.charCodeAt(i);
        h |= 0;
    }
    return Math.abs(h);
}

export function skillGroupTone(groupKey) {
    const tone = TONES[hashKey(groupKey) % TONES.length];
    return {
        tone,
        barClass: BAR_CLASS[tone] || BAR_CLASS.brand,
        ringClass: RING_CLASS[tone] || RING_CLASS.brand,
    };
}

export function skillLevelLabel(level) {
    const n = Math.max(1, Math.min(5, Number(level) || 3));
    return `${n}/5`;
}
