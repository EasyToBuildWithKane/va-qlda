<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Renderer, Program, Mesh, Triangle, Geometry, Transform } from 'ogl';
import { prefersReducedMotionNow } from './motion.js';

/**
 * Nền WebGL dùng chung cho landing /congnghe: shader aurora (fbm noise tô màu
 * thương hiệu) + point cloud có chiều sâu, phản ứng nhẹ theo con trỏ. Chỉ được
 * mount khi AuroraBackground xác định WebGL khả dụng + không reduced-motion +
 * không low-power. Lỗi runtime ⇒ emit `fail` để cha quay về ParticleField 2D.
 */
const emit = defineEmits(['fail']);

const root = ref(null);

let renderer = null;
let gl = null;
let scene = null;
let auroraProgram = null;
let pointsProgram = null;
let raf = null;
let resizeRaf = null;
let startTime = 0;
const mouse = { tx: 0, ty: 0, x: 0, y: 0 };

const vertexAurora = /* glsl */ `
    attribute vec2 uv;
    attribute vec2 position;
    varying vec2 vUv;
    void main() {
        vUv = uv;
        gl_Position = vec4(position, 0.0, 1.0);
    }
`;

const fragmentAurora = /* glsl */ `
    precision highp float;
    uniform float uTime;
    uniform vec2 uResolution;
    uniform vec2 uMouse;
    varying vec2 vUv;

    vec2 hash(vec2 p) {
        p = vec2(dot(p, vec2(127.1, 311.7)), dot(p, vec2(269.5, 183.3)));
        return -1.0 + 2.0 * fract(sin(p) * 43758.5453123);
    }
    float noise(vec2 p) {
        vec2 i = floor(p);
        vec2 f = fract(p);
        vec2 u = f * f * (3.0 - 2.0 * f);
        return mix(mix(dot(hash(i + vec2(0.0, 0.0)), f - vec2(0.0, 0.0)),
                       dot(hash(i + vec2(1.0, 0.0)), f - vec2(1.0, 0.0)), u.x),
                   mix(dot(hash(i + vec2(0.0, 1.0)), f - vec2(0.0, 1.0)),
                       dot(hash(i + vec2(1.0, 1.0)), f - vec2(1.0, 1.0)), u.x), u.y);
    }
    float fbm(vec2 p) {
        float v = 0.0;
        float a = 0.5;
        for (int i = 0; i < 5; i++) {
            v += a * noise(p);
            p *= 2.0;
            a *= 0.5;
        }
        return v;
    }

    void main() {
        float aspect = uResolution.x / max(uResolution.y, 1.0);
        vec2 p = vUv - 0.5;
        p.x *= aspect;
        float t = uTime * 0.05;

        // Vị trí con trỏ trong không gian p, và lực xoáy kéo nhiễu về phía con trỏ.
        vec2 cur = vec2(uMouse.x * 0.5 * aspect, uMouse.y * 0.5);
        vec2 toC = p - cur;
        float cd = length(toC);
        float swirl = smoothstep(0.7, 0.0, cd);
        vec2 warp = uMouse * 0.5 + vec2(-toC.y, toC.x) * swirl * 0.6;

        float n = fbm(p * 1.8 + vec2(t, -t * 0.7) + warp);
        float n2 = fbm(p * 3.0 - vec2(t * 0.8, t) + n);

        vec3 maroon = vec3(0.604, 0.0, 0.212);
        vec3 violet = vec3(0.486, 0.227, 0.929);
        vec3 cyan = vec3(0.0, 0.6, 0.78);
        vec3 col = mix(maroon, violet, smoothstep(-0.4, 0.6, n));
        col = mix(col, cyan, smoothstep(0.0, 0.9, n2) * 0.5);

        // Đèn AI bám con trỏ — toả màu cyan, mạnh hơn khi gần.
        float cursorGlow = smoothstep(0.5, 0.0, cd);
        col = mix(col, cyan, cursorGlow * 0.5);

        float glow = smoothstep(0.2, 0.95, n * 0.6 + n2 * 0.5);
        float vig = smoothstep(1.15, 0.2, length(p));
        float alpha = (glow + cursorGlow * 0.35) * vig * 0.5;
        gl_FragColor = vec4(col * (glow + cursorGlow * 0.4), alpha);
    }
`;

const vertexPoints = /* glsl */ `
    attribute vec3 position;
    uniform float uTime;
    uniform vec2 uMouse;
    uniform float uDpr;
    varying float vD;
    varying float vG;
    void main() {
        float depth = position.z;
        vec2 drift = vec2(sin(uTime * 0.1 + depth * 6.28), cos(uTime * 0.08 + position.x * 3.0)) * 0.02 * depth;
        vec2 par = uMouse * depth * 0.12;

        // Thấu kính theo con trỏ: hạt ở gần bị hút nhẹ về phía con trỏ.
        vec2 toC = uMouse - position.xy;
        float d = length(toC);
        float pullAmt = smoothstep(0.7, 0.0, d);
        vec2 pull = toC * pullAmt * 0.14 * depth;

        gl_Position = vec4(position.xy + drift + par + pull, 0.0, 1.0);
        gl_PointSize = mix(1.0, 3.4, depth) * (1.0 + pullAmt * 1.4) * uDpr;
        vD = depth;
        vG = pullAmt;
    }
`;

