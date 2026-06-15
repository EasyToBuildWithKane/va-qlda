<script setup>
defineProps({
    label: { type: String, default: 'đang phân tích' },
    tone: { type: String, default: 'cyan' },
});

const dotTone = {
    cyan: 'bg-cyan-300',
    brand: 'bg-brand',
    violet: 'bg-violet-300',
    emerald: 'bg-emerald-300',
};
</script>

<template>
  <span
    class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 font-mono text-[10px] uppercase tracking-wider text-white/70"
  >
    <span class="relative flex h-1.5 w-1.5">
      <span
        class="absolute inline-flex h-full w-full animate-cn-glow rounded-full"
        :class="dotTone[tone] || dotTone.cyan"
      />
    </span>
    <span>{{ label }}</span>
    <span class="inline-flex gap-0.5">
      <span
        class="h-1 w-1 animate-cn-glow rounded-full bg-white/60"
        style="animation-delay: 0ms"
      />
      <span
        class="h-1 w-1 animate-cn-glow rounded-full bg-white/60"
        style="animation-delay: 200ms"
      />
      <span
        class="h-1 w-1 animate-cn-glow rounded-full bg-white/60"
        style="animation-delay: 400ms"
      />
    </span>
  </span>
</template>
