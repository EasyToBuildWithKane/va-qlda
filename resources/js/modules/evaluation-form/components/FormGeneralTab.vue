<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import FormEmployeeAutocomplete from '@/modules/evaluation-form/components/FormEmployeeAutocomplete.vue';
import { useToast } from '@/shared/composables/useToast';

const form = defineModel('form', { type: Object, required: true });

const props = defineProps({
    typeOptions: { type: Array, default: () => [] },
    templateOptions: { type: Array, default: () => [] },
    employeeOptions: { type: Array, default: () => [] },
    periodKindOptions: { type: Array, default: () => [] },
    orderOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    raterRoleOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'PDG001' },
    loadingTemplateCriteria: { type: Boolean, default: false },
});

const emit = defineEmits(['refresh-code', 'template-change', 'criteria-hydrate']);

const toast = useToast();
const page = usePage();
const showTypeModal = ref(false);
const newTypeName = ref('');
const creatingType = ref(false);
const dragIndex = ref(null);

const localTypes = computed(() => props.typeOptions);

const weightSum = computed(() => form.value.raters.reduce(
    (sum, r) => sum + (Number(r.weight_percent) || 0),
    0,
));

const showMonthControls = computed(() => ['month', 'quarter', 'half_year'].includes(form.value.period_kind));
const showYearOnly = computed(() => form.value.period_kind === 'year');
const showDateRange = computed(() => ['random', 'date_range'].includes(form.value.period_kind));

const monthOptions = computed(() => {
    if (form.value.period_kind === 'quarter') {
        return [
            { value: 1, label: 'Quý 1' },
            { value: 4, label: 'Quý 2' },
            { value: 7, label: 'Quý 3' },
            { value: 10, label: 'Quý 4' },
        ];
    }
    if (form.value.period_kind === 'half_year') {
        return [
            { value: 1, label: 'Nửa đầu năm' },
            { value: 7, label: 'Nửa cuối năm' },
        ];
    }
    return Array.from({ length: 12 }, (_, i) => ({
        value: i + 1,
        label: `Tháng ${i + 1}`,
    }));
});

const yearOptions = computed(() => {
    const y = new Date().getFullYear();
    return Array.from({ length: 7 }, (_, i) => y - 2 + i);
});

function onTemplateChange() {
    emit('template-change', form.value.template_id || null);
}

function addRater() {
    form.value.raters.push({
        role_key: 'custom',
        label: '',
        weight_percent: 0,
        sort_order: form.value.raters.length,
    });
}

function removeRater(index) {
    if (form.value.raters.length <= 1) return;
    form.value.raters.splice(index, 1);
}

function onDragStart(index) {
    if (form.value.evaluation_order !== 'sequential') return;
    dragIndex.value = index;
}

function onDrop(index) {
    if (dragIndex.value === null || dragIndex.value === index) return;
    const list = [...form.value.raters];
    const [item] = list.splice(dragIndex.value, 1);
    list.splice(index, 0, item);
    form.value.raters = list.map((r, i) => ({ ...r, sort_order: i }));
    dragIndex.value = null;
}

function toggleWatcher(id) {
    const ids = form.value.watcher_ids || [];
    const idx = ids.indexOf(id);
    if (idx >= 0) ids.splice(idx, 1);
    else ids.push(id);
}

function createType() {
    const name = newTypeName.value.trim();
    if (!name) return;
    creatingType.value = true;
    router.post(route('workspace.evaluation-forms.types.store'), { name }, {
        preserveScroll: true,
        onSuccess: () => {
            const created = page.props.flash?.created_form_type
                || page.props.created_form_type;
            showTypeModal.value = false;
            newTypeName.value = '';
            toast.success('Đã thêm loại đánh giá.');
            // Reload create/edit to refresh typeOptions; keep form via Inertia flash only.
            router.reload({ only: ['typeOptions'], onFinish: () => {
                creatingType.value = false;
                const latest = (page.props.typeOptions || []).find((t) => t.name === name);
                if (latest) form.value.type_id = latest.id;
                else if (created?.id) form.value.type_id = created.id;
            } });
        },
        onError: () => {
            creatingType.value = false;
            toast.error('Không thể thêm loại đánh giá.');
        },
        onFinish: () => {
            creatingType.value = false;
        },
    });
}
</script>

