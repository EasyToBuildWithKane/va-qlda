<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    data: { type: Object, default: null },
});

const activeType = ref(props.data?.groups?.[0]?.type.value ?? null);

const activeGroup = computed(
    () => props.data?.groups?.find((g) => g.type.value === activeType.value) ?? null,
);

function pct(v) {
    return v == null ? 0 : Math.round((v / 5) * 100);
}
</script>

<template>
  <section
    v-if="data"
    class="rounded-2xl border border-slate-200/70 bg-white shadow-sm"
  >
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <div class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand">
        <AppIcon
          name="message"
          :size="16"
        />
      </div>
      <div>
        <h2 class="text-sm font-semibold text-slate-800">
          Đánh giá 360°
        </h2>
        <p class="text-[12px] text-slate-400">
          {{ data.total }} lượt đánh giá
        </p>
      </div>
    </header>

    <!-- Overall per-dimension averages -->
    <div class="grid grid-cols-1 gap-x-6 gap-y-2.5 border-b border-slate-100 p-5 sm:grid-cols-2">
      <div
        v-for="d in data.dimensions"
        :key="d.key"
      >
        <div class="mb-1 flex items-center justify-between text-[12px]">
          <span class="text-slate-600">{{ d.label }}</span>
          <span class="font-medium tabular-nums text-slate-500">{{ d.avg ?? '—' }}<span
            v-if="d.avg !== null"
            class="text-slate-300"
          >/5</span></span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full bg-brand transition-all duration-500"
            :style="{ width: pct(d.avg) + '%' }"
          />
        </div>
      </div>
    </div>

    <!-- Tabs by reviewer type -->
    <div class="flex items-center gap-1 px-5 pt-4">
      <button
        v-for="g in data.groups"
        :key="g.type.value"
        type="button"
        class="rounded-lg px-3 py-1.5 text-[12.5px] font-medium transition-colors"
        :class="activeType === g.type.value ? 'bg-brand/10 text-brand' : 'text-slate-500 hover:bg-slate-50'"
        @click="activeType = g.type.value"
      >
        {{ g.type.label }}
        <span class="ml-1 text-slate-400">{{ g.count }}</span>
      </button>
    </div>

    <div
      v-if="activeGroup"
      class="space-y-4 p-5"
    >
      <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
        <div
          v-for="r in activeGroup.ratings"
          :key="r.key"
          class="flex items-center justify-between text-[12.5px]"
        >
          <span class="text-slate-600">{{ r.label }}</span>
          <span class="flex items-center gap-0.5">
            <AppIcon
              v-for="n in 5"
              :key="n"
              name="star"
              :size="13"
              :class="r.value != null && n <= Math.round(r.value) ? 'text-amber-400' : 'text-slate-200'"
            />
          </span>
        </div>
      </div>

      <ul
        v-if="activeGroup.comments.length"
        class="space-y-2 border-t border-slate-100 pt-3"
      >
        <li
          v-for="(c, i) in activeGroup.comments"
          :key="i"
          class="rounded-lg bg-slate-50 px-3 py-2 text-[12.5px] italic text-slate-600"
        >
          “{{ c }}”
        </li>
      </ul>
    </div>
  </section>
</template>
