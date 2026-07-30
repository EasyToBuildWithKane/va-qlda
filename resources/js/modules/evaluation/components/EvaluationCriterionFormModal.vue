<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DepartmentAutocomplete from '@/modules/evaluation/components/DepartmentAutocomplete.vue';
import { useDialog } from '@/composables/useDialog';

const MIN_LEVELS = 2;
const MAX_LEVELS = 10;

const LEVEL_TONES = [
    'bg-rose-500',
    'bg-amber-500',
    'bg-slate-400',
    'bg-sky-500',
    'bg-emerald-500',
    'bg-violet-500',
    'bg-brand',
    'bg-teal-500',
    'bg-orange-500',
    'bg-indigo-500',
];

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' }, // create | edit
    criterion: { type: Object, default: null },
    departments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'TCVA001' },
    defaultScoreLevels: { type: Array, default: () => [] },
    /** Siêu quản trị: để trống phòng ban = tiêu chí chung. */
    canCreateGeneral: { type: Boolean, default: false },
    /** Prefill phòng ban khi tạo (user scoped). */
    defaultDepartmentCode: { type: String, default: '' },
});

const emit = defineEmits(['close']);
const dialog = useDialog();

const localCategories = ref([]);
const showNewCategory = ref(false);
const newCategoryName = ref('');
const newCategoryInput = ref(null);
const codeUnlocked = ref(false);

const defaultLevels = computed(() => {
    const fromProps = Array.isArray(props.defaultScoreLevels) ? props.defaultScoreLevels : [];
    if (fromProps.length >= MIN_LEVELS) {
        return fromProps.map((l, index) => ({
            code: String(l.code ?? `M${index + 1}`),
            label: String(l.label ?? ''),
            description: String(l.description ?? ''),
            weight: Number.isFinite(Number(l.weight)) ? Number(l.weight) : 0,
        }));
    }
    return [
        { code: 'M1', label: 'Không đáp ứng', description: '', weight: 1 },
        { code: 'M2', label: 'Cần cố gắng hơn', description: '', weight: 2 },
        { code: 'M3', label: 'Đạt yêu cầu', description: '', weight: 3 },
        { code: 'M4', label: 'Tốt', description: '', weight: 4 },
        { code: 'M5', label: 'Rất tốt', description: '', weight: 5 },
    ];
});

function cloneLevels(levels) {
    return (levels || []).map((l, index) => ({
        code: String(l?.code ?? `M${index + 1}`),
        label: String(l?.label ?? ''),
        description: String(l?.description ?? ''),
        weight: Number.isFinite(Number(l?.weight)) ? Number(l.weight) : 0,
    }));
}

const form = useForm({
    scope: 'department',
    department_code: '',
    department_name: '',
    local_department_id: null,
    criteria_code: '',
    criteria_name: '',
    category: '',
    description: '',
    allow_half_score: false,
    score_levels: cloneLevels(defaultLevels.value),
    is_active: true,
});

const dirty = computed(() => form.isDirty);
const canAddLevel = computed(() => form.score_levels.length < MAX_LEVELS);
const canRemoveLevel = computed(() => form.score_levels.length > MIN_LEVELS);
const isGeneral = computed(() => !String(form.department_code || '').trim());

const categoryList = computed(() => {
    const set = new Set([...(props.categories || []), ...localCategories.value]);
    if (form.category) set.add(form.category);
    return [...set].filter(Boolean).sort((a, b) => a.localeCompare(b, 'vi'));
});

const title = computed(() => (
    props.mode === 'edit' ? 'Sửa tiêu chí đánh giá' : 'Tạo tiêu chí đánh giá'
));

const displayedCode = computed(() => {
    if (props.mode === 'edit') return form.criteria_code;
    return form.criteria_code || props.nextCode || '';
});

const departmentPlaceholder = computed(() => (
    props.canCreateGeneral
        ? 'Tìm phòng ban — để trống = tiêu chí chung'
        : 'Tìm và chọn phòng ban…'
));

