<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import NeuralBackdrop from './NeuralBackdrop.vue';
import {
    hasFinePointer,
    isPointerOverCongngheHeader,
    onSharedRaf,
    onSharedPointer,
    onSharedScroll,
} from './motion.js';
import { congngheMotionReduced } from './useCongngheMotion.js';

const props = defineProps({
    tone: { type: String, default: 'cyan' },
    seed: { type: Number, default: 7 },
    /** Lớp opacity Tailwind trên canvas (vd. opacity-75) */
    opacityClass: { type: String, default: 'opacity-75' },
    /** Mật độ tương đối so với diện tích section */
    density: { type: Number, default: 1.35 },
    /** Bám con trỏ (đuôi nối, halo) — tắt trên UI điều khiển nhạy cảm. */
    followPointer: { type: Boolean, default: true },
});

const root = ref(null);
const canvas = ref(null);

// Thiết lập dùng chung: reduced-motion OS, thiết bị yếu, hoặc công tắc người dùng.
const useStatic = congngheMotionReduced;

const PALETTE = {
    cyan: { dot: 'rgba(255,255,255,', link: 'rgba(34,211,238,', hover: 'rgba(34,211,238,' },
    violet: { dot: 'rgba(255,255,255,', link: 'rgba(167,139,250,', hover: 'rgba(196,181,253,' },
    brand: { dot: 'rgba(255,255,255,', link: 'rgba(255,77,141,', hover: 'rgba(255,120,170,' },
    emerald: { dot: 'rgba(255,255,255,', link: 'rgba(52,211,153,', hover: 'rgba(110,231,183,' },
    amber: { dot: 'rgba(255,255,255,', link: 'rgba(251,191,36,', hover: 'rgba(253,224,71,' },
};

let ctx = null;
let nodes = [];
let width = 0;
let height = 0;
let dpr = 1;
let visible = false;
let finePointer = false;
let ro = null;
let io = null;
// Vị trí section so với viewport (cập nhật khi resize/scroll) — tránh
// getBoundingClientRect mỗi frame/mỗi lần di chuột.
const box = { left: 0, top: 0 };
// Con trỏ ở toạ độ client (cập nhật qua 1 mousemove dùng chung toàn trang).
const pointer = { x: -9999, y: -9999 };
// Con trỏ quy về toạ độ cục bộ canvas (suy ra trong frame).
const mouse = { x: -9999, y: -9999, active: false };

function colors() {
    return PALETTE[props.tone] ?? PALETTE.cyan;
}

function seededRandom(seed) {
    let s = (props.seed * 9301 + 49297 + seed) % 233280;
    return () => {
        s = (s * 9301 + 49297) % 233280;
        return s / 233280;
    };
}

function seedNodes() {
    const rnd = seededRandom(0);
    const area = Math.max(width * height, 1);
    const target = Math.min(
        88,
        Math.max(28, Math.floor((area / 16000) * props.density)),
    );
    nodes = Array.from({ length: target }, () => ({
        x: rnd() * width,
        y: rnd() * height,
        vx: (rnd() - 0.5) * 0.32,
        vy: (rnd() - 0.5) * 0.32,
        r: rnd() * 1.6 + 1.05,
    }));
}

function measure() {
    if (!root.value) return;
    const rect = root.value.getBoundingClientRect();
    box.left = rect.left;
    box.top = rect.top;
}

function resize() {
    if (!canvas.value || !root.value) return;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    const rect = root.value.getBoundingClientRect();
    box.left = rect.left;
    box.top = rect.top;
    width = Math.max(1, rect.width);
    height = Math.max(1, rect.height);
    canvas.value.width = width * dpr;
    canvas.value.height = height * dpr;
    canvas.value.style.width = `${width}px`;
    canvas.value.style.height = `${height}px`;
    ctx?.setTransform(dpr, 0, 0, dpr, 0, 0);
    seedNodes();
}

// Suy con trỏ cục bộ từ con trỏ client + vị trí section đã cache.
function updateMouse() {
    if (!props.followPointer || !finePointer || pointer.x < -9000
        || isPointerOverCongngheHeader(pointer.y)) {
        mouse.active = false;
        return;
    }
    const mx = pointer.x - box.left;
    const my = pointer.y - box.top;
    if (mx >= 0 && mx <= width && my >= 0 && my <= height) {
        mouse.x = mx;
        mouse.y = my;
        mouse.active = true;
    } else {
        mouse.active = false;
    }
}

function drawLinks(linkDist, linkAlpha, lineWidth) {
    const c = colors();
    for (let i = 0; i < nodes.length; i += 1) {
        for (let j = i + 1; j < nodes.length; j += 1) {
            const a = nodes[i];
            const b = nodes[j];
            const dist = Math.hypot(a.x - b.x, a.y - b.y);
            if (dist < linkDist) {
                const alpha = (1 - dist / linkDist) * linkAlpha;
                ctx.strokeStyle = `${c.link}${alpha})`;
                ctx.lineWidth = lineWidth;
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.stroke();
            }
        }
    }
}

