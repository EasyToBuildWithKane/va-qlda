import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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

/* ------------------------------------------------------------------------- *
 * Vòng lặp & listener DÙNG CHUNG (ref-counted)                              *
 * Nhiều hiệu ứng cùng chia 1 requestAnimationFrame / 1 mousemove / 1 scroll *
 * thay vì mỗi composable tự add listener — guardrail hiệu năng cốt lõi.     *
 * ------------------------------------------------------------------------- */

// --- 1 rAF dùng chung (trình duyệt tự pause khi tab ẩn) ---
const _rafSubs = new Set();
let _rafId = null;
function _rafLoop(now) {
    _rafSubs.forEach((cb) => {
        try {
            cb(now);
        } catch {
            /* một consumer lỗi không được làm sập cả vòng lặp */
        }
    });
    _rafId = _rafSubs.size ? requestAnimationFrame(_rafLoop) : null;
}

export function onSharedRaf(cb) {
    onMounted(() => {
        _rafSubs.add(cb);
        if (_rafId === null) _rafId = requestAnimationFrame(_rafLoop);
    });
    onBeforeUnmount(() => {
        _rafSubs.delete(cb);
        if (_rafSubs.size === 0 && _rafId !== null) {
            cancelAnimationFrame(_rafId);
            _rafId = null;
        }
    });
}

// --- 1 mousemove dùng chung; vị trí con trỏ chuẩn hoá (-0.5 → 0.5) ---
const _pointer = { x: 0, y: 0, nx: 0, ny: 0 };
const _pointerSubs = new Set();
function _onSharedMove(e) {
    const w = window.innerWidth || 1;
    const h = window.innerHeight || 1;
    _pointer.x = e.clientX;
    _pointer.y = e.clientY;
    _pointer.nx = e.clientX / w - 0.5;
    _pointer.ny = e.clientY / h - 0.5;
    _pointerSubs.forEach((cb) => cb(_pointer));
}

export function onSharedPointer(cb) {
    onMounted(() => {
        if (typeof window === 'undefined') return;
        if (_pointerSubs.size === 0) {
            window.addEventListener('mousemove', _onSharedMove, { passive: true });
        }
        _pointerSubs.add(cb);
    });
    onBeforeUnmount(() => {
        _pointerSubs.delete(cb);
        if (_pointerSubs.size === 0) {
            window.removeEventListener('mousemove', _onSharedMove);
        }
    });
}

export function sharedPointer() {
    return _pointer;
}

// --- 1 scroll dùng chung (throttle bằng rAF) ---
const _scroll = { y: 0, max: 0, progress: 0 };
const _scrollSubs = new Set();
let _scrollRaf = null;
function _computeScroll() {
    const h = document.documentElement;
    _scroll.y = h.scrollTop;
    _scroll.max = h.scrollHeight - h.clientHeight;
    _scroll.progress = _scroll.max > 0 ? Math.min(1, _scroll.y / _scroll.max) : 0;
    _scrollSubs.forEach((cb) => cb(_scroll));
}
function _onSharedScroll() {
    if (_scrollRaf) return;
    _scrollRaf = requestAnimationFrame(() => {
        _computeScroll();
        _scrollRaf = null;
    });
}

export function onSharedScroll(cb) {
    onMounted(() => {
        if (typeof window === 'undefined') return;
        if (_scrollSubs.size === 0) {
            window.addEventListener('scroll', _onSharedScroll, { passive: true });
            window.addEventListener('resize', _onSharedScroll, { passive: true });
        }
        _scrollSubs.add(cb);
        _onSharedScroll();
    });
    onBeforeUnmount(() => {
        _scrollSubs.delete(cb);
        if (_scrollSubs.size === 0) {
            window.removeEventListener('scroll', _onSharedScroll);
            window.removeEventListener('resize', _onSharedScroll);
            if (_scrollRaf) {
                cancelAnimationFrame(_scrollRaf);
                _scrollRaf = null;
            }
        }
    });
}

/**
 * Gõ chữ kiểu streaming (look "AI đang trả lời"). Trả về `{ display, done, restart }`.
 * Reduced-motion ⇒ hiện toàn bộ ngay. Tự chạy lại khi `active` bật true hoặc text đổi.
 */
export function useTypewriter(text, { speed = 28, startDelay = 0, active = true, loop = false } = {}) {
    const display = ref('');
    const done = ref(false);
    let timer = null;
    let i = 0;

    const fullText = () => String((typeof text === 'function' ? text() : text?.value ?? text) ?? '');
    const isActive = () => (typeof active === 'function' ? active() : active?.value ?? active);

    function clear() {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    }

    function finish() {
        clear();
        display.value = fullText();
        done.value = true;
    }

    function start() {
        clear();
        if (prefersReducedMotionNow()) {
            finish();
            return;
        }
        const str = fullText();
        i = 0;
        display.value = '';
        done.value = false;
        const step = () => {
            if (i >= str.length) {
                done.value = true;
                if (loop) timer = setTimeout(start, 2200);
                return;
            }
            i += 1;
            display.value = str.slice(0, i);
            timer = setTimeout(step, speed);
        };
        timer = setTimeout(step, startDelay);
    }

    watch(isActive, (on) => (on ? start() : finish()), { immediate: true });
    watch(fullText, () => {
        if (isActive()) start();
    });

    onBeforeUnmount(clear);

    return { display, done, restart: start };
}

