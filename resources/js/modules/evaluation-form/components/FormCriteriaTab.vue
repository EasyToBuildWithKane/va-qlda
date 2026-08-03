<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import SearchMultiSelect from '@/shared/ui/SearchMultiSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';

const form = defineModel('form', { type: Object, required: true });

const props = defineProps({
    criteriaOptions: { type: Array, default: () => [] },
});

const raterOptions = computed(() => (form.value.raters || []).map((r, index) => ({
    value: r.role_key || `rater_${index}`,
    label: r.label || r.role_key || `Hội đồng ${index + 1}`,
})));

const weightSum = computed(() => (form.value.criteria || []).reduce(
    (sum, row) => sum + (Number(row.weight) || 0),
    0,
));

const filledCount = computed(() => (form.value.criteria || []).filter(
    (c) => c.criterion_id || (c.name && c.name.trim()),
).length);

function scoreLevelsFor(row) {
    if (row.score_levels?.length) return row.score_levels;
    const opt = props.criteriaOptions.find((c) => c.id === row.criterion_id);
    return opt?.score_levels || [];
}

function onCriterionPick(row, criterionId) {
    row.criterion_id = criterionId;
    const opt = props.criteriaOptions.find((c) => c.id === criterionId);
    if (!opt) {
        if (!criterionId) {
            row.score_levels = [];
            row.required_score_label = null;
        }
        return;
    }
    row.name = opt.criteria_name;
    row.score_levels = opt.score_levels || [];
    const match = (opt.score_levels || []).find(
        (l) => String(l.label || '').toLowerCase().includes('đạt'),
    );
    row.required_score_label = match?.label || opt.score_levels?.[0]?.label || null;
}

function onEvaluatorKeys(row, keys) {
    const next = Array.isArray(keys) ? keys.filter(Boolean) : [];
    row.evaluator_role_keys = next;
    row.evaluator_mode = next.length === 0 ? 'all' : 'tags';
}

function addRow() {
    form.value.criteria.push({
        criterion_id: null,
        name: '',
        weight: 0,
        required_score_label: null,
        evaluator_mode: 'all',
        evaluator_role_keys: [],
        sort_order: form.value.criteria.length,
        score_levels: [],
    });
}

function removeRow(index) {
    form.value.criteria.splice(index, 1);
}

function distributeWeights() {
    const rows = form.value.criteria;
    const n = rows.length;
    if (n === 0) return;
    const base = Math.floor(100 / n);
    let remain = 100;
    rows.forEach((row, i) => {
        if (i === n - 1) {
            row.weight = remain;
        } else {
            row.weight = base;
            remain -= base;
        }
    });
}

const weightOptions = Array.from({ length: 10 }, (_, i) => (i + 1) * 10);
</script>

