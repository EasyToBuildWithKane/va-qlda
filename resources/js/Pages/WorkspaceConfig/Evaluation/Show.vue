<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Avatar from '@/shared/ui/Avatar.vue';
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

function weightBarPct(weight) {
    return Math.max(6, Math.round((Math.abs(weight) / maxAbsWeight.value) * 100));
}

function onDelete() {
    confirmDelete(
        `Xoá tiêu chí «${props.criterion.criteria_name}»?`,
        () => router.delete(route('workspace.evaluation.destroy', props.criterion.id)),
    );
}

const ACTIVITY_ICONS = {
    'evaluation.criteria_created': { name: 'add', tone: 'bg-emerald-500' },
    'evaluation.criteria_updated': { name: 'edit', tone: 'bg-amber-500' },
    'evaluation.criteria_deleted': { name: 'trash', tone: 'bg-rose-500' },
};

function activityIcon(item) {
    return ACTIVITY_ICONS[item.action] || { name: 'clock', tone: 'bg-slate-400' };
}

const isGeneralScope = computed(() => props.criterion.scope === 'general');

const headerSubtitle = computed(() => {
    const parts = [props.criterion.criteria_code].filter(Boolean);
    if (isGeneralScope.value) {
        parts.push('Tiêu chí chung');
    } else if (props.criterion.department_name || props.criterion.scope_label) {
        parts.push(props.criterion.department_name || props.criterion.scope_label);
    }
    if (props.criterion.category) {
        parts.push(props.criterion.category);
    }
    return parts.join(' · ');
});

const statusBadge = computed(() => (
    props.criterion.is_active ? 'Đang hoạt động' : 'Ngưng hoạt động'
));
</script>

<template>
  <Head :title="criterion.criteria_name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="criterion.criteria_name"
        :subtitle="headerSubtitle"
        :badge="statusBadge"
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

    <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-3">
      <div class="space-y-5 xl:col-span-2">
        <!-- Overview -->
        <div class="card overflow-hidden">
          <div class="grid grid-cols-2 gap-px bg-slate-100 sm:grid-cols-4">
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="target"
                  :size="13"
                />
                Số mức thang điểm
              </p>
              <p class="mt-1 text-lg font-semibold tabular-nums text-slate-800">
                {{ scoreRows.length }}
              </p>
            </div>
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="check-circle"
                  :size="13"
                />
                Chấm điểm 0.5
              </p>
              <p class="mt-1 text-lg font-semibold text-slate-800">
                {{ criterion.allow_half_score ? 'Có' : 'Không' }}
              </p>
            </div>
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="calendar"
                  :size="13"
                />
                Ngày tạo
              </p>
              <p class="mt-1 text-sm font-medium text-slate-800">
                {{ criterion.created_at ? datetime(criterion.created_at) : EMPTY_LABELS.notUpdated }}
              </p>
            </div>
            <div class="bg-white px-4 py-3.5">
              <p class="flex items-center gap-1.5 text-[11px] font-medium text-slate-400">
                <AppIcon
                  name="edit"
                  :size="13"
                />
                Cập nhật gần nhất
              </p>
              <p class="mt-1 text-sm font-medium text-slate-800">
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
            <p
              v-if="criterion.creator?.display_name"
              class="mt-3 flex items-center gap-1.5 text-xs text-slate-400"
            >
              <AppIcon
                name="account"
                :size="13"
              />
              Người tạo: <span class="font-medium text-slate-600">{{ criterion.creator.display_name }}</span>
            </p>
          </div>
        </div>

        <!-- Score scale -->
        <div class="card overflow-hidden">
          <div class="flex items-center border-b border-slate-100 bg-slate-50/80 px-5 py-3">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
              <AppIcon
                name="target"
                :size="15"
                class="text-slate-400"
              />
              Thang điểm đánh giá
            </h2>
          </div>

          <ul
            v-if="scoreRows.length"
            class="divide-y divide-slate-100"
          >
            <li
              v-for="row in scoreRows"
              :key="`score-${row.n}`"
              class="flex items-center gap-3 px-5 py-3"
            >
              <span
                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-xs font-bold text-white shadow-sm"
                :class="row.tone"
              >
                {{ row.code }}
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-2">
                  <p class="truncate text-sm font-medium text-slate-800">
                    {{ displayOrEmpty(row.label, EMPTY_LABELS.notUpdated) }}
                  </p>
                  <span class="shrink-0 text-xs font-semibold tabular-nums text-slate-500">{{ row.weight }} điểm</span>
                </div>
                <p
                  v-if="row.description"
                  class="mt-0.5 truncate text-xs text-slate-500"
                  :title="row.description"
                >
                  {{ row.description }}
                </p>
                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
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
        </div>
      </div>

      <div class="card overflow-hidden xl:sticky xl:top-5">
        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-5 py-3">
          <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
            <AppIcon
              name="clock"
              :size="15"
              class="text-slate-400"
            />
            Lịch sử hoạt động
          </h2>
        </div>

        <ul
          v-if="activity.length"
          class="space-y-0 px-5 py-5"
        >
          <li
            v-for="(item, index) in activity"
            :key="item.id"
            class="relative flex gap-3 pb-6 last:pb-0"
          >
            <span
              v-if="index < activity.length - 1"
              class="absolute left-[15px] top-8 h-[calc(100%-1.25rem)] w-px bg-slate-200"
              aria-hidden="true"
            />
            <span
              class="relative z-10 mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full text-white shadow-sm"
              :class="activityIcon(item).tone"
            >
              <AppIcon
                :name="activityIcon(item).name"
                :size="14"
              />
            </span>
            <div class="min-w-0 flex-1 rounded-xl bg-slate-50 px-3.5 py-2.5">
              <div class="flex items-start justify-between gap-3">
                <p class="min-w-0 text-sm text-slate-800">
                  <span class="font-semibold">{{ item.actor_name || 'Hệ thống' }}</span>
                  {{ item.label || item.action }}
                </p>
                <Avatar
                  :name="item.actor_name || 'Hệ thống'"
                  :size="22"
                  class="shrink-0"
                />
              </div>
              <p
                v-if="item.meta?.criteria_code && item.meta.criteria_code !== criterion.criteria_code"
                class="mt-0.5 truncate font-mono text-[11px] text-slate-400"
              >
                {{ item.meta.criteria_code }}
              </p>
              <time
                class="mt-1 block text-xs tabular-nums text-slate-400"
                :datetime="item.created_at"
              >
                {{ item.created_at ? datetime(item.created_at) : EMPTY_LABELS.notUpdated }}
              </time>
            </div>
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
      :next-code="criterion.criteria_code"
      :default-score-levels="defaultScoreLevels"
      :can-create-general="canCreateGeneral"
      :default-department-code="viewer.own_department_code || ''"
      @close="showFormModal = false"
    />
  </AppLayout>
</template>
