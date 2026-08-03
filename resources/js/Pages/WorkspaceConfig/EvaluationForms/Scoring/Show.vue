<script setup>
import { reactive, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import FormScoreLevelPicker from '@/modules/evaluation-form/components/FormScoreLevelPicker.vue';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    form: { type: Object, required: true },
    assignee: { type: Object, required: true },
    roleKey: { type: String, required: true },
    scorableRoles: { type: Array, default: () => [] },
    submission: { type: Object, default: null },
    criteria: { type: Array, default: () => [] },
    fields: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    sequentialBlocked: { type: Boolean, default: false },
});

const toast = useToast();

const state = reactive({
    comment: props.submission?.comment || '',
    lines: Object.fromEntries(
        (props.criteria || []).map((c) => [c.id, {
            form_criterion_id: c.id,
            score_level_code: c.selected_code || null,
            score_level_label: c.selected_label || null,
            score_weight: c.selected_weight ?? null,
        }]),
    ),
    field_values: Object.fromEntries(
        (props.fields || []).map((f) => [f.id, {
            form_field_id: f.id,
            value: f.value || '',
        }]),
    ),
});

watch(() => props.criteria, (list) => {
    list.forEach((c) => {
        if (!state.lines[c.id]) {
            state.lines[c.id] = {
                form_criterion_id: c.id,
                score_level_code: c.selected_code || null,
                score_level_label: c.selected_label || null,
                score_weight: c.selected_weight ?? null,
            };
        }
    });
}, { deep: true });

const scoreForm = useForm({});

function onSelectLevel(criterionId, payload) {
    state.lines[criterionId] = {
        form_criterion_id: criterionId,
        score_level_code: payload.code,
        score_level_label: payload.label,
        score_weight: payload.weight,
    };
}

function payload() {
    return {
        rater_role_key: props.roleKey,
        comment: state.comment || null,
        lines: Object.values(state.lines).filter((l) => l.score_level_label || l.score_level_code),
        field_values: Object.values(state.field_values),
    };
}

function saveDraft() {
    scoreForm.transform(() => payload()).put(
        route('workspace.evaluation-forms.scoring.save', {
            evaluationForm: props.form.id,
            assignee: props.assignee.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã lưu nháp điểm.'),
        },
    );
}

function submitScores() {
    scoreForm.transform(() => payload()).post(
        route('workspace.evaluation-forms.scoring.submit', {
            evaluationForm: props.form.id,
            assignee: props.assignee.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã nộp phiếu đánh giá.'),
        },
    );
}

function switchRole(role) {
    router.get(route('workspace.evaluation-forms.scoring.show', {
        evaluationForm: props.form.id,
        assignee: props.assignee.id,
        role,
    }), {}, { preserveState: false });
}
</script>

<template>
  <Head :title="`Chấm: ${assignee.employee_name || 'Nhân sự'}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="displayOrEmpty(assignee.employee_name, EMPTY_LABELS.notUpdated)"
        :subtitle="`${form.name} · ${form.form_code}`"
        icon="clipboard-list"
        :back-href="route('workspace.evaluation-forms.scoring.index', form.id)"
      />
    </template>

    <div
      v-if="sequentialBlocked"
      class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
    >
      Đánh giá tuần tự: vui lòng chờ các vai trò trước nộp điểm trước khi chấm.
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-2">
      <span class="text-xs font-medium text-slate-500">Vai trò chấm:</span>
      <button
        v-for="role in scorableRoles"
        :key="role.value"
        type="button"
        class="rounded-full px-3 py-1 text-xs font-medium ring-1 transition"
        :class="role.value === roleKey
          ? 'bg-brand/10 text-brand ring-brand/30'
          : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'"
        @click="switchRole(role.value)"
      >
        {{ role.label }}
      </button>
      <span
        v-if="submission"
        class="ml-auto text-xs text-slate-500"
      >
        {{ submission.status_label }}
        <template v-if="submission.total_score != null">
          · điểm {{ submission.total_score }}
        </template>
      </span>
    </div>

    <section class="space-y-3">
      <div
        v-for="criterion in criteria"
        :key="criterion.id"
        class="rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
      >
        <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
          <div>
            <h3 class="text-sm font-semibold text-slate-800">
              {{ criterion.name }}
            </h3>
            <p class="mt-0.5 text-[11px] text-slate-400">
              Trọng số {{ criterion.weight }}%
              <template v-if="criterion.required_score_label">
                · Yêu cầu: {{ criterion.required_score_label }}
              </template>
            </p>
          </div>
        </div>
        <FormScoreLevelPicker
          :levels="criterion.score_levels || []"
          :selected-code="state.lines[criterion.id]?.score_level_code"
          :selected-label="state.lines[criterion.id]?.score_level_label"
          :disabled="!can.write"
          @select="(p) => onSelectLevel(criterion.id, p)"
        />
      </div>

      <div
        v-if="!criteria.length"
        class="rounded-card border border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-400"
      >
        Không có tiêu chí nào trong phạm vi vai trò này.
      </div>
    </section>

    <section
      v-if="fields.length"
      class="mt-4 space-y-3 rounded-card border border-slate-200/80 bg-white p-4 shadow-sm"
    >
      <h3 class="text-sm font-semibold text-slate-800">
        Trường tùy biến
      </h3>
      <div
        v-for="field in fields"
        :key="field.id"
      >
        <label class="mb-1 block text-xs font-medium text-slate-600">
          {{ field.label }}
        </label>
        <textarea
          v-model="state.field_values[field.id].value"
          rows="3"
          class="input w-full text-sm"
          :disabled="!can.write"
        />
      </div>
    </section>

    <section class="mt-4 rounded-card border border-slate-200/80 bg-white p-4 shadow-sm">
      <label class="mb-1 block text-xs font-medium text-slate-600">
        Nhận xét chung
      </label>
      <textarea
        v-model="state.comment"
        rows="3"
        class="input w-full text-sm"
        :disabled="!can.write"
        placeholder="Ghi chú khi chấm (tuỳ chọn)"
      />
    </section>

    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
      <button
        type="button"
        class="btn-ghost h-9 px-4 text-sm"
        :disabled="!can.write || scoreForm.processing"
        @click="saveDraft"
      >
        Lưu nháp
      </button>
      <button
        type="button"
        class="btn-primary h-9 gap-1.5 px-4 text-sm"
        :disabled="!can.submit || scoreForm.processing"
        @click="submitScores"
      >
        <AppIcon
          name="done"
          :size="15"
        />
        Nộp phiếu
      </button>
    </div>
  </AppLayout>
</template>
