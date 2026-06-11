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
    { key: 'detail', label: 'Chi tiết', icon: 'documents' },
    { key: 'resolution', label: 'Hướng xử lý', icon: 'meeting-notes' },
    { key: 'comments', label: 'Trao đổi', icon: 'comment' },
];

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    initialTab: { type: String, default: 'detail' },
    canComment: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    partialReloadKeys: { type: Array, default: () => ['blockers'] },
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

/** Trạng thái dùng badge nhạt để không trùng màu mức độ */
const statusBadgeColor = computed(() => {
    const v = props.blocker?.status?.value;
    if (v === 'resolved' || v === 'closed') return 'emerald';
    if (v === 'in_progress') return 'sky';
    return 'slate';
});
</script>

<template>
  <Modal
    :show="show && !!blocker"
    :title="modalTitle"
    max-width="max-w-3xl"
    @close="emit('close')"
  >
    <template v-if="blocker">
      <header class="blocker-detail-summary mb-3 rounded-lg border border-slate-200/80 bg-slate-50/50 px-3 py-2.5">
        <p class="text-sm font-semibold leading-snug text-slate-800">
          {{ blocker.title }}
        </p>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1">
          <div class="flex flex-wrap items-center gap-1.5">
            <Badge
              v-if="blocker.severity"
              :label="blocker.severity.label"
              :color="blocker.severity.color"
              class="!px-2 !py-px !text-[11px] !font-semibold"
            />
            <Badge
              v-if="blocker.status"
              :label="blocker.status.label"
              :color="statusBadgeColor"
              class="!px-2 !py-px !text-[11px] !font-medium"
            />
            <span
              v-if="blocker.is_overdue"
              class="inline-flex items-center gap-0.5 rounded-md bg-rose-50 px-1.5 py-0.5 text-[11px] font-medium text-rose-700 ring-1 ring-rose-200/80"
            >
              <AppIcon
                name="clock"
                :size="11"
                class="shrink-0"
              />
              Quá hạn
            </span>
          </div>
          <span
            v-if="person(blocker.owner)"
            class="inline-flex min-w-0 max-w-full items-center gap-1.5 text-[11px] text-slate-500 before:hidden sm:before:inline sm:before:text-slate-300 sm:before:content-['·']"
          >
            <Avatar
              :name="blocker.owner.name"
              :src="blocker.owner.avatar_path"
              :size="18"
              class="shrink-0"
            />
            <span class="truncate">
              <span class="text-slate-400">Xử lý</span>
              {{ blocker.owner.name }}
            </span>
          </span>
        </div>
      </header>

      <nav
        class="mb-3 flex gap-1 overflow-x-auto rounded-lg bg-slate-100/80 p-0.5"
        role="tablist"
        aria-label="Phần chi tiết vướng mắc"
      >
        <button
          v-for="tab in TABS"
          :key="tab.key"
          type="button"
          role="tab"
          class="flex shrink-0 items-center gap-1 rounded-md px-2.5 py-1.5 text-[11px] font-semibold transition"
          :class="activeTab === tab.key
            ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200/80'
            : 'text-slate-500 hover:text-slate-700'"
          :aria-selected="activeTab === tab.key"
          @click="activeTab = tab.key"
        >
          <AppIcon
            :name="tab.icon"
            :size="13"
          />
          {{ tab.label }}
          <span
            v-if="tab.key === 'comments' && commentsCount"
            class="min-w-[1.125rem] rounded-full bg-slate-200/90 px-1 text-center text-[10px] font-bold tabular-nums text-slate-600"
          >
            {{ commentsCount }}
          </span>
        </button>
      </nav>

      <div class="blocker-detail-body max-h-[min(26rem,52vh)] overflow-y-auto">
        <div
          v-show="activeTab === 'detail'"
          class="space-y-3"
        >
          <p class="flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-slate-500">
            <span v-if="blocker.raised_at">Báo {{ date(blocker.raised_at) }}</span>
            <span>Hạn {{ blocker.due_date ? date(blocker.due_date) : '—' }}</span>
            <span v-if="blocker.resolved_at">Xong {{ datetime(blocker.resolved_at) }}</span>
            <span v-if="blocker.updated_at">Cập nhật {{ datetime(blocker.updated_at) }}</span>
          </p>

          <section class="blocker-detail-section">
            <h3 class="blocker-detail-label">
              Mô tả
            </h3>
            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
              {{ blocker.description?.trim() || 'Chưa có mô tả chi tiết.' }}
            </p>
          </section>

          <section
            v-if="blocker.task?.title"
            class="blocker-detail-section"
          >
            <h3 class="blocker-detail-label">
              Công việc liên quan
            </h3>
            <p class="mt-1.5 text-sm text-slate-700">
              {{ blocker.task.title }}
            </p>
          </section>

          <section
            v-if="normalizeEvidenceLinks(blocker.evidence_links).length"
            class="blocker-detail-section"
          >
            <h3 class="blocker-detail-label">
              Link dẫn chứng
            </h3>
            <ul class="mt-1.5 space-y-1">
              <li
                v-for="(link, linkIdx) in normalizeEvidenceLinks(blocker.evidence_links)"
                :key="linkIdx"
              >
                <a
                  :href="link.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex max-w-full items-start gap-1 text-sm font-medium text-brand hover:underline"
                >
                  <AppIcon
                    name="dependency"
                    :size="13"
                    class="mt-0.5 shrink-0"
                  />
                  <span class="min-w-0 break-all">{{ evidenceLinkLabel(link) }}</span>
                </a>
              </li>
            </ul>
          </section>

          <section
            v-if="blockerAttachments(blocker).length"
            class="blocker-detail-section"
          >
            <h3 class="blocker-detail-label">
              Ảnh &amp; file đính kèm
            </h3>
            <div class="mt-2">
              <BlockerAttachmentsBlock
                :blocker-id="blocker.id"
                :attachments="blockerAttachments(blocker)"
                :can-upload="false"
                compact
              />
            </div>
          </section>

          <p
            v-if="person(blocker.raised_by)"
            class="flex items-center gap-1.5 text-[11px] text-slate-500"
          >
            <span>Người báo</span>
            <Avatar
              :name="blocker.raised_by.name"
              :src="blocker.raised_by.avatar_path"
              :size="18"
            />
            <span class="font-medium text-slate-700">{{ blocker.raised_by.name }}</span>
          </p>
        </div>

        <div
          v-show="activeTab === 'resolution'"
          class="space-y-3"
        >
          <section class="blocker-detail-section blocker-detail-section--amber">
            <h3 class="blocker-detail-label text-amber-800/80">
              Nguyên nhân
            </h3>
            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
              {{ blocker.root_cause?.trim() || 'Chưa ghi nhận — cập nhật qua «Hướng xử lý».' }}
            </p>
          </section>

          <section class="blocker-detail-section blocker-detail-section--emerald">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h3 class="blocker-detail-label text-emerald-800/80">
                Kế hoạch xử lý
              </h3>
              <button
                v-if="canUpdate"
                type="button"
                class="text-[11px] font-semibold text-brand hover:underline"
                @click="emit('edit-resolution', blocker)"
              >
                Cập nhật
              </button>
            </div>
            <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
              {{ blocker.resolution?.trim() || 'Chưa có hướng xử lý.' }}
            </p>
          </section>
        </div>

        <div
          v-show="activeTab === 'comments'"
          class="pt-0.5"
        >
          <CommentThread
            :comments="blockerComments(blocker)"
            commentable-type="blocker"
            :commentable-id="blocker.id"
            :can-comment="canComment"
            :can-moderate="canUpdate"
            :partial-reload-keys="partialReloadKeys"
            placeholder="Chia sẻ cập nhật, câu hỏi hoặc thông tin hỗ trợ xử lý vướng mắc…"
          />
        </div>
      </div>

      <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
        <button
          type="button"
          class="btn-ghost h-8 px-3 text-sm"
          @click="emit('close')"
        >
          Đóng
        </button>
      </div>
    </template>
  </Modal>
</template>

<style scoped>
.blocker-detail-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: rgb(148 163 184);
}

.blocker-detail-section {
    border-radius: 0.5rem;
    border: 1px solid rgb(226 232 240 / 0.9);
    background: rgb(255 255 255);
    padding: 0.75rem 0.875rem;
}

.blocker-detail-section--amber {
    border-color: rgb(253 230 138 / 0.8);
    background: rgb(255 251 235 / 0.5);
}

.blocker-detail-section--emerald {
    border-color: rgb(167 243 208 / 0.8);
    background: rgb(236 253 245 / 0.45);
}
</style>