function applyDepartment(code) {
    const trimmed = String(code || '').trim();
    form.department_code = trimmed;
    if (!trimmed) {
        form.department_name = '';
        form.local_department_id = null;
        form.scope = 'general';
        return;
    }
    const dept = props.departments.find((d) => d.code === trimmed);
    form.department_name = dept?.name || '';
    form.local_department_id = dept?.local_department_id ?? null;
    form.scope = 'department';
}

function onDepartmentSelect(dept) {
    if (!dept) {
        applyDepartment('');
        return;
    }
    form.department_code = dept.code;
    form.department_name = dept.name;
    form.local_department_id = dept.local_department_id ?? null;
    form.scope = 'department';
    form.clearErrors('department_code');
}

function resetFromProps() {
    const c = props.criterion;
    codeUnlocked.value = false;
    if (props.mode === 'edit' && c) {
        const levels = Array.isArray(c.score_levels) && c.score_levels.length >= MIN_LEVELS
            ? cloneLevels(c.score_levels)
            : cloneLevels(defaultLevels.value);
        form.defaults({
            scope: c.scope || 'general',
            department_code: c.department_code || '',
            department_name: c.department_name || '',
            local_department_id: c.local_department_id || null,
            criteria_code: c.criteria_code || '',
            criteria_name: c.criteria_name || '',
            category: c.category || '',
            description: c.description || '',
            allow_half_score: !!c.allow_half_score,
            score_levels: levels,
            is_active: c.is_active ?? true,
        });
    } else {
        const defaultCode = props.canCreateGeneral
            ? ''
            : (props.defaultDepartmentCode || props.departments[0]?.code || '');
        const dept = props.departments.find((d) => d.code === defaultCode);
        form.defaults({
            scope: defaultCode ? 'department' : 'general',
            department_code: defaultCode,
            department_name: dept?.name || '',
            local_department_id: dept?.local_department_id ?? null,
            criteria_code: '',
            criteria_name: '',
            category: '',
            description: '',
            allow_half_score: false,
            score_levels: cloneLevels(defaultLevels.value),
            is_active: true,
        });
    }
    form.reset();
    form.clearErrors();
    showNewCategory.value = false;
    newCategoryName.value = '';
}

watch(() => props.show, (open) => {
    if (open) resetFromProps();
});

async function openNewCategory() {
    showNewCategory.value = true;
    newCategoryName.value = '';
    await nextTick();
    newCategoryInput.value?.focus();
}

function cancelNewCategory() {
    showNewCategory.value = false;
    newCategoryName.value = '';
}

async function addCategory() {
    const name = newCategoryName.value.trim();
    if (!name) return;

    const exists = categoryList.value.some(
        (cat) => cat.localeCompare(name, 'vi', { sensitivity: 'accent' }) === 0,
    );
    if (exists) {
        form.category = categoryList.value.find(
            (cat) => cat.localeCompare(name, 'vi', { sensitivity: 'accent' }) === 0,
        ) || name;
        newCategoryName.value = '';
        showNewCategory.value = false;
        return;
    }

    const ok = await dialog.confirm({
        title: 'Thêm loại tiêu chí mới?',
        message: `Bạn sắp tạo loại «${name}». Loại này sẽ có thể chọn lại khi tạo tiêu chí sau.`,
        confirmText: 'Thêm loại',
        cancelText: 'Huỷ',
    });
    if (!ok) return;

    if (!localCategories.value.includes(name)) {
        localCategories.value = [...localCategories.value, name];
    }
    form.category = name;
    newCategoryName.value = '';
    showNewCategory.value = false;
}

function toggleCodeLock() {
    codeUnlocked.value = !codeUnlocked.value;
    if (codeUnlocked.value && props.mode === 'create' && !form.criteria_code) {
        form.criteria_code = props.nextCode || '';
    }
    if (!codeUnlocked.value && props.mode === 'create') {
        form.criteria_code = '';
    }
}

