/**
 * Bảng lớp Tailwind tĩnh theo tên màu enum (ProjectStatus / ProjectType).
 * Giữ chuỗi lớp đầy đủ để Tailwind không purge nhầm.
 */
const TONES = {
    slate: { text: 'text-slate-300', soft: 'bg-slate-400/10 text-slate-200 ring-slate-400/20', dot: 'bg-slate-400' },
    sky: { text: 'text-sky-300', soft: 'bg-sky-400/10 text-sky-200 ring-sky-400/20', dot: 'bg-sky-400' },
    amber: { text: 'text-amber-300', soft: 'bg-amber-400/10 text-amber-200 ring-amber-400/20', dot: 'bg-amber-400' },
    emerald: { text: 'text-emerald-300', soft: 'bg-emerald-400/10 text-emerald-200 ring-emerald-400/20', dot: 'bg-emerald-400' },
    rose: { text: 'text-rose-300', soft: 'bg-rose-400/10 text-rose-200 ring-rose-400/20', dot: 'bg-rose-400' },
    violet: { text: 'text-violet-300', soft: 'bg-violet-400/10 text-violet-200 ring-violet-400/20', dot: 'bg-violet-400' },
};

const FALLBACK = TONES.slate;

export function tone(name) {
    return TONES[name] ?? FALLBACK;
}
