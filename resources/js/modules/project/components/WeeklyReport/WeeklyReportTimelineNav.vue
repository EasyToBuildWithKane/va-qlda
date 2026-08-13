<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    sprint: { type: Object, default: null },
    weeks: { type: Array, default: () => [] },
    currentWeek: { type: Number, default: 1 },
    activeReportId: { type: [Number, null], default: null },
    pendingWeek: { type: [Number, null], default: null },
    /** Tab Tổng quan — ẩn ô Phạm vi Sprint (chỉ đọc, thường «Ngoài Sprint»). */
    hideSprintScope: { type: Boolean, default: false },
    /** { mode: 'llm'|'heuristic', provider?, model? } */
    engine: { type: Object, default: () => ({ mode: 'heuristic' }) },
});

const emit = defineEmits(['select']);

const sprintLabel = computed(() => props.sprint?.name || 'Ngoài Sprint');
const isOutsideSprint = computed(() => !props.sprint);
const llmEnabled = computed(() => props.engine?.mode === 'llm');

const selectedWeekNumber = computed(() => {
    if (props.pendingWeek != null) return props.pendingWeek;
    if (props.activeReportId) {
        const match = props.weeks.find((w) => w.report_id === props.activeReportId);
        if (match) return match.week_number;
    }
    return props.currentWeek;
});

const activeWeek = computed(() => props.weeks.find((w) => w.week_number === selectedWeekNumber.value) ?? null);

function fmt(d) {
    if (!d) return '';
    const [, m, day] = d.split('-');
    return `${day}/${m}`;
}

function isDone(status) {
    return status === 'submitted' || status === 'approved';
}

function weekOptionLabel(w) {
    const range = `${fmt(w.week_start)} – ${fmt(w.week_end)}`;
    if (isDone(w.status)) return `Tuần ${w.week_number} · ${range} · Đã gửi/duyệt`;
    if (w.report_id) return `Tuần ${w.week_number} · ${range} · Đã tạo`;
    return `Tuần ${w.week_number} · ${range} · Chưa tạo`;
}

function onWeekChange(event) {
    const num = Number(event.target.value);
    const week = props.weeks.find((w) => w.week_number === num);
    if (week) emit('select', week);
}
</script>

