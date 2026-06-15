import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * true khi người dùng bật "giảm chuyển động" trong hệ điều hành. Mọi hiệu ứng
 * nặng (particle, parallax, tilt) phải tôn trọng cờ này.
 */
export function usePrefersReducedMotion() {
    const reduced = ref(false);
    let mql = null;

    function sync() {
        reduced.value = mql ? mql.matches : false;
    }

    onMounted(() => {
        if (typeof window === 'undefined' || !window.matchMedia) return;
        mql = window.matchMedia('(prefers-reduced-motion: reduce)');
        sync();
        mql.addEventListener?.('change', sync);
    });

    onBeforeUnmount(() => mql?.removeEventListener?.('change', sync));

    return reduced;
}

export function prefersReducedMotionNow() {
    return typeof window !== 'undefined'
        && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches === true;
}

export function hasFinePointer() {
    return typeof window !== 'undefined'
        && window.matchMedia?.('(hover: hover) and (pointer: fine)').matches === true;
}

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

        if (prefersReducedMotionNow()) {
            value.value = end;
            return;
        }

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

/**
 * 3D tilt theo con trỏ. Trả về `el` để gắn vào card và `style` (computed-like
 * reactive object) áp vào phần tử. Tự tắt khi reduced-motion / không có chuột.
 */
export function useTilt({ max = 8, scale = 1.02, glare = true } = {}) {
    const el = ref(null);
    const style = ref({ transform: '', '--cn-mx': '50%', '--cn-my': '50%' });
    let enabled = false;

    function onMove(e) {
        if (!enabled || !el.value) return;
        const rect = el.value.getBoundingClientRect();
        const px = (e.clientX - rect.left) / rect.width;
        const py = (e.clientY - rect.top) / rect.height;
        const rx = (0.5 - py) * max * 2;
        const ry = (px - 0.5) * max * 2;
        style.value = {
            transform: `perspective(900px) rotateX(${rx.toFixed(2)}deg) rotateY(${ry.toFixed(2)}deg) scale(${scale})`,
            '--cn-mx': `${(px * 100).toFixed(1)}%`,
            '--cn-my': `${(py * 100).toFixed(1)}%`,
        };
    }

    function reset() {
        style.value = { transform: '', '--cn-mx': '50%', '--cn-my': '50%' };
    }

    onMounted(() => {
        enabled = !prefersReducedMotionNow() && hasFinePointer();
        if (!enabled || !el.value) return;
        el.value.addEventListener('mousemove', onMove);
        el.value.addEventListener('mouseleave', reset);
    });

    onBeforeUnmount(() => {
        if (!el.value) return;
        el.value.removeEventListener('mousemove', onMove);
        el.value.removeEventListener('mouseleave', reset);
    });

    return { el, style, glare };
}

/**
 * Nút "nam châm" — dịch nhẹ về phía con trỏ khi hover.
 */
export function useMagnetic({ strength = 0.35 } = {}) {
    const el = ref(null);
    const style = ref({ transform: '' });
    let enabled = false;

    function onMove(e) {
        if (!enabled || !el.value) return;
        const rect = el.value.getBoundingClientRect();
        const x = (e.clientX - rect.left - rect.width / 2) * strength;
        const y = (e.clientY - rect.top - rect.height / 2) * strength;
        style.value = { transform: `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px)` };
    }

    function reset() {
        style.value = { transform: 'translate(0,0)' };
    }

    onMounted(() => {
        enabled = !prefersReducedMotionNow() && hasFinePointer();
        if (!enabled || !el.value) return;
        el.value.addEventListener('mousemove', onMove);
        el.value.addEventListener('mouseleave', reset);
    });

    onBeforeUnmount(() => {
        if (!el.value) return;
        el.value.removeEventListener('mousemove', onMove);
        el.value.removeEventListener('mouseleave', reset);
    });

    return { el, style };
}

/**
 * Tiến độ cuộn trang (0 → 1) cho thanh progress trên cùng.
 */
export function useScrollProgress() {
    const progress = ref(0);
    let raf = null;

    function onScroll() {
        if (raf) return;
        raf = requestAnimationFrame(() => {
            const h = document.documentElement;
            const max = h.scrollHeight - h.clientHeight;
            progress.value = max > 0 ? Math.min(1, h.scrollTop / max) : 0;
            raf = null;
        });
    }

    onMounted(() => {
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
    });

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
        if (raf) cancelAnimationFrame(raf);
    });

    return progress;
}
