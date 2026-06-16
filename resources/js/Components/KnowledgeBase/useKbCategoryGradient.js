const GRADIENTS = [
    'from-brand/20 via-violet-500/10 to-slate-900/5',
    'from-sky-500/20 via-cyan-400/10 to-slate-900/5',
    'from-emerald-500/15 via-teal-400/10 to-slate-900/5',
    'from-amber-500/15 via-orange-400/10 to-slate-900/5',
    'from-violet-500/20 via-fuchsia-400/10 to-slate-900/5',
    'from-rose-500/15 via-pink-400/10 to-slate-900/5',
    'from-indigo-500/20 via-blue-400/10 to-slate-900/5',
    'from-slate-600/15 via-brand/10 to-slate-900/5',
];

export function kbCategoryGradientClass(seed = '') {
    let h = 0;
    for (const c of seed) h = (h + c.charCodeAt(0)) % GRADIENTS.length;
    return GRADIENTS[h];
}

export function kbCoverImageUrl(article) {
    return article?.cover_image_url || article?.cover_url || null;
}
