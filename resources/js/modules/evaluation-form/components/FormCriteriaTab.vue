<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const form = defineModel('form', { type: Object, required: true });

const props = defineProps({
    criteriaOptions: { type: Array, default: () => [] },
});

const raterTags = computed(() => (form.value.raters || []).map((r) => ({
    value: r.role_key,
    label: r.label || r.role_key,
})));

function scoreLevelsFor(row) {
    if (row.score_levels?.length) return row.score_levels;
    const opt = props.criteriaOptions.find((c) => c.id === row.criterion_id);
    return opt?.score_levels || [];
}

function onCriterionChange(row) {
    const opt = props.criteriaOptions.find((c) => c.id === row.criterion_id);
    if (!opt) return;
    row.name = opt.criteria_name;
    row.score_levels = opt.score_levels || [];
    const match = (opt.score_levels || []).find(
        (l) => String(l.label || '').toLowerCase().includes('đạt'),
    );
    row.required_score_label = match?.label || opt.score_levels?.[0]?.label || null;
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

function isAllMode(row) {
    return row.evaluator_mode === 'all' || !row.evaluator_role_keys?.length;
}

function setAllMode(row) {
    row.evaluator_mode = 'all';
    row.evaluator_role_keys = [];
}

function toggleTag(row, roleKey) {
    row.evaluator_mode = 'tags';
    const keys = row.evaluator_role_keys || [];
    const idx = keys.indexOf(roleKey);
    if (idx >= 0) keys.splice(idx, 1);
    else keys.push(roleKey);
    row.evaluator_role_keys = keys;
    if (keys.length === 0) setAllMode(row);
}

const weightOptions = Array.from({ length: 10 }, (_, i) => (i + 1) * 10);
</script>

<template>
  <section class="rounded-card border border-slate-200/80 bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold text-slate-800">
      Tiêu chí đánh giá
    </h3>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
            <th class="px-2 py-2 w-12">
              STT
            </th>
            <th class="px-2 py-2 min-w-[14rem]">
              Tiêu chí năng lực *
            </th>
            <th class="px-2 py-2 w-32">
              Trọng số
            </th>
            <th class="px-2 py-2 w-40">
              Điểm yêu cầu *
            </th>
            <th class="px-2 py-2 min-w-[16rem]">
              Người đánh giá *
            </th>
            <th class="px-2 py-2 w-12" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in form.criteria"
            :key="index"
            class="border-b border-slate-50 align-top"
          >
            <td class="px-2 py-2 text-slate-400">
              {{ index + 1 }}
            </td>
            <td class="px-2 py-2">
              <select
                v-model="row.criterion_id"
                class="input h-10 w-full text-sm"
                @change="onCriterionChange(row)"
              >
                <option :value="null">
                  Chọn tiêu chí
                </option>
                <option
                  v-for="c in criteriaOptions"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.label || c.criteria_name }}
                </option>
              </select>
              <input
                v-if="!row.criterion_id"
                v-model="row.name"
                type="text"
                class="input mt-2 h-10 w-full text-sm"
                placeholder="Hoặc nhập tiêu chí tùy chỉnh"
              >
            </td>
            <td class="px-2 py-2">
              <select
                v-model.number="row.weight"
                class="input h-10 w-full text-sm"
              >
                <option :value="0">
                  Nhập trọng số
                </option>
                <option
                  v-for="w in weightOptions"
                  :key="w"
                  :value="w"
                >
                  {{ w }}%
                </option>
              </select>
            </td>
            <td class="px-2 py-2">
              <select
                v-model="row.required_score_label"
                class="input h-10 w-full text-sm"
              >
                <option :value="null">
                  Chọn điểm yêu cầu
                </option>
                <option
                  v-for="level in scoreLevelsFor(row)"
                  :key="level.code || level.label"
                  :value="level.label"
                >
                  {{ level.label }}
                </option>
              </select>
            </td>
            <td class="px-2 py-2">
              <div class="flex flex-wrap gap-1.5">
                <button
                  type="button"
                  class="rounded-full px-2.5 py-1 text-[11px] font-medium ring-1"
                  :class="isAllMode(row)
                    ? 'bg-brand/10 text-brand ring-brand/30'
                    : 'bg-white text-slate-500 ring-slate-200'"
                  @click="setAllMode(row)"
                >
                  Tất cả
                </button>
                <button
                  v-for="tag in raterTags"
                  :key="tag.value + tag.label"
                  type="button"
                  class="rounded-full px-2.5 py-1 text-[11px] font-medium ring-1"
                  :class="(!isAllMode(row) && (row.evaluator_role_keys || []).includes(tag.value))
                    ? 'bg-brand/10 text-brand ring-brand/30'
                    : 'bg-white text-slate-500 ring-slate-200'"
                  @click="toggleTag(row, tag.value)"
                >
                  {{ tag.label }}
                </button>
              </div>
            </td>
            <td class="px-2 py-2">
              <button
                type="button"
                class="text-slate-300 hover:text-rose-500"
                @click="removeRow(index)"
              >
                <AppIcon
                  name="close"
                  :size="14"
                />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <button
      type="button"
      class="mt-3 inline-flex h-9 items-center gap-1.5 rounded-full bg-brand px-3 text-xs font-medium text-white"
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
