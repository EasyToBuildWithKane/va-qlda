<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    mode: { type: String, default: 'create' }, // create | edit
    criterion: { type: Object, default: null },
    departments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: '1' },
    defaultScoreLabels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close']);

const localCategories = ref([]);
const showNewCategory = ref(false);
const newCategoryName = ref('');

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
    description: '',
    allow_half_score: false,
    score_1: '',
    score_2: '',
    score_3: '',
    score_4: '',
    score_5: '',
    is_active: true,
});

const dirty = computed(() => form.isDirty);
const isDepartment = computed(() => form.scope === 'department');
const categoryList = computed(() => {
    const set = new Set([...(props.categories || []), ...localCategories.value]);
    if (form.category) set.add(form.category);
    return [...set].filter(Boolean).sort((a, b) => a.localeCompare(b, 'vi'));
});

const title = computed(() => (
    props.mode === 'edit' ? 'Sửa tiêu chí đánh giá' : 'Tạo tiêu chí đánh giá'
));

function resetFromProps() {
    const c = props.criterion;
    const d = defaults.value;
    if (props.mode === 'edit' && c) {
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
            score_1: c.score_1 || d[1],
            score_2: c.score_2 || d[2],
            score_3: c.score_3 || d[3],
            score_4: c.score_4 || d[4],
            score_5: c.score_5 || d[5],
            is_active: c.is_active ?? true,
        });
    } else {
        form.defaults({
            scope: 'general',
            department_code: '',
            department_name: '',
            local_department_id: null,
            criteria_code: '',
            criteria_name: '',
            category: '',
            description: '',
            allow_half_score: false,
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

function addCategory() {
    const name = newCategoryName.value.trim();
    if (!name) return;
    if (!localCategories.value.includes(name)) {
        localCategories.value = [...localCategories.value, name];
    }
    form.category = name;
    newCategoryName.value = '';
    showNewCategory.value = false;
}

function close() {
    emit('close');
}

function submit() {
    if (!String(form.criteria_code || '').trim()) {
        form.criteria_code = props.nextCode || '';
    }

    if (props.mode === 'edit' && props.criterion?.id) {
        form.put(route('workspace.evaluation.update', props.criterion.id), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
    } else {
        form.post(route('workspace.evaluation.store'), {
            preserveScroll: true,
            onSuccess: () => close(),
        });
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
      class="space-y-5"
      @submit.prevent="submit"
    >
      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <!-- Phạm vi -->
        <div class="space-y-1.5 md:col-span-2">
          <span class="text-xs font-medium text-slate-600">
            Phạm vi tiêu chí <span class="text-rose-500">*</span>
          </span>
          <div class="flex flex-wrap gap-3">
            <label
              v-for="opt in scopeOptions"
              :key="opt.value"
              class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm transition"
              :class="form.scope === opt.value
                ? 'border-brand/40 bg-brand/5 text-brand'
                : 'border-slate-200 text-slate-700 hover:border-slate-300'"
            >
              <input
                v-model="form.scope"
                type="radio"
                class="text-brand focus:ring-brand"
                :value="opt.value"
              >
              {{ opt.label }}
            </label>
          </div>
          <p
            v-if="fieldError('scope')"
            class="text-xs text-rose-600"
          >
            {{ fieldError('scope') }}
          </p>
        </div>

        <!-- Phòng ban -->
        <label
          v-if="isDepartment"
          class="block space-y-1.5 md:col-span-2"
        >
          <span class="text-xs font-medium text-slate-600">
            Phòng ban <span class="text-rose-500">*</span>
          </span>
          <select
            v-model="form.department_code"
            class="input h-10 w-full text-sm"
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

        <!-- Mã -->
        <label class="block space-y-1.5">
          <span class="text-xs font-medium text-slate-600">
            Mã tiêu chí
          </span>
          <input
            v-model="form.criteria_code"
            type="text"
            class="input h-10 w-full font-mono text-sm"
            :placeholder="`Tự động: ${nextCode}`"
            maxlength="100"
          >
          <p class="text-[11px] text-slate-400">
            Để trống để hệ thống tự tạo mã. Có thể điền để ghi đè.
          </p>
          <p
            v-if="fieldError('criteria_code')"
            class="text-xs text-rose-600"
          >
            {{ fieldError('criteria_code') }}
          </p>
        </label>

        <!-- Trạng thái -->
        <label class="block space-y-1.5">
          <span class="text-xs font-medium text-slate-600">
            Trạng thái <span class="text-rose-500">*</span>
          </span>
          <select
            class="input h-10 w-full text-sm"
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

        <!-- Tên -->
        <label class="block space-y-1.5 md:col-span-2">
          <span class="text-xs font-medium text-slate-600">
            Tên tiêu chí <span class="text-rose-500">*</span>
          </span>
          <input
            v-model="form.criteria_name"
            type="text"
            class="input h-10 w-full text-sm"
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

        <!-- Loại tiêu chí -->
        <div class="space-y-1.5 md:col-span-2">
          <span class="text-xs font-medium text-slate-600">
            Loại tiêu chí <span class="text-rose-500">*</span>
          </span>
          <div class="flex gap-2">
            <input
              v-model="form.category"
              type="text"
              list="evaluation-category-list"
              class="input h-10 min-w-0 flex-1 text-sm"
              placeholder="VD: Thái độ — gõ hoặc chọn từ danh sách"
              maxlength="100"
              autocomplete="off"
            >
            <datalist id="evaluation-category-list">
              <option
                v-for="cat in categoryList"
                :key="cat"
                :value="cat"
              />
            </datalist>
            <button
              type="button"
              class="btn-ghost inline-flex h-10 w-10 shrink-0 items-center justify-center"
              title="Thêm loại tiêu chí mới"
              @click="showNewCategory = !showNewCategory"
            >
              <AppIcon
                name="add"
                :size="16"
              />
            </button>
          </div>
          <div
            v-if="showNewCategory"
            class="flex gap-2"
          >
            <input
              v-model="newCategoryName"
              type="text"
              class="input h-10 min-w-0 flex-1 text-sm"
              placeholder="Tên loại tiêu chí mới"
              maxlength="100"
              @keydown.enter.prevent="addCategory"
            >
            <button
              type="button"
              class="btn-primary h-10 px-3 text-xs"
              @click="addCategory"
            >
              Thêm
            </button>
          </div>
          <p
            v-if="fieldError('category')"
            class="text-xs text-rose-600"
          >
            {{ fieldError('category') }}
          </p>
        </div>

        <!-- Half score -->
        <label class="flex items-start gap-2.5 md:col-span-2">
          <input
            v-model="form.allow_half_score"
            type="checkbox"
            class="mt-0.5 rounded border-slate-300 text-brand focus:ring-brand"
          >
          <span class="text-sm text-slate-700">
            Chấm điểm chính xác 0.5
            <span class="block text-[11px] text-slate-400">
              Cho phép nhập điểm lẻ (ví dụ 3.5) khi chấm tiêu chí này.
            </span>
          </span>
        </label>

        <!-- Mô tả -->
        <label class="block space-y-1.5 md:col-span-2">
          <span class="text-xs font-medium text-slate-600">
            Mô tả
          </span>
          <textarea
            v-model="form.description"
            class="input min-h-[5rem] w-full text-sm"
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

        <!-- Thang điểm -->
        <div class="md:col-span-2">
          <p class="mb-2 text-xs font-medium text-slate-600">
            Thang điểm đánh giá <span class="text-rose-500">*</span>
          </p>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <label
              v-for="n in 5"
              :key="n"
              class="block space-y-1.5"
            >
              <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                Điểm {{ n }}
              </span>
              <input
                v-model="form[`score_${n}`]"
                type="text"
                class="input h-10 w-full text-sm"
                :placeholder="defaults[n]"
                maxlength="255"
              >
              <p
                v-if="fieldError(`score_${n}`)"
                class="text-xs text-rose-600"
              >
                {{ fieldError(`score_${n}`) }}
              </p>
            </label>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
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
