<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import FormEmployeeAutocomplete from '@/modules/evaluation-form/components/FormEmployeeAutocomplete.vue';

const form = defineModel('form', { type: Object, required: true });

defineProps({
    employeeOptions: { type: Array, default: () => [] },
});

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

function onEmployeeSelect(row, emp) {
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

if (form.value.assignees.length === 0) {
    addRow();
}
</script>

<template>
  <section class="rounded-card border border-slate-200/80 bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-sm font-semibold text-slate-800">
      Danh sách nhân sự
    </h3>
    <p
      v-if="form.errors.assignees"
      class="mb-2 text-xs text-rose-600"
    >
      {{ form.errors.assignees }}
    </p>
    <div class="space-y-3">
      <div
        v-for="(row, index) in form.assignees"
        :key="index"
        class="rounded-lg border border-slate-100 p-3"
      >
        <div class="mb-2 flex items-center justify-between">
          <span class="text-xs font-medium uppercase tracking-wide text-slate-400">
            Nhân sự #{{ index + 1 }}
          </span>
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
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Mã NV</label>
            <input
              v-model="row.employee_code"
              type="text"
              class="input h-10 w-full text-sm"
              placeholder="Mã NV"
            >
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">
              Nhân sự <span class="text-rose-500">*</span>
            </label>
            <FormEmployeeAutocomplete
              v-model="row.employee_id"
              :options="employeeOptions"
              placeholder="Chọn nhân sự"
              @select="(emp) => onEmployeeSelect(row, emp)"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Phòng ban</label>
            <input
              v-model="row.department_name"
              type="text"
              class="input h-10 w-full text-sm"
              placeholder="Phòng ban"
            >
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">
              Trưởng phòng <span class="text-rose-500">*</span>
            </label>
            <FormEmployeeAutocomplete
              v-model="row.dept_head_employee_id"
              :options="employeeOptions"
              placeholder="Chọn trưởng phòng"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">
              Quản lý trực tiếp <span class="text-rose-500">*</span>
            </label>
            <FormEmployeeAutocomplete
              v-model="row.direct_manager_employee_id"
              :options="employeeOptions"
              placeholder="Chọn quản lý trực tiếp"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Ban giám đốc</label>
            <FormEmployeeAutocomplete
              v-model="row.board_employee_id"
              :options="employeeOptions"
              placeholder="Chọn ban giám đốc"
            />
          </div>
        </div>
      </div>
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
      Thêm nhân sự
    </button>
  </section>
</template>
