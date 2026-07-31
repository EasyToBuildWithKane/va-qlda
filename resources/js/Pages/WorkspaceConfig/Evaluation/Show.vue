<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import EvaluationCriterionFormModal from '@/modules/evaluation/components/EvaluationCriterionFormModal.vue';
import EvaluationActivityTimeline from '@/modules/evaluation/components/EvaluationActivityTimeline.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { useToast } from '@/shared/composables/useToast';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    criterion: { type: Object, required: true },
    activity: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    defaultScoreLevels: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
    viewer: {
        type: Object,
        default: () => ({
            can_create_general: true,
            own_department_code: null,
        }),
    },
});

const toast = useToast();
const confirmDelete = useConfirmDelete();
const showFormModal = ref(false);
const canCreateGeneral = computed(() => props.viewer?.can_create_general !== false);

const LEVEL_TONES = [
    'bg-rose-500',
    'bg-orange-500',
    'bg-amber-500',
    'bg-lime-500',
    'bg-emerald-500',
    'bg-teal-500',
    'bg-sky-500',
    'bg-indigo-500',
    'bg-violet-500',
    'bg-fuchsia-500',
];

const scoreRows = computed(() => {
    const levels = Array.isArray(props.criterion.score_levels)
        ? props.criterion.score_levels
        : [];
    return levels.map((level, index) => ({
        n: index + 1,
        code: level?.code || `M${index + 1}`,
        label: level?.label ?? '',
        description: level?.description ?? '',
        weight: Number.isFinite(Number(level?.weight)) ? Number(level.weight) : 0,
        tone: LEVEL_TONES[index % LEVEL_TONES.length],
    }));
});

const maxAbsWeight = computed(() => Math.max(
    1,
    ...scoreRows.value.map((r) => Math.abs(r.weight)),
));

const weightSpan = computed(() => {
    if (!scoreRows.value.length) return EMPTY_LABELS.notUpdated;
    const weights = scoreRows.value.map((r) => r.weight);
    const min = Math.min(...weights);
    const max = Math.max(...weights);
    if (min === max) return formatWeight(min);
    return `${formatWeight(min)} → ${formatWeight(max)}`;
});

const isGeneralScope = computed(() => props.criterion.scope === 'general');

const scopeLabel = computed(() => {
    if (isGeneralScope.value) return 'Tiêu chí chung';
    return props.criterion.department_name
        || props.criterion.scope_label
        || 'Theo phòng ban';
});

const statusBadge = computed(() => (
    props.criterion.is_active ? 'Đang hoạt động' : 'Ngưng hoạt động'
));

function formatWeight(weight) {
    const n = Number(weight);
    if (!Number.isFinite(n)) return '';
    return n > 0 ? `+${n}` : String(n);
}

function weightBarPct(weight) {
    return Math.max(6, Math.round((Math.abs(weight) / maxAbsWeight.value) * 100));
}

function onDelete() {
    confirmDelete(
        `Xoá tiêu chí «${props.criterion.criteria_name}»?`,
        () => router.delete(route('workspace.evaluation.destroy', props.criterion.id)),
    );
}

async function copyCode() {
    const code = props.criterion.criteria_code;
    if (!code) return;
    try {
        await navigator.clipboard.writeText(code);
        toast.success(`Đã sao chép mã ${code}`);
    } catch {
        toast.error('Không sao chép được mã tiêu chí');
    }
}
</script>

