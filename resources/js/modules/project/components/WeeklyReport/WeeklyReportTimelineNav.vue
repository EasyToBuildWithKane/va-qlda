<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';

const props = defineProps({
    sprint: { type: Object, default: null },
    reports: { type: Array, default: () => [] },
    periodStart: { type: String, default: '' },
    periodEnd: { type: String, default: '' },
    activeReportId: { type: [Number, null], default: null },
    /** Tab Tổng quan — ẩn ô Phạm vi Sprint (chỉ đọc, thường «Ngoài Sprint»). */
    hideSprintScope: { type: Boolean, default: false },
});

const emit = defineEmits(['select-report', 'update-period']);

const sprintLabel = computed(() => props.sprint?.name || 'Ngoài Sprint');

function fmt(iso) {
    if (!iso) return '';
    const parts = String(iso).split('-');
    if (parts.length < 3) return '';
    return `${parts[2]}/${parts[1]}`;
}

function reportOptionLabel(r) {
    return `${fmt(r.week_start)} – ${fmt(r.week_end)}${r.status_label ? ` · ${r.status_label}` : ''}`;
}

function onReportChange(event) {
    const raw = event.target.value;
    emit('select-report', raw ? Number(raw) : null);
}

function onStartChange(iso) {
    let end = props.periodEnd;
    if (iso && end && iso > end) end = iso;
    emit('update-period', { start: iso || '', end });
}

function onEndChange(iso) {
    let start = props.periodStart;
    if (iso && start && iso < start) start = iso;
    emit('update-period', { start, end: iso || '' });
}
</script>

<template>
  <div class="wr-scope">
    <div
      class="grid min-w-0 grid-cols-1 gap-2.5"
      :class="hideSprintScope ? 'lg:grid-cols-2' : 'lg:grid-cols-3'"
    >
      <div
        v-if="!hideSprintScope"
        class="wr-scope__card"
      >
        <span
          class="wr-scope__icon"
          aria-hidden="true"
        >
          <AppIcon
            name="sprint"
            :size="16"
          />
        </span>
        <div class="min-w-0 flex-1">
          <span class="wr-scope__eyebrow">Phạm vi Sprint</span>
          <p
            class="truncate font-display text-sm font-semibold text-slate-800 dark:text-slate-100"
            :title="sprintLabel"
          >
            {{ sprintLabel }}
          </p>
        </div>
      </div>

      <div class="wr-scope__card wr-scope__card--interactive min-w-0 lg:col-span-2">
        <span
          class="wr-scope__icon"
          aria-hidden="true"
        >
          <AppIcon
            name="weekly"
            :size="16"
          />
        </span>
        <div class="min-w-0 flex-1 space-y-2">
          <span class="wr-scope__eyebrow">Kỳ báo cáo</span>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <FilterDatePicker
              :model-value="periodStart"
              placeholder="Từ ngày"
              @update:model-value="onStartChange"
            />
            <FilterDatePicker
              :model-value="periodEnd"
              placeholder="Đến ngày"
              @update:model-value="onEndChange"
            />
          </div>
          <div
            v-if="reports.length"
            class="relative"
          >
            <select
              class="wr-scope__select"
              :value="activeReportId ?? ''"
              aria-label="Báo cáo đã tạo"
              @change="onReportChange"
            >
              <option value="">
                Kỳ mới…
              </option>
              <option
                v-for="r in reports"
                :key="r.id"
                :value="r.id"
              >
                {{ reportOptionLabel(r) }}
              </option>
            </select>
            <span
              class="wr-scope__chevron"
              aria-hidden="true"
            >
              <AppIcon
                name="chevron-down"
                :size="14"
              />
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wr-scope {
  background: #fff;
  padding: 0.875rem 1.25rem 1rem;
}
.dark .wr-scope {
  background: #0f172a;
}
.wr-scope__card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-width: 0;
  border-radius: 0.75rem;
  border: 1px solid #e2e8f0;
  background: #fff;
  padding: 0.7rem 0.85rem;
}
.dark .wr-scope__card {
  border-color: rgb(51 65 85 / 0.8);
  background: rgb(15 23 42 / 0.7);
}
.wr-scope__card--interactive:focus-within {
  border-color: #9A0036;
  box-shadow: 0 0 0 3px color-mix(in srgb, #9A0036 16%, transparent);
}
.wr-scope__icon {
  display: grid;
  height: 2.25rem;
  width: 2.25rem;
  flex-shrink: 0;
  place-items: center;
  border-radius: 0.5rem;
  background: #f8fafc;
  color: #64748b;
}
.dark .wr-scope__icon {
  background: rgb(30 41 59 / 0.8);
  color: #94a3b8;
}
.wr-scope__eyebrow {
  display: block;
  margin-bottom: 0.15rem;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #94a3b8;
}
.wr-scope__select {
  display: block;
  width: 100%;
  appearance: none;
  background: transparent;
  padding: 0.15rem 1.4rem 0 0;
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.35;
  color: #0f172a;
  cursor: pointer;
}
.dark .wr-scope__select {
  color: #f8fafc;
}
.wr-scope__select:focus {
  outline: none;
}
.wr-scope__chevron {
  pointer-events: none;
  position: absolute;
  right: 0;
  top: 50%;
  display: grid;
  place-items: center;
  color: #64748b;
  transform: translateY(-50%);
}
@media (prefers-reduced-motion: reduce) {
  .wr-scope__card--interactive:focus-within {
    box-shadow: none;
  }
}
</style>