/**
 * Luân phiên một mảng chuỗi theo chu kỳ (cho ticker dạng "cycle").
 * Pause khi tab ẩn; reduced-motion ⇒ khoá ở phần tử đầu.
 */
export function useDataStream({ values = [], interval = 2200 } = {}) {
    const index = ref(0);
    let timer = null;

    const list = () => (typeof values === 'function' ? values() : values?.value ?? values) || [];
    const current = computed(() => list()[index.value] ?? '');

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        stop();
        if (prefersReducedMotionNow()) {
            index.value = 0;
            return;
        }
        timer = setInterval(() => {
            if (typeof document !== 'undefined' && document.hidden) return;
            const n = list().length;
            if (n > 0) index.value = (index.value + 1) % n;
        }, interval);
    }

    onMounted(start);
    onBeforeUnmount(stop);

    return { current, index };
}

/**
 * Tiến độ cuộn (0 → 1) của MỘT phần tử so với viewport (đọc rect 1 lần/tick scroll).
 * Khác useScrollProgress (toàn trang). Reduced-motion ⇒ hằng 1 (coi như đã hiện hết).
 */
export function useScrollScene(target, { start = 0, end = 1 } = {}) {
    const progress = ref(0);

    function update() {
        if (prefersReducedMotionNow()) {
            progress.value = 1;
            return;
        }
        const el = target?.value ?? target;
        if (!el || typeof el.getBoundingClientRect !== 'function') return;
        const rect = el.getBoundingClientRect();
        const vh = window.innerHeight || 1;
        const total = rect.height + vh;
        const raw = total > 0 ? Math.max(0, Math.min(1, (vh - rect.top) / total)) : 0;
        progress.value = end > start ? Math.max(0, Math.min(1, (raw - start) / (end - start))) : raw;
    }

    onMounted(update);
    onSharedScroll(update);

    return progress;
}

/**
 * Parallax nhiều tầng theo con trỏ (dùng chung 1 mousemove). `layer(depth)` trả về
 * style transform. Tắt khi reduced-motion / không có chuột ⇒ transform identity.
 */
export function useParallaxLayers({ strength = 1 } = {}) {
    const nx = ref(0);
    const ny = ref(0);
    const enabled = ref(false);

    onMounted(() => {
        enabled.value = !prefersReducedMotionNow() && hasFinePointer();
    });

    onSharedPointer((p) => {
        if (!enabled.value) return;
        nx.value = p.nx;
        ny.value = p.ny;
    });

    function layer(depth = 1) {
        if (!enabled.value) return { transform: 'translate3d(0,0,0)' };
        const x = (nx.value * depth * strength).toFixed(1);
        const y = (ny.value * depth * strength).toFixed(1);
        return { transform: `translate3d(${x}px, ${y}px, 0)` };
    }

    return { layer, enabled };
}

/**
 * Hiệu ứng gợn sóng khi nhấn. `bind` gắn vào @pointerdown; `ripples` render vào
 * phần tử (cần position:relative + overflow:hidden). Reduced-motion ⇒ no-op.
 */
let _rippleId = 0;
export function useRipple({ duration = 650 } = {}) {
    const ripples = ref([]);

    function bind(e) {
        if (prefersReducedMotionNow()) return;
        const el = e.currentTarget;
        if (!el || typeof el.getBoundingClientRect !== 'function') return;
        const rect = el.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const id = ++_rippleId;
        const x = (e.clientX ?? rect.left + rect.width / 2) - rect.left;
        const y = (e.clientY ?? rect.top + rect.height / 2) - rect.top;
        ripples.value = [
            ...ripples.value,
            {
                id,
                style: {
                    left: `${(x - size / 2).toFixed(1)}px`,
                    top: `${(y - size / 2).toFixed(1)}px`,
                    width: `${size}px`,
                    height: `${size}px`,
                },
            },
        ];
        setTimeout(() => {
            ripples.value = ripples.value.filter((r) => r.id !== id);
        }, duration);
    }

    return { bind, ripples };
}

/**
 * Nhóm "nam châm": 1 mousemove kéo nhiều phần tử con về phía con trỏ khi ở gần.
 * Dùng `register(el)` qua :ref callback. Chỉ bật khi fine-pointer + non-reduced.
 */
export function useMagneticGroup({ strength = 0.25 } = {}) {
    const els = new Set();
    let enabled = false;

    onMounted(() => {
        enabled = !prefersReducedMotionNow() && hasFinePointer();
    });

    onSharedPointer((p) => {
        if (!enabled) return;
        els.forEach((el) => {
            const rect = el.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const dx = p.x - cx;
            const dy = p.y - cy;
            const radius = Math.max(rect.width, rect.height) * 1.25;
            if (Math.hypot(dx, dy) < radius) {
                el.style.transform = `translate(${(dx * strength).toFixed(1)}px, ${(dy * strength).toFixed(1)}px)`;
            } else if (el.style.transform && el.style.transform !== 'translate(0px, 0px)') {
                el.style.transform = 'translate(0px, 0px)';
            }
        });
    });

    function register(el) {
        if (el) els.add(el);
    }
    function unregister(el) {
        if (el) {
            els.delete(el);
            el.style.transform = '';
        }
    }

    onBeforeUnmount(() => els.clear());

    return { register, unregister };
}