<template>
  <Head :title="criterion.criteria_name" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Chi tiết tiêu chí"
        :subtitle="criterion.criteria_code"
        icon="award"
        back-href="/workspace-config/evaluation"
      />
    </template>

    <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-3">
      <div class="space-y-5 xl:col-span-2">
        <!-- Hero -->
        <section class="card overflow-hidden">
          <div class="relative overflow-hidden border-b border-slate-100 bg-gradient-to-br from-brand/[0.06] via-white to-slate-50/80 px-5 py-5">
            <div
              class="pointer-events-none absolute -right-8 -top-10 h-36 w-36 rounded-full bg-brand/[0.06]"
              aria-hidden="true"
            />
            <div class="relative flex flex-wrap items-start gap-3">
              <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand/10 text-brand ring-1 ring-brand/15">
                <AppIcon
                  name="award"
                  :size="22"
                />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="font-display text-xl font-semibold leading-snug text-slate-800">
                    {{ criterion.criteria_name }}
                  </h2>
                  <span
                    class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[11px] font-semibold leading-none"
                    :class="criterion.is_active
                      ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200/80'
                      : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200'"
                  >
                    {{ statusBadge }}
                  </span>
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                  <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 font-mono text-[11px] font-semibold text-slate-600 ring-1 ring-slate-200 transition-colors hover:text-brand"
                    :title="`Sao chép ${criterion.criteria_code}`"
                    @click="copyCode"
                  >
                    {{ criterion.criteria_code }}
                    <AppIcon
                      name="copy"
                      :size="12"
                    />
                  </button>
                  <span class="inline-flex items-center rounded-md bg-white px-2 py-1 text-[11px] font-medium text-slate-600 ring-1 ring-slate-200">
                    {{ scopeLabel }}
                  </span>
                  <span
                    v-if="criterion.category"
                    class="inline-flex items-center rounded-md bg-white px-2 py-1 text-[11px] font-medium text-slate-600 ring-1 ring-slate-200"
                  >
                    {{ criterion.category }}
                  </span>
                </div>
              </div>

              <div
                v-if="can.manage"
                class="flex shrink-0 flex-wrap items-center gap-1.5"
              >
                <button
                  type="button"
                  class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-sm"
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
              </div>
            </div>
          </div>

          <!-- KPI strip -->
          <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="target"
                  :size="13"
                />
                Số mức
              </p>
              <p class="mt-1 font-display text-xl font-semibold tabular-nums text-slate-800">
                {{ scoreRows.length }}
              </p>
            </div>
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="check-circle"
                  :size="13"
                />
                Khoảng điểm
              </p>
              <p class="mt-1 text-sm font-semibold tabular-nums text-slate-800">
                {{ weightSpan }}
              </p>
            </div>
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="check-circle"
                  :size="13"
                />
                Chấm 0.5
              </p>
              <p class="mt-1 text-sm font-semibold text-slate-800">
                {{ criterion.allow_half_score ? 'Có' : 'Không' }}
              </p>
            </div>
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="calendar"
                  :size="13"
                />
                Cập nhật
              </p>
              <p class="mt-1 text-xs font-medium tabular-nums text-slate-800">
                {{ criterion.updated_at ? datetime(criterion.updated_at) : EMPTY_LABELS.notUpdated }}
              </p>
            </div>
          </div>

          <div class="border-t border-slate-100 px-5 py-4">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
              Mô tả
            </p>
            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
              {{ displayOrEmpty(criterion.description, EMPTY_LABELS.notUpdated) }}
            </p>

            <div
              v-if="criterion.creator?.display_name"
              class="mt-4 flex items-center gap-2.5 rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-slate-100"
            >
              <Avatar
                :name="criterion.creator.display_name"
                :src="criterion.creator.avatar || null"
                :size="32"
              />
              <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                  Người tạo
                </p>
                <p class="truncate text-sm font-medium text-slate-700">
                  {{ criterion.creator.display_name }}
                </p>
              </div>
              <p
                v-if="criterion.created_at"
                class="ml-auto shrink-0 text-[11px] tabular-nums text-slate-400"
              >
                {{ datetime(criterion.created_at) }}
              </p>
            </div>
          </div>
        </section>

        <!-- Score scale -->
        <section class="card overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/80 px-5 py-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
              <AppIcon
                name="target"
                :size="15"
                class="text-slate-400"
              />
              Thang điểm đánh giá
            </h2>
            <span
              v-if="scoreRows.length"
              class="text-[11px] tabular-nums text-slate-400"
            >
              {{ scoreRows.length }} mức · {{ weightSpan }}
            </span>
          </div>

          <ul
            v-if="scoreRows.length"
            class="divide-y divide-slate-100"
          >
            <li
              v-for="row in scoreRows"
              :key="`score-${row.n}`"
              class="flex items-start gap-3 px-5 py-3.5 transition-colors hover:bg-slate-50/70"
            >
              <span
                class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-lg text-xs font-bold text-white shadow-sm"
                :class="row.tone"
              >
                {{ row.code }}
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800">
                      {{ displayOrEmpty(row.label, EMPTY_LABELS.notUpdated) }}
                    </p>
                    <p
                      v-if="row.description"
                      class="mt-0.5 text-xs leading-relaxed text-slate-500"
                    >
                      {{ row.description }}
                    </p>
                  </div>
                  <span class="shrink-0 rounded-md bg-slate-100 px-2 py-1 text-xs font-bold tabular-nums text-slate-700">
                    {{ formatWeight(row.weight) }}
                    <span class="font-medium text-slate-400">điểm</span>
                  </span>
                </div>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="row.tone"
                    :style="{ width: `${weightBarPct(row.weight)}%` }"
                  />
                </div>
              </div>
            </li>
          </ul>
          <p
            v-else
            class="px-5 py-8 text-center text-sm text-slate-500"
          >
            Chưa cấu hình thang điểm.
          </p>
        </section>
      </div>

      <EvaluationActivityTimeline :activity="activity" />
    </div>

    <EvaluationCriterionFormModal
      :show="showFormModal"
      mode="edit"
      :criterion="criterion"
      :departments="departments"
      :categories="categories"
      :next-code="criterion.criteria_code"
      :default-score-levels="defaultScoreLevels"
      :can-create-general="canCreateGeneral"
      :default-department-code="viewer.own_department_code || ''"
      @close="showFormModal = false"
    />
  </AppLayout>
</template>
