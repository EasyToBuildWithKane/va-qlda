<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import FormEmployeeAutocomplete from '@/modules/evaluation-form/components/FormEmployeeAutocomplete.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';
import { useToast } from '@/shared/composables/useToast';

const form = defineModel('form', { type: Object, required: true });

const props = defineProps({
    employeeOptions: { type: Array, default: () => [] },
});

const toast = useToast();
const MAX_ASSIGNEES = 500;
const searchQuery = ref('');

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
    form.value.assignees.push(emptyRow());
}

function removeRow(index) {
    form.value.assignees.splice(index, 1);
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

function loadAllFromHrm() {
    const existing = new Set(
        (form.value.assignees || [])
            .map((a) => a.employee_id)
            .filter(Boolean),
    );
    const toAdd = props.employeeOptions.filter((e) => !existing.has(e.id));
    if (toAdd.length === 0) {
        toast.info('Tất cả nhân sự HRM đã có trong danh sách.');
        return;
    }

    const room = MAX_ASSIGNEES - form.value.assignees.filter((a) => a.employee_id).length;
    form.value.assignees = form.value.assignees.filter((a) => a.employee_id);

    const slice = toAdd.slice(0, Math.max(0, room));
    slice.forEach((emp) => {
        const row = emptyRow();
        row.employee_id = emp.id;
        fillEmployeeFields(row, emp);
        row.sort_order = form.value.assignees.length;
        form.value.assignees.push(row);
    });

    if (slice.length === 0) {
        toast.error(`Đã đạt giới hạn ${MAX_ASSIGNEES} nhân sự trên phiếu.`);
        return;
    }

    const skipped = toAdd.length - slice.length;
    toast.success(
        skipped > 0
            ? `Đã thêm ${slice.length} nhân sự từ HRM (bỏ qua ${skipped} vì giới hạn).`
            : `Đã thêm ${slice.length} nhân sự từ HRM.`,
    );
}

function clearEmptyRows() {
    const before = form.value.assignees.length;
    form.value.assignees = form.value.assignees.filter((a) => a.employee_id);
    const removed = before - form.value.assignees.length;
    if (removed === 0) {
        toast.info('Không có dòng trống để xóa.');
        return;
    }
    toast.success(`Đã xóa ${removed} dòng trống.`);
    if (form.value.assignees.length === 0) addRow();
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
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          :disabled="employeeOptions.length === 0"
          @click="loadAllFromHrm"
        >
          <AppIcon
            name="download"
            :size="14"
          />
          Tải tất cả từ HRM
        </button>
        <button
          type="button"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
          @click="clearEmptyRows"
        >
          Dọn dòng trống
        </button>
      </div>
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
          : 'Thêm từng người hoặc tải toàn bộ từ HRM.' }}
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
      class="space-y-0"
    >
      <div
        v-for="index in filteredIndexes"
        :key="index"
        class="border-b border-slate-100 py-4 first:pt-0 last:border-b-0"
      >
        <div class="mb-3 flex items-center justify-between gap-2">
          <div class="flex min-w-0 items-center gap-2">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[11px] tabular-nums text-slate-600">
              {{ index + 1 }}
            </span>
            <div class="min-w-0">
              <p class="truncate text-sm font-medium text-slate-800">
                {{ form.assignees[index].employee_name || 'Chưa chọn nhân sự' }}
              </p>
              <p
                v-if="form.assignees[index].employee_code || form.assignees[index].department_name"
                class="truncate text-[11px] text-slate-400"
              >
                {{
                  [
                    form.assignees[index].employee_code,
                    form.assignees[index].department_name,
                  ].filter(Boolean).join(' · ')
                }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="shrink-0 rounded p-1 text-slate-300 transition hover:bg-rose-50 hover:text-rose-500"
            title="Xóa khỏi danh sách"
            @click="removeRow(index)"
          >
            <AppIcon
              name="close"
              :size="14"
            />
          </button>
        </div>

        <div class="grid grid-cols-1 gap-x-4 gap-y-3 md:grid-cols-2 xl:grid-cols-3">
          <div>
            <label class="mb-1.5 block text-xs font-medium text-slate-600">
              Nhân sự <span class="text-rose-500">*</span>
            </label>
            <FormEmployeeAutocomplete
              v-model="form.assignees[index].employee_id"
              :options="employeeOptions"
              placeholder="Tìm nhân sự HRM…"
              @select="(emp) => onEmployeeSelect(form.assignees[index], emp)"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-medium text-slate-600">Mã NV</label>
            <input
              v-model="form.assignees[index].employee_code"
              type="text"
              class="input h-10 w-full text-sm"
              placeholder="Tự điền khi chọn nhân sự"
            >
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-medium text-slate-600">Phòng ban</label>
            <input
              v-model="form.assignees[index].department_name"
              type="text"
              class="input h-10 w-full text-sm"
              placeholder="Tự điền khi chọn nhân sự"
            >
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-medium text-slate-600">
              Trưởng phòng <span class="text-rose-500">*</span>
            </label>
            <FormEmployeeAutocomplete
              v-model="form.assignees[index].dept_head_employee_id"
              :options="employeeOptions"
              placeholder="Tìm trưởng phòng…"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-medium text-slate-600">
              Quản lý trực tiếp <span class="text-rose-500">*</span>
            </label>
            <FormEmployeeAutocomplete
              v-model="form.assignees[index].direct_manager_employee_id"
              :options="employeeOptions"
              placeholder="Tìm quản lý trực tiếp…"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-xs font-medium text-slate-600">Ban giám đốc</label>
            <FormEmployeeAutocomplete
              v-model="form.assignees[index].board_employee_id"
              :options="employeeOptions"
              placeholder="Tìm ban giám đốc…"
            />
          </div>
        </div>
      </div>
    </div>

    <button
      v-if="form.assignees.length > 0"
      type="button"
      class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-xs font-medium text-white transition hover:bg-brand/90"
      @click="addRow"
    >
      <AppIcon
        name="plus"
        :size="14"
      />
      Thêm nhân sự
    </button>
  </section>
</template>
