<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';
import EvaluationCriteriaEditor from '@/modules/evaluation/components/EvaluationCriteriaEditor.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    mode: { type: String, default: 'create' }, // create | edit
    config: { type: Object, default: null },
    departments: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    templateTypeOptions: { type: Array, default: () => [] },
});

const dialog = useDialog();

const form = useForm({
    department_code: props.config?.department_code || '',
    department_name: props.config?.department_name || '',
    local_department_id: props.config?.local_department_id || null,
    template_id: props.config?.template_id || null,
    template_type: props.config?.template_type || 'point_system',
    config_name: props.config?.config_name || '',
    description: props.config?.description || '',
    effective_from: props.config?.effective_from || '',
    effective_to: props.config?.effective_to || '',
    base_score: props.config?.base_score ?? 100,
    is_active: props.config?.is_active ?? true,
    apply_template: false,
    criteria: props.config?.criteria ? [...props.config.criteria] : [],
});

const isPoint = computed(() => form.template_type === 'point_system');

const filteredTemplates = computed(() => props.templates.filter(
    (t) => t.template_type === form.template_type,
));

watch(() => form.department_code, (code) => {
    const dept = props.departments.find((d) => d.code === code);
    if (dept) {
        form.department_name = dept.name;
        form.local_department_id = dept.local_department_id;
    }
});

async function onTemplateTypeChange(event) {
    const next = event.target.value;
    const prev = form.template_type;
    if (next === prev) return;

    if (form.criteria.length > 0) {
        const ok = await dialog.confirm({
            title: 'Đổi loại mẫu?',
            message: 'Đổi loại mẫu sẽ xóa danh sách tiêu chí hiện tại. Tiếp tục?',
            confirmText: 'Đổi loại mẫu',
            cancelText: 'Hủy',
            tone: 'danger',
        });
        if (!ok) {
            event.target.value = prev;
            return;
        }
        form.criteria = [];
        form.template_id = null;
    }

    form.template_type = next;
    if (next === 'point_system' && (form.base_score === null || form.base_score === undefined)) {
        form.base_score = 100;
    }
}

async function applySelectedTemplate() {
    if (!form.template_id) return;
    const tpl = props.templates.find((t) => t.id === Number(form.template_id));
    if (!tpl) return;

    if (form.criteria.length > 0) {
        const ok = await dialog.confirm({
            title: 'Áp dụng mẫu phiếu?',
            message: 'Tiêu chí hiện tại sẽ được thay bằng tiêu chí của mẫu. Tiếp tục?',
            confirmText: 'Áp dụng',
            cancelText: 'Hủy',
        });
        if (!ok) return;
    }

    form.template_type = tpl.template_type;
    form.criteria = (tpl.criteria || []).map((c) => ({
        id: null,
        criteria_code: c.criteria_code,
        criteria_name: c.criteria_name,
        category: c.category,
        description: c.description,
        point_value: c.point_value,
        max_points: c.max_points,
        max_frequency: c.max_frequency,
        weight: c.weight,
        required_score: c.required_score,
        importance: c.importance,
        sort_order: c.sort_order,
        is_active: true,
    }));
    form.apply_template = props.mode === 'create';
}

function submit() {
    form.base_score = isPoint.value ? form.base_score : null;
    if (form.criteria.length > 0) {
        form.apply_template = false;
    } else if (props.mode === 'create' && form.template_id) {
        form.apply_template = true;
    }

    if (props.mode === 'create') {
        form.post(route('workspace.evaluation.store'), { preserveScroll: true });
    } else {
        form.put(route('workspace.evaluation.update', props.config.id), { preserveScroll: true });
    }
}
</script>

