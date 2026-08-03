<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ScoreSelector from '@/modules/daily-report/components/ScoreSelector.vue';

const props = defineProps({
    report: { type: Object, required: true },
    rubric: {
        type: Object,
        default: () => ({
            weights: {
                task_completion: 0.3,
                skill_score: 0.2,
                attitude_score: 0.15,
                expertise_score: 0.2,
            },
            kaizen_bonus_max: 2,
            source: 'system',
            department_code: null,
            department_name: null,
        }),
    },
});

const emit = defineEmits(['scored', 'rejected']);

const DIMENSIONS = [
    { key: 'task_completion', label: 'Hoàn thành', hint: 'Mức độ hoàn thành mục tiêu và khối lượng công việc đặt ra trong ngày.' },
    { key: 'skill_score', label: 'Kỹ năng', hint: 'Kỹ năng chuyên môn và cách xử lý công việc thể hiện trong báo cáo.' },
    { key: 'attitude_score', label: 'Thái độ', hint: 'Tinh thần trách nhiệm, chủ động, hợp tác và đúng hạn.' },
    { key: 'expertise_score', label: 'Chuyên môn', hint: 'Chiều sâu chuyên môn và chất lượng giải pháp đưa ra.' },
];

const form = useForm({
    task_completion: 8,
    skill_score: 8,
    attitude_score: 8,
    kaizen_score: 5,
    expertise_score: 8,
    notes: '',
});

const rejectForm = useForm({ notes: '' });

const weights = computed(() => props.rubric?.weights ?? {});
const weightSum = computed(() => {
    const w = weights.value;
    return Object.values(w).reduce((s, v) => s + Number(v || 0), 0) || 1;
});
const kaizenBonusMax = computed(() => Number(props.rubric?.kaizen_bonus_max ?? 2));

const pct = (key) => Math.round((Number(weights.value[key] || 0) / weightSum.value) * 100);
const contribution = (key) => Number(form[key] || 0) * (Number(weights.value[key] || 0) / weightSum.value);

const kaizenBonus = computed(() => (Number(form.kaizen_score || 0) / 10) * kaizenBonusMax.value);

const baseTotal = computed(() =>
    Object.keys(weights.value).reduce((s, k) => s + contribution(k), 0),
);

const total = computed(() => baseTotal.value + kaizenBonus.value);

const gradeInfo = computed(() => {
    const t = total.value;
    if (t >= 9) return { label: 'S', cls: 'bg-brand text-white' };
    if (t >= 8) return { label: 'A', cls: 'bg-emerald-500 text-white' };
    if (t >= 6.5) return { label: 'B', cls: 'bg-sky-500 text-white' };
    if (t >= 5) return { label: 'C', cls: 'bg-amber-400 text-white' };
    return { label: 'D', cls: 'bg-slate-400 text-white' };
});

const rubricHint = computed(() => {
    if (props.rubric?.source === 'department' && props.rubric?.department_code) {
        return `Theo cấu hình PB ${props.rubric.department_code}`;
    }
    return 'Mặc định hệ thống';
});

function submit({ onSuccess, onFinish, ...restOptions } = {}) {
    form.post(`/daily-reports/${props.report.id}/score`, {
        preserveScroll: true,
        ...restOptions,
        onSuccess: (...args) => {
            emit('scored', props.report.id);
            onSuccess?.(...args);
        },
        onFinish: (...args) => {
            onFinish?.(...args);
        },
    });
}

function reject(notes, { onSuccess, onFinish, ...restOptions } = {}) {
    rejectForm.notes = notes;
    rejectForm.post(`/daily-reports/${props.report.id}/reject`, {
        preserveScroll: true,
        ...restOptions,
        onSuccess: (...args) => {
            emit('rejected', props.report.id);
            onSuccess?.(...args);
        },
        onFinish: (...args) => {
            onFinish?.(...args);
        },
    });
}

/** Payload for bulk score using current panel values. */
function scorePayload() {
    return {
        task_completion: form.task_completion,
        skill_score: form.skill_score,
        attitude_score: form.attitude_score,
        kaizen_score: form.kaizen_score,
        expertise_score: form.expertise_score,
        notes: form.notes || null,
    };
}

defineExpose({ submit, reject, form, total, gradeInfo, scorePayload });
</script>

