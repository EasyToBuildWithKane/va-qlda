<script setup>
import { computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

const props = defineProps({
    departments: { type: Array, default: () => [] },
    departmentCode: { type: String, default: null },
    departmentName: { type: String, default: null },
    config: { type: Object, default: null },
    rubric: { type: Object, required: true },
    systemDefaults: { type: Object, required: true },
    dimensionLabels: { type: Object, default: () => ({}) },
    viewer: {
        type: Object,
        default: () => ({
            can_manage_all: true,
            can_manage: false,
            own_department_code: null,
            forced_department_code: null,
        }),
    },
    can: { type: Object, default: () => ({ manage: false }) },
});

const DIMENSION_KEYS = [
    'task_completion',
    'skill_score',
    'attitude_score',
    'expertise_score',
];

const form = useForm({
    department_code: props.departmentCode ?? '',
    department_name: props.departmentName ?? '',
    local_department_id: props.config?.local_department_id ?? null,
    weights: {
        task_completion: Number(props.rubric?.weights?.task_completion ?? 0.3),
        skill_score: Number(props.rubric?.weights?.skill_score ?? 0.2),
        attitude_score: Number(props.rubric?.weights?.attitude_score ?? 0.15),
        expertise_score: Number(props.rubric?.weights?.expertise_score ?? 0.2),
    },
    kaizen_bonus_max: Number(props.rubric?.kaizen_bonus_max ?? 2),
    status: props.config?.status ?? 'active',
});

watch(
    () => props.departmentCode,
    (code) => {
        form.department_code = code ?? '';
        form.department_name = props.departmentName ?? '';
        form.local_department_id = props.config?.local_department_id ?? null;
        form.weights = {
            task_completion: Number(props.rubric?.weights?.task_completion ?? 0.3),
            skill_score: Number(props.rubric?.weights?.skill_score ?? 0.2),
            attitude_score: Number(props.rubric?.weights?.attitude_score ?? 0.15),
            expertise_score: Number(props.rubric?.weights?.expertise_score ?? 0.2),
        };
        form.kaizen_bonus_max = Number(props.rubric?.kaizen_bonus_max ?? 2);
        form.status = props.config?.status ?? 'active';
    },
);

const weightSum = computed(() =>
    DIMENSION_KEYS.reduce((sum, key) => sum + (Number(form.weights[key]) || 0), 0),
);

function pct(key) {
    const sum = weightSum.value;
    if (sum <= 0) return 0;
    return Math.round(((Number(form.weights[key]) || 0) / sum) * 100);
}

const canManage = computed(() => props.can?.manage === true);
const canPickDept = computed(() => props.viewer?.can_manage_all !== false && !props.viewer?.forced_department_code);

const sourceLabel = computed(() => {
    if (props.config) return 'Đang dùng cấu hình phòng ban';
    if (props.departmentCode) return 'Chưa lưu — đang xem mặc định hệ thống';
    return 'Chọn phòng ban để cấu hình';
});

const pageTitle = computed(() => {
    if (props.departmentName) return `Trọng số BC ngày · ${props.departmentName}`;
    return 'Trọng số báo cáo ngày';
});

function selectDepartment(code) {
    router.get('/workspace-config/daily-report-scoring', {
        department_code: code || undefined,
    }, {
        preserveState: false,
        preserveScroll: true,
    });
}

function restoreDefaults() {
    const d = props.systemDefaults;
    form.weights = {
        task_completion: Number(d.weights?.task_completion ?? 0.3),
        skill_score: Number(d.weights?.skill_score ?? 0.2),
        attitude_score: Number(d.weights?.attitude_score ?? 0.15),
        expertise_score: Number(d.weights?.expertise_score ?? 0.2),
    };
    form.kaizen_bonus_max = Number(d.kaizen_bonus_max ?? 2);
}

function submit() {
    if (!canManage.value || !form.department_code) return;
    form.put('/workspace-config/daily-report-scoring', {
        preserveScroll: true,
    });
}
</script>

<template>
  <Head :title="pageTitle" />

  <AppLayout>
    <template #header>
      <PageHeader
        :title="pageTitle"
        :subtitle="sourceLabel"
        icon="daily"
        icon-color="brand"
        back-href="/workspace-config"
      >
        <button
          v-if="canManage && departmentCode"
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="restoreDefaults"
        >
          <AppIcon
            name="refresh"
            :size="15"
          />
          Khôi phục mặc định hệ thống
        </button>
        <button
          v-if="canManage && departmentCode"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          :disabled="form.processing"
          @click="submit"
        >
          <AppIcon
            name="check"
            :size="15"
          />
          Lưu trọng số
        </button>
      </PageHeader>
    </template>

    <div class="mx-auto max-w-3xl space-y-5">
      <div
        v-if="canPickDept"
        class="card p-5"
      >
        <label class="mb-1.5 block text-xs font-medium text-slate-600">
          Phòng ban
        </label>
        <select
          class="input h-10 w-full text-sm"
          :value="departmentCode || ''"
          @change="selectDepartment($event.target.value)"
        >
          <option value="">
            Chọn phòng ban…
          </option>
          <option
            v-for="d in departments"
            :key="d.code"
            :value="d.code"
          >
            {{ d.name }} ({{ d.code }})
          </option>
        </select>
      </div>

      <div
        v-if="!departmentCode"
        class="card px-6 py-12 text-center"
      >
        <p class="text-sm text-slate-500">
          Chọn phòng ban để xem và chỉnh trọng số chấm báo cáo ngày.
        </p>
      </div>

      <template v-else>
        <section
          class="card overflow-hidden"
          aria-label="Trọng số tiêu chí"
        >
          <div class="border-b border-slate-100 bg-gradient-to-b from-slate-50/90 to-white px-5 py-4">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
              Chấm điểm báo cáo ngày
            </p>
            <h2 class="mt-0.5 font-display text-base font-semibold text-slate-800">
              Bốn tiêu chí chính (chuẩn hóa %) + Kaizen cộng thêm
            </h2>
            <p class="mt-1 text-xs text-slate-500">
              Grade S/A/B/C/D vẫn dùng ngưỡng toàn hệ thống. Chỉ trọng số và trần Kaizen khác theo phòng ban.
            </p>
          </div>

          <div class="divide-y divide-slate-100">
            <div
              v-for="key in DIMENSION_KEYS"
              :key="key"
              class="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-slate-700">
                    {{ dimensionLabels[key] || key }}
                  </span>
                  <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-slate-500">
                    {{ pct(key) }}%
                  </span>
                </div>
                <p
                  v-if="form.errors[`weights.${key}`]"
                  class="mt-1 text-xs text-danger"
                >
                  {{ form.errors[`weights.${key}`] }}
                </p>
              </div>
              <div class="flex w-full items-center gap-3 sm:w-56">
                <input
                  v-model.number="form.weights[key]"
                  type="number"
                  min="0.01"
                  max="100"
                  step="0.01"
                  class="input h-10 w-full text-sm tabular-nums"
                  :disabled="!canManage"
                >
              </div>
            </div>

            <div class="flex flex-col gap-2 bg-emerald-50/40 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <span class="text-sm font-medium text-emerald-800">Kaizen — điểm cộng tối đa</span>
                <p class="mt-0.5 text-xs text-emerald-700/80">
                  Slider 0–10 quy đổi thành tối đa +{{ form.kaizen_bonus_max }} điểm
                </p>
                <p
                  v-if="form.errors.kaizen_bonus_max"
                  class="mt-1 text-xs text-danger"
                >
                  {{ form.errors.kaizen_bonus_max }}
                </p>
              </div>
              <input
                v-model.number="form.kaizen_bonus_max"
                type="number"
                min="0"
                max="5"
                step="0.1"
                class="input h-10 w-full text-sm tabular-nums sm:w-56"
                :disabled="!canManage"
              >
            </div>
          </div>
        </section>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div
            v-for="key in DIMENSION_KEYS"
            :key="`bar-${key}`"
            class="rounded-xl border border-slate-200 bg-white px-3 py-3 text-center"
          >
            <p class="text-[10px] uppercase tracking-wide text-slate-500">
              {{ dimensionLabels[key] || key }}
            </p>
            <p class="mt-1 font-display text-xl font-bold tabular-nums text-brand">
              {{ pct(key) }}%
            </p>
          </div>
        </div>

        <p
          v-if="!canManage"
          class="text-center text-xs text-slate-500"
        >
          Bạn chỉ có quyền xem. Liên hệ super admin để chỉnh trọng số.
        </p>
      </template>
    </div>
  </AppLayout>
</template>
