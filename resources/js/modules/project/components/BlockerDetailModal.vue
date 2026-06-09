<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import BlockerAttachmentsBlock from '@/modules/project/components/BlockerAttachmentsBlock.vue';
import { date, datetime } from '@/composables/useFormat';

const TABS = [
    { key: 'detail', label: 'Nội dung chi tiết', icon: 'documents' },
    { key: 'resolution', label: 'Hướng xử lý', icon: 'meeting-notes' },
    { key: 'comments', label: 'Trao đổi', icon: 'comment' },
];

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    initialTab: { type: String, default: 'detail' },
    canComment: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'edit-resolution']);

const activeTab = ref('detail');

watch(
    () => [props.show, props.initialTab, props.blocker?.id],
    ([open]) => {
        if (open) {
            activeTab.value = props.initialTab || 'detail';
        }
    },
);

const modalTitle = computed(() => {
    if (!props.blocker) return 'Chi tiết vướng mắc';
    return props.blocker.code ? `Vướng mắc ${props.blocker.code}` : 'Chi tiết vướng mắc';
});

function normalizeEvidenceLinks(links) {
    const list = Array.isArray(links) ? links : [];
    return list
        .map((item) => ({
            label: (item?.label ?? '').trim(),
            url: (item?.url ?? '').trim(),
        }))
        .filter((item) => item.url);
}

function evidenceLinkLabel(item) {
    return item.label || item.url;
}

function blockerComments(b) {
    const raw = b?.comments;
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
}

function blockerAttachments(b) {
    const raw = b?.attachments;
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
}

const commentsCount = computed(() => props.blocker?.comments_count ?? blockerComments(props.blocker).length);

const person = (p) => (p?.name ? p : null);
</script>

