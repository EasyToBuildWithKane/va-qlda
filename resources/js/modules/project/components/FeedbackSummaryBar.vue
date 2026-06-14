<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    activeScope: { type: String, default: '' },
    activeStatus: { type: String, default: '' },
});

const emit = defineEmits(['quick-filter']);

const toneClass = {
    brand: 'kpi-card--brand',
    emerald: 'kpi-card--emerald',
    amber: 'kpi-card--amber',
    sky: 'kpi-card--sky',
    violet: 'kpi-card--violet',
};

const iconToneClass = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
};

const cards = computed(() => {
    const s = props.summary;
    const total = s.total ?? 0;
    const open = s.open ?? 0;
    const pct = (n) => (total > 0 ? Math.round((n / total) * 100) : 0);

    return [
        {
            key: 'total',
            label: 'Tổng phản hồi',
            field: 'total',
            tone: 'brand',
            icon: 'feedback',
            sub: total ? 'Toàn hệ thống' : 'Chưa có phản hồi',
            progress: null,
            filter: { scope: '', status: '' },
        },
        {
            key: 'open',
            label: 'Đang xử lý',
            field: 'open',
            tone: 'sky',
            icon: 'sprint',
            sub: total ? `${pct(open)}% chưa kết thúc` : 'Bấm để lọc',
            progress: pct(open),
            filter: { scope: 'open', status: '' },
        },
        {
            key: 'new',
            label: 'Mới',
            field: 'new',
            tone: 'violet',
            icon: 'add',
            sub: total ? `${pct(s.new ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.new ?? 0),
            filter: { scope: '', status: 'new' },
        },
        {
            key: 'resolved',
            label: 'Đã xử lý',
            field: 'resolved',
            tone: 'emerald',
            icon: 'done',
            sub: total ? `${pct(s.resolved ?? 0)}% tổng` : 'Bấm để lọc',
            progress: pct(s.resolved ?? 0),
            filter: { scope: '', status: 'resolved' },
        },
        {
            key: 'rating',
            label: 'Đánh giá TB',
            field: 'avg_rating',
            tone: 'amber',
            icon: 'star',
            format: 'rating',
            sub: 'Thang 1–5 sao từ người gửi',
            progress: null,
            filter: null,
        },
    ];
});

function displayValue(summary, card) {
    if (card.format === 'rating') {
        const v = summary.avg_rating;
        return v != null && v !== '' ? v : '—';
    }
    return summary[card.field] ?? 0;
}

function isInteractive(card) {
    return Boolean(card.filter);
}

function isActive(card) {
    if (!card.filter) return false;
    if (card.filter.scope === 'open') return props.activeScope === 'open';
    if (card.filter.status === '') return !props.activeScope && !props.activeStatus;
    return props.activeStatus === card.filter.status && !props.activeScope;
}

function onCard(card) {
    if (!card.filter) return;
    emit('quick-filter', card.filter);
}
</script>

<template>
  <section
    class="kpi-strip relative mb-5 overflow-x-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm sm:px-5 sm:py-5"
    aria-label="Thống kê phản hồi"
  >
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
          Tổng quan phản hồi hệ thống
        </h2>
      </div>
      <p class="text-[11px] text-slate-500">
        Thẻ có viền nét đứt — bấm để lọc nhanh danh sách
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
        @click="onCard(card)"
      >
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
                <span
                  v-if="card.format === 'rating' && summary.avg_rating"
                  class="text-lg text-amber-500"
                >★</span>
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

.kpi-card--sky .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(14 165 233 / 0.16), rgb(14 165 233 / 0.04));
}

.kpi-card--sky .kpi-card__bar {
    background: linear-gradient(90deg, #0284c7, #38bdf8);
}

.kpi-card--violet .kpi-card__shell-accent {
    background: linear-gradient(120deg, rgb(139 92 246 / 0.16), rgb(139 92 246 / 0.04));
}

.kpi-card--violet .kpi-card__bar {
    background: linear-gradient(90deg, #7c3aed, #a78bfa);
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
