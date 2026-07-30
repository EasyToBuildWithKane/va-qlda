<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import EvaluationCriterionFormModal from '@/modules/evaluation/components/EvaluationCriterionFormModal.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    criterion: { type: Object, required: true },
    activity: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    defaultScoreLabels: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const confirmDelete = useConfirmDelete();
const showFormModal = ref(false);

const scoreRows = computed(() => ([
    { n: 1, label: props.criterion.score_1 },
    { n: 2, label: props.criterion.score_2 },
    { n: 3, label: props.criterion.score_3 },
    { n: 4, label: props.criterion.score_4 },
    { n: 5, label: props.criterion.score_5 },
]));

function onDelete() {
    confirmDelete(
        `Xoá tiêu chí «${props.criterion.criteria_name}»?`,
        () => router.delete(route('workspace.evaluation.destroy', props.criterion.id)),
    );
}

function activityLine(item) {
    const name = item.actor_name || 'Hệ thống';
    return `${name} ${item.label || item.action}`;
}
</script>

<template>
  <Head :title="criterion.display_name || criterion.criteria_name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="criterion.display_name || criterion.criteria_name"
        :subtitle="`${criterion.scope_label} · Mã ${criterion.criteria_code}`"
        icon="award"
        back-href="/workspace-config/evaluation"
      >
        <template v-if="can.manage">
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-sm"
            @click="showFormModal = true"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Sửa
          </button>
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-sm text-rose-600"
            @click="onDelete"
          >
            <AppIcon
              name="trash"
              :size="15"
            />
            Xóa
          </button>
        </template>
      </PageHeader>
    </template>

    <div class="space-y-5">
      <div class="card space-y-5 p-5">
        <h2 class="text-sm font-semibold text-slate-800">
          Thông tin tiêu chí
        </h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Tên tiêu chí
            </p>
            <p class="mt-1 font-medium text-slate-800">
              {{ criterion.display_name || criterion.criteria_name }}
            </p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Mã tiêu chí
            </p>
            <p class="mt-1 font-mono text-slate-800">
              {{ criterion.criteria_code }}
            </p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Trạng thái
            </p>
            <div class="mt-1">
              <Badge
                :color="criterion.is_active ? 'emerald' : 'slate'"
                :label="criterion.is_active ? 'Hoạt động' : 'Ngưng hoạt động'"
              />
            </div>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Loại tiêu chí
            </p>
            <p class="mt-1 text-slate-800">
              {{ displayOrEmpty(criterion.category, EMPTY_LABELS.notUpdated) }}
            </p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Phạm vi
            </p>
            <p class="mt-1 text-slate-800">
              {{ criterion.scope_label }}
              <span
                v-if="criterion.department_name"
                class="text-slate-500"
              > · {{ criterion.department_name }}</span>
            </p>
          </div>
          <div>
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Chấm điểm chính xác 0.5
            </p>
            <p class="mt-1 text-slate-800">
              {{ criterion.allow_half_score ? 'Có' : 'Không' }}
            </p>
          </div>
          <div class="sm:col-span-2 lg:col-span-3">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Mô tả
            </p>
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">
              {{ displayOrEmpty(criterion.description, EMPTY_LABELS.notUpdated) }}
            </p>
          </div>
        </div>
      </div>

      <div class="card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
          <h2 class="text-sm font-semibold text-slate-800">
            Thang điểm đánh giá
          </h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-white text-[11px] uppercase tracking-wide text-slate-500">
              <tr>
                <th
                  v-for="row in scoreRows"
                  :key="`h-${row.n}`"
                  class="px-4 py-3 text-center font-medium"
                >
                  Điểm {{ row.n }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr class="border-t border-slate-100">
                <td
                  v-for="row in scoreRows"
                  :key="`v-${row.n}`"
                  class="px-4 py-4 text-center text-slate-800"
                >
                  {{ displayOrEmpty(row.label, EMPTY_LABELS.notUpdated) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
          <h2 class="text-sm font-semibold text-slate-800">
            Lịch sử hoạt động
          </h2>
        </div>
        <ul
          v-if="activity.length"
          class="divide-y divide-slate-100"
        >
          <li
            v-for="item in activity"
            :key="item.id"
            class="flex items-start justify-between gap-4 px-5 py-3.5"
          >
            <div class="min-w-0">
              <p class="text-sm text-slate-800">
                {{ activityLine(item) }}
              </p>
              <p
                v-if="item.meta?.criteria_code"
                class="mt-0.5 font-mono text-[11px] text-slate-400"
              >
                ID: {{ criterion.id }} — {{ item.meta.criteria_name || criterion.criteria_name }}
              </p>
            </div>
            <time
              class="shrink-0 tabular-nums text-xs text-slate-400"
              :datetime="item.created_at"
            >
              {{ item.created_at ? datetime(item.created_at) : EMPTY_LABELS.notUpdated }}
            </time>
          </li>
        </ul>
        <p
          v-else
          class="px-5 py-8 text-center text-sm text-slate-500"
        >
          Chưa có lịch sử hoạt động.
        </p>
      </div>
    </div>

    <EvaluationCriterionFormModal
      :show="showFormModal"
      mode="edit"
      :criterion="criterion"
      :departments="departments"
      :categories="categories"
      :scope-options="scopeOptions"
      :next-code="criterion.criteria_code"
      :default-score-labels="defaultScoreLabels"
      @close="showFormModal = false"
    />
  </AppLayout>
</template>
