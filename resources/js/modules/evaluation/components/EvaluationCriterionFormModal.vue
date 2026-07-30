<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' }, // create | edit
    criterion: { type: Object, default: null },
    departments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    scoringTypeOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: '1' },
    defaultScoreLabels: { type: Object, default: () => ({}) },
    initialScope: { type: String, default: 'general' },
});

const emit = defineEmits(['close']);
const dialog = useDialog();

const localCategories = ref([]);
const showNewCategory = ref(false);
const newCategoryName = ref('');
const newCategoryInput = ref(null);
const codeUnlocked = ref(false);

const defaults = computed(() => ({
    1: props.defaultScoreLabels?.[1] || props.defaultScoreLabels?.['1'] || 'Không đáp ứng',
    2: props.defaultScoreLabels?.[2] || props.defaultScoreLabels?.['2'] || 'Cần cố gắng hơn',
    3: props.defaultScoreLabels?.[3] || props.defaultScoreLabels?.['3'] || 'Đạt yêu cầu',
    4: props.defaultScoreLabels?.[4] || props.defaultScoreLabels?.['4'] || 'Tốt',
    5: props.defaultScoreLabels?.[5] || props.defaultScoreLabels?.['5'] || 'Rất tốt',
}));

const form = useForm({
    scope: 'general',
    department_code: '',
    department_name: '',
    local_department_id: null,
    criteria_code: '',
    criteria_name: '',
    category: '',
    scoring_type: 'scale',
    description: '',
    allow_half_score: false,
    point_bonus: 0,
    point_penalty: 0,
    score_1: '',
    score_2: '',
    score_3: '',
    score_4: '',
    score_5: '',
    is_active: true,
});

const dirty = computed(() => form.isDirty);
const isDepartment = computed(() => form.scope === 'department');
const isPoints = computed(() => form.scoring_type === 'points');
const isScale = computed(() => form.scoring_type === 'scale');

const categoryList = computed(() => {
    const set = new Set([...(props.categories || []), ...localCategories.value]);
    if (form.category) set.add(form.category);
    return [...set].filter(Boolean).sort((a, b) => a.localeCompare(b, 'vi'));
});

const scopeTabs = computed(() => {
    const opts = props.scopeOptions?.length
        ? props.scopeOptions
        : [
            { value: 'general', label: 'Tiêu chí chung' },
            { value: 'department', label: 'Theo phòng ban' },
        ];
    return opts;
});

const scoringTabs = computed(() => {
    const opts = props.scoringTypeOptions?.length
        ? props.scoringTypeOptions
        : [
            { value: 'scale', label: 'Thang nhãn 1–5' },
            { value: 'points', label: 'Điểm cộng / trừ' },
        ];
    return opts;
});

const title = computed(() => (
    props.mode === 'edit' ? 'Sửa tiêu chí đánh giá' : 'Tạo tiêu chí đánh giá'
));

const displayedCode = computed(() => {
    if (props.mode === 'edit') return form.criteria_code;
    return form.criteria_code || props.nextCode || '';
});

