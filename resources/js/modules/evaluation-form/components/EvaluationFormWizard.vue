<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppIcon from '@/Components/AppIcon.vue';
import FormGeneralTab from '@/modules/evaluation-form/components/FormGeneralTab.vue';
import FormCriteriaTab from '@/modules/evaluation-form/components/FormCriteriaTab.vue';
import FormAssigneesTab from '@/modules/evaluation-form/components/FormAssigneesTab.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    mode: { type: String, default: 'create' },
    formData: { type: Object, default: null },
    typeOptions: { type: Array, default: () => [] },
    templateOptions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
    employeeOptions: { type: Array, default: () => [] },
    periodKindOptions: { type: Array, default: () => [] },
    orderOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    defaultRaters: { type: Array, default: () => [] },
    defaultFields: { type: Array, default: () => [] },
    raterRoleOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'PDG001' },
    prefill: { type: Object, default: () => ({}) },
});

const toast = useToast();
const activeTab = ref('general');
const loadingTemplateCriteria = ref(false);

const tabs = [
    { key: 'general', label: 'Thông tin chung' },
    { key: 'criteria', label: 'Tiêu chí đánh giá' },
    { key: 'assignees', label: 'Danh sách nhân sự' },
];

const now = new Date();
const defaultTypeId = computed(() => props.typeOptions[0]?.id ?? null);

function buildInitial() {
    const existing = props.formData;
    if (existing) {
        return {
            form_code: existing.form_code,
            name: existing.name,
            template_id: existing.template_id,
            type_id: existing.type_id,
            period_kind: existing.period_kind || 'month',
            period_month: existing.period_month || (now.getMonth() + 1),
            period_year: existing.period_year || now.getFullYear(),
            period_start: existing.period_start,
            period_end: existing.period_end,
            auto_create_next: !!existing.auto_create_next,
            manager_employee_id: existing.manager_employee_id,
            deadline: existing.deadline,
            evaluation_order: existing.evaluation_order || 'parallel',
            use_weight: existing.use_weight !== false,
            status: existing.status || 'draft',
            watcher_ids: [...(existing.watcher_ids || [])],
            raters: (existing.raters || []).map((r) => ({ ...r })),
            fields: (existing.fields?.length ? existing.fields : props.defaultFields).map((f) => ({ ...f })),
            criteria: (existing.criteria || []).map((c) => ({ ...c })),
            assignees: (existing.assignees || []).map((a) => ({ ...a })),
        };
    }

    return {
        form_code: props.nextCode,
        name: '',
        template_id: props.prefill?.template_id ?? null,
        type_id: defaultTypeId.value,
        period_kind: 'month',
        period_month: now.getMonth() + 1,
        period_year: now.getFullYear(),
        period_start: null,
        period_end: null,
        auto_create_next: false,
        manager_employee_id: null,
        deadline: null,
        evaluation_order: 'parallel',
        use_weight: true,
        status: 'draft',
        watcher_ids: [],
        raters: (props.defaultRaters.length ? props.defaultRaters : []).map((r) => ({ ...r })),
        fields: (props.defaultFields || []).map((f) => ({ ...f })),
        criteria: (props.prefill?.criteria || []).map((c) => ({ ...c })),
        assignees: [],
    };
}

const form = useForm(buildInitial());

watch(() => props.typeOptions, (opts) => {
    if (!form.type_id && opts?.[0]?.id) form.type_id = opts[0].id;
});

function refreshCode() {
    form.form_code = props.nextCode;
}

async function onTemplateChange(templateId) {
    if (!templateId) return;
    loadingTemplateCriteria.value = true;
    try {
        const { data } = await axios.get(
            route('workspace.evaluation-forms.templates.criteria', templateId),
        );
        if (Array.isArray(data.criteria) && data.criteria.length) {
            form.criteria = data.criteria.map((c) => ({ ...c }));
            toast.success('Đã tải tiêu chí từ mẫu đánh giá.');
            activeTab.value = 'criteria';
        }
    } catch {
        toast.error('Không tải được tiêu chí từ mẫu.');
    } finally {
        loadingTemplateCriteria.value = false;
    }
}

function payload() {
    return {
        ...form.data(),
        template_id: form.template_id || null,
        assignees: (form.assignees || []).filter((a) => a.employee_id),
        criteria: (form.criteria || []).filter((c) => c.name || c.criterion_id),
    };
}

function submit() {
    const data = payload();
    if (props.mode === 'edit' && props.formData?.id) {
        form.transform(() => data).put(route('workspace.evaluation-forms.update', props.formData.id), {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã cập nhật phiếu đánh giá.'),
        });
        return;
    }
    form.transform(() => data).post(route('workspace.evaluation-forms.store'), {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã tạo phiếu đánh giá.'),
    });
}

function cancel() {
    router.get(route('workspace.evaluation-forms.index'));
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center gap-1 border-b border-slate-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="relative px-4 py-2.5 text-sm font-medium transition"
        :class="activeTab === tab.key
          ? 'text-brand'
          : 'text-slate-500 hover:text-slate-700'"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span
          v-if="activeTab === tab.key"
          class="absolute inset-x-2 -bottom-px h-0.5 rounded-full bg-brand"
        />
      </button>
    </div>

    <FormGeneralTab
      v-show="activeTab === 'general'"
      v-model:form="form"
      :type-options="typeOptions"
      :template-options="templateOptions"
      :employee-options="employeeOptions"
      :period-kind-options="periodKindOptions"
      :order-options="orderOptions"
      :status-options="statusOptions"
      :rater-role-options="raterRoleOptions"
      :next-code="nextCode"
      :loading-template-criteria="loadingTemplateCriteria"
      @refresh-code="refreshCode"
      @template-change="onTemplateChange"
    />

    <FormCriteriaTab
      v-show="activeTab === 'criteria'"
      v-model:form="form"
      :criteria-options="criteriaOptions"
    />

    <FormAssigneesTab
      v-show="activeTab === 'assignees'"
      v-model:form="form"
      :employee-options="employeeOptions"
    />

    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
      <button
        type="button"
        class="btn-primary h-9 gap-1.5 px-4 text-sm"
        :disabled="form.processing"
        @click="submit"
      >
        <AppIcon
          name="done"
          :size="15"
        />
        {{ mode === 'edit' ? 'Cập nhật' : 'Tạo phiếu' }}
      </button>
      <button
        type="button"
        class="btn-ghost h-9 px-4 text-sm"
        :disabled="form.processing"
        @click="cancel"
      >
        Hủy bỏ
      </button>
    </div>
  </div>
</template>
