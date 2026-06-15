<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    cards: { type: Array, required: true },
    activeKey: { type: String, default: '' },
    variant: {
        type: String,
        default: 'standalone',
        validator: (v) => ['standalone', 'embedded'].includes(v),
    },
    ariaLabel: { type: String, required: true },
    eyebrow: { type: String, default: 'Thống kê' },
    heading: { type: String, required: true },
    hint: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    loadingText: { type: String, default: 'Đang tải thống kê…' },
    progressDenominator: { type: Number, default: 0 },
    gridClass: {
        type: String,
        default: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
    },
    hideHeader: { type: Boolean, default: false },
    shellClass: { type: String, default: '' },
});

const emit = defineEmits(['select']);

const toneClass = {
    brand: 'kpi-card--brand',
    emerald: 'kpi-card--emerald',
    amber: 'kpi-card--amber',
    sky: 'kpi-card--sky',
    violet: 'kpi-card--violet',
    rose: 'kpi-card--rose',
    slate: 'kpi-card--slate',
};

const iconToneClass = {
    brand: 'text-brand bg-brand/10 ring-brand/20',
    emerald: 'text-emerald-700 bg-emerald-50 ring-emerald-200/80',
    amber: 'text-amber-700 bg-amber-50 ring-amber-200/80',
    sky: 'text-sky-700 bg-sky-50 ring-sky-200/80',
    violet: 'text-violet-700 bg-violet-50 ring-violet-200/80',
    rose: 'text-rose-700 bg-rose-50 ring-rose-200/80',
    slate: 'text-slate-600 bg-slate-100 ring-slate-200/80',
};

const stripClass = computed(() => {
    if (props.shellClass) {
        return props.shellClass;
    }
    return props.variant === 'embedded'
        ? 'kpi-strip relative shrink-0 overflow-x-hidden border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 sm:px-5 sm:py-5'
        : 'kpi-strip relative mb-5 overflow-x-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm sm:px-5 sm:py-5';
});

function isInteractive(card) {
    return Boolean(card.interactive);
}

function isActive(card) {
    return props.activeKey === card.key;
}

function onCard(card) {
    if (!isInteractive(card)) return;
    emit('select', card);
}

function trendClass(tone) {
    if (tone === 'good') return 'kpi-card__trend--good';
    if (tone === 'bad') return 'kpi-card__trend--bad';
    return 'kpi-card__trend--neutral';
}

function showProgress(card) {
    return card.progress != null && props.progressDenominator > 0;
}
</script>

<template>
  <section
    v-if="loading"
    class="mb-5 rounded-card border border-slate-200/80 bg-white px-5 py-8 text-center text-sm text-slate-500 shadow-sm"
  >
    {{ loadingText }}
  </section>

  <section
    v-else
    :class="stripClass"
    :aria-label="ariaLabel"
  >
    <div
      class="pointer-events-none absolute inset-0 opacity-[0.45]"
      aria-hidden="true"
    >
      <div class="kpi-strip__bg-outer absolute -right-8 top-0 h-32 w-[min(55%,22rem)] bg-brand/[0.06]" />
      <div class="kpi-strip__bg-inner absolute -right-4 top-2 h-24 w-[min(45%,18rem)] bg-brand/[0.04]" />
    </div>

    <header
      v-if="!hideHeader"
      class="relative mb-3 flex flex-wrap items-end justify-between gap-2"
    >
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          {{ eyebrow }}
        </p>
        <h2 class="font-display text-sm font-semibold text-slate-800">
          {{ heading }}
        </h2>
      </div>
      <p
        v-if="hint"
        class="text-[11px] text-slate-500"
      >
        {{ hint }}
      </p>
    </header>

    <div
      class="relative grid gap-3 pb-1"
      :class="gridClass"
    >
      <component
        :is="isInteractive(card) ? 'button' : 'div'"
        v-for="card in cards"
        :key="card.key"
        type="button"
        class="kpi-card group relative min-h-[6.75rem] text-left outline-none transition-[transform,box-shadow] duration-300 ease-out"
        :class="[
          toneClass[card.tone] ?? toneClass.brand,
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
                <slot
                  name="value"
                  :card="card"
                >
                  {{ card.value }}
                  <span
                    v-if="card.suffix"
                    class="text-lg"
                    :class="card.suffixClass ?? ''"
                  >{{ card.suffix }}</span>
                </slot>
              </p>
            </div>
            <span
              v-if="card.icon"
              class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ring-1 transition-transform duration-300 group-hover:scale-105"
              :class="iconToneClass[card.tone] ?? iconToneClass.brand"
            >
              <AppIcon
                :name="card.icon"
                :size="18"
              />
            </span>
          </div>

          <div
            v-if="card.sub || card.trend"
            class="mt-auto pt-2"
          >
            <p
              v-if="card.sub"
              class="text-[11px] leading-snug text-slate-500"
            >
              {{ card.sub }}
            </p>
            <span
              v-if="card.trend?.text"
              class="mt-1.5 inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[10px] font-semibold tabular-nums"
              :class="trendClass(card.trend.tone)"
            >
              <span
                v-if="card.trend.arrow"
                aria-hidden="true"
              >{{ card.trend.arrow }}</span>
              {{ card.trend.text }}
            </span>
          </div>

          <slot
            name="footer"
            :card="card"
          />

          <div
            v-if="showProgress(card)"
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

<style src="@/shared/styles/kpi-summary-strip.css"></style>