function onCodeInput(event) {
    if (!codeUnlocked.value) return;
    form.criteria_code = String(event.target.value || '').toUpperCase();
}

function addScoreLevel() {
    if (!canAddLevel.value) return;
    const n = form.score_levels.length + 1;
    form.score_levels.push({ code: `M${n}`, label: '', description: '', weight: n });
}

function removeScoreLevel(index) {
    if (!canRemoveLevel.value) return;
    form.score_levels.splice(index, 1);
}

function close() {
    emit('close');
}

function submit() {
    if (!props.canCreateGeneral && !String(form.department_code || '').trim()) {
        form.setError('department_code', 'Vui lòng chọn phòng ban.');
        return;
    }

    applyDepartment(form.department_code);

    if (props.mode === 'create' && !codeUnlocked.value) {
        form.criteria_code = '';
    } else if (props.mode === 'create' && !String(form.criteria_code || '').trim()) {
        form.criteria_code = props.nextCode || '';
    }

    form.score_levels.forEach(snapWeight);

    const payload = {
        preserveScroll: true,
        onSuccess: () => close(),
    };

    if (props.mode === 'edit' && props.criterion?.id) {
        form.put(route('workspace.evaluation.update', props.criterion.id), payload);
    } else {
        form.post(route('workspace.evaluation.store'), payload);
    }
}

const fieldError = (key) => form.errors[key];

function levelError(index, field) {
    return form.errors[`score_levels.${index}.${field}`]
        || form.errors[`score_levels.${index}`]
        || '';
}

const weightStep = computed(() => (form.allow_half_score ? 0.5 : 1));

function snapWeight(level) {
    const n = Number(level.weight);
    const safe = Number.isFinite(n) ? n : 0;
    const step = weightStep.value;
    level.weight = Math.round(safe / step) * step;
}

/** Chặn dấu thập phân / e khi chưa bật chấm 0.5. */
function onWeightKeydown(event) {
    if (form.allow_half_score) return;
    if (['.', ',', 'e', 'E', '+'].includes(event.key)) {
        event.preventDefault();
    }
}

function onWeightInput(level, event) {
    if (form.allow_half_score) return;
    const raw = String(event.target?.value ?? '');
    if (!/[.,]/.test(raw)) return;
    const cleaned = raw.replace(/[.,]/g, '');
    const n = Number(cleaned);
    level.weight = Number.isFinite(n) ? n : 0;
    event.target.value = String(level.weight);
}

