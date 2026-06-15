import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Reveal-on-scroll: trả về `target` (gắn vào phần tử) và `shown` (true khi vào
 * viewport lần đầu). Dùng cho hiệu ứng fade/slide nhẹ của các section landing.
 */
export function useInView({ threshold = 0.15, once = true } = {}) {
    const target = ref(null);
    const shown = ref(false);

    let observer = null;

    onMounted(() => {
        if (typeof IntersectionObserver === 'undefined') {
            shown.value = true;
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        shown.value = true;
                        if (once && observer) observer.disconnect();
                    } else if (!once) {
                        shown.value = false;
                    }
                });
            },
            { threshold },
        );

        if (target.value) observer.observe(target.value);
    });

    onBeforeUnmount(() => observer?.disconnect());

    return { target, shown };
}

/**
 * Đếm số từ 0 → `to` khi `active` chuyển true. requestAnimationFrame, ease-out.
 */
export function useCountUp(to, active, { duration = 1400 } = {}) {
    const value = ref(0);
    let raf = null;

    function run() {
        const end = Number(to.value ?? to) || 0;
        const start = performance.now();

        const tick = (now) => {
            const progress = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            value.value = Math.round(end * eased);
            if (progress < 1) raf = requestAnimationFrame(tick);
        };

        cancelAnimationFrame(raf);
        raf = requestAnimationFrame(tick);
    }

    watch(
        () => (typeof active === 'function' ? active() : active.value),
        (on) => {
            if (on) run();
        },
        { immediate: true },
    );

    onBeforeUnmount(() => cancelAnimationFrame(raf));

    return value;
}
