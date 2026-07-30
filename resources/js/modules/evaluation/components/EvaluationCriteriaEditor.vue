<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    templateType: { type: String, required: true },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const isPoint = computed(() => props.templateType === 'point_system');

const rows = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

function emptyRow() {
    return {
        id: null,
        criteria_code: '',
        criteria_name: '',
        category: isPoint.value ? 'Điểm Cộng' : 'Thái độ',
        description: '',
        point_value: isPoint.value ? 0 : null,
        max_points: null,
        max_frequency: null,
        weight: isPoint.value ? null : 1,
        required_score: isPoint.value ? null : 5,
        importance: isPoint.value ? null : 'Khá quan trọng',
        sort_order: rows.value.length + 1,
        is_active: true,
    };
}

function addRow() {
    rows.value = [...rows.value, emptyRow()];
}

function removeRow(index) {
    rows.value = rows.value.filter((_, i) => i !== index);
}

function moveRow(index, delta) {
    const next = [...rows.value];
    const target = index + delta;
    if (target < 0 || target >= next.length) return;
    const tmp = next[index];
    next[index] = next[target];
    next[target] = tmp;
    rows.value = next.map((r, i) => ({ ...r, sort_order: i + 1 }));
}

function updateField(index, key, value) {
    const next = [...rows.value];
    next[index] = { ...next[index], [key]: value };
    rows.value = next;
}

function formatPoint(value) {
    if (value === null || value === undefined || value === '') return EMPTY_LABELS.notUpdated;
    const n = Number(value);
    if (Number.isNaN(n)) return displayOrEmpty(value, EMPTY_LABELS.notUpdated);
    return n > 0 ? `+${n}` : String(n);
}
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-center justify-between gap-2">
      <h3 class="text-sm font-semibold text-slate-800">
        Danh sách tiêu chí
      </h3>
      <button
        v-if="!readonly"
        type="button"
        class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        @click="addRow"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm tiêu chí
      </button>
    </div>

    <div
      v-if="rows.length === 0"
      class="rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-4 py-8 text-center text-sm text-slate-500"
    >
      Chưa có tiêu chí. {{ readonly ? '' : 'Bấm «Thêm tiêu chí» để bắt đầu.' }}
    </div>

    <div
      v-else
      class="overflow-x-auto rounded-lg border border-slate-200"
    >
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-3 py-2 font-medium">
              Danh mục
            </th>
            <th class="px-3 py-2 font-medium">
              Mã
            </th>
            <th class="px-3 py-2 font-medium">
              Tên
            </th>
            <th
              v-if="isPoint"
              class="px-3 py-2 font-medium"
            >
              Điểm
            </th>
            <th
              v-if="isPoint"
              class="px-3 py-2 font-medium"
            >
              Tối đa
            </th>
            <th
              v-if="!isPoint"
              class="px-3 py-2 font-medium"
            >
              Trọng số
            </th>
            <th
              v-if="!isPoint"
              class="px-3 py-2 font-medium"
            >
              Điểm YC
            </th>
            <th class="px-3 py-2 font-medium">
              Mô tả
            </th>
            <th
              v-if="!readonly"
              class="px-3 py-2 font-medium text-right"
            >
              Hành động
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr
            v-for="(row, index) in rows"
            :key="row.id ?? `new-${index}`"
            class="align-top"
          >
            <td class="px-3 py-2">
              <input
                v-if="!readonly"
                class="input h-9 w-40 text-sm"
                :value="row.category"
                @input="updateField(index, 'category', $event.target.value)"
              >
              <span
                v-else
                class="text-slate-700"
              >{{ displayOrEmpty(row.category, EMPTY_LABELS.notUpdated) }}</span>
            </td>
            <td class="px-3 py-2">
              <input
                v-if="!readonly"
                class="input h-9 w-20 text-sm font-mono"
                :value="row.criteria_code"
                @input="updateField(index, 'criteria_code', $event.target.value)"
              >
              <span
                v-else
                class="font-mono text-slate-800"
              >{{ row.criteria_code }}</span>
            </td>
            <td class="px-3 py-2 min-w-[12rem]">
              <input
                v-if="!readonly"
                class="input h-9 w-full text-sm"
                :value="row.criteria_name"
                @input="updateField(index, 'criteria_name', $event.target.value)"
              >
              <span
                v-else
                class="text-slate-800"
              >{{ row.criteria_name }}</span>
            </td>
            <td
              v-if="isPoint"
              class="px-3 py-2"
            >
              <input
                v-if="!readonly"
                type="number"
                class="input h-9 w-20 text-sm"
                :value="row.point_value"
                @input="updateField(index, 'point_value', $event.target.value === '' ? null : Number($event.target.value))"
              >
              <span
                v-else
                class="tabular-nums"
              >{{ formatPoint(row.point_value) }}</span>
            </td>
            <td
              v-if="isPoint"
              class="px-3 py-2"
            >
              <input
                v-if="!readonly"
                type="number"
                class="input h-9 w-20 text-sm"
                :value="row.max_points ?? ''"
                placeholder="Không giới hạn"
                @input="updateField(index, 'max_points', $event.target.value === '' ? null : Number($event.target.value))"
              >
              <span
                v-else
                class="tabular-nums text-slate-600"
              >{{ displayOrEmpty(row.max_points, 'Không giới hạn') }}</span>
            </td>
            <td
              v-if="!isPoint"
              class="px-3 py-2"
            >
              <input
                v-if="!readonly"
                type="number"
                step="0.01"
                class="input h-9 w-20 text-sm"
                :value="row.weight"
                @input="updateField(index, 'weight', $event.target.value === '' ? null : Number($event.target.value))"
              >
              <span
                v-else
                class="tabular-nums"
              >{{ displayOrEmpty(row.weight, EMPTY_LABELS.notUpdated) }}</span>
            </td>
            <td
              v-if="!isPoint"
              class="px-3 py-2"
            >
              <input
                v-if="!readonly"
                type="number"
                class="input h-9 w-20 text-sm"
                :value="row.required_score"
                @input="updateField(index, 'required_score', $event.target.value === '' ? null : Number($event.target.value))"
              >
              <span
                v-else
                class="tabular-nums"
              >{{ displayOrEmpty(row.required_score, EMPTY_LABELS.notUpdated) }}</span>
            </td>
            <td class="px-3 py-2 min-w-[14rem]">
              <textarea
                v-if="!readonly"
                class="input min-h-[2.25rem] w-full text-sm"
                rows="2"
                :value="row.description || ''"
                @input="updateField(index, 'description', $event.target.value)"
              />
              <p
                v-else
                class="text-slate-600 whitespace-pre-wrap"
              >
                {{ displayOrEmpty(row.description, EMPTY_LABELS.notUpdated) }}
              </p>
            </td>
            <td
              v-if="!readonly"
              class="px-3 py-2"
            >
              <div class="flex items-center justify-end gap-1">
                <button
                  type="button"
                  class="btn-ghost h-8 w-8 p-0"
                  title="Lên"
                  @click="moveRow(index, -1)"
                >
                  <AppIcon
                    name="chevron-up"
                    :size="14"
                  />
                </button>
                <button
                  type="button"
                  class="btn-ghost h-8 w-8 p-0"
                  title="Xuống"
                  @click="moveRow(index, 1)"
                >
                  <AppIcon
                    name="chevron-down"
                    :size="14"
                  />
                </button>
                <button
                  type="button"
                  class="btn-ghost h-8 w-8 p-0 text-rose-600"
                  title="Xóa"
                  @click="removeRow(index)"
                >
                  <AppIcon
                    name="trash"
                    :size="14"
                  />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