<template>
  <Modal
    :show="show && !!blocker"
    :title="modalTitle"
    max-width="max-w-3xl"
    @close="emit('close')"
  >
    <template v-if="blocker">
      <div class="mb-4 flex flex-wrap items-start gap-3 border-b border-slate-100 pb-4">
        <div class="min-w-0 flex-1">
          <p class="text-base font-semibold leading-snug text-slate-800">
            {{ blocker.title }}
          </p>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <Badge
              v-if="blocker.severity"
              :label="blocker.severity.label"
              :color="blocker.severity.color"
            />
            <Badge
              v-if="blocker.status"
              :label="blocker.status.label"
              :color="blocker.status.color"
            />
            <span
              v-if="blocker.is_overdue"
              class="text-[10px] font-bold uppercase tracking-wide text-rose-600"
            >
              Quá hạn
            </span>
          </div>
        </div>
        <div
          v-if="person(blocker.owner)"
          class="flex shrink-0 items-center gap-2 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2"
        >
          <Avatar
            :name="blocker.owner.name"
            :src="blocker.owner.avatar_path"
            :size="28"
          />
          <div class="min-w-0 text-xs">
            <p class="font-medium text-slate-500">
              Người xử lý
            </p>
            <p class="truncate font-semibold text-slate-800">
              {{ blocker.owner.name }}
            </p>
          </div>
        </div>
      </div>

      <nav
        class="-mx-1 mb-4 flex gap-0.5 overflow-x-auto border-b border-slate-200"
        role="tablist"
        aria-label="Phần chi tiết vướng mắc"
      >
        <button
          v-for="tab in TABS"
          :key="tab.key"
          type="button"
          role="tab"
          class="flex shrink-0 items-center gap-1.5 border-b-2 px-3 py-2.5 text-xs font-semibold transition"
          :class="activeTab === tab.key
            ? 'border-brand text-brand'
            : 'border-transparent text-slate-500 hover:text-slate-700'"
          :aria-selected="activeTab === tab.key"
          @click="activeTab = tab.key"
        >
          <AppIcon
            :name="tab.icon"
            :size="14"
          />
          {{ tab.label }}
          <span
            v-if="tab.key === 'comments' && commentsCount"
            class="rounded-full bg-slate-200 px-1.5 text-[10px] font-bold tabular-nums text-slate-600"
          >
            {{ commentsCount }}
          </span>
        </button>
      </nav>

      <div class="max-h-[min(28rem,55vh)] overflow-y-auto pr-0.5">
        <div
          v-show="activeTab === 'detail'"
          class="space-y-4"
        >
          <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <span v-if="blocker.raised_at">Báo {{ date(blocker.raised_at) }}</span>
            <span>Hạn {{ blocker.due_date ? date(blocker.due_date) : '—' }}</span>
            <span v-if="blocker.resolved_at">Xong {{ datetime(blocker.resolved_at) }}</span>
            <span v-if="blocker.updated_at">Cập nhật {{ datetime(blocker.updated_at) }}</span>
          </div>

          <section class="rounded-xl border border-slate-200/90 bg-slate-50/40 p-4">
            <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
              Mô tả
            </h3>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
              {{ blocker.description?.trim() || 'Chưa có mô tả chi tiết.' }}
            </p>
          </section>

          <section
            v-if="blocker.task?.title"
            class="rounded-xl border border-slate-200/90 p-4"
          >
            <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
              Công việc liên quan
            </h3>
            <p class="mt-2 text-sm text-slate-700">
              {{ blocker.task.title }}
            </p>
          </section>

          <section
            v-if="normalizeEvidenceLinks(blocker.evidence_links).length"
            class="rounded-xl border border-slate-200/90 p-4"
          >
            <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
              Link dẫn chứng
            </h3>
            <ul class="mt-2 space-y-2">
              <li
                v-for="(link, linkIdx) in normalizeEvidenceLinks(blocker.evidence_links)"
                :key="linkIdx"
              >
                <a
                  :href="link.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex max-w-full items-start gap-1.5 text-sm font-medium text-brand hover:underline"
                >
                  <AppIcon
                    name="dependency"
                    :size="14"
                    class="mt-0.5 shrink-0"
                  />
                  <span class="min-w-0 break-all">{{ evidenceLinkLabel(link) }}</span>
                </a>
              </li>
            </ul>
          </section>

          <section
            v-if="blockerAttachments(blocker).length"
            class="rounded-xl border border-slate-200/90 p-4"
          >
            <h3 class="text-[11px] font-bold uppercase tracking-wide text-slate-400">
              Ảnh &amp; file đính kèm
            </h3>
            <div class="mt-3">
              <BlockerAttachmentsBlock
                :blocker-id="blocker.id"
                :attachments="blockerAttachments(blocker)"
                :can-upload="false"
                compact
              />
            </div>
          </section>

          <div
            v-if="person(blocker.raised_by)"
            class="flex items-center gap-2 text-xs text-slate-500"
          >
            <span>Người báo:</span>
            <Avatar
              :name="blocker.raised_by.name"
              :src="blocker.raised_by.avatar_path"
              :size="22"
            />
            <span class="font-medium text-slate-700">{{ blocker.raised_by.name }}</span>
          </div>
        </div>

        <div
          v-show="activeTab === 'resolution'"
          class="space-y-4"
        >
          <section class="rounded-xl border border-amber-200/80 bg-amber-50/50 p-4">
            <h3 class="text-[11px] font-bold uppercase tracking-wide text-amber-800/70">
              Nguyên nhân
            </h3>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
              {{ blocker.root_cause?.trim() || 'Chưa ghi nhận nguyên nhân — người xử lý cập nhật qua «Hướng xử lý».' }}
            </p>
          </section>

          <section class="rounded-xl border border-emerald-200/80 bg-emerald-50/40 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h3 class="text-[11px] font-bold uppercase tracking-wide text-emerald-800/80">
                Kế hoạch xử lý
              </h3>
              <button
                v-if="canUpdate"
                type="button"
                class="text-xs font-semibold text-brand hover:underline"
                @click="emit('edit-resolution', blocker)"
              >
                Cập nhật hướng xử lý
              </button>
            </div>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
              {{ blocker.resolution?.trim() || 'Chưa có hướng xử lý. Người phụ trách mô tả các bước cụ thể để gỡ vướng mắc.' }}
            </p>
          </section>
        </div>

        <div v-show="activeTab === 'comments'">
          <CommentThread
            :comments="blockerComments(blocker)"
            commentable-type="blocker"
            :commentable-id="blocker.id"
            :can-comment="canComment"
            placeholder="Chia sẻ cập nhật, câu hỏi hoặc thông tin hỗ trợ xử lý vướng mắc…"
          />
        </div>
      </div>

      <div class="mt-5 flex justify-end border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost"
          @click="emit('close')"
        >
          Đóng
        </button>
      </div>
    </template>
  </Modal>
</template>
