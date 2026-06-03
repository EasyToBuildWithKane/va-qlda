<script setup>
import { computed, reactive } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import { costUnitSuffix } from '@/modules/aiAccount/utils/formatVnd';

const props = defineProps({
    loading: Boolean,
    groups: { type: Array, default: () => [] },
    expanded: { type: Object, required: true },
    detailExpanded: { type: Object, required: true },
    colVisible: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
});

const emit = defineEmits([
    'toggle-group',
    'toggle-detail',
    'approve',
    'reject',
    'save-notes',
]);

const notesDraft = reactive({});

const tableColSpan = computed(
    () => 3 + Object.values(props.colVisible).filter(Boolean).length,
);

function ensureDraft(row) {
    if (notesDraft[row.id] === undefined) {
        notesDraft[row.id] = row.review_notes ?? '';
    }
}

function saveNotes(row) {
    ensureDraft(row);
    emit('save-notes', { id: row.id, review_notes: notesDraft[row.id] ?? '' });
}
</script>

<template>
  <div>
    <div
      v-if="loading"
      class="px-5 py-10 text-center text-sm text-slate-500"
    >
      Đang tải…
    </div>

    <div
      v-else-if="groups.length === 0"
      class="px-5 py-12 text-center text-sm text-slate-500"
    >
      Không có đề xuất phù hợp bộ lọc.
    </div>

    <div
      v-else
      class="divide-y divide-slate-100"
    >
      <div
        v-for="g in groups"
        :key="g.group"
      >
        <button
          type="button"
          class="flex w-full flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3.5 text-left transition-colors hover:bg-slate-50/80"
          @click="emit('toggle-group', g.group)"
        >
          <span class="flex min-w-0 flex-1 items-center gap-2.5">
            <span
              class="h-2.5 w-2.5 shrink-0 rounded-full"
              :style="{ backgroundColor: g.dot_color }"
            />
            <span class="font-semibold text-slate-800">{{ g.group_label }}</span>
            <span class="text-sm text-slate-500">{{ g.items.length }} đề xuất</span>
          </span>
          <AppIcon
            name="chevron-down"
            :size="18"
            class="shrink-0 text-slate-400 transition"
            :class="expanded[g.group] ? 'rotate-180' : ''"
          />
        </button>

        <div v-show="expanded[g.group]">
          <div class="overflow-x-auto border-t border-slate-100 bg-white">
            <table class="w-full min-w-[720px] text-left text-sm">
              <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                  <th class="w-8 px-3 py-2" />
                  <th class="px-4 py-2.5">
                    Công cụ
                  </th>
                  <th
                    v-if="colVisible.license"
                    class="px-4 py-2.5"
                  >
                    License
                  </th>
                  <th
                    v-if="colVisible.cost"
                    class="px-4 py-2.5 text-right"
                  >
                    Chi phí
                  </th>
                  <th
                    v-if="colVisible.status"
                    class="px-4 py-2.5"
                  >
                    Duyệt
                  </th>
                  <th
                    v-if="colVisible.sender"
                    class="px-4 py-2.5"
                  >
                    Người gửi
                  </th>
                  <th class="px-4 py-2.5 text-right">
                    Thao tác
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <template
                  v-for="row in g.items"
                  :key="row.id"
                >
                  <tr class="hover:bg-slate-50/40">
                    <td class="px-3 py-2.5">
                      <button
                        type="button"
                        class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100"
                        :aria-expanded="detailExpanded[row.id]"
                        @click="emit('toggle-detail', row.id)"
                      >
                        <AppIcon
                          name="chevron-down"
                          :size="16"
                          :class="detailExpanded[row.id] ? 'rotate-180 transition' : 'transition'"
                        />
                      </button>
                    </td>
                    <td class="px-4 py-2.5 font-medium text-slate-800">
                      {{ row.tool_name }}
                    </td>
                    <td
                      v-if="colVisible.license"
                      class="px-4 py-2.5 text-slate-600"
                    >
                      {{ row.license_type }}
                    </td>
                    <td
                      v-if="colVisible.cost"
                      class="px-4 py-2.5 text-right"
                    >
                      <VndAmount
                        :amount="row.cost_amount"
                        compact
                      />
                      <p class="text-xs text-slate-500">
                        {{ costUnitSuffix(row.cost_unit) }}
                      </p>
                    </td>
                    <td
                      v-if="colVisible.status"
                      class="px-4 py-2.5"
                    >
                      <Badge
                        :label="row.status_label"
                        :color="row.status_color"
                      />
                    </td>
                    <td
                      v-if="colVisible.sender"
                      class="px-4 py-2.5 text-xs text-slate-600"
                    >
                      {{ row.created_by_name }}
                    </td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                      <a
                        v-if="row.export_pdf_url"
                        :href="row.export_pdf_url"
                        class="btn-ghost mr-1 px-2 py-1 text-xs text-brand"
                        target="_blank"
                        rel="noopener"
                        title="Tải phiếu PDF"
                      >
                        PDF
                      </a>
                      <template v-if="row.status === 'pending' && can.review_proposals">
                        <button
                          type="button"
                          class="btn-ghost mr-1 px-2 py-1 text-xs text-emerald-700"
                          @click="emit('approve', row)"
                        >
                          Duyệt
                        </button>
                        <button
                          type="button"
                          class="btn-ghost px-2 py-1 text-xs text-rose-600"
                          @click="emit('reject', row)"
                        >
                          Từ chối
                        </button>
                      </template>
                      <button
                        v-else
                        type="button"
                        class="btn-ghost px-2 py-1 text-xs text-slate-600"
                        @click="emit('toggle-detail', row.id)"
                      >
                        Chi tiết
                      </button>
                    </td>
                  </tr>
                  <tr
                    v-if="detailExpanded[row.id]"
                    class="bg-slate-50/60"
                  >
                    <td
                      :colspan="tableColSpan"
                      class="px-4 py-4"
                    >
                      <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                          <p class="mb-1 text-[11px] font-semibold uppercase text-slate-400">
                            Nội dung đề xuất
                          </p>
                          <p class="text-sm leading-relaxed text-slate-700 whitespace-pre-wrap">
                            {{ row.proposal_content || row.justification }}
                          </p>
                          <p
                            v-if="row.subject_about"
                            class="mt-2 text-xs text-slate-500"
                          >
                            Trích yếu: {{ row.subject_about }}
                          </p>
                        </div>
                        <div
                          v-if="row.objectives"
                          class="rounded-lg border border-slate-200 bg-white p-3"
                        >
                          <p class="mb-1 text-[11px] font-semibold uppercase text-slate-400">
                            Mục tiêu
                          </p>
                          <p class="text-sm text-slate-700 whitespace-pre-wrap">
                            {{ row.objectives }}
                          </p>
                        </div>
                        <div class="flex flex-wrap gap-2 lg:col-span-2">
                          <a
                            v-if="row.export_pdf_url"
                            :href="row.export_pdf_url"
                            class="btn-secondary text-xs"
                            target="_blank"
                            rel="noopener"
                          >
                            Xuất PDF (phiếu PDX)
                          </a>
                          <a
                            v-if="row.export_docx_url"
                            :href="row.export_docx_url"
                            class="btn-secondary text-xs"
                            target="_blank"
                            rel="noopener"
                          >
                            Xuất DOCX
                          </a>
                        </div>
                        <div
                          v-if="row.status === 'rejected' && row.rejection_reason"
                          class="rounded-lg border border-rose-200 bg-rose-50/80 p-3"
                        >
                          <p class="mb-1 text-[11px] font-semibold uppercase text-rose-600">
                            Lý do từ chối
                          </p>
                          <p class="text-sm text-rose-900 whitespace-pre-wrap">
                            {{ row.rejection_reason }}
                          </p>
                        </div>
                        <div
                          v-if="row.reviewed_by_name"
                          class="text-xs text-slate-500 lg:col-span-2"
                        >
                          Duyệt bởi {{ row.reviewed_by_name }} · {{ row.reviewed_at }}
                        </div>
                        <div
                          v-if="row.can_edit_notes && can.review_proposals"
                          class="lg:col-span-2"
                        >
                          <label class="label">Ghi chú sau duyệt / triển khai</label>
                          <textarea
                            v-model="notesDraft[row.id]"
                            class="input w-full text-sm"
                            rows="2"
                            placeholder="Bổ sung chi tiết sau khi duyệt…"
                            @focus="ensureDraft(row)"
                          />
                          <button
                            type="button"
                            class="btn-secondary mt-2 text-xs"
                            @click="saveNotes(row)"
                          >
                            Lưu ghi chú
                          </button>
                        </div>
                        <div
                          v-else-if="row.review_notes"
                          class="rounded-lg border border-slate-200 bg-white p-3 lg:col-span-2"
                        >
                          <p class="mb-1 text-[11px] font-semibold uppercase text-slate-400">
                            Ghi chú
                          </p>
                          <p class="text-sm text-slate-700 whitespace-pre-wrap">
                            {{ row.review_notes }}
                          </p>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