function resetFromProps() {
    const c = props.criterion;
    const d = defaults.value;
    codeUnlocked.value = false;
    if (props.mode === 'edit' && c) {
        form.defaults({
            scope: c.scope || 'general',
            department_code: c.department_code || '',
            department_name: c.department_name || '',
            local_department_id: c.local_department_id || null,
            criteria_code: c.criteria_code || '',
            criteria_name: c.criteria_name || '',
            category: c.category || '',
            scoring_type: c.scoring_type || 'scale',
            description: c.description || '',
            allow_half_score: !!c.allow_half_score,
            point_bonus: c.point_bonus ?? 0,
            point_penalty: c.point_penalty ?? 0,
            score_1: c.score_1 || d[1],
            score_2: c.score_2 || d[2],
            score_3: c.score_3 || d[3],
            score_4: c.score_4 || d[4],
            score_5: c.score_5 || d[5],
            is_active: c.is_active ?? true,
        });
    } else {
        form.defaults({
            scope: props.initialScope === 'department' ? 'department' : 'general',
            department_code: '',
            department_name: '',
            local_department_id: null,
            criteria_code: '',
            criteria_name: '',
            category: '',
            scoring_type: 'scale',
            description: '',
            allow_half_score: false,
            point_bonus: 0,
            point_penalty: 0,
            score_1: d[1],
            score_2: d[2],
            score_3: d[3],
            score_4: d[4],
            score_5: d[5],
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

watch(() => form.department_code, (code) => {
    if (!isDepartment.value) return;
    const dept = props.departments.find((d) => d.code === code);
    if (dept) {
        form.department_name = dept.name;
        form.local_department_id = dept.local_department_id;
    }
});

watch(() => form.scope, (scope) => {
    if (scope === 'general') {
        form.department_code = '';
        form.department_name = '';
        form.local_department_id = null;
        form.clearErrors('department_code');
    }
});

watch(() => form.scoring_type, (type) => {
    if (type === 'points') {
        form.allow_half_score = false;
        if (form.point_bonus === null || form.point_bonus === '') form.point_bonus = 0;
        if (form.point_penalty === null || form.point_penalty === '') form.point_penalty = 0;
    } else {
        const d = defaults.value;
        if (!form.score_1) form.score_1 = d[1];
        if (!form.score_2) form.score_2 = d[2];
        if (!form.score_3) form.score_3 = d[3];
        if (!form.score_4) form.score_4 = d[4];
        if (!form.score_5) form.score_5 = d[5];
    }
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

function close() {
    emit('close');
}

function submit() {
    if (props.mode === 'create' && !codeUnlocked.value) {
        form.criteria_code = '';
    } else if (props.mode === 'create' && !String(form.criteria_code || '').trim()) {
        form.criteria_code = props.nextCode || '';
    }

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
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="max-w-5xl"
    :dirty="dirty"
    close-confirm-title="Huỷ tạo tiêu chí?"
    close-confirm-message="Thay đổi chưa lưu sẽ bị mất."
    @close="close"
  >
    <form
      class="space-y-3"
      @submit.prevent="submit"
    >
      <div class="space-y-1.5">
        <span class="text-xs font-medium text-slate-600">
          Phạm vi tiêu chí <span class="text-rose-500">*</span>
        </span>
        <div
          class="grid grid-cols-2 gap-2"
          role="tablist"
          aria-label="Phạm vi tiêu chí"
        >
          <button
            v-for="opt in scopeTabs"
            :key="opt.value"
            type="button"
            role="tab"
            class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg border px-3 text-sm font-medium transition"
            :class="form.scope === opt.value
              ? 'border-brand/40 bg-brand/5 text-brand shadow-sm'
              : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
            :aria-selected="form.scope === opt.value"
            @click="form.scope = opt.value"
          >
            <AppIcon
              :name="opt.value === 'general' ? 'documents' : 'department'"
              :size="15"
              class="shrink-0 opacity-80"
            />
            <span class="truncate">{{ opt.label }}</span>
          </button>
        </div>
        <p
          v-if="fieldError('scope')"
          class="text-xs text-rose-600"
        >
          {{ fieldError('scope') }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 lg:items-start">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:col-span-5">
          <label
            v-if="isDepartment"
            class="block space-y-1 sm:col-span-2"
          >
            <span class="text-xs font-medium text-slate-600">
              Phòng ban <span class="text-rose-500">*</span>
            </span>
            <select
              v-model="form.department_code"
              class="input h-9 w-full text-sm"
            >
              <option
                value=""
                disabled
              >
                Chọn phòng ban
              </option>
              <option
                v-for="d in departments"
                :key="d.code"
                :value="d.code"
              >
                {{ d.name }} ({{ d.code }})
              </option>
            </select>
            <p
              v-if="fieldError('department_code')"
              class="text-xs text-rose-600"
            >
              {{ fieldError('department_code') }}
            </p>
          </label>

          <label class="block space-y-1 sm:col-span-2">
            <span class="text-xs font-medium text-slate-600">
              Tên tiêu chí <span class="text-rose-500">*</span>
            </span>
            <input
              v-model="form.criteria_name"
              type="text"
              class="input h-9 w-full text-sm"
              placeholder="VD: Thái độ hợp tác/tinh thần tập thể"
              maxlength="255"
            >
            <p
              v-if="fieldError('criteria_name')"
              class="text-xs text-rose-600"
            >
              {{ fieldError('criteria_name') }}
            </p>
          </label>

          <div class="space-y-1 sm:col-span-2">
            <div class="flex items-center gap-1.5">
              <span class="text-xs font-medium text-slate-600">
                Loại tiêu chí <span class="text-rose-500">*</span>
              </span>
              <button
                type="button"
                class="inline-flex h-5 w-5 items-center justify-center rounded text-brand hover:bg-brand/10"
                title="Thêm loại tiêu chí mới"
                aria-label="Thêm loại tiêu chí mới"
                @click="openNewCategory"
              >
                <AppIcon
                  name="add"
                  :size="14"
                />
              </button>
            </div>
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
            <select
              v-else
              v-model="form.category"
              class="input h-9 w-full text-sm"
            >
              <option value="">
                Chọn loại tiêu chí
              </option>
              <option
                v-for="cat in categoryList"
                :key="cat"
                :value="cat"
              >
                {{ cat }}
              </option>
            </select>
            <p
              v-if="fieldError('category')"
              class="text-xs text-rose-600"
            >
              {{ fieldError('category') }}
            </p>
          </div>

          <!-- Mã: disabled + unlock -->
          <div class="space-y-1">
            <span class="text-xs font-medium text-slate-600">
              Mã tiêu chí
            </span>
            <div class="flex gap-1.5">
              <input
                :value="displayedCode"
                type="text"
                class="input h-9 min-w-0 flex-1 font-mono text-sm"
                :class="!codeUnlocked ? 'bg-slate-50 text-slate-500' : ''"
                :placeholder="`Tự động: ${nextCode}`"
                maxlength="100"
                :readonly="!codeUnlocked"
                :disabled="!codeUnlocked"
                @input="codeUnlocked && (form.criteria_code = $event.target.value)"
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
            <p class="text-[11px] text-slate-400">
              {{ codeUnlocked ? 'Đang cho phép sửa mã thủ công.' : 'Mã tự động — bấm biểu tượng khoá để sửa.' }}
            </p>
            <p
              v-if="fieldError('criteria_code')"
              class="text-xs text-rose-600"
            >
              {{ fieldError('criteria_code') }}
            </p>
          </div>

          <label class="block space-y-1">
            <span class="text-xs font-medium text-slate-600">
              Trạng thái <span class="text-rose-500">*</span>
            </span>
            <select
              class="input h-9 w-full text-sm"
              :value="form.is_active ? '1' : '0'"
              @change="form.is_active = $event.target.value === '1'"
            >
              <option value="1">
                Hoạt động
              </option>
              <option value="0">
                Ngưng hoạt động
              </option>
            </select>
            <p
              v-if="fieldError('is_active')"
              class="text-xs text-rose-600"
            >
              {{ fieldError('is_active') }}
            </p>
          </label>

          <label
            v-if="isScale"
            class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 sm:col-span-2"
          >
            <input
              v-model="form.allow_half_score"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand"
            >
            <span class="text-sm text-slate-700">
              Chấm điểm chính xác 0.5
            </span>
          </label>

          <label class="block space-y-1 sm:col-span-2">
            <span class="text-xs font-medium text-slate-600">
              Mô tả
            </span>
            <textarea
              v-model="form.description"
              class="input w-full resize-none text-sm"
              rows="3"
              placeholder="Mô tả cách đánh giá tiêu chí này…"
              maxlength="5000"
            />
            <p
              v-if="fieldError('description')"
              class="text-xs text-rose-600"
            >
              {{ fieldError('description') }}
            </p>
          </label>
        </div>

        <!-- Thang điểm / điểm cộng trừ -->
        <aside class="rounded-xl border border-slate-200 bg-gradient-to-b from-slate-50/90 to-white p-3 sm:p-4 lg:col-span-7">
          <div class="mb-3 flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand/10 text-brand ring-1 ring-brand/15">
              <AppIcon
                name="award"
                :size="15"
              />
            </span>
            <div class="min-w-0">
              <p class="text-xs font-semibold text-slate-800">
                Thang điểm đánh giá <span class="text-rose-500">*</span>
              </p>
              <p class="text-[11px] text-slate-400">
                Chọn kiểu theo loại tiêu chí
              </p>
            </div>
          </div>

          <div
            class="mb-3 grid grid-cols-2 gap-2"
            role="tablist"
            aria-label="Kiểu thang điểm"
          >
            <button
              v-for="opt in scoringTabs"
              :key="opt.value"
              type="button"
              role="tab"
              class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border px-2 text-xs font-medium transition sm:text-sm"
              :class="form.scoring_type === opt.value
                ? 'border-brand/40 bg-brand/5 text-brand shadow-sm'
                : 'border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-white'"
              :aria-selected="form.scoring_type === opt.value"
              @click="form.scoring_type = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>
          <p
            v-if="fieldError('scoring_type')"
            class="mb-2 text-xs text-rose-600"
          >
            {{ fieldError('scoring_type') }}
          </p>

          <!-- Points mode -->
          <div
            v-if="isPoints"
            class="grid grid-cols-1 gap-3 sm:grid-cols-2"
          >
            <label class="block space-y-1.5 rounded-lg border border-emerald-200/80 bg-emerald-50/40 p-3">
              <span class="flex items-center gap-1.5 text-xs font-semibold text-emerald-800">
                <AppIcon
                  name="add"
                  :size="14"
                />
                Điểm cộng
              </span>
              <input
                v-model.number="form.point_bonus"
                type="number"
                min="0"
                max="999"
                step="1"
                class="input h-10 w-full text-sm tabular-nums"
                placeholder="0"
              >
              <p class="text-[11px] text-emerald-700/70">
                Số điểm cộng khi đạt tiêu chí
              </p>
              <p
                v-if="fieldError('point_bonus')"
                class="text-xs text-rose-600"
              >
                {{ fieldError('point_bonus') }}
              </p>
            </label>
            <label class="block space-y-1.5 rounded-lg border border-rose-200/80 bg-rose-50/40 p-3">
              <span class="flex items-center gap-1.5 text-xs font-semibold text-rose-800">
                <AppIcon
                  name="minus"
                  :size="14"
                />
                Điểm trừ
              </span>
              <input
                v-model.number="form.point_penalty"
                type="number"
                min="0"
                max="999"
                step="1"
                class="input h-10 w-full text-sm tabular-nums"
                placeholder="0"
              >
              <p class="text-[11px] text-rose-700/70">
                Số điểm trừ khi vi phạm / không đạt
              </p>
              <p
                v-if="fieldError('point_penalty')"
                class="text-xs text-rose-600"
              >
                {{ fieldError('point_penalty') }}
              </p>
            </label>
          </div>

          <!-- Scale mode -->
          <div
            v-else
            class="grid grid-cols-1 gap-2"
          >
            <label
              v-for="n in 5"
              :key="n"
              class="flex items-start gap-2.5 rounded-lg border border-slate-200/80 bg-white px-2.5 py-2"
            >
              <span
                class="mt-1.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-[11px] font-bold tabular-nums text-white"
                :class="{
                  'bg-rose-500': n === 1,
                  'bg-amber-500': n === 2,
                  'bg-slate-400': n === 3,
                  'bg-sky-500': n === 4,
                  'bg-emerald-500': n === 5,
                }"
              >
                {{ n }}
              </span>
              <div class="min-w-0 flex-1 space-y-1">
                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                  Điểm {{ n }}
                </span>
                <input
                  v-model="form[`score_${n}`]"
                  type="text"
                  class="input h-9 w-full text-sm"
                  :placeholder="defaults[n]"
                  maxlength="255"
                >
                <p
                  v-if="fieldError(`score_${n}`)"
                  class="text-xs text-rose-600"
                >
                  {{ fieldError(`score_${n}`) }}
                </p>
              </div>
            </label>
          </div>
        </aside>
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
