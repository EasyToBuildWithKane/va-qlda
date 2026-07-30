import { reactive } from 'vue';

const toasts = reactive([]);
let _id = 1;
let audioCtx = null;

const AUTO_DISMISS_MS = 4500;

const remove = (id) => {
    const idx = toasts.findIndex((t) => t.id === id);
    if (idx !== -1) toasts.splice(idx, 1);
};

function getAudioCtx() {
    if (typeof window === 'undefined') return null;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (!audioCtx || audioCtx.state === 'closed') {
        audioCtx = new Ctx();
    }
    return audioCtx;
}

function beep(ctx, freq, start, duration, gainValue, type = 'sine') {
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(gainValue, start + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start(start);
    osc.stop(start + duration + 0.02);
}

/** Âm thanh toast qua Web Audio API — parity VA-HRM (`toastSound.ts`). */
function playToastSound(tone) {
    if (typeof window === 'undefined') return;
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;

    try {
        const ctx = getAudioCtx();
        if (!ctx) return;

        const play = () => {
            const t = ctx.currentTime;
            if (tone === 'success') {
                beep(ctx, 880, t, 0.1, 0.12);
                beep(ctx, 1174.7, t + 0.1, 0.14, 0.1);
            } else if (tone === 'error') {
                beep(ctx, 320, t, 0.16, 0.14, 'triangle');
                beep(ctx, 220, t + 0.14, 0.2, 0.12, 'triangle');
            }
        };

        if (ctx.state === 'suspended') {
            void ctx.resume().then(play).catch(() => {});
        } else {
            play();
        }
    } catch {
        // Trình duyệt chặn autoplay / không hỗ trợ — bỏ qua.
    }
}

const add = (type, message, duration = AUTO_DISMISS_MS) => {
    const id = _id++;
    toasts.push({ id, type, message });
    if (type === 'success' || type === 'error') {
        playToastSound(type);
    }
    setTimeout(() => remove(id), duration);
};

export const toastList = toasts;
export const dismissToast = remove;

export function useToast() {
    return {
        success: (msg, dur) => add('success', msg, dur),
        error: (msg, dur) => add('error', msg, dur),
        info: (msg, dur) => add('info', msg, dur),
        warning: (msg, dur) => add('warning', msg, dur),
        dismiss: remove,
    };
}
