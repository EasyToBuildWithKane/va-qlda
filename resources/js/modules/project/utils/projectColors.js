// Bảng màu nhãn dùng chung cho dự án — nguồn duy nhất để form, card và lưới
// đồng bộ. Class viết literal đầy đủ để Tailwind JIT nhận diện (không purge).

/** @type {Array<{ key: string, label: string }>} */
export const PROJECT_COLOR_OPTIONS = [
    { key: 'brand', label: 'Thương hiệu' },
    { key: 'rose', label: 'Hồng đậm' },
    { key: 'pink', label: 'Hồng' },
    { key: 'red', label: 'Đỏ' },
    { key: 'orange', label: 'Cam' },
    { key: 'amber', label: 'Hổ phách' },
    { key: 'yellow', label: 'Vàng' },
    { key: 'lime', label: 'Vàng chanh' },
    { key: 'green', label: 'Xanh lá' },
    { key: 'emerald', label: 'Ngọc lục' },
    { key: 'teal', label: 'Xanh mòng két' },
    { key: 'cyan', label: 'Xanh lơ' },
    { key: 'sky', label: 'Xanh trời' },
    { key: 'blue', label: 'Xanh dương' },
    { key: 'indigo', label: 'Chàm' },
    { key: 'violet', label: 'Tím' },
    { key: 'purple', label: 'Tía' },
    { key: 'fuchsia', label: 'Cánh sen' },
    { key: 'slate', label: 'Xám xanh' },
    { key: 'stone', label: 'Xám đá' },
];

/** Danh sách key màu theo thứ tự hiển thị. */
export const PROJECT_COLORS = PROJECT_COLOR_OPTIONS.map((c) => c.key);

/** Map key → class nền dạng swatch/stripe/dot. */
export const PROJECT_COLOR_SWATCH = {
    brand: 'bg-brand',
    rose: 'bg-rose-500',
    pink: 'bg-pink-500',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    amber: 'bg-amber-500',
    yellow: 'bg-yellow-500',
    lime: 'bg-lime-500',
    green: 'bg-green-500',
    emerald: 'bg-emerald-500',
    teal: 'bg-teal-500',
    cyan: 'bg-cyan-500',
    sky: 'bg-sky-500',
    blue: 'bg-blue-500',
    indigo: 'bg-indigo-500',
    violet: 'bg-violet-500',
    purple: 'bg-purple-500',
    fuchsia: 'bg-fuchsia-500',
    slate: 'bg-slate-400',
    stone: 'bg-stone-400',
};

/** Map key → class tag mềm (nền nhạt + chữ đậm) cho chip/nhãn. */
export const PROJECT_COLOR_SOFT = {
    brand: 'bg-brand-50 text-brand',
    rose: 'bg-rose-50 text-rose-700',
    pink: 'bg-pink-50 text-pink-700',
    red: 'bg-red-50 text-red-700',
    orange: 'bg-orange-50 text-orange-700',
    amber: 'bg-amber-50 text-amber-700',
    yellow: 'bg-yellow-50 text-yellow-700',
    lime: 'bg-lime-50 text-lime-700',
    green: 'bg-green-50 text-green-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    teal: 'bg-teal-50 text-teal-700',
    cyan: 'bg-cyan-50 text-cyan-700',
    sky: 'bg-sky-50 text-sky-700',
    blue: 'bg-blue-50 text-blue-700',
    indigo: 'bg-indigo-50 text-indigo-700',
    violet: 'bg-violet-50 text-violet-700',
    purple: 'bg-purple-50 text-purple-700',
    fuchsia: 'bg-fuchsia-50 text-fuchsia-700',
    slate: 'bg-slate-100 text-slate-600',
    stone: 'bg-stone-100 text-stone-600',
};