<template>
  <div class="space-y-4">
    <div class="sticky top-0 z-10 -mx-1 flex items-center justify-between gap-3 rounded-lg bg-white/95 px-1 py-2 backdrop-blur">
      <div class="min-w-0">
        <h3 class="font-display text-sm font-semibold text-slate-800">
          Chấm điểm
        </h3>
        <p class="truncate text-[11px] text-slate-400">
          {{ rubricHint }} · 4 tiêu chí + Kaizen tối đa +{{ kaizenBonusMax }}
        </p>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <span class="font-display text-2xl font-bold tabular-nums text-brand">{{ total.toFixed(2) }}</span>
        <span
          class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
          :class="gradeInfo.cls"
        >{{ gradeInfo.label }}</span>
      </div>
    </div>

    <div class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
      <div
        v-for="dim in DIMENSIONS"
        :key="dim.key"
        class="space-y-2 px-3 py-2.5"
      >
        <div class="flex min-w-0 items-center justify-between gap-2">
          <div class="flex min-w-0 items-center gap-2">
            <span class="text-sm font-medium text-slate-700">{{ dim.label }}</span>
            <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">{{ pct(dim.key) }}%</span>
          </div>
          <div
            v-if="form[dim.key]"
            class="flex shrink-0 items-center gap-1 text-xs"
          >
            <span class="tabular-nums text-slate-500">{{ form[dim.key] }} × {{ pct(dim.key) }}%</span>
            <span class="text-slate-400">=</span>
            <span class="font-semibold tabular-nums text-brand">{{ contribution(dim.key).toFixed(2) }}</span>
          </div>
        </div>
        <ScoreSelector
          v-model="form[dim.key]"
          compact
        />
        <p
          v-if="form.errors[dim.key]"
          class="text-xs text-danger"
        >
          {{ form.errors[dim.key] }}
        </p>
      </div>

      <div class="space-y-2 bg-emerald-50/50 px-3 py-2.5">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-emerald-800">Kaizen</span>
            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">+{{ kaizenBonusMax }} điểm</span>
          </div>
          <span class="text-xs font-semibold tabular-nums text-emerald-600">+{{ kaizenBonus.toFixed(2) }}</span>
        </div>
        <ScoreSelector
          v-model="form.kaizen_score"
          compact
        />
        <p
          v-if="form.errors.kaizen_score"
          class="text-xs text-danger"
        >
          {{ form.errors.kaizen_score }}
        </p>
      </div>
    </div>

    <div class="grid grid-cols-3 divide-x divide-slate-200 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
      <div class="px-3 py-2.5 text-center">
        <p class="text-[10px] uppercase tracking-wide text-slate-500">
          Cơ sở
        </p>
        <p class="mt-0.5 font-display text-lg font-bold tabular-nums text-slate-700">
          {{ baseTotal.toFixed(2) }}
        </p>
      </div>
      <div class="px-3 py-2.5 text-center">
        <p class="text-[10px] uppercase tracking-wide text-emerald-600">
          Kaizen
        </p>
        <p class="mt-0.5 font-display text-lg font-bold tabular-nums text-emerald-600">
          +{{ kaizenBonus.toFixed(2) }}
        </p>
      </div>
      <div class="px-3 py-2.5 text-center">
        <p class="text-[10px] uppercase tracking-wide text-brand/80">
          Cuối
        </p>
        <div class="mt-0.5 flex items-center justify-center gap-1.5">
          <p class="font-display text-lg font-bold tabular-nums text-brand">
            {{ total.toFixed(2) }}
          </p>
          <span
            class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
            :class="gradeInfo.cls"
          >{{ gradeInfo.label }}</span>
        </div>
      </div>
    </div>

    <div>
      <label
        for="scoring-notes"
        class="label text-sm"
      >
        Nhận xét
        <span class="font-normal text-slate-400">(không bắt buộc)</span>
      </label>
      <textarea
        id="scoring-notes"
        v-model="form.notes"
        rows="2"
        class="input mt-1.5 resize-none text-sm"
        placeholder="Ghi nhận điểm tốt và góp ý để thành viên cải thiện…"
      />
      <p
        v-if="form.errors.notes"
        class="mt-1 text-xs text-danger"
      >
        {{ form.errors.notes }}
      </p>
    </div>
  </div>
</template>
