/**
 * true khi người dùng bật "giảm chuyển động" trong hệ điều hành. Mọi hiệu ứng
 * chuyển động (float, transition) trong module onboarding phải tôn trọng cờ
 * này. Bản sao thuần (không phụ thuộc Vue) của hàm cùng tên trong
 * resources/js/Pages/Congnghe/partials/motion.js — giữ ranh giới module rõ
 * ràng (Pages/ dành riêng cho từng trang, modules/ dùng chung).
 */
export function prefersReducedMotionNow() {
    return typeof window !== 'undefined'
        && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}
