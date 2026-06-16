<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { prefersReducedMotionNow } from './motion.js';

const canvas = ref(null);

let ctx = null;
let raf = null;
let nodes = [];
let width = 0;
let height = 0;
let dpr = 1;
const mouse = { x: -9999, y: -9999 };

function resize() {
    if (!canvas.value) return;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.value.width = width * dpr;
    canvas.value.height = height * dpr;
    canvas.value.style.width = `${width}px`;
    canvas.value.style.height = `${height}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    seed();
}

function seed() {
    const target = Math.min(72, Math.floor((width * height) / 24000));
    nodes = Array.from({ length: target }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.25,
        vy: (Math.random() - 0.5) * 0.25,
        r: Math.random() * 1.6 + 0.6,
    }));
}

function onMouseMove(e) {
    mouse.x = e.clientX;
    mouse.y = e.clientY;
}

function onMouseLeave() {
    mouse.x = -9999;
    mouse.y = -9999;
}

function frame() {
    ctx.clearRect(0, 0, width, height);

    for (const n of nodes) {
        n.x += n.vx;
        n.y += n.vy;

        if (n.x < 0 || n.x > width) n.vx *= -1;
        if (n.y < 0 || n.y > height) n.vy *= -1;

        // soft mouse attraction
        const dxm = mouse.x - n.x;
        const dym = mouse.y - n.y;
        const dm = Math.hypot(dxm, dym);
        if (dm < 160) {
            n.x += (dxm / dm) * 0.5;
            n.y += (dym / dm) * 0.5;
        }

        ctx.beginPath();
        ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,0.55)';
        ctx.fill();
    }

    for (let i = 0; i < nodes.length; i++) {
        for (let j = i + 1; j < nodes.length; j++) {
            const a = nodes[i];
            const b = nodes[j];
            const dist = Math.hypot(a.x - b.x, a.y - b.y);
            if (dist < 130) {
                const alpha = (1 - dist / 130) * 0.32;
                ctx.strokeStyle = `rgba(219,78,124,${alpha})`;
                ctx.lineWidth = 0.7;
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.stroke();
            }
        }
    }

    // Mạng nơ-ron bám con trỏ: nối con trỏ tới các nút lân cận + nút sáng tại con trỏ.
    if (mouse.x > -9000) {
        for (const n of nodes) {
            const d = Math.hypot(mouse.x - n.x, mouse.y - n.y);
            if (d < 220) {
                const alpha = (1 - d / 220) * 0.55;
                ctx.strokeStyle = `rgba(34,211,238,${alpha})`;
                ctx.lineWidth = 0.9;
                ctx.beginPath();
                ctx.moveTo(mouse.x, mouse.y);
                ctx.lineTo(n.x, n.y);
                ctx.stroke();
            }
        }
        const halo = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 16);
        halo.addColorStop(0, 'rgba(34,211,238,0.85)');
        halo.addColorStop(1, 'rgba(34,211,238,0)');
        ctx.fillStyle = halo;
        ctx.beginPath();
        ctx.arc(mouse.x, mouse.y, 16, 0, Math.PI * 2);
        ctx.fill();
    }

    raf = requestAnimationFrame(frame);
}

function start() {
    if (raf) return;
    raf = requestAnimationFrame(frame);
}

function stop() {
    if (raf) cancelAnimationFrame(raf);
    raf = null;
}

function onVisibility() {
    if (document.hidden) stop();
    else start();
}

onMounted(() => {
    if (!canvas.value || prefersReducedMotionNow()) return;
    ctx = canvas.value.getContext('2d');
    if (!ctx) return;

    resize();
    window.addEventListener('resize', resize, { passive: true });
    window.addEventListener('mousemove', onMouseMove, { passive: true });
    window.addEventListener('mouseout', onMouseLeave, { passive: true });
    document.addEventListener('visibilitychange', onVisibility);
    start();
});

onBeforeUnmount(() => {
    stop();
    window.removeEventListener('resize', resize);
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseout', onMouseLeave);
    document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<template>
  <canvas
    ref="canvas"
    class="pointer-events-none absolute inset-0 h-full w-full opacity-70"
  />
</template>
