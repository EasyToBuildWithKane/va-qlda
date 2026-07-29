<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { date } from '@/composables/useFormat';

const props = defineProps({
    config: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const confirmDelete = useConfirmDelete();

const groups = computed(() => props.config.criteria_groups || []);

function formatRange(from, to) {
    const a = from ? date(from) : null;
    const b = to ? date(to) : null;
    if (!a && !b) return EMPTY_LABELS.period;
    if (a && !b) return `${a} trở đi`;
    if (!a && b) return `đến ${b}`;
    return `${a} – ${b}`;
}

function onDelete() {
    confirmDelete(
        `Xoá cấu hình «${props.config.config_name}»?`,
        () => router.delete(route('workspace.evaluation.destroy', props.config.id)),
    );
}

function formatPoint(value) {
    if (value === null || value === undefined || value === '') return EMPTY_LABELS.notUpdated;
    const n = Number(value);
    if (Number.isNaN(n)) return displayOrEmpty(value, EMPTY_LABELS.notUpdated);
    return n > 0 ? `+${n}` : String(n);
}
</script>

<template>
  <Head :title="config.config_name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="config.config_name"
        :subtitle="`${config.department_name} · ${config.template_type_label}`"
        icon="award"
        back-href="/workspace-config/evaluation"
      >
        <template v-if="can.manage">
          <Link
            :href="route('workspace.evaluation.edit', config.id)"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-sm"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Sửa
          </Link>
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
      <div class="card grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Phòng ban
          </p>
          <p class="mt-1 font-medium text-slate-800">
            {{ config.department_name }}
          </p>
          <p class="font-mono text-xs text-slate-500">
            {{ config.department_code }}
          </p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Hiệu lực
          </p>
          <p class="mt-1 tabular-nums text-slate-800">
            {{ formatRange(config.effective_from, config.effective_to) }}
          </p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Loại mẫu
          </p>
          <div class="mt-1">
            <Badge
              :color="config.template_type === 'point_system' ? 'violet' : 'amber'"
              :label="config.template_type_label"
            />
          </div>
        </div>
        <div v-if="config.template_type === 'point_system'">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Điểm khởi đầu
          </p>
          <p class="mt-1 font-display text-2xl tabular-nums text-slate-900">
            {{ displayOrEmpty(config.base_score, EMPTY_LABELS.notUpdated) }}
          </p>
        </div>
        <div>
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Trạng thái
          </p>
          <div class="mt-1">
            <Badge
              :color="config.is_active ? 'emerald' : 'slate'"
              :label="config.is_active ? 'Đang bật' : 'Đã tắt'"
            />
          </div>
        </div>
        <div
          v-if="config.description"
          class="sm:col-span-2 lg:col-span-3"
        >
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
            Mô tả
          </p>
          <p class="mt-1 whitespace-pre-wrap text-sm text-slate-700">
            {{ config.description }}
          </p>
        </div>
      </div>

      <div
        v-for="group in groups"
        :key="group.category"
        class="card overflow-hidden"
      >
        <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
          <h3 class="text-sm font-semibold text-slate-800">
            {{ group.category }}
          </h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="text-[11px] uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-5 py-2 font-medium">
                  Mã
                </th>
                <th class="px-5 py-2 font-medium">
                  Tên
                </th>
                <th class="px-5 py-2 font-medium">
                  {{ config.template_type === 'point_system' ? 'Điểm' : 'Trọng số' }}
                </th>
                <th
                  v-if="config.template_type === 'point_system'"
                  class="px-5 py-2 font-medium"
                >
                  Tối đa
                </th>
                <th
                  v-if="config.template_type === 'scorecard'"
                  class="px-5 py-2 font-medium"
                >
                  Điểm YC
                </th>
                <th class="px-5 py-2 font-medium">
                  Mô tả
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="c in group.criteria"
                :key="c.id"
              >
                <td class="px-5 py-3 font-mono text-slate-800">
                  {{ c.criteria_code }}
                </td>
                <td class="px-5 py-3 font-medium text-slate-800">
                  {{ c.criteria_name }}
                </td>
                <td class="px-5 py-3 tabular-nums">
                  <template v-if="config.template_type === 'point_system'">
                    {{ formatPoint(c.point_value) }}
                  </template>
                  <template v-else>
                    {{ displayOrEmpty(c.weight, EMPTY_LABELS.notUpdated) }}
                  </template>
                </td>
                <td
                  v-if="config.template_type === 'point_system'"
                  class="px-5 py-3 tabular-nums text-slate-600"
                >
                  {{ displayOrEmpty(c.max_points, 'Không giới hạn') }}
                </td>
                <td
                  v-if="config.template_type === 'scorecard'"
                  class="px-5 py-3 tabular-nums"
                >
                  {{ displayOrEmpty(c.required_score, EMPTY_LABELS.notUpdated) }}
                </td>
                <td class="px-5 py-3 text-slate-600">
                  {{ displayOrEmpty(c.description, EMPTY_LABELS.notUpdated) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div
        v-if="groups.length === 0"
        class="card p-8 text-center text-sm text-slate-500"
      >
        Chưa có tiêu chí trong cấu hình này.
        <Link
          v-if="can.manage"
          :href="route('workspace.evaluation.edit', config.id)"
          class="ml-1 text-brand hover:underline"
        >
          Thêm tiêu chí
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
