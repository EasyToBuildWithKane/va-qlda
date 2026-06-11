<script setup>
import { computed, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import EvidenceLinkCard from '@/shared/ui/EvidenceLinkCard.vue';
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

const evidenceLinks = computed(() => normalizeEvidenceLinks(props.blocker?.evidence_links));

const person = (p) => (p?.name ? p : null);

const statusBadgeColor = computed(() => {
    const v = props.blocker?.status?.value;
    if (v === 'resolved' || v === 'closed') return 'emerald';
    if (v === 'in_progress') return 'sky';
    return 'slate';
});

const metaItems = computed(() => {
    const b = props.blocker;
    if (!b) return [];
    const items = [];
    if (b.raised_at) items.push({ label: 'Ngày báo', value: date(b.raised_at) });
    items.push({ label: 'Hạn xử lý', value: b.due_date ? date(b.due_date) : '—' });
    if (b.resolved_at) items.push({ label: 'Đã xong', value: datetime(b.resolved_at) });
    if (b.updated_at) items.push({ label: 'Cập nhật', value: datetime(b.updated_at) });
    return items;
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
      <header class="blocker-detail-hero mb-4 rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-3">
        <p class="text-base font-semibold leading-snug text-slate-900">
          {{ blocker.title }}
        </p>
        <div class="mt-2.5 flex flex-wrap items-center gap-2">
          <Badge
            v-if="blocker.severity"
            :label="blocker.severity.label"
            :color="blocker.severity.color"
            class="!px-2.5 !py-0.5 !text-[11px] !font-semibold"
          />
          <Badge
            v-if="blocker.status"
            :label="blocker.status.label"
            :color="statusBadgeColor"
            class="!px-2.5 !py-0.5 !text-[11px] !font-medium"
          />
          <span
            v-if="blocker.is_overdue"
            class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700 ring-1 ring-rose-200/80"
          >
            <AppIcon
              name="clock"
              :size="11"
              class="shrink-0"
            />
            Quá hạn
          </span>
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <div
            v-if="person(blocker.owner)"
            class="flex min-w-0 items-center gap-2 rounded-lg bg-white/80 px-2.5 py-2 ring-1 ring-slate-200/70"
          >
            <Avatar
              :name="blocker.owner.name"
              :src="blocker.owner.avatar_path"
              :size="28"
              class="shrink-0"
            />
            <div class="min-w-0">
              <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                Người xử lý
              </p>
              <p class="truncate text-sm font-medium text-slate-800">
                {{ blocker.owner.name }}
              </p>
            </div>
          </div>
          <div
            v-if="person(blocker.raised_by)"
            class="flex min-w-0 items-center gap-2 rounded-lg bg-white/80 px-2.5 py-2 ring-1 ring-slate-200/70"
          >
            <Avatar
              :name="blocker.raised_by.name"
              :src="blocker.raised_by.avatar_path"
              :size="28"
              class="shrink-0"
            />
            <div class="min-w-0">
              <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                Người báo
              </p>
              <p class="truncate text-sm font-medium text-slate-800">
                {{ blocker.raised_by.name }}
              </p>
            </div>
          </div>
        </div>
      </header>

      <nav
        class="mb-4 flex gap-1 overflow-x-auto rounded-lg bg-slate-100/80 p-1"
        role="tablist"
        aria-label="Phần chi tiết vướng mắc"
      >
        <button
          v-for="tab in TABS"
          :key="tab.key"
          type="button"
          role="tab"
          class="flex shrink-0 items-center gap-1.5 rounded-md px-3 py-2 text-xs font-semibold transition"
          :class="activeTab === tab.key
            ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200/80'
            : 'text-slate-500 hover:text-slate-700'"
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
            class="min-w-[1.125rem] rounded-full bg-slate-200/90 px-1.5 text-center text-[10px] font-bold tabular-nums text-slate-600"
          >
            {{ commentsCount }}
          </span>
        </button>
      </nav>

      <div class="blocker-detail-body max-h-[min(28rem,58vh)] overflow-y-auto pr-0.5">
        <div
          v-show="activeTab === 'detail'"
          class="space-y-4"
        >
          <dl
            v-if="metaItems.length"
            class="grid grid-cols-2 gap-2 sm:grid-cols-4"
          >
            <div
              v-for="item in metaItems"
              :key="item.label"
              class="rounded-lg border border-slate-200/80 bg-slate-50/60 px-2.5 py-2"
            >
              <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                {{ item.label }}
              </dt>
              <dd class="mt-0.5 text-xs font-medium tabular-nums text-slate-700">
                {{ item.value }}
              </dd>
            </div>
          </dl>

          <section class="blocker-detail-section">
            <h3 class="blocker-detail-label">
              Mô tả
            </h3>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
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
            <p class="mt-2 text-sm font-medium text-slate-700">
              {{ blocker.task.title }}
            </p>
          </section>

          <section
            v-if="evidenceLinks.length"
            class="blocker-detail-section"
          >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h3 class="blocker-detail-label">
                Link dẫn chứng
              </h3>
              <p class="text-[10px] text-slate-400">
                Rê chuột để xem trước ảnh (prnt.sc, …)
              </p>
            </div>
            <div class="mt-3 space-y-2">
              <EvidenceLinkCard
                v-for="(link, linkIdx) in evidenceLinks"
                :key="linkIdx"
                :url="link.url"
                :label="evidenceLinkLabel(link)"
              />
            </div>
          </section>

          <section
            v-if="blockerAttachments(blocker).length"
            class="blocker-detail-section"
          >
            <h3 class="blocker-detail-label">
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
        </div>

        <div
          v-show="activeTab === 'resolution'"
          class="space-y-4"
        >
          <section class="blocker-detail-section blocker-detail-section--amber">
            <h3 class="blocker-detail-label text-amber-800/90">
              Nguyên nhân
            </h3>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
              {{ blocker.root_cause?.trim() || 'Chưa ghi nhận — cập nhật qua «Hướng xử lý».' }}
            </p>
          </section>

          <section class="blocker-detail-section blocker-detail-section--emerald">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h3 class="blocker-detail-label text-emerald-800/90">
                Kế hoạch xử lý
              </h3>
              <button
                v-if="canUpdate"
                type="button"
                class="text-xs font-semibold text-brand hover:underline"
                @click="emit('edit-resolution', blocker)"
              >
                Cập nhật
              </button>
            </div>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
              {{ blocker.resolution?.trim() || 'Chưa có hướng xử lý.' }}
            </p>
          </section>
        </div>

        <div
          v-show="activeTab === 'comments'"
          class="pt-1"
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

      <div class="mt-5 flex justify-end border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-9 px-4 text-sm"
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
    color: rgb(100 116 139);
}

.blocker-detail-section {
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240 / 0.95);
    background: rgb(255 255 255);
    padding: 0.875rem 1rem;
}

.blocker-detail-section--amber {
    border-color: rgb(253 230 138 / 0.85);
    background: rgb(255 251 235 / 0.55);
}

.blocker-detail-section--emerald {
    border-color: rgb(167 243 208 / 0.85);
    background: rgb(236 253 245 / 0.5);
}
</style>