<template>
  <div class="wr-scope">
    <div class="wr-scope__accent" />
    <div class="flex flex-col gap-3 lg:flex-row lg:items-stretch lg:justify-between">
      <div
        class="grid min-w-0 flex-1 grid-cols-1 gap-2.5"
        :class="hideSprintScope ? 'sm:max-w-md' : 'sm:grid-cols-2 lg:max-w-3xl'"
      >
        <div
          v-if="!hideSprintScope"
          class="wr-scope__card"
          :class="isOutsideSprint ? 'wr-scope__card--warn' : ''"
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
            <p
              v-if="isOutsideSprint"
              class="mt-0.5 text-[11px] leading-snug text-amber-700 dark:text-amber-300"
            >
              Dự án chưa gán Sprint — báo cáo theo tuần lịch hiện tại.
            </p>
          </div>
        </div>

        <label class="wr-scope__card wr-scope__card--interactive min-w-0">
          <span
            class="wr-scope__icon wr-scope__icon--brand"
            aria-hidden="true"
          >
            <AppIcon
              name="weekly"
              :size="16"
            />
          </span>
          <div class="min-w-0 flex-1">
            <span class="wr-scope__eyebrow">Tuần báo cáo</span>
            <div class="relative">
              <select
                class="wr-scope__select"
                :value="selectedWeekNumber"
                aria-label="Chọn tuần báo cáo"
                @change="onWeekChange"
              >
                <option
                  v-for="w in weeks"
                  :key="w.week_number"
                  :value="w.week_number"
                >
                  {{ weekOptionLabel(w) }}
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
        </label>
      </div>

      <div
        v-if="activeWeek"
        class="flex flex-wrap items-center gap-1.5 lg:max-w-xs lg:justify-end lg:self-center"
      >
        <span
          v-if="activeWeek.week_number === currentWeek"
          class="wr-scope__pill wr-scope__pill--amber"
        >
          Tuần hiện tại
        </span>
        <span
          v-if="isDone(activeWeek.status)"
          class="wr-scope__pill wr-scope__pill--emerald"
        >
          <AppIcon
            name="check"
            :size="11"
          />
          Đã gửi hoặc duyệt
        </span>
        <span
          v-else-if="activeWeek.report_id"
          class="wr-scope__pill wr-scope__pill--sky"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-sky-500" />
          Đã có báo cáo
        </span>
        <span
          v-else
          class="wr-scope__pill wr-scope__pill--slate"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-slate-400" />
          Chưa tạo báo cáo
        </span>
        <span
          class="wr-scope__pill"
          :class="llmEnabled ? 'wr-scope__pill--ai' : 'wr-scope__pill--slate'"
          :title="llmEnabled
            ? `Tổng hợp bằng ${engine.provider || 'AI'} (${engine.model || 'mô hình đã cấu hình'})`
            : 'Tổng hợp từ dữ liệu Sprint (chưa cấu hình API key AI)'"
        >
          <AppIcon
            name="sparkles"
            :size="11"
          />
          {{ llmEnabled ? 'Tổng hợp bằng AI' : 'Tổng hợp nội bộ' }}
        </span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wr-scope {
  position: relative;
  overflow: hidden;
  background:
    linear-gradient(180deg, color-mix(in srgb, #9A0036 7%, white) 0%, white 72%);
  padding: 0.875rem 1.25rem 1rem;
}
.dark .wr-scope {
  background:
    linear-gradient(180deg, color-mix(in srgb, #9A0036 18%, #0f172a) 0%, #0f172a 78%);
}
.wr-scope__accent {
  position: absolute;
  inset: 0 0 auto;
  height: 3px;
  background: linear-gradient(90deg, #9A0036 0%, #c43a62 42%, transparent 100%);
}
.wr-scope__card {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  min-width: 0;
  border-radius: 0.85rem;
  border: 1px solid rgb(226 232 240 / 0.9);
  background: rgb(255 255 255 / 0.88);
  padding: 0.7rem 0.85rem;
  box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
}
.dark .wr-scope__card {
  border-color: rgb(51 65 85 / 0.8);
  background: rgb(15 23 42 / 0.7);
}
.wr-scope__card--warn {
  border-color: rgb(253 186 116 / 0.7);
  background: rgb(255 251 235 / 0.9);
}
.dark .wr-scope__card--warn {
  border-color: rgb(180 83 9 / 0.5);
  background: rgb(69 26 3 / 0.35);
}
.wr-scope__card--interactive {
  border-color: color-mix(in srgb, #9A0036 22%, #e2e8f0);
  box-shadow: 0 0 0 1px color-mix(in srgb, #9A0036 8%, transparent);
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
  border-radius: 0.65rem;
  background: #f1f5f9;
  color: #64748b;
}
.dark .wr-scope__icon {
  background: rgb(30 41 59 / 0.8);
  color: #94a3b8;
}
.wr-scope__icon--brand {
  background: color-mix(in srgb, #9A0036 12%, white);
  color: #9A0036;
}
.dark .wr-scope__icon--brand {
  background: color-mix(in srgb, #9A0036 28%, #0f172a);
  color: #f9a8c0;
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
  padding: 0 1.4rem 0 0;
  font-family: inherit;
  font-size: 0.875rem;
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
  color: #9A0036;
  transform: translateY(-50%);
}
.wr-scope__pill {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  border-radius: 999px;
  padding: 0.2rem 0.55rem;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.wr-scope__pill--amber {
  background: #fef3c7;
  color: #92400e;
}
.dark .wr-scope__pill--amber {
  background: rgb(69 26 3 / 0.7);
  color: #fde68a;
}
.wr-scope__pill--emerald {
  background: #d1fae5;
  color: #047857;
}
.dark .wr-scope__pill--emerald {
  background: rgb(6 78 59 / 0.55);
  color: #6ee7b7;
}
.wr-scope__pill--sky {
  background: #e0f2fe;
  color: #0369a1;
}
.dark .wr-scope__pill--sky {
  background: rgb(12 74 110 / 0.55);
  color: #7dd3fc;
}
.wr-scope__pill--slate {
  background: #f1f5f9;
  color: #64748b;
}
.dark .wr-scope__pill--slate {
  background: rgb(30 41 59 / 0.8);
  color: #94a3b8;
}
.wr-scope__pill--ai {
  background: color-mix(in srgb, #9A0036 12%, white);
  color: #9A0036;
}
.dark .wr-scope__pill--ai {
  background: color-mix(in srgb, #9A0036 32%, #0f172a);
  color: #f9a8c0;
}
@media (prefers-reduced-motion: reduce) {
  .wr-scope__card--interactive:focus-within {
    box-shadow: none;
  }
}
</style>