<template>
  <div class="space-y-6">
    <section class="rounded-card border border-slate-200/80 bg-white p-5 shadow-sm">
      <h3 class="mb-4 text-sm font-semibold text-slate-800">
        Thông tin chung
      </h3>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Mã phiếu đánh giá</label>
          <div class="flex gap-2">
            <input
              v-model="form.form_code"
              type="text"
              class="input h-10 w-full text-sm"
              :placeholder="nextCode"
            >
            <button
              type="button"
              class="btn-ghost h-10 shrink-0 px-3"
              title="Gợi ý mã mới"
              @click="emit('refresh-code')"
            >
              <AppIcon
                name="refresh"
                :size="15"
              />
            </button>
          </div>
        </div>

        <div class="md:col-span-2">
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Tên phiếu đánh giá <span class="text-rose-500">*</span>
          </label>
          <input
            v-model="form.name"
            type="text"
            class="input h-10 w-full text-sm"
            placeholder="Nhập tên phiếu đánh giá"
          >
          <p
            v-if="form.errors.name"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.name }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Mẫu đánh giá</label>
          <select
            v-model="form.template_id"
            class="input h-10 w-full text-sm"
            @change="onTemplateChange"
          >
            <option :value="null">
              Không chọn mẫu
            </option>
            <option
              v-for="t in templateOptions"
              :key="t.id"
              :value="t.id"
            >
              {{ t.label || t.name }}
            </option>
          </select>
          <p
            v-if="loadingTemplateCriteria"
            class="mt-1 text-[11px] text-slate-400"
          >
            Đang tải tiêu chí từ mẫu…
          </p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Loại đánh giá <span class="text-rose-500">*</span>
          </label>
          <div class="flex gap-2">
            <select
              v-model="form.type_id"
              class="input h-10 w-full text-sm"
            >
              <option
                v-for="t in localTypes"
                :key="t.id"
                :value="t.id"
              >
                {{ t.name }}
              </option>
            </select>
            <button
              type="button"
              class="btn-primary h-10 shrink-0 px-3"
              title="Thêm loại nhanh"
              @click="showTypeModal = true"
            >
              <AppIcon
                name="plus"
                :size="15"
              />
            </button>
          </div>
          <p
            v-if="form.errors.type_id"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.type_id }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Kỳ đánh giá <span class="text-rose-500">*</span>
          </label>
          <div class="flex flex-wrap gap-2">
            <select
              v-model="form.period_kind"
              class="input h-10 min-w-[8rem] flex-1 text-sm"
            >
              <option
                v-for="opt in periodKindOptions"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
            <select
              v-if="showMonthControls"
              v-model.number="form.period_month"
              class="input h-10 min-w-[8rem] flex-1 text-sm"
            >
              <option
                v-for="m in monthOptions"
                :key="m.value"
                :value="m.value"
              >
                {{ m.label }}
              </option>
            </select>
            <select
              v-if="showMonthControls || showYearOnly"
              v-model.number="form.period_year"
              class="input h-10 min-w-[7rem] flex-1 text-sm"
            >
              <option
                v-for="y in yearOptions"
                :key="y"
                :value="y"
              >
                Năm {{ y }}
              </option>
            </select>
          </div>
          <div
            v-if="showDateRange"
            class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2"
          >
            <FilterDatePicker
              v-model="form.period_start"
              placeholder="Từ ngày"
            />
            <FilterDatePicker
              v-model="form.period_end"
              placeholder="Đến ngày"
            />
          </div>
        </div>

        <div class="flex items-end">
          <label class="flex items-start gap-2 text-sm text-slate-700">
            <input
              v-model="form.auto_create_next"
              type="checkbox"
              class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand"
            >
            <span>Tự động tạo mới đánh giá định kỳ cho kỳ tiếp theo</span>
          </label>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Người quản lý <span class="text-rose-500">*</span>
          </label>
          <FormEmployeeAutocomplete
            v-model="form.manager_employee_id"
            :options="employeeOptions"
            placeholder="Chọn người quản lý"
          />
          <p
            v-if="form.errors.manager_employee_id"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.manager_employee_id }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Hạn đánh giá <span class="text-rose-500">*</span>
          </label>
          <FilterDatePicker
            v-model="form.deadline"
            placeholder="dd/mm/yyyy"
          />
          <p
            v-if="form.errors.deadline"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.deadline }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Người theo dõi</label>
          <div class="max-h-36 space-y-1 overflow-auto rounded-lg border border-slate-200 p-2">
            <label
              v-for="emp in employeeOptions.slice(0, 80)"
              :key="emp.id"
              class="flex items-center gap-2 rounded px-1.5 py-1 text-sm hover:bg-slate-50"
            >
              <input
                type="checkbox"
                class="rounded border-slate-300 text-brand focus:ring-brand"
                :checked="(form.watcher_ids || []).includes(emp.id)"
                @change="toggleWatcher(emp.id)"
              >
              <span class="truncate">{{ emp.name }}</span>
            </label>
          </div>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Thứ tự đánh giá <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="form.evaluation_order"
            class="input h-10 w-full text-sm"
          >
            <option
              v-for="opt in orderOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Tỷ trọng điểm đánh giá <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="form.use_weight"
            class="input h-10 w-full text-sm"
          >
            <option :value="true">
              Có
            </option>
            <option :value="false">
              Không
            </option>
          </select>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-slate-600">Trạng thái</label>
          <select
            v-model="form.status"
            class="input h-10 w-full text-sm"
          >
            <option
              v-for="opt in statusOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
      </div>
    </section>

    <section class="rounded-card border border-slate-200/80 bg-white p-5 shadow-sm">
      <div class="mb-3 flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-slate-800">
          Đối tượng đánh giá (hội đồng)
        </h3>
        <p
          v-if="form.use_weight"
          class="text-xs"
          :class="Math.abs(weightSum - 100) < 0.01 ? 'text-emerald-600' : 'text-amber-600'"
        >
          Tổng tỷ trọng: {{ weightSum }}%
        </p>
      </div>
      <p
        v-if="form.evaluation_order === 'sequential'"
        class="mb-3 text-xs text-slate-500"
      >
        Bạn có thể kéo thả danh sách để thay đổi thứ tự đánh giá tuần tự.
      </p>
      <p
        v-if="form.errors.raters"
        class="mb-2 text-xs text-rose-600"
      >
        {{ form.errors.raters }}
      </p>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
              <th class="px-2 py-2 w-12">
                STT
              </th>
              <th class="px-2 py-2">
                Hội đồng đánh giá *
              </th>
              <th
                v-if="form.use_weight"
                class="px-2 py-2 w-36"
              >
                Tỷ trọng (100%)
              </th>
              <th class="px-2 py-2 w-12" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(rater, index) in form.raters"
              :key="index"
              class="border-b border-slate-50"
              :draggable="form.evaluation_order === 'sequential'"
              @dragstart="onDragStart(index)"
              @dragover.prevent
              @drop="onDrop(index)"
            >
              <td class="px-2 py-2 text-slate-400">
                {{ index + 1 }}
              </td>
              <td class="px-2 py-2">
                <input
                  v-model="rater.label"
                  type="text"
                  class="input h-10 w-full text-sm"
                  placeholder="Chọn hội đồng đánh giá"
                >
              </td>
              <td
                v-if="form.use_weight"
                class="px-2 py-2"
              >
                <div class="flex items-center gap-1">
                  <input
                    v-model.number="rater.weight_percent"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    class="input h-10 w-full text-sm"
                  >
                  <span class="text-xs text-slate-400">%</span>
                </div>
              </td>
              <td class="px-2 py-2">
                <button
                  type="button"
                  class="text-slate-300 hover:text-rose-500"
                  @click="removeRater(index)"
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
        @click="addRater"
      >
        <AppIcon
          name="plus"
          :size="14"
        />
        Thêm hội đồng
      </button>
    </section>

    <section class="rounded-card border border-slate-200/80 bg-white p-5 shadow-sm">
      <h3 class="mb-4 text-sm font-semibold text-slate-800">
        Trường tùy biến
      </h3>
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div
          v-for="(field, index) in form.fields"
          :key="field.field_key || index"
          class="rounded-lg border border-slate-100 p-3"
        >
          <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input
              v-model="field.is_enabled"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand"
            >
            {{ field.label }}
          </label>
          <p class="mt-2 text-xs text-slate-400">
            Trường sẽ hiển thị trên phiếu khi chấm điểm (phase sau).
          </p>
        </div>
      </div>
    </section>

    <div
      v-if="showTypeModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
      @click.self="showTypeModal = false"
    >
      <div class="w-full max-w-md rounded-card border border-slate-200 bg-white p-5 shadow-xl">
        <h4 class="text-sm font-semibold text-slate-800">
          Thêm loại đánh giá
        </h4>
        <input
          v-model="newTypeName"
          type="text"
          class="input mt-3 h-10 w-full text-sm"
          placeholder="Tên loại đánh giá"
          @keyup.enter="createType"
        >
        <div class="mt-4 flex justify-end gap-2">
          <button
            type="button"
            class="btn-ghost h-9 px-3 text-sm"
            @click="showTypeModal = false"
          >
            Hủy
          </button>
          <button
            type="button"
            class="btn-primary h-9 px-3 text-sm"
            :disabled="creatingType || !newTypeName.trim()"
            @click="createType"
          >
            Lưu
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
