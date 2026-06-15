<script setup>
import SectionHeading from './SectionHeading.vue';
import { tone } from './tones.js';

defineProps({
    phases: { type: Array, default: () => [] },
});
</script>

<template>
  <section
    id="du-an"
    class="relative border-t border-white/5 py-24"
  >
    <div class="mx-auto max-w-7xl px-5 sm:px-8">
      <SectionHeading
        eyebrow="Dự án đang triển khai"
        title="Vòng đời sản phẩm số"
        subtitle="Từ nghiên cứu phát triển, triển khai nghiệm thu đến vận hành cải tiến — mỗi dự án đi qua một hành trình rõ ràng."
      />

      <div class="mt-12 grid gap-5 lg:grid-cols-3">
        <div
          v-for="(phase, i) in phases"
          :key="phase.value"
          class="rounded-2xl border border-white/10 bg-white/[0.03] p-6"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span
                class="grid h-9 w-9 place-items-center rounded-xl text-sm font-bold"
                :class="tone(phase.color).soft"
              >
                {{ i + 1 }}
              </span>
              <h3 class="font-display text-base font-bold text-white">
                {{ phase.label }}
              </h3>
            </div>
            <span class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[11px] font-semibold text-white/60">
              {{ phase.total }} dự án
            </span>
          </div>

          <div class="mt-5 space-y-3">
            <div
              v-for="project in phase.items"
              :key="project.id"
              class="rounded-xl border border-white/10 bg-white/[0.02] p-4"
            >
              <div class="flex items-center gap-3">
                <span
                  class="h-2.5 w-2.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: project.color || '#9A0036' }"
                />
                <p class="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-white">
                  {{ project.name }}
                </p>
                <span class="shrink-0 text-[12px] font-semibold text-white/55">{{ project.progress }}%</span>
              </div>
              <div class="mt-2.5 h-1 overflow-hidden rounded-full bg-white/10">
                <div
                  class="h-full rounded-full bg-gradient-to-r from-brand to-[#ff4d8d]"
                  :style="{ width: `${project.progress}%` }"
                />
              </div>
            </div>

            <p
              v-if="!phase.items.length"
              class="rounded-xl border border-dashed border-white/10 px-4 py-6 text-center text-[12.5px] text-white/40"
            >
              Chưa có dự án ở giai đoạn này.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