<template>
  <form
    class="space-y-5"
    @submit.prevent="submit"
  >
    <div class="card space-y-4 p-5">
      <h3 class="text-sm font-semibold text-slate-800">
        Thông tin chung
      </h3>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <label class="block space-y-1.5">
          <span class="text-xs font-medium text-slate-600">Phòng ban <span class="text-rose-500">*</span></span>
          <select
            v-model="form.department_code"
            class="input h-10 w-full text-sm"
            required
          >
            <option
              value=""
              disabled
            >Chọn phòng ban</option>
            <option
              v-for="d in departments"
              :key="d.code"
              :value="d.code"
            >
              {{ d.name }} ({{ d.code }})
            </option>
          </select>
          <p
            v-if="form.errors.department_code"
            class="text-xs text-rose-600"
          >{{ form.errors.department_code }}</p>
        </label>

        <label class="block space-y-1.5">
          <span class="text-xs font-medium text-slate-600">Loại mẫu <span class="text-rose-500">*</span></span>
          <select
            class="input h-10 w-full text-sm"
            :value="form.template_type"
            required
            @change="onTemplateTypeChange"
          >
            <option
              v-for="opt in templateTypeOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
          <p
            v-if="form.errors.template_type"
            class="text-xs text-rose-600"
          >{{ form.errors.template_type }}</p>
        </label>

        <label class="block space-y-1.5 md:col-span-2">
          <span class="text-xs font-medium text-slate-600">Tên cấu hình <span class="text-rose-500">*</span></span>
          <input
            v-model="form.config_name"
            type="text"
            class="input h-10 w-full text-sm"
            required
            maxlength="255"
          >
          <p
            v-if="form.errors.config_name"
            class="text-xs text-rose-600"
          >{{ form.errors.config_name }}</p>
        </label>

        <label class="block space-y-1.5 md:col-span-2">
          <span class="text-xs font-medium text-slate-600">Mô tả</span>
          <textarea
            v-model="form.description"
            class="input min-h-[4rem] w-full text-sm"
            rows="3"
          />
        </label>

        <label class="block space-y-1.5">
          <span class="text-xs font-medium text-slate-600">Hiệu lực từ <span class="text-rose-500">*</span></span>
          <FilterDatePicker
            v-model="form.effective_from"
            placeholder="Hiệu lực từ"
          />
          <p
            v-if="form.errors.effective_from"
            class="text-xs text-rose-600"
          >{{ form.errors.effective_from }}</p>
        </label>

        <label class="block space-y-1.5">
          <span class="text-xs font-medium text-slate-600">Hiệu lực tới</span>
          <FilterDatePicker
            v-model="form.effective_to"
            placeholder="Hiệu lực tới"
          />
          <p
            v-if="form.errors.effective_to"
            class="text-xs text-rose-600"
          >{{ form.errors.effective_to }}</p>
        </label>

        <label
          v-if="isPoint"
          class="block space-y-1.5"
        >
          <span class="text-xs font-medium text-slate-600">Điểm khởi đầu</span>
          <input
            v-model.number="form.base_score"
            type="number"
            min="0"
            max="200"
            class="input h-10 w-full text-sm"
          >
          <p
            v-if="form.errors.base_score"
            class="text-xs text-rose-600"
          >{{ form.errors.base_score }}</p>
        </label>

        <label class="flex items-center gap-2 pt-6">
          <input
            v-model="form.is_active"
            type="checkbox"
            class="rounded border-slate-300 text-brand focus:ring-brand"
          >
          <span class="text-sm text-slate-700">Đang bật</span>
        </label>
      </div>
    </div>

    <div class="card space-y-4 p-5">
      <div class="flex flex-wrap items-end gap-3">
        <label class="block min-w-[14rem] flex-1 space-y-1.5">
          <span class="text-xs font-medium text-slate-600">Áp dụng mẫu phiếu</span>
          <select
            v-model="form.template_id"
            class="input h-10 w-full text-sm"
          >
            <option :value="null">Không chọn mẫu</option>
            <option
              v-for="t in filteredTemplates"
              :key="t.id"
              :value="t.id"
            >
              {{ t.name }} ({{ t.criteria_count ?? (t.criteria?.length || 0) }} tiêu chí)
            </option>
          </select>
        </label>
        <button
          type="button"
          class="btn-ghost inline-flex h-10 items-center gap-1.5 px-3 text-sm"
          :disabled="!form.template_id"
          @click="applySelectedTemplate"
        >
          <AppIcon
            name="template"
            :size="15"
          />
          Áp dụng mẫu
        </button>
      </div>

      <EvaluationCriteriaEditor
        v-model="form.criteria"
        :template-type="form.template_type"
      />
      <p
        v-if="form.errors.criteria"
        class="text-xs text-rose-600"
      >
        {{ form.errors.criteria }}
      </p>
    </div>

    <div class="flex items-center justify-end gap-2">
      <a
        :href="route('workspace.evaluation.index')"
        class="btn-ghost inline-flex h-9 items-center px-3 text-sm"
      >
        Hủy
      </a>
      <button
        type="submit"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
        :disabled="form.processing"
      >
        <AppIcon
          name="save"
          :size="15"
        />
        {{ mode === 'create' ? 'Lưu cấu hình' : 'Cập nhật' }}
      </button>
    </div>
  </form>
</template>
