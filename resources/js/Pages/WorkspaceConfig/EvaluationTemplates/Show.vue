<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import EvaluationTemplateFormModal from '@/modules/evaluation-template/components/EvaluationTemplateFormModal.vue';
import EvaluationActivityTimeline from '@/modules/evaluation/components/EvaluationActivityTimeline.vue';
import { useConfirmDelete } from '@/composables/useConfirmClose';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { date as formatDate } from '@/composables/useFormat';

const props = defineProps({
    template: { type: Object, required: true },
    activity: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    jobTitles: { type: Array, default: () => [] },
    jobRanks: { type: Array, default: () => [] },
    fieldTypeOptions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
    relatedForms: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const confirmDelete = useConfirmDelete();
const showFormModal = ref(false);
const activeTab = ref('detail');
const historyAnchor = ref(null);

const criteriaGrouped = computed(() => {
    const lines = Array.isArray(props.template.criteria) ? props.template.criteria : [];
    const map = new Map();
    lines.forEach((line) => {
        const cat = line.category?.trim() || 'Khác';
        if (!map.has(cat)) map.set(cat, []);
        map.get(cat).push(line);
    });
    return [...map.entries()].map(([category, items]) => ({ category, items }));
});

function openEdit() {
    showFormModal.value = true;
}

function onDelete() {
    confirmDelete(
        `Xóa mẫu «${props.template.name}»? Thao tác không thể hoàn tác.`,
        () => {
            router.delete(route('workspace.evaluation-templates.destroy', props.template.id));
        },
        { title: 'Xóa mẫu đánh giá', confirmText: 'Xóa', tone: 'danger' },
    );
}

function onDuplicate() {
    router.post(route('workspace.evaluation-templates.duplicate', props.template.id), {}, {
        preserveScroll: true,
    });
}

function scrollToHistory() {
    historyAnchor.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
  <Head :title="template.name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="template.name"
        subtitle="Chi tiết mẫu đánh giá"
        icon="clipboard-list"
        back-href="/workspace-config/evaluation-templates"
      >
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="btn-ghost inline-flex h-9 cursor-not-allowed items-center gap-1.5 px-3 text-sm opacity-50"
            disabled
            title="Sắp ra mắt"
          >
            <AppIcon
              name="plus"
              :size="15"
            />
            Phiếu đánh giá
          </button>
          <button
            v-if="can.manage"
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-sm"
            @click="openEdit"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Sửa
          </button>
          <button
            v-if="can.manage"
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-sm text-rose-600 hover:bg-rose-50"
            @click="onDelete"
          >
            <AppIcon
              name="trash"
              :size="15"
            />
            Xóa
          </button>
          <button
            v-if="can.manage"
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-sm"
            @click="onDuplicate"
          >
            <AppIcon
              name="copy"
              :size="15"
            />
            Nhân bản
          </button>
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-sm"
            @click="scrollToHistory"
          >
            <AppIcon
              name="report-history"
              :size="15"
            />
            Lịch sử
          </button>
        </div>
      </PageHeader>
    </template>

    <div class="mb-4 flex gap-4 border-b border-slate-200">
      <button
        type="button"
        class="border-b-2 px-1 pb-2 text-sm font-medium"
        :class="activeTab === 'detail' ? 'border-brand text-brand' : 'border-transparent text-slate-500'"
        @click="activeTab = 'detail'"
      >
        Chi tiết
      </button>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <div class="min-w-0 space-y-5">
        <!-- Thông tin chung -->
        <section class="rounded-card border border-slate-200/80 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">
              Thông tin chung
            </h2>
          </div>
          <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Mã mẫu đánh giá
              </p>
              <p class="mt-1 font-mono text-sm text-slate-800">
                {{ template.template_code }}
              </p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Tên mẫu đánh giá
              </p>
              <p class="mt-1 text-sm font-medium text-slate-800">
                {{ template.name }}
              </p>
            </div>
            <div class="sm:col-span-2">
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Chức danh áp dụng
              </p>
              <div
                v-if="template.titles?.length"
                class="mt-1.5 flex flex-wrap gap-1.5"
              >
                <span
                  v-for="t in template.titles"
                  :key="`title-${t.code}`"
                  class="inline-flex rounded-lg bg-brand/10 px-2 py-0.5 text-xs font-medium text-brand"
                >
                  {{ t.name }}
                </span>
              </div>
              <p
                v-else
                class="mt-1 text-sm text-slate-500"
              >
                {{ displayOrEmpty(template.position_name, EMPTY_LABELS.notUpdated) }}
              </p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Cấp bậc
              </p>
              <div
                v-if="template.ranks?.length"
                class="mt-1.5 flex flex-wrap gap-1.5"
              >
                <span
                  v-for="r in template.ranks"
                  :key="`rank-${r.code}`"
                  class="inline-flex rounded-lg bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700"
                >
                  {{ r.name }}
                </span>
              </div>
              <p
                v-else
                class="mt-1 text-sm text-slate-500"
              >
                {{ EMPTY_LABELS.notUpdated }}
              </p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Số tiêu chí
              </p>
              <p class="mt-1 text-sm tabular-nums text-slate-800">
                {{ template.criteria_count ?? 0 }}
              </p>
            </div>
            <div>
              <p class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                Trường bổ sung
              </p>
              <p class="mt-1 text-sm tabular-nums text-slate-800">
                {{ template.fields_count ?? 0 }}
              </p>
            </div>
          </div>
        </section>

        <!-- Danh sách tiêu chí -->
        <section class="rounded-card border border-slate-200/80 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">
              Danh sách tiêu chí đánh giá
            </h2>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-5 py-2.5 font-semibold">
                    Tiêu chí đánh giá
                  </th>
                  <th class="w-24 px-3 py-2.5 font-semibold">
                    Trọng số
                  </th>
                  <th class="px-3 py-2.5 font-semibold">
                    Điểm yêu cầu
                  </th>
                  <th class="w-28 px-3 py-2.5 font-semibold">
                    Nguồn
                  </th>
                  <th class="w-36 px-3 py-2.5 font-semibold">
                    Tính vào tổng điểm
                  </th>
                </tr>
              </thead>
              <tbody>
                <template
                  v-for="group in criteriaGrouped"
                  :key="group.category"
                >
                  <tr class="bg-slate-50/80">
                    <td
                      colspan="5"
                      class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500"
                    >
                      {{ group.category }}
                    </td>
                  </tr>
                  <tr
                    v-for="line in group.items"
                    :key="line.id || `${line.source}-${line.criterion_id || line.criteria_name}`"
                    class="border-t border-slate-100"
                  >
                    <td class="px-5 py-2.5 text-slate-800">
                      {{ line.criteria_name }}
                      <span
                        v-if="line.criteria_code"
                        class="text-slate-400"
                      >({{ line.criteria_code }})</span>
                    </td>
                    <td class="px-3 py-2.5 tabular-nums text-slate-700">
                      {{ line.weight }}
                    </td>
                    <td class="px-3 py-2.5 text-slate-700">
                      {{ displayOrEmpty(line.required_score_label, EMPTY_LABELS.notUpdated) }}
                    </td>
                    <td class="px-3 py-2.5">
                      <span
                        class="inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                        :class="line.is_custom || line.source === 'custom'
                          ? 'bg-violet-50 text-violet-700'
                          : 'bg-sky-50 text-sky-700'"
                      >
                        {{ line.is_custom || line.source === 'custom' ? 'Tuỳ chỉnh' : 'Danh mục' }}
                      </span>
                    </td>
                    <td class="px-3 py-2.5 text-slate-700">
                      {{ line.include_in_total ? 'Có' : 'Không' }}
                    </td>
                  </tr>
                </template>
                <tr v-if="!criteriaGrouped.length">
                  <td
                    colspan="5"
                    class="px-5 py-10 text-center text-sm text-slate-400"
                  >
                    Chưa gắn tiêu chí vào mẫu.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Trường bổ sung -->
        <section class="rounded-card border border-slate-200/80 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">
              Trường bổ sung trên phiếu
            </h2>
          </div>
          <div
            v-if="template.fields?.length"
            class="overflow-x-auto"
          >
            <table class="min-w-full text-sm">
              <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="px-5 py-2.5 font-semibold">
                    Nhãn
                  </th>
                  <th class="px-3 py-2.5 font-semibold">
                    Loại
                  </th>
                  <th class="w-28 px-3 py-2.5 font-semibold">
                    Bắt buộc
                  </th>
                  <th class="px-3 py-2.5 font-semibold">
                    Gợi ý
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="field in template.fields"
                  :key="field.id || field.field_key"
                  class="border-t border-slate-100"
                >
                  <td class="px-5 py-2.5 font-medium text-slate-800">
                    {{ field.label }}
                    <span class="ml-1 font-mono text-[11px] text-slate-400">{{ field.field_key }}</span>
                  </td>
                  <td class="px-3 py-2.5 text-slate-700">
                    {{ field.field_type_label || field.field_type }}
                  </td>
                  <td class="px-3 py-2.5 text-slate-700">
                    {{ field.is_required ? 'Có' : 'Không' }}
                  </td>
                  <td class="px-3 py-2.5 text-slate-600">
                    {{ displayOrEmpty(field.help_text, EMPTY_LABELS.notUpdated) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p
            v-else
            class="px-5 py-10 text-center text-sm text-slate-400"
          >
            Chưa cấu hình trường bổ sung.
          </p>
        </section>

        <!-- Danh sách phiếu -->
        <section class="rounded-card border border-slate-200/80 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">
              Danh sách phiếu đánh giá
            </h2>
            <Link
              v-if="can.manage_forms"
              :href="route('workspace.evaluation-forms.create', { template_id: template.id })"
              class="btn-primary inline-flex h-8 items-center gap-1.5 px-2.5 text-xs"
            >
              <AppIcon
                name="plus"
                :size="14"
              />
              Tạo phiếu từ mẫu
            </Link>
          </div>
          <div
            v-if="relatedForms.length"
            class="overflow-x-auto"
          >
            <table class="min-w-full text-sm">
              <thead>
                <tr class="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-500">
                  <th class="px-4 py-2.5">
                    Tên phiếu
                  </th>
                  <th class="px-4 py-2.5">
                    Trạng thái
                  </th>
                  <th class="px-4 py-2.5">
                    Số tiêu chí
                  </th>
                  <th class="px-4 py-2.5">
                    Số nhân sự
                  </th>
                  <th class="px-4 py-2.5">
                    Ngày tạo
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="form in relatedForms"
                  :key="form.id"
                  class="border-b border-slate-50"
                >
                  <td class="px-4 py-2.5">
                    <Link
                      :href="route('workspace.evaluation-forms.edit', form.id)"
                      class="font-medium text-slate-800 hover:text-brand"
                    >
                      {{ form.name }}
                    </Link>
                  </td>
                  <td class="px-4 py-2.5 text-slate-600">
                    {{ form.status_label || form.status }}
                  </td>
                  <td class="px-4 py-2.5 tabular-nums text-slate-600">
                    {{ form.criteria_count ?? 0 }}
                  </td>
                  <td class="px-4 py-2.5 tabular-nums text-slate-600">
                    {{ form.assignees_count ?? 0 }}
                  </td>
                  <td class="px-4 py-2.5 text-slate-600">
                    {{ form.created_at ? formatDate(form.created_at) : displayOrEmpty(null, EMPTY_LABELS.notUpdated) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div
            v-else
            class="flex flex-col items-center justify-center px-5 py-14 text-center"
          >
            <div class="mb-3 grid h-14 w-14 place-items-center rounded-full bg-slate-50 text-slate-300">
              <AppIcon
                name="clipboard-list"
                :size="24"
              />
            </div>
            <p class="text-sm font-medium text-slate-500">
              Chưa có phiếu đánh giá
            </p>
            <p class="mt-1 text-xs text-slate-400">
              Tạo phiếu mới từ mẫu này để bắt đầu kỳ đánh giá.
            </p>
          </div>
        </section>
      </div>

      <!-- Lịch sử hoạt động -->
      <aside
        ref="historyAnchor"
        class="min-w-0"
      >
        <section class="rounded-card border border-slate-200/80 bg-white shadow-sm lg:sticky lg:top-4">
          <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-800">
              Lịch sử hoạt động
            </h2>
          </div>
          <div class="px-3 py-3">
            <EvaluationActivityTimeline :activity="activity" />
          </div>
        </section>
      </aside>
    </div>

    <EvaluationTemplateFormModal
      :show="showFormModal"
      :template="template"
      :criteria-options="criteriaOptions"
      :job-titles="jobTitles"
      :job-ranks="jobRanks"
      :field-type-options="fieldTypeOptions"
      @close="showFormModal = false"
    />
  </AppLayout>
</template>
