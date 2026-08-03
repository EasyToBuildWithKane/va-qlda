<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import RadioCard from '@/shared/ui/RadioCard.vue';
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
    raterRoleOptions: { type: Array, default: () => [] },
    jobTitleOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'PDG001' },
    loadingTemplateCriteria: { type: Boolean, default: false },
});

const emit = defineEmits(['template-change']);

const toast = useToast();
const page = usePage();
const showTypeModal = ref(false);
const newTypeName = ref('');
const creatingType = ref(false);
const dragIndex = ref(null);
const codeUnlocked = ref(false);
const typeMenuOpen = ref(false);

const localTypes = computed(() => props.typeOptions);

const selectedType = computed(() =>
    localTypes.value.find((t) => t.id === form.value.type_id) || null,
);

const templateSelectOptions = computed(() => props.templateOptions.map((t) => ({
    id: t.id,
    name: t.name || t.label,
    template_code: t.template_code || '',
    label: t.label || (t.template_code ? `${t.name} (${t.template_code})` : t.name),
    subtitle: t.template_code || '',
})));

const councilOptions = computed(() => {
    const roles = (props.raterRoleOptions || []).map((o) => ({
        value: o.value,
        label: o.label,
        role_key: o.value,
        subtitle: o.value === 'custom' ? 'Nhập tên tùy chỉnh' : null,
    }));
    const titles = (props.jobTitleOptions || []).map((t) => ({
        value: `title:${t.code}`,
        label: t.name,
        role_key: 'custom',
        subtitle: 'Chức danh HRM',
    }));
    return [...roles, ...titles];
});

const weightSum = computed(() => form.value.raters.reduce(
    (sum, r) => sum + (Number(r.weight_percent) || 0),
    0,
));

const weightOk = computed(() => Math.abs(weightSum.value - 100) < 0.01);
const weightOver = computed(() => weightSum.value > 100.01);

const showMonthControls = computed(() => ['month', 'quarter', 'half_year'].includes(form.value.period_kind));
const showYearOnly = computed(() => form.value.period_kind === 'year');
const showApplyDate = computed(() => ['random', 'date_range'].includes(form.value.period_kind));

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

const periodUnitPlaceholder = computed(() => {
    if (form.value.period_kind === 'quarter') return 'Chọn quý';
    if (form.value.period_kind === 'half_year') return 'Chọn nửa năm';
    return 'Chọn tháng';
});

const enabledFieldCount = computed(() => (form.value.fields || []).filter((f) => f.is_enabled).length);

const displayedCode = computed(() => {
    if (!codeUnlocked.value) {
        return form.value.form_code || props.nextCode;
    }
    return form.value.form_code || '';
});

watch(() => props.nextCode, (code) => {
    if (!codeUnlocked.value && form.value) {
        form.value.form_code = code;
    }
});

watch(() => form.value.period_start, (value) => {
    if (showApplyDate.value) {
        form.value.period_end = value || null;
    }
});

function toggleCodeLock() {
    codeUnlocked.value = !codeUnlocked.value;
    if (codeUnlocked.value && !form.value.form_code) {
        form.value.form_code = props.nextCode;
    }
    if (!codeUnlocked.value) {
        form.value.form_code = props.nextCode;
    }
}

function onCodeInput(event) {
    if (!codeUnlocked.value) return;
    form.value.form_code = String(event.target.value || '').toUpperCase();
}

function onTemplateChange(templateId) {
    form.value.template_id = templateId || null;
    emit('template-change', form.value.template_id);
}

