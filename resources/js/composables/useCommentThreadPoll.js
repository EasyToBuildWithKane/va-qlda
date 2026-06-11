import { onBeforeUnmount, onMounted, unref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Đồng bộ trao đổi khi Socket.IO/Redis chưa ổn: partial reload Inertia định kỳ + khi quay lại tab.
 *
 * @param {{
 *   active: import('vue').Ref<boolean>|import('vue').ComputedRef<boolean>,
 *   enabled: import('vue').Ref<boolean>|import('vue').ComputedRef<boolean>,
 *   subscribed: import('vue').Ref<boolean>|import('vue').ComputedRef<boolean>,
 *   reloadKeys: import('vue').Ref<string[]>|import('vue').ComputedRef<string[]>,
 *   fastIntervalMs?: number,
 *   slowIntervalMs?: number,
 * }} options
 */
export function useCommentThreadPoll(options) {
    const fastIntervalMs = options.fastIntervalMs ?? 25000;
    const slowIntervalMs = options.slowIntervalMs ?? 45000;
    let fastTimerId = null;
    let slowTimerId = null;
    let visHandler = null;

    const reload = () => {
        const keys = unref(options.reloadKeys) || [];
        if (!keys.length || !unref(options.active) || !unref(options.enabled)) {
            return;
        }
        router.reload({
            only: keys,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const syncTimers = () => {
        if (fastTimerId) {
            clearInterval(fastTimerId);
            fastTimerId = null;
        }
        if (slowTimerId) {
            clearInterval(slowTimerId);
            slowTimerId = null;
        }
        if (!unref(options.active) || !unref(options.enabled)) {
            return;
        }
        if (!unref(options.subscribed)) {
            fastTimerId = window.setInterval(reload, fastIntervalMs);
            return;
        }
        slowTimerId = window.setInterval(reload, slowIntervalMs);
    };

    watch(
        () => [unref(options.active), unref(options.enabled), unref(options.subscribed)],
        syncTimers,
        { immediate: true },
    );

    onMounted(() => {
        visHandler = () => {
            if (document.visibilityState === 'visible') {
                reload();
            }
        };
        document.addEventListener('visibilitychange', visHandler);
    });

    onBeforeUnmount(() => {
        if (fastTimerId) {
            clearInterval(fastTimerId);
        }
        if (slowTimerId) {
            clearInterval(slowTimerId);
        }
        if (visHandler) {
            document.removeEventListener('visibilitychange', visHandler);
        }
    });
}
