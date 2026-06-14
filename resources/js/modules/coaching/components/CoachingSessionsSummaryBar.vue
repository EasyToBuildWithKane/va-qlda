<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { hours as fmtHours } from '@/composables/useFormat';

const props = defineProps({
    summary: { type: Object, required: true },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['filter-status']);

const toneClass = {
    brand: 'kpi-card--brand',
    emerald: 'kpi-card--emerald',
    amber: 'kpi-card--amber',
    rose: 'kpi-card--rose',
    sky: 'kpi-card--sky',
    violet: 'kpi-card--violet',
    slate: 'kpi-card--slate',
};

const iconToneClass = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    rose: 'text-rose-700 bg-rose-50 ring-rose-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
    slate: 'text-slate-600 bg-slate-100 ring-slate-200/80',
};

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng buổi',
            field: 'total',
            filter: '',
            tone: 'brand',
            icon: 'weekly',
            sub: total ? `${s.courses ?? 0} khóa · theo bộ lọc` : 'Chưa có buổi phù hợp',
            progress: null,
        },
        {
            key: 'courses',
            label: 'Khóa học',
            field: 'courses',
            filter: '',
            tone: 'sky',
            icon: 'knowledge',
            sub: 'Số khóa có buổi trong kết quả',
            progress: null,
        },
        {
            key: 'hours',
            label: 'Tổng giờ',
            field: 'hours_total',
            filter: '',
            tone: 'violet',
            icon: 'clock',
            format: 'hours',
            sub: 'Giờ ghi nhận trên buổi lọc',
            progress: null,
        },
        {
            key: 'completed',
            label: 'Hoàn thành',
            field: 'completed',
            filter: 'completed',
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.completed ?? 0)}% tổng buổi` : 'Bấm để lọc',
            progress: pct(s.completed ?? 0),
        },
        {
            key: 'pending',
            label: 'Chưa học',
            field: 'pending',
            filter: 'pending',
            tone: 'slate',
            icon: 'task',
            sub: total ? `${pct(s.pending ?? 0)}% tổng buổi` : 'Bấm để lọc',
            progress: pct(s.pending ?? 0),
        },
    ];
});

function displayValue(summary, card) {
    const v = summary[card.field];
    if (card.format === 'hours') return fmtHours(v ?? 0);
    return v ?? 0;
}

function isActive(card) {
    if (!card.filter) return false;
    return props.activeStatus === card.filter;
}

function isInteractive(card) {
    return Boolean(card.filter);
}

function onCard(card) {
    if (!card.filter) return;
    emit('filter-status', card.filter);
}
</script>

<template>
  <section
    class="kpi-strip relative shrink-0 overflow-x-hidden border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 sm:px-5 sm:py-5"
    aria-label="Thống kê buổi học theo bộ lọc"
  >
    <!-- Nền trang trí clip-path lồng (section) -->
    <div
      class="pointer-events-none absolute inset-0 opacity-[0.45]"
      aria-hidden="true"
    >
      <div class="kpi-strip__bg-outer absolute -right-8 top-0 h-32 w-[min(55%,22rem)] bg-brand/[0.06]" />
      <div class="kpi-strip__bg-inner absolute -right-4 top-2 h-24 w-[min(45%,18rem)] bg-brand/[0.04]" />
    </div>

    <header class="relative mb-3 flex flex-wrap items-end justify-between gap-2">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Thống kê
        </p>
        <h2 class="font-display text-sm font-semibold text-slate-800">
          Chỉ số theo bộ lọc hiện tại
        </h2>
      </div>
      <p class="text-[11px] text-slate-500">
        Thẻ có viền nét đứt — bấm để lọc nhanh
      </p>
    </header>

    <div class="relative grid grid-cols-2 gap-3 pb-1 sm:grid-cols-3 lg:grid-cols-5">
      <component
        :is="isInteractive(card) ? 'button' : 'div'"
        v-for="card in cards"
        :key="card.key"
        type="button"
        class="kpi-card group relative min-h-[6.75rem] text-left outline-none transition-[transform,box-shadow] duration-300 ease-out"
        :class="[
          toneClass[card.tone],
          isInteractive(card) ? 'kpi-card--interactive cursor-pointer' : 'kpi-card--static',
          isActive(card) ? 'kpi-card--active' : '',
        ]"
        :disabled="!isInteractive(card)"
        :aria-pressed="isInteractive(card) ? isActive(card) : undefined"
        :aria-label="isInteractive(card) ? `${card.label}: ${displayValue(summary, card)}. Lọc theo ${card.label}` : undefined"
        @click="onCard(card)"
      >
        <!-- Lớp clip-path lồng -->
        <div
          class="pointer-events-none absolute inset-0"
          aria-hidden="true"
        >
          <div class="kpi-card__shell-outer absolute inset-0 bg-white shadow-sm transition-shadow duration-300 group-hover:shadow-md" />
          <div class="kpi-card__shell-inner absolute inset-[3px] opacity-90" />
          <div class="kpi-card__shell-accent absolute left-0 top-0 h-full w-[38%] max-w-[7rem] opacity-100" />
          <div class="kpi-card__shine absolute inset-0 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
        </div>

        <div class="relative z-[1] flex h-full flex-col p-3.5 sm:p-4">
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
              <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                {{ card.label }}
              </p>
              <p
                class="mt-1 font-display text-2xl font-bold tabular-nums leading-none tracking-tight sm:text-[1.65rem]"
                :class="card.tone === 'brand' ? 'text-brand' : 'text-slate-900'"
              >
                {{ displayValue(summary, card) }}
              </p>
            </div>
            <span
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1 transition-transform duration-300 group-hover:scale-105"
              :class="iconToneClass[card.tone]"
            >
              <AppIcon
                :name="card.icon"
                :size="18"
              />
            </span>
          </div>

          <p class="mt-auto pt-2 text-[11px] leading-snug text-slate-500">
            {{ card.sub }}
          </p>

          <div
            v-if="card.progress != null && summary.total > 0"
            class="mt-2"
          >
            <div class="flex items-center justify-between text-[10px] tabular-nums text-slate-400">
              <span>Tỷ lệ</span>
              <span>{{ card.progress }}%</span>
            </div>
            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
              <div
                class="kpi-card__bar h-full rounded-full transition-all duration-700 ease-out"
                :style="{ width: `${Math.min(100, card.progress)}%` }"
              />
            </div>
          </div>

          <span
            v-if="isInteractive(card)"
            class="mt-2 inline-flex items-center gap-1 text-[10px] font-medium text-slate-400 opacity-0 transition-all duration-300 group-hover:opacity-100"
          >
            <AppIcon
              name="filter"
              :size="11"
            />
            {{ isActive(card) ? 'Đang lọc' : 'Lọc nhanh' }}
          </span>
        </div>
      </component>
    </div>
  </section>
</template>

<style scoped>
.kpi-strip__bg-outer {
    clip-path: polygon(18% 0, 100% 0, 100% 100%, 0 100%);
}

.kpi-strip__bg-inner {
    clip-path: polygon(22% 0, 100% 0, 100% 92%, 8% 100%, 0 28%);
}

.kpi-card__shell-outer {
    clip-path: polygon(0 0, 100% 0, 100% calc(100% - 14px), calc(100% - 14px) 100%, 0 100%);
}

.kpi-card__shell-inner {
    clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 calc(100% - 6px), 0 10px);
    background: linear-gradient(135deg, rgb(248 250 252 / 0.95) 0%, rgb(255 255 255 / 0.85) 55%, rgb(255 255 255) 100%);
}

.kpi-card__shell-accent {
    clip-path: polygon(0 0, 100% 0, 72% 100%, 0 100%);
    transition: opacity 0.35s ease, transform 0.35s ease;
}

.kpi-card__shine {
    clip-path: polygon(0 0, 40% 0, 28% 100%, 0 100%);
    background: linear-gradient(
        105deg,
        transparent 0%,
        rgb(255 255 255 / 0.55) 45%,
        transparent 70%
    );
    transform: translateX(-120%);
    animation: none;
}

.kpi-card--interactive:hover .kpi-card__shine {
    animation: kpi-shimmer 0.85s ease-out forwards;
}

.kpi-card--interactive:hover {
    transform: translateY(-3px);
}

.kpi-card--interactive:focus-visible {
    transform: translateY(-2px);
    box-shadow: 0 0 0 3px rgb(154 0 54 / 0.22);
}

.kpi-card--active .kpi-card__shell-outer {
    box-shadow:
        0 4px 18px rgb(154 0 54 / 0.12),
        inset 0 0 0 1px rgb(154 0 54 / 0.35);
}

.kpi-card--active .kpi-card__shell-accent {
    opacity: 1;
    transform: scale(1.02);
    transform-origin: left center;
}

.kpi-card--brand .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(154 0 54 / 0.14), rgb(154 0 54 / 0.04));
}

.kpi-card--brand .kpi-card__bar {
    background: linear-gradient(90deg, #9a0036, #c41e5a);
}

.kpi-card--emerald .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(16 185 129 / 0.18), rgb(16 185 129 / 0.04));
}

.kpi-card--emerald .kpi-card__bar {
    background: linear-gradient(90deg, #059669, #34d399);
}

.kpi-card--amber .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(245 158 11 / 0.2), rgb(245 158 11 / 0.05));
}

.kpi-card--amber .kpi-card__bar {
    background: linear-gradient(90deg, #d97706, #fbbf24);
}

.kpi-card--rose .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(244 63 94 / 0.18), rgb(244 63 94 / 0.04));
}

.kpi-card--rose .kpi-card__bar {
    background: linear-gradient(90deg, #e11d48, #fb7185);
}

.kpi-card--sky .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(14 165 233 / 0.16), rgb(14 165 233 / 0.04));
}

.kpi-card--violet .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(139 92 246 / 0.16), rgb(139 92 246 / 0.04));
}

.kpi-card--slate .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(100 116 139 / 0.12), rgb(148 163 184 / 0.04));
}

.kpi-card--slate .kpi-card__bar {
    background: linear-gradient(90deg, #64748b, #94a3b8);
}

.kpi-card--static .kpi-card__shell-outer {
    box-shadow:
        0 1px 2px rgb(15 23 42 / 0.04),
        inset 0 0 0 1px rgb(226 232 240 / 0.95);
}

.kpi-card--interactive .kpi-card__shell-outer {
    box-shadow: inset 0 0 0 1px rgb(203 213 225 / 0.9);
    outline: 1px dashed rgb(203 213 225 / 0.85);
    outline-offset: -4px;
}

.kpi-card--interactive:hover .kpi-card__shell-outer {
    box-shadow:
        0 8px 24px rgb(15 23 42 / 0.08),
        inset 0 0 0 1px rgb(154 0 54 / 0.28);
    outline-color: rgb(154 0 54 / 0.35);
    outline-style: solid;
}

@keyframes kpi-shimmer {
    0% {
        transform: translateX(-120%);
        opacity: 0;
    }
    15% {
        opacity: 1;
    }
    100% {
        transform: translateX(220%);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .kpi-card--interactive:hover {
        transform: none;
    }

    .kpi-card--interactive:hover .kpi-card__shine {
        animation: none;
    }

    .kpi-card__bar {
        transition: none;
    }
}
</style>