function drawMouseLinks(maxDist, linkAlpha) {
    if (!mouse.active || mouse.x < -9000) return;
    const c = colors();
    for (const n of nodes) {
        const d = Math.hypot(mouse.x - n.x, mouse.y - n.y);
        if (d < maxDist) {
            const alpha = (1 - d / maxDist) * linkAlpha;
            ctx.strokeStyle = `${c.hover}${alpha})`;
            ctx.lineWidth = 1.1;
            ctx.beginPath();
            ctx.moveTo(mouse.x, mouse.y);
            ctx.lineTo(n.x, n.y);
            ctx.stroke();
        }
    }
    const halo = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 14);
    halo.addColorStop(0, `${c.hover}0.75)`);
    halo.addColorStop(1, `${c.hover}0)`);
    ctx.fillStyle = halo;
    ctx.beginPath();
    ctx.arc(mouse.x, mouse.y, 14, 0, Math.PI * 2);
    ctx.fill();
}

// Một callback duy nhất chạy trên rAF DÙNG CHUNG toàn trang. Tự bỏ qua khi
// section ngoài viewport ⇒ off-screen không tốn gì ngoài 1 phép kiểm tra.
function frame() {
    if (useStatic.value || !visible || !ctx) return;

    updateMouse();
    ctx.clearRect(0, 0, width, height);
    const c = colors();

    for (const n of nodes) {
        n.x += n.vx;
        n.y += n.vy;
        if (n.x < 0 || n.x > width) n.vx *= -1;
        if (n.y < 0 || n.y > height) n.vy *= -1;

        if (mouse.active) {
            const dxm = mouse.x - n.x;
            const dym = mouse.y - n.y;
            const dm = Math.hypot(dxm, dym);
            if (dm < 140 && dm > 0.01) {
                n.x += (dxm / dm) * 0.35;
                n.y += (dym / dm) * 0.35;
            }
        }

        ctx.beginPath();
        ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
        ctx.fillStyle = `${c.link}0.88)`;
        ctx.fill();
        ctx.beginPath();
        ctx.arc(n.x, n.y, n.r * 0.42, 0, Math.PI * 2);
        ctx.fillStyle = `${c.dot}0.92)`;
        ctx.fill();
    }

    drawLinks(138, 0.48, 0.95);
    drawMouseLinks(228, 0.78);
}

// Đăng ký vòng lặp & listener DÙNG CHUNG ngay trong setup (gọi vô điều kiện
// theo quy tắc composable). frame() tự bỏ qua khi `useStatic` ⇒ công tắc bật
// lại hiệu ứng được phản hồi tức thì mà không cần đăng ký lại.
onSharedRaf(frame);
onSharedPointer((p) => {
    pointer.x = p.x;
    pointer.y = p.y;
});
onSharedScroll(() => {
    if (visible) measure();
});

function initCanvas() {
    if (useStatic.value || ctx || !canvas.value || !root.value) return;

    ctx = canvas.value.getContext('2d');
    if (!ctx) return;

    finePointer = hasFinePointer();
    resize();
    ro = new ResizeObserver(() => resize());
    ro.observe(root.value);

    io = new IntersectionObserver(
        (entries) => {
            visible = entries.some((e) => e.isIntersecting);
            if (visible) measure();
        },
        { root: null, rootMargin: '80px 0px', threshold: 0 },
    );
    io.observe(root.value);
}

function destroyCanvas() {
    ro?.disconnect();
    io?.disconnect();
    ro = null;
    io = null;
    ctx = null;
    visible = false;
    nodes = [];
}

onMounted(initCanvas);

// Đổi công tắc giảm hiệu ứng khi đang xem: tháo canvas (chuyển nền tĩnh) hoặc
// dựng lại canvas (sau khi <canvas v-else> được mount lại ở DOM).
watch(useStatic, (isStatic) => {
    if (isStatic) {
        destroyCanvas();
    } else {
        nextTick(initCanvas);
    }
});

onBeforeUnmount(destroyCanvas);
</script>

<template>
  <div
    ref="root"
    class="pointer-events-none absolute inset-0 -z-0 overflow-hidden"
    aria-hidden="true"
  >
    <NeuralBackdrop
      v-if="useStatic"
      :seed="seed"
      :tone="tone"
      class="h-full w-full opacity-55"
      :node-count="32"
      :link-distance="38"
    />
    <canvas
      v-else
      ref="canvas"
      class="h-full w-full"
      :class="opacityClass"
    />
  </div>
</template>