const fragmentPoints = /* glsl */ `
    precision mediump float;
    varying float vD;
    varying float vG;
    void main() {
        vec2 c = gl_PointCoord - 0.5;
        float a = smoothstep(0.5, 0.0, length(c)) * (0.18 + vD * 0.4 + vG * 0.45);
        vec3 col = mix(vec3(0.0, 0.8, 0.95), vec3(1.0, 0.45, 0.65), vD);
        col = mix(col, vec3(0.6, 0.95, 1.0), vG * 0.6);
        gl_FragColor = vec4(col, a);
    }
`;

function buildPoints() {
    const area = window.innerWidth * window.innerHeight;
    const count = Math.max(24, Math.min(120, Math.floor(area / 16000)));
    const data = new Float32Array(count * 3);
    for (let i = 0; i < count; i += 1) {
        data[i * 3] = Math.random() * 2 - 1;
        data[i * 3 + 1] = Math.random() * 2 - 1;
        data[i * 3 + 2] = Math.random();
    }
    return new Geometry(gl, { position: { size: 3, data } });
}

function resize() {
    if (!renderer) return;
    const w = window.innerWidth;
    const h = window.innerHeight;
    renderer.setSize(w, h);
    if (auroraProgram) auroraProgram.uniforms.uResolution.value = [w, h];
}

function onResize() {
    if (resizeRaf) return;
    resizeRaf = requestAnimationFrame(() => {
        resize();
        resizeRaf = null;
    });
}

function onMouseMove(e) {
    mouse.tx = (e.clientX / window.innerWidth - 0.5) * 2;
    mouse.ty = -(e.clientY / window.innerHeight - 0.5) * 2;
}

function frame(now) {
    const t = (now - startTime) / 1000;
    mouse.x += (mouse.tx - mouse.x) * 0.09;
    mouse.y += (mouse.ty - mouse.y) * 0.09;

    if (auroraProgram) {
        auroraProgram.uniforms.uTime.value = t;
        auroraProgram.uniforms.uMouse.value = [mouse.x, mouse.y];
    }
    if (pointsProgram) {
        pointsProgram.uniforms.uTime.value = t;
        pointsProgram.uniforms.uMouse.value = [mouse.x, mouse.y];
    }

    renderer.render({ scene });
    raf = requestAnimationFrame(frame);
}

function start() {
    if (raf) return;
    startTime = performance.now();
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

function onContextLost(e) {
    e.preventDefault();
    stop();
}

onMounted(() => {
    if (!root.value || prefersReducedMotionNow()) {
        emit('fail');
        return;
    }

    try {
        const dpr = Math.min(window.devicePixelRatio || 1, 1.5);
        renderer = new Renderer({ alpha: true, antialias: false, dpr, depth: false });
        gl = renderer.gl;
        gl.canvas.className = 'absolute inset-0 h-full w-full';
        gl.canvas.style.width = '100%';
        gl.canvas.style.height = '100%';
        root.value.appendChild(gl.canvas);

        scene = new Transform();

        auroraProgram = new Program(gl, {
            vertex: vertexAurora,
            fragment: fragmentAurora,
            transparent: true,
            uniforms: {
                uTime: { value: 0 },
                uResolution: { value: [window.innerWidth, window.innerHeight] },
                uMouse: { value: [0, 0] },
            },
        });
        const auroraMesh = new Mesh(gl, { geometry: new Triangle(gl), program: auroraProgram });
        auroraMesh.setParent(scene);

        pointsProgram = new Program(gl, {
            vertex: vertexPoints,
            fragment: fragmentPoints,
            transparent: true,
            uniforms: {
                uTime: { value: 0 },
                uMouse: { value: [0, 0] },
                uDpr: { value: dpr },
            },
        });
        const pointsMesh = new Mesh(gl, { geometry: buildPoints(), program: pointsProgram, mode: gl.POINTS });
        pointsMesh.renderOrder = 1;
        pointsMesh.setParent(scene);

        resize();

        window.addEventListener('resize', onResize, { passive: true });
        window.addEventListener('mousemove', onMouseMove, { passive: true });
        document.addEventListener('visibilitychange', onVisibility);
        gl.canvas.addEventListener('webglcontextlost', onContextLost);

        start();
    } catch {
        emit('fail');
    }
});

onBeforeUnmount(() => {
    stop();
    if (resizeRaf) cancelAnimationFrame(resizeRaf);
    window.removeEventListener('resize', onResize);
    window.removeEventListener('mousemove', onMouseMove);
    document.removeEventListener('visibilitychange', onVisibility);
    if (gl) {
        gl.canvas.removeEventListener('webglcontextlost', onContextLost);
        gl.getExtension('WEBGL_lose_context')?.loseContext();
        if (gl.canvas.parentNode) gl.canvas.parentNode.removeChild(gl.canvas);
    }
    renderer = null;
    gl = null;
    scene = null;
    auroraProgram = null;
    pointsProgram = null;
});
</script>

<template>
  <div
    ref="root"
    class="pointer-events-none absolute inset-0 overflow-hidden"
    aria-hidden="true"
  />
</template>
