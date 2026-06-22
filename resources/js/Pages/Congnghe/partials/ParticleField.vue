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

// Giới hạn ~36fps: hạt trôi chậm nên vẫn mượt mắt mà tiết kiệm CPU đáng kể.
const FRAME_INTERVAL = 1000 / 36;
let lastFrame = 0;

function resize() {
    if (!canvas.value) return;
    dpr = Math.min(window.devicePixelRatio || 1, 1.5);
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
    const target = Math.min(48, Math.floor((width * height) / 38000));
    nodes = Array.from({ length: target }, () => ({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.25,
        vy: (Math.random() - 0.5) * 0.25,
        r: Math.random() * 1.6 + 0.6,
    }));
}

function frame(now) {
    raf = requestAnimationFrame(frame);
    if (now - lastFrame < FRAME_INTERVAL) return;
    lastFrame = now;

    ctx.clearRect(0, 0, width, height);

    for (const n of nodes) {
        n.x += n.vx;
        n.y += n.vy;

        if (n.x < 0 || n.x > width) n.vx *= -1;
        if (n.y < 0 || n.y > height) n.vy *= -1;

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
    document.addEventListener('visibilitychange', onVisibility);
    start();
});

onBeforeUnmount(() => {
    stop();
    window.removeEventListener('resize', resize);
    document.removeEventListener('visibilitychange', onVisibility);
});
</script>

<template>
  <canvas
    ref="canvas"
    class="pointer-events-none absolute inset-0 h-full w-full opacity-70"
  />
</template>
