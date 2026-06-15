<script setup>
import { computed } from 'vue';

const props = defineProps({
    nodeCount: { type: Number, default: 18 },
    // ngưỡng nối cạnh, theo đơn vị viewBox 0–100
    linkDistance: { type: Number, default: 34 },
    seed: { type: Number, default: 7 },
    tone: { type: String, default: 'cyan' },
});

const stroke = {
    cyan: 'rgba(34,211,238,',
    violet: 'rgba(167,139,250,',
    brand: 'rgba(255,77,141,',
    emerald: 'rgba(52,211,153,',
};

// LCG tất định ⇒ SSR & client khớp nhau, không nhấp nháy khi hydrate
function makeNodes(count, seed) {
    let s = seed * 9301 + 49297;
    const rnd = () => {
        s = (s * 9301 + 49297) % 233280;
        return s / 233280;
    };
    const out = [];
    for (let i = 0; i < count; i += 1) {
        out.push({ x: rnd() * 100, y: rnd() * 100, d: (i % 6) * 0.45 });
    }
    return out;
}

const baseColor = computed(() => stroke[props.tone] || stroke.cyan);
const nodes = computed(() => makeNodes(props.nodeCount, props.seed));
const links = computed(() => {
    const out = [];
    const n = nodes.value;
    for (let i = 0; i < n.length; i += 1) {
        for (let j = i + 1; j < n.length; j += 1) {
            const dx = n[i].x - n[j].x;
            const dy = n[i].y - n[j].y;
            if (Math.hypot(dx, dy) < props.linkDistance) {
                out.push({ x1: n[i].x, y1: n[i].y, x2: n[j].x, y2: n[j].y });
            }
        }
    }
    return out;
});
</script>

<template>
  <svg
    class="pointer-events-none absolute inset-0 h-full w-full"
    viewBox="0 0 100 100"
    preserveAspectRatio="none"
    aria-hidden="true"
  >
    <line
      v-for="(l, i) in links"
      :key="`l-${i}`"
      :x1="l.x1"
      :y1="l.y1"
      :x2="l.x2"
      :y2="l.y2"
      :stroke="`${baseColor}0.12)`"
      stroke-width="0.15"
    />
    <circle
      v-for="(nd, i) in nodes"
      :key="`n-${i}`"
      :cx="nd.x"
      :cy="nd.y"
      r="0.55"
      class="animate-cn-glow"
      :style="{ animationDelay: `${nd.d}s` }"
      :fill="`${baseColor}0.65)`"
    />
  </svg>
</template>