function selectType(type) {
    form.value.type_id = type.id;
    typeMenuOpen.value = false;
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

function raterSelectValue(rater) {
    if (rater.role_key !== 'custom') return rater.role_key;
    const title = (props.jobTitleOptions || []).find((t) => t.name === rater.label);
    return title ? `title:${title.code}` : 'custom';
}

function onRaterSelect(rater, value) {
    const opt = councilOptions.value.find((o) => o.value === value);
    if (!opt) return;
    rater.role_key = opt.role_key || 'custom';
    if (opt.role_key !== 'custom' || String(opt.value).startsWith('title:')) {
        rater.label = opt.label;
    } else if (!rater.label) {
        rater.label = '';
    }
}

function onWeightInput(rater, index) {
    let next = Number(rater.weight_percent);
    if (Number.isNaN(next) || next < 0) next = 0;
    if (next > 100) next = 100;

    const others = form.value.raters.reduce((sum, r, i) => (
        i === index ? sum : sum + (Number(r.weight_percent) || 0)
    ), 0);
    const maxAllowed = Math.max(0, Math.round((100 - others) * 100) / 100);
    if (next > maxAllowed) next = maxAllowed;

    rater.weight_percent = next;
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
  <div class="space-y-5">
    <!-- Định danh -->
    <section class="space-y-3">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">
          Định danh phiếu
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Mã, tên, mẫu và loại đánh giá
        </p>
      </div>

      <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12">
        <div class="xl:col-span-3">
          <label class="mb-1 block text-xs font-medium text-slate-600">Mã phiếu</label>
          <div class="flex gap-1.5">
            <input
              :value="displayedCode"
              type="text"
              class="input h-10 w-full font-mono text-sm uppercase"
              :class="!codeUnlocked ? 'bg-slate-50 text-slate-500' : ''"
              :placeholder="`Tự động: ${nextCode}`"
              :readonly="!codeUnlocked"
              :disabled="!codeUnlocked"
              @input="onCodeInput"
            >
            <button
              type="button"
              class="btn-ghost inline-flex h-10 w-10 shrink-0 items-center justify-center p-0"
              :class="codeUnlocked ? 'bg-brand/5 text-brand' : 'text-slate-500'"
              :title="codeUnlocked ? 'Khoá mã (tự động)' : 'Mở khoá để sửa mã'"
              :aria-label="codeUnlocked ? 'Khoá mã tự động' : 'Mở khoá sửa mã'"
              @click="toggleCodeLock"
            >
              <AppIcon
                :name="codeUnlocked ? 'unlock' : 'lock'"
                :size="15"
              />
            </button>
          </div>
        </div>

        <div class="xl:col-span-5">
          <label class="mb-1 block text-xs font-medium text-slate-600">
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

        <div class="xl:col-span-4">
          <label class="mb-1 block text-xs font-medium text-slate-600">Mẫu đánh giá</label>
          <SearchSelect
            :model-value="form.template_id"
            :options="templateSelectOptions"
            value-key="id"
            label-key="label"
            :search-keys="['name', 'template_code', 'label']"
            placeholder="Tìm & chọn mẫu đánh giá…"
            @update:model-value="onTemplateChange"
          />
          <p
            v-if="loadingTemplateCriteria"
            class="mt-1 text-[11px] text-brand"
          >
            Đang tải tiêu chí từ mẫu…
          </p>
        </div>

        <div class="relative md:col-span-2 xl:col-span-12">
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Loại đánh giá <span class="text-rose-500">*</span>
          </label>
          <div class="flex gap-1.5">
            <button
              type="button"
              class="input flex h-auto min-h-10 w-full items-start gap-2 py-2 text-left"
              @click="typeMenuOpen = !typeMenuOpen"
            >
              <span class="min-w-0 flex-1">
                <template v-if="selectedType">
                  <span class="block text-sm font-medium text-slate-700">{{ selectedType.name }}</span>
                  <span
                    v-if="selectedType.description"
                    class="mt-0.5 block text-xs leading-snug text-slate-400"
                  >{{ selectedType.description }}</span>
                </template>
                <span
                  v-else
                  class="text-sm text-slate-400"
                >Chọn loại đánh giá…</span>
              </span>
              <AppIcon
                name="chevron-down"
                :size="16"
                class="mt-1 shrink-0 text-slate-400"
              />
            </button>
            <button
              type="button"
              class="btn-ghost h-10 w-10 shrink-0 px-0"
              title="Thêm loại nhanh"
              @click="showTypeModal = true"
            >
              <AppIcon
                name="plus"
                :size="15"
              />
            </button>
          </div>

          <div
            v-if="typeMenuOpen"
            class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-card border border-slate-200 bg-white shadow-elevation-2"
          >
            <button
              v-for="t in localTypes"
              :key="t.id"
              type="button"
              class="flex w-full flex-col items-start gap-0.5 px-3 py-2.5 text-left transition hover:bg-slate-50"
              :class="t.id === form.type_id ? 'bg-brand-50' : ''"
              @click="selectType(t)"
            >
              <span class="text-sm font-medium text-slate-700">{{ t.name }}</span>
              <span
                v-if="t.description"
                class="text-xs leading-snug text-slate-400"
              >{{ t.description }}</span>
            </button>
          </div>
          <p
            v-if="form.errors.type_id"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.type_id }}
          </p>
        </div>
      </div>
    </section>

    <!-- Kỳ & hạn -->
    <section class="space-y-3 border-t border-slate-100 pt-4">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">
          Kỳ đánh giá & hạn
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Chọn kỳ áp dụng — không cần nhập khoảng từ–đến
        </p>
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-medium text-slate-600">
          Kỳ đánh giá <span class="text-rose-500">*</span>
        </label>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="opt in periodKindOptions"
            :key="opt.value"
            type="button"
            class="h-9 rounded-lg px-3 text-xs font-medium transition"
            :class="form.period_kind === opt.value
              ? 'bg-brand text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
            @click="form.period_kind = opt.value"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div v-if="showMonthControls">
          <label class="mb-1 block text-xs font-medium text-slate-600">Đơn vị kỳ</label>
          <select
            v-model.number="form.period_month"
            class="input h-10 w-full text-sm"
          >
            <option
              disabled
              :value="null"
            >
              {{ periodUnitPlaceholder }}
            </option>
            <option
              v-for="m in monthOptions"
              :key="m.value"
              :value="m.value"
            >
              {{ m.label }}
            </option>
          </select>
        </div>

        <div v-if="showMonthControls || showYearOnly">
          <label class="mb-1 block text-xs font-medium text-slate-600">Năm</label>
          <select
            v-model.number="form.period_year"
            class="input h-10 w-full text-sm"
          >
            <option
              disabled
              :value="null"
            >
              Chọn năm
            </option>
            <option
              v-for="y in yearOptions"
              :key="y"
              :value="y"
            >
              Năm {{ y }}
            </option>
          </select>
        </div>

        <div v-if="showApplyDate">
          <label class="mb-1 block text-xs font-medium text-slate-600">
            Ngày áp dụng <span class="text-rose-500">*</span>
          </label>
          <FilterDatePicker
            v-model="form.period_start"
            placeholder="Chọn ngày áp dụng"
          />
          <p
            v-if="form.errors.period_start"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.period_start }}
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

        <div class="flex items-end sm:col-span-2 xl:col-span-2">
          <label class="inline-flex w-full cursor-pointer items-start gap-2.5 rounded-lg bg-slate-50 px-3 py-2.5 text-sm text-slate-700 transition hover:bg-slate-100/80">
            <input
              v-model="form.auto_create_next"
              type="checkbox"
              class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand"
            >
            <span>
              <span class="font-medium">Tự tạo kỳ tiếp theo</span>
              <span class="mt-0.5 block text-xs font-normal text-slate-400">
                Sinh phiếu mới khi hết kỳ định kỳ
              </span>
            </span>
          </label>
        </div>
      </div>
    </section>

    <!-- Người liên quan -->
    <section class="space-y-3 border-t border-slate-100 pt-4">
      <div>
        <h3 class="text-sm font-semibold text-slate-800">
          Người liên quan
        </h3>
        <p class="mt-0.5 text-xs text-slate-400">
          Quản lý phiếu và người theo dõi tiến độ
        </p>
      </div>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
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
          <label class="mb-1 block text-xs font-medium text-slate-600">Người theo dõi</label>
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

    <!-- Quy tắc + hội đồng -->
    <section class="space-y-3 border-t border-slate-100 pt-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-semibold text-slate-800">
            Quy tắc & hội đồng đánh giá
          </h3>
          <p class="mt-0.5 text-xs text-slate-400">
            <template v-if="form.evaluation_order === 'sequential'">
              Bạn có thể kéo thả danh sách ở dưới để thay đổi thứ tự
            </template>
            <template v-else>
              Thành viên có thể đánh giá song song
            </template>
          </p>
        </div>
        <div
          v-if="form.use_weight"
          class="flex items-center gap-2"
        >
          <div class="text-right">
            <p class="text-[10px] uppercase tracking-wide text-slate-400">
              Tổng tỷ trọng
            </p>
            <p
              class="font-display text-lg tabular-nums leading-none"
              :class="weightOk ? 'text-emerald-600' : weightOver ? 'text-rose-600' : 'text-amber-600'"
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

      <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
        <RadioCard
          v-for="opt in orderOptions"
          :key="opt.value"
          v-model="form.evaluation_order"
          :value="opt.value"
          :label="opt.label"
          :description="opt.description || ''"
          :icon="opt.value === 'parallel' ? 'people' : 'list'"
        />
      </div>

      <div>
        <label class="mb-1.5 block text-xs font-medium text-slate-600">
          Tỷ trọng điểm <span class="text-rose-500">*</span>
        </label>
        <div class="flex gap-2">
          <button
            type="button"
            class="h-9 flex-1 rounded-lg text-xs font-medium transition"
            :class="form.use_weight
              ? 'bg-brand text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
            @click="form.use_weight = true"
          >
            Có tỷ trọng
          </button>
          <button
            type="button"
            class="h-9 flex-1 rounded-lg text-xs font-medium transition"
            :class="!form.use_weight
              ? 'bg-brand text-white'
              : 'bg-slate-100 text-slate-600 hover:bg-slate-200/80'"
            @click="form.use_weight = false"
          >
            Không
          </button>
        </div>
      </div>

      <p
        v-if="form.errors.raters"
        class="text-xs text-rose-600"
      >
        {{ form.errors.raters }}
      </p>

      <div
        v-if="form.use_weight"
        class="h-1.5 overflow-hidden rounded-full bg-slate-100"
      >
        <div
          class="h-full rounded-full transition-all duration-300"
          :class="weightOk ? 'bg-emerald-500' : weightOver ? 'bg-rose-400' : 'bg-amber-400'"
          :style="{ width: `${Math.min(weightSum, 100)}%` }"
        />
      </div>

      <div class="space-y-1">
        <div
          class="grid gap-2 px-1 pb-1 text-[10px] font-medium uppercase tracking-wide text-slate-400"
          :class="form.use_weight
            ? 'grid-cols-[2rem_minmax(0,1fr)_6.5rem_2rem]'
            : 'grid-cols-[2rem_minmax(0,1fr)_2rem]'"
        >
          <span>#</span>
          <span>Vai trò / chức danh</span>
          <span v-if="form.use_weight">Tỷ trọng</span>
          <span />
        </div>

        <div
          v-for="(rater, index) in form.raters"
          :key="index"
          class="group grid items-start gap-2 rounded-lg px-1 py-1.5 transition hover:bg-slate-50/80"
          :class="[
            form.use_weight
              ? 'grid-cols-[2rem_minmax(0,1fr)_6.5rem_2rem]'
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
              :model-value="raterSelectValue(rater)"
              :options="councilOptions"
              value-key="value"
              label-key="label"
              :search-keys="['label', 'value', 'subtitle']"
              placeholder="Tìm hội đồng / chức danh…"
              :clearable="false"
              @update:model-value="(v) => onRaterSelect(rater, v)"
            />
            <input
              v-if="raterSelectValue(rater) === 'custom'"
              v-model="rater.label"
              type="text"
              class="input mt-1.5 h-10 w-full text-sm"
              placeholder="Nhập tên hội đồng tùy chỉnh *"
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
              @input="onWeightInput(rater, index)"
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
        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-xs font-medium text-white transition hover:bg-brand/90"
        @click="addRater"
      >
        <AppIcon
          name="plus"
          :size="14"
        />
        Thêm hội đồng
      </button>
    </section>

    <!-- Trường tùy biến -->
    <section class="space-y-3 border-t border-slate-100 pt-4">
      <div class="flex items-end justify-between gap-3">
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
        <div
          v-for="(field, index) in form.fields"
          :key="field.field_key || index"
          class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/60 px-3 py-2"
          :class="field.is_enabled ? 'border-brand/20 bg-brand/[0.04]' : ''"
        >
          <div class="min-w-0 flex-1">
            <input
              type="text"
              class="input h-10 w-full bg-white text-sm"
              :placeholder="field.label"
              :disabled="!field.is_enabled"
              readonly
            >
          </div>
          <label class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 text-xs text-slate-500">
            <input
              v-model="field.is_enabled"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand"
            >
            Bật
          </label>
        </div>
      </div>
    </section>

    <!-- Modal thêm loại -->
    <div
      v-if="showTypeModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
      @click.self="showTypeModal = false"
    >
      <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
        <h4 class="text-sm font-semibold text-slate-800">
          Thêm loại đánh giá
        </h4>
        <label class="mt-3 mb-1 block text-xs font-medium text-slate-600">
          Tên loại <span class="text-rose-500">*</span>
        </label>
        <input
          v-model="newTypeName"
          type="text"
          class="input h-10 w-full text-sm"
          placeholder="Nhập tên loại đánh giá *"
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

    <!-- Click outside type menu -->
    <div
      v-if="typeMenuOpen"
      class="fixed inset-0 z-30"
      @click="typeMenuOpen = false"
    />
  </div>
</template>
