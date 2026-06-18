/**
 * Thiết lập chuyển động dùng chung cho landing Công Nghệ.
 *
 * Gộp ba nguồn quyết định "có giảm hiệu ứng nặng hay không":
 *   1. OS `prefers-reduced-motion` (luôn tôn trọng).
 *   2. Lớp thiết bị yếu (coarse pointer + ít core / ít RAM) — hạ tải mặc định
 *      trên điện thoại tầm trung dù người dùng chưa bật reduced-motion ở OS.
 *   3. Override người dùng qua công tắc trong UI (lưu localStorage).
 *
 * `congngheMotionReduced` là computed dùng chung — particle/parallax đọc trực
 * tiếp để bật/tắt theo thời gian thực khi đổi công tắc.
 */
import { computed, ref } from 'vue';
import { prefersReducedMotionNow, hasFinePointer } from './motion.js';

const STORAGE_KEY = 'congnghe:motion-pref';

/** @typedef {'auto' | 'reduced' | 'full'} MotionPref */

function readStoredPref() {
    if (typeof window === 'undefined') {
        return 'auto';
    }
    try {
        const value = window.localStorage.getItem(STORAGE_KEY);
        return value === 'reduced' || value === 'full' ? value : 'auto';
    } catch {
        return 'auto';
    }
}

// Lựa chọn của người dùng (module-level ⇒ chia sẻ giữa mọi component landing).
const userPref = ref(readStoredPref());

/**
 * Thiết bị yếu: không có con trỏ tinh (≈ cảm ứng) VÀ ít lõi / ít RAM. Khi đó
 * canvas particle (vẽ link O(n²)) tốn pin và gây giật — nên hạ về nền tĩnh.
 */
function isLowPowerDevice() {
    if (typeof navigator === 'undefined') {
        return false;
    }
    const coarse = !hasFinePointer();
    const cores = Number(navigator.hardwareConcurrency) || 8;
    const memory = Number(navigator.deviceMemory) || 8;
    return coarse && (cores <= 4 || memory <= 4);
}

/** true ⇒ tắt particle/parallax/tilt, dùng nền tĩnh. */
export const congngheMotionReduced = computed(() => {
    if (userPref.value === 'reduced') {
        return true;
    }
    if (userPref.value === 'full') {
        // Người dùng chủ động yêu cầu hiệu ứng đầy đủ — chỉ còn tôn trọng OS.
        return prefersReducedMotionNow();
    }
    // auto
    return prefersReducedMotionNow() || isLowPowerDevice();
});

/** @param {MotionPref} pref */
export function setCongngheMotionPref(pref) {
    const next = pref === 'reduced' || pref === 'full' ? pref : 'auto';
    userPref.value = next;
    if (typeof window === 'undefined') {
        return;
    }
    try {
        if (next === 'auto') {
            window.localStorage.removeItem(STORAGE_KEY);
        } else {
            window.localStorage.setItem(STORAGE_KEY, next);
        }
    } catch {
        /* localStorage không khả dụng — giữ trạng thái trong phiên là đủ. */
    }
}

/**
 * Công tắc 2 trạng thái cho UI: trả về `reduced` (đang giảm hay không) và
 * `toggle()` để đảo. Đảo sẽ ghi đè rõ ràng ('reduced' | 'full') thay vì 'auto'
 * để lựa chọn của người dùng được tôn trọng ổn định giữa các phiên.
 */
export function useCongngheMotion() {
    const reduced = congngheMotionReduced;

    function toggle() {
        setCongngheMotionPref(reduced.value ? 'full' : 'reduced');
    }

    return { reduced, pref: userPref, toggle, setPref: setCongngheMotionPref };
}