<template>
  <section>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">
          Tiêu chí đánh giá
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Chọn tiêu chí năng lực, trọng số và hội đồng chấm từng mục
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-4 text-xs tabular-nums text-slate-500">
          <span>
            <span class="font-medium text-slate-700">{{ filledCount }}</span>
            /{{ form.criteria.length }} tiêu chí
          </span>
          <span>
            Tổng trọng số
            <span
              class="ml-1 font-display text-sm font-semibold"
              :class="weightSum === 100 ? 'text-emerald-600' : weightSum > 100 ? 'text-rose-600' : 'text-amber-600'"
            >
              {{ weightSum }}%
            </span>
          </span>
        </div>
        <button
          v-if="form.criteria.length > 0"
          type="button"
          class="btn-ghost h-9 px-2.5 text-xs"
          title="Chia đều trọng số 100%"
          @click="distributeWeights"
        >
          Chia đều
        </button>
      </div>
    </div>

    <div
      v-if="form.criteria.length > 0"
      class="mb-4 h-1.5 overflow-hidden rounded-full bg-slate-100"
    >
      <div
        class="h-full rounded-full transition-all duration-300"
        :class="weightSum === 100 ? 'bg-emerald-500' : weightSum > 100 ? 'bg-rose-400' : 'bg-amber-400'"
        :style="{ width: `${Math.min(weightSum, 100)}%` }"
      />
    </div>

    <div
      v-if="form.criteria.length === 0"
      class="flex flex-col items-center justify-center rounded-xl bg-slate-50 px-4 py-12 text-center"
    >
      <div class="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-300 shadow-sm">
        <AppIcon
          name="list"
          :size="20"
        />
      </div>
      <p class="text-sm font-medium text-slate-700">
        Chưa có tiêu chí nào
      </p>
      <p class="mt-1 max-w-sm text-xs text-slate-400">
        Thêm tiêu chí thủ công hoặc chọn mẫu đánh giá ở tab Thông tin chung để tự điền.
      </p>
      <button
        type="button"
        class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-xs font-medium text-white"
        @click="addRow"
      >
        <AppIcon
          name="plus"
          :size="14"
        />
        Thêm tiêu chí đầu tiên
      </button>
    </div>

    <div
      v-else
      class="space-y-0"
    >
      <div
        v-for="(row, index) in form.criteria"
        :key="index"
        class="group border-b border-slate-100 py-4 first:pt-0 last:border-b-0"
      >
        <div class="mb-3 flex items-center justify-between gap-2">
          <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 text-[11px] tabular-nums text-slate-600">
              {{ index + 1 }}
            </span>
            Tiêu chí {{ index + 1 }}
          </span>
          <button
            type="button"
            class="rounded p-1 text-slate-300 transition hover:bg-rose-50 hover:text-rose-500"
            title="Xóa tiêu chí"
            @click="removeRow(index)"
          >
            <AppIcon
              name="close"
              :size="14"
            />
          </button>
        </div>

        <div class="grid grid-cols-1 gap-x-4 gap-y-3 md:grid-cols-2 xl:grid-cols-12">
          <div class="xl:col-span-5">
            <label class="mb-1.5 block text-xs font-medium text-slate-600">
              Tiêu chí năng lực <span class="text-rose-500">*</span>
            </label>
            <SearchSelect
              :model-value="row.criterion_id"
              :options="criteriaOptions"
              value-key="id"
              label-key="label"
              :search-keys="['criteria_name', 'criteria_code', 'label', 'category']"
              placeholder="Tìm tiêu chí năng lực…"
              @update:model-value="(v) => onCriterionPick(row, v)"
            />
            <input
              v-if="!row.criterion_id"
              v-model="row.name"
              type="text"
              class="input mt-2 h-10 w-full text-sm"
              placeholder="Hoặc nhập tiêu chí tùy chỉnh"
            >
          </div>

          <div class="xl:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600">Trọng số</label>
            <select
              v-model.number="row.weight"
              class="input h-10 w-full text-sm"
            >
              <option :value="0">
                Chưa chọn
              </option>
              <option
                v-for="w in weightOptions"
                :key="w"
                :value="w"
              >
                {{ w }}%
              </option>
            </select>
          </div>

          <div class="xl:col-span-2">
            <label class="mb-1.5 block text-xs font-medium text-slate-600">
              Điểm yêu cầu <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="row.required_score_label"
              class="input h-10 w-full text-sm"
              :disabled="scoreLevelsFor(row).length === 0"
            >
              <option :value="null">
                {{ scoreLevelsFor(row).length ? 'Chọn điểm' : 'Chọn tiêu chí trước' }}
              </option>
              <option
                v-for="level in scoreLevelsFor(row)"
                :key="level.code || level.label"
                :value="level.label"
              >
                {{ level.label }}
              </option>
            </select>
          </div>

          <div class="xl:col-span-3">
            <label class="mb-1.5 block text-xs font-medium text-slate-600">
              Người đánh giá
            </label>
            <SearchMultiSelect
              :model-value="row.evaluator_role_keys || []"
              :options="raterOptions"
              value-key="value"
              label-key="label"
              :search-keys="['label', 'value']"
              placeholder="Tất cả hội đồng"
              control-size="md"
              :max-chips="2"
              @update:model-value="(keys) => onEvaluatorKeys(row, keys)"
            />
            <p class="mt-1 text-[11px] text-slate-400">
              Để trống = tất cả hội đồng
            </p>
          </div>
        </div>
      </div>
    </div>

    <button
      v-if="form.criteria.length > 0"
      type="button"
      class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-xs font-medium text-white transition hover:bg-brand/90"
      @click="addRow"
    >
      <AppIcon
        name="plus"
        :size="14"
      />
      Thêm tiêu chí
    </button>
  </section>
</template>