watch(() => form.allow_half_score, () => {
    form.score_levels.forEach(snapWeight);
});
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="max-w-4xl"
    :dirty="dirty"
    close-confirm-title="Huỷ tạo tiêu chí?"
    close-confirm-message="Thay đổi chưa lưu sẽ bị mất."
    @close="close"
  >
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <!-- Phòng ban + toggles -->
      <div class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-3">
        <div class="flex flex-wrap items-start gap-3">
          <div class="min-w-[14rem] flex-1 space-y-1">
            <div class="flex items-center justify-between gap-2">
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Phòng ban
                <span
                  v-if="!canCreateGeneral"
                  class="text-rose-500"
                >*</span>
              </span>
              <span
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="isGeneral
                  ? 'bg-violet-50 text-violet-700 ring-1 ring-violet-200/80'
                  : 'bg-sky-50 text-sky-700 ring-1 ring-sky-200/80'"
              >
                <AppIcon
                  :name="isGeneral ? 'documents' : 'department'"
                  :size="11"
                />
                {{ isGeneral ? 'Tiêu chí chung' : 'Theo phòng ban' }}
              </span>
            </div>
            <DepartmentAutocomplete
              id="evaluation-criterion-department"
              :model-value="form.department_code"
              :options="departments"
              :placeholder="departmentPlaceholder"
              :clearable="canCreateGeneral"
              :invalid="!!fieldError('department_code')"
              @update:model-value="applyDepartment"
              @select="onDepartmentSelect"
            />
            <p
              v-if="fieldError('department_code')"
              class="text-xs text-rose-600"
            >
              {{ fieldError('department_code') }}
            </p>
          </div>

          <div class="flex shrink-0 flex-wrap items-center gap-2 sm:pt-5">
            <button
              type="button"
              role="switch"
              class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-medium transition"
              :class="form.allow_half_score
                ? 'border-brand/30 bg-brand/5 text-brand shadow-sm'
                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'"
              :aria-checked="form.allow_half_score"
              title="Cho phép trọng số lẻ 0.5"
              @click="form.allow_half_score = !form.allow_half_score"
            >
              <span
                class="relative inline-flex h-4 w-7 shrink-0 rounded-full transition-colors"
                :class="form.allow_half_score ? 'bg-brand' : 'bg-slate-300'"
              >
                <span
                  class="absolute top-0.5 left-0.5 h-3 w-3 rounded-full bg-white shadow transition-transform"
                  :class="form.allow_half_score ? 'translate-x-3' : 'translate-x-0'"
                />
              </span>
              Chấm 0.5
            </button>

            <button
              type="button"
              role="switch"
              class="inline-flex h-9 items-center gap-2 rounded-lg border px-3 text-xs font-medium transition"
              :class="form.is_active
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 shadow-sm'
                : 'border-slate-200 bg-slate-50 text-slate-500 hover:border-slate-300'"
              :aria-checked="form.is_active"
              title="Trạng thái tiêu chí"
              @click="form.is_active = !form.is_active"
            >
              <span
                class="relative inline-flex h-4 w-7 shrink-0 rounded-full transition-colors"
                :class="form.is_active ? 'bg-emerald-500' : 'bg-slate-300'"
              >
                <span
                  class="absolute top-0.5 left-0.5 h-3 w-3 rounded-full bg-white shadow transition-transform"
                  :class="form.is_active ? 'translate-x-3' : 'translate-x-0'"
                />
              </span>
              {{ form.is_active ? 'Hoạt động' : 'Ngưng' }}
            </button>
          </div>
        </div>
        <p
          v-if="fieldError('scope')"
          class="mt-1.5 text-xs text-rose-600"
        >
          {{ fieldError('scope') }}
        </p>
      </div>

      <!-- Tên tiêu chí -->
      <div class="grid grid-cols-[7rem_1fr] items-center gap-x-3 gap-y-1">
        <span class="text-sm font-medium text-slate-600">
          Tên tiêu chí <span class="text-rose-500">*</span>
        </span>
        <input
          v-model="form.criteria_name"
          type="text"
          class="input h-9 w-full text-sm"
          :class="fieldError('criteria_name') ? 'border-rose-300' : ''"
          placeholder="VD: Thái độ hợp tác/tinh thần tập thể"
          maxlength="255"
        >
        <span />
        <p
          v-if="fieldError('criteria_name')"
          class="text-xs text-rose-600"
        >
          {{ fieldError('criteria_name') }}
        </p>
      </div>

      <!-- Loại + Mã -->
      <div class="grid grid-cols-1 gap-x-6 gap-y-2.5 sm:grid-cols-2">
        <div class="grid grid-cols-[7rem_1fr] items-center gap-x-3 gap-y-1">
          <span class="text-sm font-medium text-slate-600">
            Loại tiêu chí <span class="text-rose-500">*</span>
          </span>
          <div
            v-if="showNewCategory"
            class="flex gap-1.5"
          >
            <input
              ref="newCategoryInput"
              v-model="newCategoryName"
              type="text"
              class="input h-9 min-w-0 flex-1 text-sm"
              placeholder="Tên loại mới…"
              maxlength="100"
              @keydown.enter.prevent="addCategory"
              @keydown.esc.prevent="cancelNewCategory"
            >
            <button
              type="button"
              class="btn-primary h-9 shrink-0 px-2.5 text-xs"
              title="Lưu loại"
              @click="addCategory"
            >
              <AppIcon
                name="check"
                :size="14"
              />
            </button>
            <button
              type="button"
              class="btn-ghost h-9 w-9 shrink-0 p-0"
              title="Huỷ"
              @click="cancelNewCategory"
            >
              <AppIcon
                name="close"
                :size="14"
              />
            </button>
          </div>
          <div
            v-else
            class="flex gap-1.5"
          >
            <select
              v-model="form.category"
              class="input h-9 min-w-0 flex-1 text-sm"
              :class="fieldError('category') ? 'border-rose-300' : ''"
            >
              <option value="">
                Chọn loại…
              </option>
              <option
                v-for="cat in categoryList"
                :key="cat"
                :value="cat"
              >
                {{ cat }}
              </option>
            </select>
            <button
              type="button"
              class="btn-ghost inline-flex h-9 w-9 shrink-0 items-center justify-center p-0 text-brand"
              title="Thêm loại tiêu chí mới"
              aria-label="Thêm loại tiêu chí mới"
              @click="openNewCategory"
            >
              <AppIcon
                name="add"
                :size="16"
              />
            </button>
          </div>
          <span />
          <p
            v-if="fieldError('category')"
            class="text-xs text-rose-600"
          >
            {{ fieldError('category') }}
          </p>
        </div>

        <div class="grid grid-cols-[7rem_1fr] items-center gap-x-3 gap-y-1">
          <span class="text-sm font-medium text-slate-600">
            Mã tiêu chí
          </span>
          <div class="flex gap-1.5">
            <input
              :value="displayedCode"
              type="text"
              class="input h-9 min-w-0 flex-1 font-mono text-sm uppercase"
              :class="!codeUnlocked ? 'bg-slate-50 text-slate-500' : ''"
              :placeholder="`Tự động: ${nextCode}`"
              maxlength="100"
              :readonly="!codeUnlocked"
              :disabled="!codeUnlocked"
              @input="onCodeInput"
            >
            <button
              type="button"
              class="btn-ghost inline-flex h-9 w-9 shrink-0 items-center justify-center p-0"
              :class="codeUnlocked ? 'text-brand bg-brand/5' : 'text-slate-500'"
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
          <span />
          <p
            v-if="fieldError('criteria_code')"
            class="text-xs text-rose-600"
          >
            {{ fieldError('criteria_code') }}
          </p>
        </div>
      </div>

      <!-- Mô tả -->
      <div class="grid grid-cols-[7rem_1fr] items-start gap-x-3 gap-y-1">
        <span class="pt-2 text-sm font-medium text-slate-600">
          Mô tả
        </span>
        <textarea
          v-model="form.description"
          class="input w-full resize-none text-sm"
          rows="2"
          placeholder="Mô tả cách đánh giá tiêu chí này…"
          maxlength="5000"
        />
        <span />
        <p
          v-if="fieldError('description')"
          class="text-xs text-rose-600"
        >
          {{ fieldError('description') }}
        </p>
      </div>

      <!-- Thang điểm -->
      <div class="rounded-xl border border-slate-200 bg-gradient-to-b from-slate-50/90 to-white p-3">
        <div class="mb-2 flex items-center justify-between gap-2">
          <p class="text-sm font-semibold text-slate-800">
            Thang điểm đánh giá <span class="text-rose-500">*</span>
          </p>
          <button
            type="button"
            class="btn-ghost inline-flex h-8 shrink-0 items-center gap-1 px-2 text-xs text-brand"
            :disabled="!canAddLevel"
            @click="addScoreLevel"
          >
            <AppIcon
              name="add"
              :size="14"
            />
            Thêm mức
          </button>
        </div>

        <p
          v-if="fieldError('score_levels')"
          class="mb-2 text-xs text-rose-600"
        >
          {{ fieldError('score_levels') }}
        </p>

        <div class="grid grid-cols-[0.25rem_5rem_1fr_1fr_6.5rem_1.75rem] items-center gap-x-2 px-1 pb-1 text-[11px] font-medium uppercase tracking-wide text-slate-400">
          <span />
          <span>Mã mức</span>
          <span>Nhãn mức</span>
          <span>Mô tả ngắn</span>
          <span class="text-center">Trọng số</span>
          <span />
        </div>

        <div class="space-y-1">
          <div
            v-for="(level, index) in form.score_levels"
            :key="`level-${index}`"
            class="grid grid-cols-[0.25rem_5rem_1fr_1fr_6.5rem_1.75rem] items-center gap-x-2 rounded-lg border border-slate-200/80 bg-white px-1.5 py-1.5"
          >
            <span
              class="h-6 w-1 shrink-0 rounded-full"
              :class="LEVEL_TONES[index % LEVEL_TONES.length]"
              aria-hidden="true"
            />

            <div class="min-w-0">
              <input
                v-model="level.code"
                type="text"
                class="input h-8 w-full text-sm"
                :class="levelError(index, 'code') ? 'border-rose-300' : ''"
                :placeholder="`M${index + 1}`"
                maxlength="50"
              >
              <p
                v-if="levelError(index, 'code')"
                class="mt-0.5 text-[11px] text-rose-600"
              >
                {{ levelError(index, 'code') }}
              </p>
            </div>

            <div class="min-w-0">
              <input
                v-model="level.label"
                type="text"
                class="input h-8 w-full text-sm"
                :class="levelError(index, 'label') ? 'border-rose-300' : ''"
                :placeholder="`Nhãn mức ${index + 1}`"
                maxlength="255"
              >
              <p
                v-if="levelError(index, 'label')"
                class="mt-0.5 text-[11px] text-rose-600"
              >
                {{ levelError(index, 'label') }}
              </p>
            </div>

            <div class="min-w-0">
              <input
                v-model="level.description"
                type="text"
                class="input h-8 w-full text-sm"
                :class="levelError(index, 'description') ? 'border-rose-300' : ''"
                placeholder="Mô tả ngắn (tuỳ chọn)"
                maxlength="500"
              >
              <p
                v-if="levelError(index, 'description')"
                class="mt-0.5 text-[11px] text-rose-600"
              >
                {{ levelError(index, 'description') }}
              </p>
            </div>

            <div class="min-w-0">
              <input
                v-model.number="level.weight"
                type="number"
                :step="weightStep"
                min="-999"
                max="999"
                class="input h-8 w-full px-1 text-center text-sm tabular-nums"
                :class="levelError(index, 'weight') ? 'border-rose-300' : ''"
                :aria-label="`Trọng số mức ${index + 1}`"
                :title="form.allow_half_score ? 'Trọng số (điểm, có thể lẻ 0.5)' : 'Trọng số (điểm nguyên)'"
                @keydown="onWeightKeydown"
                @input="onWeightInput(level, $event)"
                @blur="snapWeight(level)"
              >
              <p
                v-if="levelError(index, 'weight')"
                class="mt-0.5 text-center text-[11px] text-rose-600"
              >
                {{ levelError(index, 'weight') }}
              </p>
            </div>

            <button
              type="button"
              class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-30"
              :disabled="!canRemoveLevel"
              :title="canRemoveLevel ? 'Xóa mức' : `Tối thiểu ${MIN_LEVELS} mức`"
              :aria-label="`Xóa mức ${index + 1}`"
              @click="removeScoreLevel(index)"
            >
              <AppIcon
                name="trash"
                :size="14"
              />
            </button>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
        <button
          type="button"
          class="btn-ghost h-9 px-3 text-sm"
          @click="close"
        >
          Hủy
        </button>
        <button
          type="submit"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-sm"
          :disabled="form.processing"
        >
          <AppIcon
            name="check"
            :size="15"
          />
          {{ mode === 'edit' ? 'Lưu thay đổi' : 'Tạo tiêu chí' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
