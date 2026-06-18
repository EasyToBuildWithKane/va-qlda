/**
 * Focus trap dùng chung cho landing Công Nghệ (modal thành viên, modal chi tiết
 * dự án, mobile drawer…). Khi `active` bật:
 *   - Lưu phần tử đang focus (trigger) để trả lại khi đóng.
 *   - Đưa focus vào phần tử focus-được đầu tiên trong container.
 *   - Giữ Tab / Shift+Tab vòng trong container.
 *
 * Không tự xử lý Escape / khoá scroll — các component đã có sẵn; composable này
 * chỉ lo việc bẫy focus để hợp chuẩn a11y (WAI-ARIA dialog).
 *
 * @param {import('vue').Ref<HTMLElement|null> | (() => HTMLElement|null)} containerRef
 * @param {import('vue').Ref<boolean> | (() => boolean)} active
 */
import { nextTick, onBeforeUnmount, unref, watch } from 'vue';

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

export function useCongngheFocusTrap(containerRef, active) {
    let previousActive = null;

    const readContainer = typeof containerRef === 'function'
        ? containerRef
        : () => unref(containerRef);
    const readActive = typeof active === 'function'
        ? active
        : () => unref(active);

    function focusables() {
        const el = readContainer();
        if (!el) {
            return [];
        }
        return Array.from(el.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
            (node) => node.offsetWidth > 0
                || node.offsetHeight > 0
                || node === document.activeElement,
        );
    }

    function onKeydown(e) {
        if (e.key !== 'Tab') {
            return;
        }
        const el = readContainer();
        if (!el) {
            return;
        }
        const items = focusables();
        if (items.length === 0) {
            e.preventDefault();
            el.focus?.({ preventScroll: true });
            return;
        }
        const first = items[0];
        const last = items[items.length - 1];
        const current = document.activeElement;
        if (e.shiftKey) {
            if (current === first || !el.contains(current)) {
                e.preventDefault();
                last.focus({ preventScroll: true });
            }
        } else if (current === last || !el.contains(current)) {
            e.preventDefault();
            first.focus({ preventScroll: true });
        }
    }

    function activate() {
        if (typeof document === 'undefined') {
            return;
        }
        previousActive = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        document.addEventListener('keydown', onKeydown, true);
        nextTick(() => {
            const items = focusables();
            (items[0] ?? readContainer())?.focus?.({ preventScroll: true });
        });
    }

    function deactivate() {
        if (typeof document === 'undefined') {
            return;
        }
        document.removeEventListener('keydown', onKeydown, true);
        previousActive?.focus?.({ preventScroll: true });
        previousActive = null;
    }

    watch(readActive, (on) => {
        if (on) {
            activate();
        } else {
            deactivate();
        }
    });

    onBeforeUnmount(() => {
        if (typeof document !== 'undefined') {
            document.removeEventListener('keydown', onKeydown, true);
        }
    });
}
