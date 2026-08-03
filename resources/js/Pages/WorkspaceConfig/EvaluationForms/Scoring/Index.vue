<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import FormScoringProgress from '@/modules/evaluation-form/components/FormScoringProgress.vue';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    form: { type: Object, required: true },
    raters: { type: Array, default: () => [] },
    progress: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const toast = useToast();

const statusTone = {
    draft: 'bg-slate-100 text-slate-600',
    active: 'bg-emerald-50 text-emerald-700',
    closed: 'bg-amber-50 text-amber-700',
};

function scoreHref(row, roleKey) {
    return route('workspace.evaluation-forms.scoring.show', {
        evaluationForm: props.form.id,
        assignee: row.assignee_id,
        role: roleKey || row.default_role,
    });
}

function closeForm() {
    router.post(route('workspace.evaluation-forms.close', props.form.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã khóa kỳ đánh giá.'),
    });
}

function reopenForm() {
    router.post(route('workspace.evaluation-forms.reopen', props.form.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã mở lại phiếu.'),
    });
}

const submittedOverall = computed(() => {
    let done = 0;
    let total = 0;
    props.progress.forEach((row) => {
        (row.roles || []).forEach((r) => {
            total += 1;
            if (r.status === 'submitted') done += 1;
        });
    });
    return { done, total };
});
</script>

<template>
  <Head :title="`Chấm điểm: ${form.name}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="form.name"
        :subtitle="`${form.form_code} · ${form.status_label || form.status}`"
        icon="clipboard-list"
        back-href="/workspace-config/evaluation-forms"
      >
        <button
          v-if="can.close"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
          @click="closeForm"
        >
          <AppIcon
            name="close"
            :size="15"
          />
          Khóa kỳ
        </button>
        <button
          v-else-if="can.reopen"
          type="button"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
          @click="reopenForm"
        >
          <AppIcon
            name="refresh"
            :size="15"
          />
          Mở lại
        </button>
      </PageHeader>
    </template>

    <div class="mb-4 rounded-card border border-slate-200/80 bg-white p-4 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
            Tổng quan chấm điểm
          </p>
          <p class="mt-1 text-sm text-slate-600">
            Đã nộp {{ submittedOverall.done }}/{{ submittedOverall.total }} lượt chấm hội đồng
          </p>
        </div>
        <span
          class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
          :class="statusTone[form.status] || 'bg-slate-100 text-slate-600'"
        >
          {{ form.status_label || form.status }}
        </span>
      </div>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-slate-100 bg-slate-50/80 text-left text-xs uppercase tracking-wide text-slate-500">
              <th class="px-4 py-3">
                Nhân sự
              </th>
              <th
                v-for="rater in raters"
                :key="rater.role_key"
                class="px-4 py-3"
              >
                {{ rater.label }}
              </th>
              <th class="px-4 py-3">
                Tổng hợp
              </th>
              <th class="px-4 py-3 w-28" />
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in progress"
              :key="row.assignee_id"
              class="border-b border-slate-50 align-top hover:bg-slate-50/50"
            >
              <td class="px-4 py-3">
                <div class="font-medium text-slate-800">
                  {{ displayOrEmpty(row.employee_name, EMPTY_LABELS.notUpdated) }}
                </div>
                <div class="text-[11px] text-slate-400">
                  {{ [row.employee_code, row.department_name].filter(Boolean).join(' · ') }}
                </div>
                <div class="mt-2 max-w-xs">
                  <FormScoringProgress :roles="row.roles" />
                </div>
              </td>
              <td
                v-for="role in row.roles"
                :key="role.role_key"
                class="px-4 py-3"
              >
                <div class="space-y-1">
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium"
                    :class="role.status === 'submitted'
                      ? 'bg-emerald-50 text-emerald-700'
                      : role.status === 'draft'
                        ? 'bg-amber-50 text-amber-700'
                        : 'bg-slate-100 text-slate-500'"
                  >
                    {{ role.status_label }}
                  </span>
                  <div
                    v-if="role.total_score != null"
                    class="tabular-nums text-sm font-semibold text-slate-700"
                  >
                    {{ role.total_score }}
                  </div>
                  <Link
                    v-if="role.can_score"
                    :href="scoreHref(row, role.role_key)"
                    class="inline-flex text-[11px] font-medium text-brand hover:underline"
                  >
                    Chấm điểm
                  </Link>
                </div>
              </td>
              <td class="px-4 py-3">
                <span
                  v-if="row.aggregate_score != null"
                  class="font-display text-lg tabular-nums text-brand"
                >
                  {{ row.aggregate_score }}
                </span>
                <span
                  v-else
                  class="text-xs text-slate-400"
                >Chưa đủ điểm</span>
              </td>
              <td class="px-4 py-3">
                <Link
                  v-if="row.can_open_scoring"
                  :href="scoreHref(row)"
                  class="btn-ghost h-8 px-2 text-xs"
                >
                  Mở
                </Link>
              </td>
            </tr>
            <tr v-if="!progress.length">
              <td
                :colspan="(raters.length || 0) + 3"
                class="px-4 py-16 text-center text-sm text-slate-400"
              >
                Chưa có nhân sự trên phiếu này.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
