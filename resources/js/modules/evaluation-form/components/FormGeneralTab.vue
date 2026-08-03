<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import SearchMultiSelect from '@/shared/ui/SearchMultiSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
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

const weightOk = computed(() => Math.abs(weightSum.value - 100) < 0.01);

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

const enabledFieldCount = computed(() => (form.value.fields || []).filter((f) => f.is_enabled).length);

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

function onRaterRoleChange(rater, roleKey) {
    const opt = props.raterRoleOptions.find((o) => o.value === roleKey);
    rater.role_key = roleKey || 'custom';
    if (opt && roleKey !== 'custom') {
        rater.label = opt.label;
    } else if (!rater.label) {
        rater.label = opt?.label || '';
    }
}

function distributeWeightsEvenly() {
    const n = form.value.raters.length;
    if (n === 0) return;
    const base = Math.floor((10000 / n)) / 100;
    let remain = 100;
    form.value.raters.forEach((r, i) => {
        if (i === n - 1) {
            r.weight_percent = Math.round(remain * 100) / 100;
        } else {
            r.weight_percent = base;
            remain -= base;
        }
    });
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
  <div class="space-y-8">
    <!-- Định danh phiếu -->
    <section>
      <div class="mb-4">
        <h3 class="text-sm font-semibold text-slate-800">
          Định danh phiếu
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Mã, tên và phân loại phiếu đánh giá
        </p>
      </div>
      <div class="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2 xl:grid-cols-3">
        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Mã phiếu</label>
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
          <label class="mb-1.5 block text-xs font-medium text-slate-600">
            Tên phiếu <span class="text-rose-500">*</span>
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
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Mẫu đánh giá</label>
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
            class="mt-1 text-[11px] text-brand"
          >
            Đang tải tiêu chí từ mẫu…
          </p>
        </div>

        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">
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
              class="btn-ghost h-10 shrink-0 px-3"
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
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Trạng thái</label>
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

    <div class="border-b border-slate-100" />

    <!-- Kỳ & hạn -->
    <section>
      <div class="mb-4">
        <h3 class="text-sm font-semibold text-slate-800">
          Kỳ đánh giá & hạn
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Thời gian áp dụng và hạn hoàn thành phiếu
        </p>
      </div>
      <div class="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="md:col-span-2 xl:col-span-2">
          <label class="mb-1.5 block text-xs font-medium text-slate-600">
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

        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">
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

        <div class="md:col-span-2 xl:col-span-3">
          <label class="inline-flex cursor-pointer items-start gap-2.5 rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-700 transition hover:bg-slate-100/80">
            <input
              v-model="form.auto_create_next"
              type="checkbox"
              class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand"
            >
            <span>
              <span class="font-medium">Tự tạo kỳ tiếp theo</span>
              <span class="mt-0.5 block text-xs font-normal text-slate-400">
                Tự động sinh phiếu mới khi hết kỳ đánh giá định kỳ
              </span>
            </span>
          </label>
        </div>
      </div>
    </section>

    <div class="border-b border-slate-100" />

    <!-- Người liên quan -->
    <section>
      <div class="mb-4">
        <h3 class="text-sm font-semibold text-slate-800">
          Người liên quan
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Quản lý phiếu và người theo dõi tiến độ
        </p>
      </div>
      <div class="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">
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
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Người theo dõi</label>
          <SearchMultiSelect
            v-model="form.watcher_ids"
            :options="employeeOptions"
            value-key="id"
            label-key="name"
            :search-keys="['name', 'code', 'email', 'department_name']"
            placeholder="Tìm & chọn người theo dõi…"
            control-size="md"
            :max-chips="2"
          />
        </div>
      </div>
    </section>

    <div class="border-b border-slate-100" />

    <!-- Quy tắc chấm -->
    <section>
      <div class="mb-4">
        <h3 class="text-sm font-semibold text-slate-800">
          Quy tắc chấm điểm
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Thứ tự hội đồng và cách áp dụng tỷ trọng
        </p>
      </div>
      <div class="grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">
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
          <label class="mb-1.5 block text-xs font-medium text-slate-600">
            Tỷ trọng điểm <span class="text-rose-500">*</span>
          </label>
          <div class="flex gap-2">
            <button
              type="button"
              class="h-10 flex-1 rounded-lg text-sm font-medium transition"
              :class="form.use_weight
                ? 'bg-brand text-white'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
              @click="form.use_weight = true"
            >
              Có tỷ trọng
            </button>
            <button
              type="button"
              class="h-10 flex-1 rounded-lg text-sm font-medium transition"
              :class="!form.use_weight
                ? 'bg-brand text-white'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
              @click="form.use_weight = false"
            >
              Không
            </button>
          </div>
        </div>
      </div>
    </section>

    <div class="border-b border-slate-100" />

    <!-- Hội đồng -->
    <section>
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">
            Hội đồng đánh giá
          </h3>
          <p class="mt-0.5 text-xs text-slate-400">
            <template v-if="form.evaluation_order === 'sequential'">
              Kéo thả để đổi thứ tự chấm tuần tự
            </template>
            <template v-else>
              Các vai trò tham gia chấm điểm trên phiếu
            </template>
          </p>
        </div>
        <div
          v-if="form.use_weight"
          class="flex items-center gap-3"
        >
          <div class="text-right">
            <p class="text-[10px] uppercase tracking-wide text-slate-400">
              Tổng tỷ trọng
            </p>
            <p
              class="font-display text-lg tabular-nums leading-none"
              :class="weightOk ? 'text-emerald-600' : 'text-amber-600'"
            >
              {{ weightSum }}%
            </p>
          </div>
          <button
            type="button"
            class="btn-ghost h-9 px-2.5 text-xs"
            title="Chia đều 100%"
            @click="distributeWeightsEvenly"
          >
            Chia đều
          </button>
        </div>
      </div>

      <p
        v-if="form.errors.raters"
        class="mb-3 text-xs text-rose-600"
      >
        {{ form.errors.raters }}
      </p>

      <div
        v-if="form.use_weight"
        class="mb-3 h-1.5 overflow-hidden rounded-full bg-slate-100"
      >
        <div
          class="h-full rounded-full transition-all duration-300"
          :class="weightOk ? 'bg-emerald-500' : weightSum > 100 ? 'bg-rose-400' : 'bg-amber-400'"
          :style="{ width: `${Math.min(weightSum, 100)}%` }"
        />
      </div>

      <div class="space-y-1">
        <div
          class="grid gap-2 px-1 pb-2 text-[10px] font-medium uppercase tracking-wide text-slate-400"
          :class="form.use_weight
            ? 'grid-cols-[2rem_minmax(0,1fr)_7rem_2rem]'
            : 'grid-cols-[2rem_minmax(0,1fr)_2rem]'"
        >
          <span>#</span>
          <span>Vai trò hội đồng</span>
          <span v-if="form.use_weight">Tỷ trọng</span>
          <span />
        </div>

        <div
          v-for="(rater, index) in form.raters"
          :key="index"
          class="group grid items-start gap-2 rounded-lg px-1 py-2 transition hover:bg-slate-50/80"
          :class="[
            form.use_weight
              ? 'grid-cols-[2rem_minmax(0,1fr)_7rem_2rem]'
              : 'grid-cols-[2rem_minmax(0,1fr)_2rem]',
            form.evaluation_order === 'sequential' ? 'cursor-grab active:cursor-grabbing' : '',
          ]"
          :draggable="form.evaluation_order === 'sequential'"
          @dragstart="onDragStart(index)"
          @dragover.prevent
          @drop="onDrop(index)"
        >
          <div class="flex h-10 items-center gap-1 text-xs tabular-nums text-slate-400">
            <AppIcon
              v-if="form.evaluation_order === 'sequential'"
              name="grip-vertical"
              :size="12"
              class="opacity-40 group-hover:opacity-70"
            />
            {{ index + 1 }}
          </div>
          <div>
            <SearchSelect
              :model-value="rater.role_key"
              :options="raterRoleOptions"
              value-key="value"
              label-key="label"
              :search-keys="['label', 'value']"
              placeholder="Tìm hội đồng đánh giá…"
              @update:model-value="(v) => onRaterRoleChange(rater, v)"
            />
            <input
              v-if="rater.role_key === 'custom'"
              v-model="rater.label"
              type="text"
              class="input mt-2 h-10 w-full text-sm"
              placeholder="Nhập tên hội đồng tùy chỉnh"
            >
          </div>
          <div
            v-if="form.use_weight"
            class="flex h-10 items-center gap-1"
          >
            <input
              v-model.number="rater.weight_percent"
              type="number"
              min="0"
              max="100"
              step="0.01"
              class="input h-10 w-full text-sm tabular-nums"
            >
            <span class="shrink-0 text-xs text-slate-400">%</span>
          </div>
          <div class="flex h-10 items-center justify-center">
            <button
              type="button"
              class="rounded p-1 text-slate-300 transition hover:bg-rose-50 hover:text-rose-500 disabled:opacity-30"
              :disabled="form.raters.length <= 1"
              title="Xóa"
              @click="removeRater(index)"
            >
              <AppIcon
                name="close"
                :size="14"
              />
            </button>
          </div>
        </div>
      </div>

      <button
        type="button"
        class="mt-3 inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-xs font-medium text-white transition hover:bg-brand/90"
        @click="addRater"
      >
        <AppIcon
          name="plus"
          :size="14"
        />
        Thêm hội đồng
      </button>
    </section>

    <div class="border-b border-slate-100" />

    <!-- Trường tùy biến -->
    <section>
      <div class="mb-4 flex items-end justify-between gap-3">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">
            Trường tùy biến
          </h3>
          <p class="mt-0.5 text-xs text-slate-400">
            Bật các trường bổ sung khi chấm điểm
          </p>
        </div>
        <p class="text-xs tabular-nums text-slate-400">
          {{ enabledFieldCount }}/{{ form.fields.length }} đang bật
        </p>
      </div>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <label
          v-for="(field, index) in form.fields"
          :key="field.field_key || index"
          class="flex cursor-pointer items-start gap-3 rounded-lg px-3 py-3 transition"
          :class="field.is_enabled ? 'bg-brand/[0.06] hover:bg-brand/[0.09]' : 'bg-slate-50 hover:bg-slate-100/80'"
        >
          <input
            v-model="field.is_enabled"
            type="checkbox"
            class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand"
          >
          <span>
            <span class="block text-sm font-medium text-slate-700">{{ field.label }}</span>
            <span class="mt-0.5 block text-[11px] text-slate-400">
              Hiển thị trên phiếu khi chấm điểm
            </span>
          </span>
        </label>
      </div>
    </section>

    <div
      v-if="showTypeModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
      @click.self="showTypeModal = false"
    >
      <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
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
