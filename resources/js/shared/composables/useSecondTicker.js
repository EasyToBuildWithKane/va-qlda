import { ref, onMounted, onBeforeUnmount } from 'vue';

/** Cập nhật mỗi giây — dùng cho countdown UI (một interval cho cả trang). */
export function useSecondTicker() {
    const now = ref(Date.now());
    let timerId;

    onMounted(() => {
        timerId = window.setInterval(() => {
            now.value = Date.now();
        }, 1000);
    });

    onBeforeUnmount(() => {
        if (timerId != null) window.clearInterval(timerId);
    });

    return { now };
}
