<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import FormEmployeeAutocomplete from '@/modules/evaluation-form/components/FormEmployeeAutocomplete.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';
import { useToast } from '@/shared/composables/useToast';

const form = defineModel('form', { type: Object, required: true });

defineProps({
    employeeOptions: { type: Array, default: () => [] },
});

const toast = useToast();
const MAX_ASSIGNEES = 500;
const searchQuery = ref('');

const cellInputClass = 'input h-9 w-full min-w-0 text-sm pr-8';

function emptyRow() {
    return {
        employee_id: null,
        employee_code: '',
        employee_name: '',
        department_code: '',
        department_name: '',
        dept_head_employee_id: null,
        direct_manager_employee_id: null,
        board_employee_id: null,
        sort_order: form.value.assignees.length,
    };
}

function addRow() {
    if (assignedCount.value >= MAX_ASSIGNEES) {
        toast.error(`Đã đạt giới hạn ${MAX_ASSIGNEES} nhân sự trên phiếu.`);
        return;
    }
    form.value.assignees.push(emptyRow());
}

function removeRow(index) {
    form.value.assignees.splice(index, 1);
    if (form.value.assignees.length === 0) {
        addRow();
    }
}

function fillEmployeeFields(row, emp) {
    if (!emp) {
        row.employee_code = '';
        row.employee_name = '';
        row.department_code = '';
        row.department_name = '';
        return;
    }
    row.employee_code = emp.code || '';
    row.employee_name = emp.name || '';
    row.department_code = emp.department_code || '';
    row.department_name = emp.department_name || '';
}

function onEmployeeSelect(row, emp) {
    if (emp) {
        const dup = form.value.assignees.some(
            (a) => a !== row && a.employee_id === emp.id,
        );
        if (dup) {
            toast.error('Nhân sự này đã có trong danh sách.');
            row.employee_id = null;
            fillEmployeeFields(row, null);
            return;
        }
    }
    fillEmployeeFields(row, emp);
}

const assignedCount = computed(() => (form.value.assignees || []).filter((a) => a.employee_id).length);

const filteredIndexes = computed(() => {
    const q = searchQuery.value.trim();
    const list = form.value.assignees || [];
    if (!q) return list.map((_, i) => i);
    return list
        .map((row, i) => ({ row, i }))
        .filter(({ row }) => matchesSearchQuery(
            [row.employee_name, row.employee_code, row.department_name],
            q,
        ))
        .map(({ i }) => i);
});

if (form.value.assignees.length === 0) {
    addRow();
}
</script>

<template>
  <section>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">
          Danh sách nhân sự
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Nguồn HRM: {{ employeeOptions.length }} người · Đã chọn
          <span class="font-medium tabular-nums text-slate-600">{{ assignedCount }}</span>
          /{{ MAX_ASSIGNEES }}
        </p>
      </div>
      <button
        type="button"
        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-xs font-medium text-white transition hover:bg-brand/90"
        @click="addRow"
      >
        <AppIcon
          name="plus"
          :size="14"
        />
        Thêm nhân sự
      </button>
    </div>

    <div class="mb-4">
      <DatagridToolbarSearch
        v-model="searchQuery"
        input-id="eval-form-assignees-search"
        hide-label
        stretch
        input-height="h-10"
        placeholder="Lọc theo tên, mã NV hoặc phòng ban…"
      />
    </div>

    <p
      v-if="form.errors.assignees"
      class="mb-3 text-xs text-rose-600"
    >
      {{ form.errors.assignees }}
    </p>

    <div
      v-if="filteredIndexes.length === 0"
      class="flex flex-col items-center justify-center rounded-xl bg-slate-50 px-4 py-10 text-center"
    >
      <p class="text-sm font-medium text-slate-700">
        {{ searchQuery.trim() ? 'Không tìm thấy nhân sự khớp bộ lọc' : 'Chưa có nhân sự' }}
      </p>
      <p class="mt-1 text-xs text-slate-400">
        {{ searchQuery.trim()
          ? 'Thử từ khóa khác hoặc xóa bộ lọc.'
          : 'Bấm «Thêm nhân sự» rồi chọn từng người từ danh sách HRM.' }}
      </p>
      <button
        v-if="!searchQuery.trim()"
        type="button"
        class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-xs font-medium text-white"
        @click="addRow"
      >
        <AppIcon
          name="plus"
          :size="14"
        />
        Thêm nhân sự
      </button>
    </div>

    <div
      v-else
      class="overflow-x-auto rounded-xl border border-slate-200/80"
    >
      <table class="min-w-[72rem] w-full border-collapse text-left text-sm">
        <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          <tr>
            <th class="w-10 px-3 py-2.5">
              #
            </th>
            <th class="min-w-[12rem] px-3 py-2.5">
              Nhân sự <span class="normal-case text-rose-500">*</span>
            </th>
            <th class="min-w-[6.5rem] px-3 py-2.5">
              Mã NV
            </th>
            <th class="min-w-[9rem] px-3 py-2.5">
              Phòng ban
            </th>
            <th class="min-w-[11rem] px-3 py-2.5">
              Trưởng phòng <span class="normal-case text-rose-500">*</span>
            </th>
            <th class="min-w-[11rem] px-3 py-2.5">
              QL trực tiếp <span class="normal-case text-rose-500">*</span>
            </th>
            <th class="min-w-[11rem] px-3 py-2.5">
              Ban giám đốc
            </th>
            <th class="w-10 px-2 py-2.5">
              <span class="sr-only">Thao tác</span>
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
          <tr
            v-for="index in filteredIndexes"
            :key="index"
            class="align-middle"
          >
            <td class="px-3 py-2 text-[11px] tabular-nums text-slate-400">
              {{ index + 1 }}
            </td>
            <td class="px-3 py-2">
              <FormEmployeeAutocomplete
                v-model="form.assignees[index].employee_id"
                :options="employeeOptions"
                :input-class="cellInputClass"
                placeholder="Tìm nhân sự…"
                @select="(emp) => onEmployeeSelect(form.assignees[index], emp)"
              />
            </td>
            <td class="px-3 py-2">
              <input
                v-model="form.assignees[index].employee_code"
                type="text"
                class="input h-9 w-full min-w-0 text-sm"
                placeholder="Tự điền"
              >
            </td>
            <td class="px-3 py-2">
              <input
                v-model="form.assignees[index].department_name"
                type="text"
                class="input h-9 w-full min-w-0 text-sm"
                placeholder="Tự điền"
              >
            </td>
            <td class="px-3 py-2">
              <FormEmployeeAutocomplete
                v-model="form.assignees[index].dept_head_employee_id"
                :options="employeeOptions"
                :input-class="cellInputClass"
                placeholder="Tìm trưởng phòng…"
              />
            </td>
            <td class="px-3 py-2">
              <FormEmployeeAutocomplete
                v-model="form.assignees[index].direct_manager_employee_id"
                :options="employeeOptions"
                :input-class="cellInputClass"
                placeholder="Tìm QL trực tiếp…"
              />
            </td>
            <td class="px-3 py-2">
              <FormEmployeeAutocomplete
                v-model="form.assignees[index].board_employee_id"
                :options="employeeOptions"
                :input-class="cellInputClass"
                placeholder="Tìm ban GĐ…"
              />
            </td>
            <td class="px-2 py-2 text-center">
              <button
                type="button"
                class="rounded p-1 text-slate-300 transition hover:bg-rose-50 hover:text-rose-500"
                title="Xóa khỏi danh sách"
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
  </section>
</template>
