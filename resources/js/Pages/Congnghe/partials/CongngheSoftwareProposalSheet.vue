<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import {
    PROPOSAL_EMPTY,
    departmentText,
    proposalStatusLabel,
    referenceCodeLabel,
    submitterEmailText,
    submitterNameText,
    submittedAtText,
} from './congngheProposalDisplay.js';

const STATUS_TONE = {
    new: 'violet',
    triaged: 'sky',
    in_progress: 'amber',
    done: 'emerald',
    rejected: 'slate',
};

const props = defineProps({
    proposal: { type: Object, required: true },
});

const previewId = ref(null);

const attachments = computed(() => props.proposal.attachments ?? []);

const statusTone = computed(() => {
    const v = props.proposal.status?.value;
    return STATUS_TONE[v] ?? 'slate';
});

const referenceLabel = computed(() => referenceCodeLabel(props.proposal.reference_code));

const activePreview = computed(() => {
    if (previewId.value == null) return null;
    return attachments.value.find((f) => f.id === previewId.value) ?? null;
});

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let n = bytes;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i += 1;
    }
    return `${n.toFixed(i ? 1 : 0)} ${units[i]}`;
}

function isPdf(file) {
    const mime = String(file.mime_type ?? '').toLowerCase();
    const name = String(file.original_name ?? '').toLowerCase();
    return mime.includes('pdf') || name.endsWith('.pdf');
}

function canPreviewInline(file) {
    return file.url && (file.is_image || isPdf(file));
}

function fileIcon(file) {
    if (file.is_image) return 'image';
    if (isPdf(file)) return 'pdf';
    return 'documents';
}

function selectPreview(file) {
    if (!canPreviewInline(file)) return;
    previewId.value = previewId.value === file.id ? null : file.id;
}

function previewKind(file) {
    if (file.is_image) return 'image';
    if (isPdf(file)) return 'pdf';
    return 'none';
}
</script>

<template>
  <article
    class="cn-proposal-sheet mx-auto max-w-4xl"
    aria-label="Phiếu đề xuất giải pháp phần mềm"
  >
    <div class="cn-proposal-sheet__paper card overflow-hidden shadow-sm">
      <header class="cn-proposal-sheet__masthead border-b border-brand/15 bg-gradient-to-br from-brand/[0.06] via-white to-slate-50/80 px-6 py-6 sm:px-8 sm:py-7">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brand/90">
              Phòng Công nghệ · VAschools
            </p>
            <h1 class="mt-2 font-display text-lg font-bold uppercase tracking-tight text-slate-900 sm:text-xl">
              Phiếu đề xuất giải pháp phần mềm
            </h1>
            <p class="mt-2 font-mono text-xs font-semibold text-slate-600">
              {{ referenceLabel }}
            </p>
          </div>
          <div class="cn-proposal-sheet__stamp shrink-0 rounded-lg border-2 border-dashed border-brand/35 bg-white px-4 py-3 text-center shadow-sm">
            <p class="text-[9px] font-bold uppercase tracking-widest text-slate-500">
              Trạng thái
            </p>
            <Badge
              :tone="statusTone"
              size="sm"
              class="mt-2"
            >
              {{ proposalStatusLabel(proposal) }}
            </Badge>
          </div>
        </div>
      </header>

      <div class="px-6 py-6 sm:px-8 sm:py-7">
        <section
          class="cn-proposal-sheet__section"
          aria-labelledby="cn-sheet-sec-1"
        >
          <h2
            id="cn-sheet-sec-1"
            class="cn-proposal-sheet__section-title"
          >
            <span class="cn-proposal-sheet__section-num">01</span>
            Thông tin người gửi
          </h2>
          <dl class="cn-proposal-sheet__field-grid">
            <div class="cn-proposal-sheet__field">
              <dt>Họ và tên</dt>
              <dd>{{ submitterNameText(proposal.submitter_name) }}</dd>
            </div>
            <div class="cn-proposal-sheet__field">
              <dt>Email liên hệ</dt>
              <dd>
                <a
                  v-if="proposal.submitter_email"
                  :href="`mailto:${proposal.submitter_email}`"
                  class="text-brand hover:underline"
                >{{ submitterEmailText(proposal.submitter_email) }}</a>
                <span v-else>{{ PROPOSAL_EMPTY.submitterEmail }}</span>
              </dd>
            </div>
            <div class="cn-proposal-sheet__field">
              <dt>Phòng ban / đơn vị</dt>
              <dd>{{ departmentText(proposal.department) }}</dd>
            </div>
            <div class="cn-proposal-sheet__field">
              <dt>Thời điểm gửi</dt>
              <dd class="tabular-nums">
                {{ submittedAtText(proposal.created_at) }}
              </dd>
            </div>
          </dl>
        </section>

        <section
          class="cn-proposal-sheet__section mt-8"
          aria-labelledby="cn-sheet-sec-2"
        >
          <h2
            id="cn-sheet-sec-2"
            class="cn-proposal-sheet__section-title"
          >
            <span class="cn-proposal-sheet__section-num">02</span>
            Tiêu đề đề xuất
          </h2>
          <p class="cn-proposal-sheet__body-text font-display text-base font-semibold text-slate-900 sm:text-lg">
            {{ proposal.title?.trim() || PROPOSAL_EMPTY.title }}
          </p>
        </section>

        <section
          class="cn-proposal-sheet__section mt-8"
          aria-labelledby="cn-sheet-sec-3"
        >
          <h2
            id="cn-sheet-sec-3"
            class="cn-proposal-sheet__section-title"
          >
            <span class="cn-proposal-sheet__section-num">03</span>
            Nội dung mô tả yêu cầu
          </h2>
          <div class="cn-proposal-sheet__content-box">
            <p class="whitespace-pre-wrap text-sm leading-relaxed text-slate-800">
              {{ proposal.content?.trim() || PROPOSAL_EMPTY.content }}
            </p>
          </div>
        </section>

        <section
          class="cn-proposal-sheet__section mt-8"
          aria-labelledby="cn-sheet-sec-4"
        >
          <h2
            id="cn-sheet-sec-4"
            class="cn-proposal-sheet__section-title"
          >
            <span class="cn-proposal-sheet__section-num">04</span>
            Tài liệu đính kèm
          </h2>

          <p
            v-if="!attachments.length"
            class="text-sm text-slate-500"
          >
            {{ PROPOSAL_EMPTY.attachments }}
          </p>

          <template v-else>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2">
              <li
                v-for="file in attachments"
                :key="file.id"
                class="cn-proposal-sheet__file-card"
                :class="{ 'cn-proposal-sheet__file-card--active': previewId === file.id }"
              >
                <button
                  v-if="canPreviewInline(file)"
                  type="button"
                  class="cn-proposal-sheet__file-thumb"
                  :aria-pressed="previewId === file.id"
                  :aria-label="`Xem trước ${file.original_name}`"
                  @click="selectPreview(file)"
                >
                  <img
                    v-if="file.is_image"
                    :src="file.url"
                    :alt="file.original_name"
                    class="h-full w-full object-cover"
                    loading="lazy"
                  >
                  <div
                    v-else-if="isPdf(file)"
                    class="flex h-full flex-col items-center justify-center gap-2 bg-slate-100 text-slate-600"
                  >
                    <AppIcon
                      name="pdf"
                      :size="28"
                    />
                    <span class="text-[10px] font-semibold uppercase tracking-wide">PDF</span>
                  </div>
                </button>
                <div
                  v-else
                  class="cn-proposal-sheet__file-thumb cn-proposal-sheet__file-thumb--static"
                >
                  <div class="flex h-full flex-col items-center justify-center gap-2 bg-slate-100 text-slate-500">
                    <AppIcon
                      :name="fileIcon(file)"
                      :size="28"
                    />
                    <span class="px-2 text-center text-[10px] font-medium leading-tight">Không xem trước</span>
                  </div>
                </div>

                <div class="min-w-0 flex-1 p-3">
                  <p class="truncate text-sm font-medium text-slate-900">
                    {{ file.original_name }}
                  </p>
                  <p class="mt-0.5 text-xs text-slate-500">
                    {{ formatSize(file.size) }}
                  </p>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <button
                      v-if="canPreviewInline(file)"
                      type="button"
                      class="text-xs font-semibold text-brand hover:underline"
                      @click="selectPreview(file)"
                    >
                      {{ previewId === file.id ? 'Ẩn xem trước' : 'Xem trước' }}
                    </button>
                    <a
                      v-if="file.url"
                      :href="file.url"
                      class="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-brand"
                      target="_blank"
                      rel="noopener"
                    >
                      <AppIcon
                        name="download"
                        :size="13"
                      />
                      Tải xuống
                    </a>
                    <span
                      v-else
                      class="text-xs text-rose-600"
                    >File không còn</span>
                  </div>
                </div>
              </li>
            </ul>

            <div
              v-if="activePreview?.url"
              class="cn-proposal-sheet__preview mt-5 overflow-hidden rounded-xl border border-slate-200 bg-slate-50"
            >
              <div class="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2.5">
                <p class="min-w-0 truncate text-sm font-medium text-slate-800">
                  {{ activePreview.original_name }}
                </p>
                <button
                  type="button"
                  class="shrink-0 text-xs font-semibold text-slate-500 hover:text-brand"
                  @click="previewId = null"
                >
                  Đóng xem trước
                </button>
              </div>
              <div class="max-h-[min(70vh,640px)] overflow-auto p-3 sm:p-4">
                <img
                  v-if="previewKind(activePreview) === 'image'"
                  :src="activePreview.url"
                  :alt="activePreview.original_name"
                  class="mx-auto max-h-[min(65vh,600px)] w-auto max-w-full rounded-lg border border-slate-200 bg-white shadow-sm"
                >
                <iframe
                  v-else-if="previewKind(activePreview) === 'pdf'"
                  :src="activePreview.url"
                  :title="`Xem trước ${activePreview.original_name}`"
                  class="h-[min(65vh,560px)] w-full rounded-lg border border-slate-200 bg-white"
                />
              </div>
            </div>
          </template>
        </section>
      </div>

      <footer class="border-t border-dashed border-slate-200 bg-slate-50/60 px-6 py-4 text-center text-[11px] text-slate-500 sm:px-8">
        Phiếu được lưu trên hệ thống quản lý dự án VAschools · Mã tham chiếu
        <span class="font-mono font-semibold text-slate-700">{{ proposal.reference_code || '—' }}</span>
      </footer>
    </div>
  </article>
</template>

<style scoped>
.cn-proposal-sheet__section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.8125rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgb(15 23 42);
}

.cn-proposal-sheet__section-num {
    display: inline-flex;
    height: 1.75rem;
    min-width: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    background: rgb(154 0 54 / 0.08);
    font-family: ui-monospace, monospace;
    font-size: 0.6875rem;
    font-weight: 700;
    color: rgb(154 0 54);
}

.cn-proposal-sheet__field-grid {
    display: grid;
    gap: 1rem 1.5rem;
    margin-top: 1rem;
}

@media (min-width: 640px) {
    .cn-proposal-sheet__field-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.cn-proposal-sheet__field dt {
    font-size: 0.6875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: rgb(100 116 139);
}

.cn-proposal-sheet__field dd {
    margin-top: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: rgb(30 41 59);
}

.cn-proposal-sheet__content-box {
    margin-top: 1rem;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240);
    background: rgb(248 250 252 / 0.8);
    padding: 1.25rem 1.5rem;
    box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.8);
}

.cn-proposal-sheet__file-card {
    display: flex;
    gap: 0;
    overflow: hidden;
    border-radius: 0.75rem;
    border: 1px solid rgb(226 232 240);
    background: white;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.cn-proposal-sheet__file-card--active {
    border-color: rgb(154 0 54 / 0.35);
    box-shadow: 0 0 0 1px rgb(154 0 54 / 0.12);
}

.cn-proposal-sheet__file-thumb {
    height: 5.5rem;
    width: 5.5rem;
    flex-shrink: 0;
    overflow: hidden;
    border-right: 1px solid rgb(241 245 249);
    cursor: pointer;
    padding: 0;
    background: rgb(248 250 252);
}

.cn-proposal-sheet__file-thumb--static {
    cursor: default;
}

.cn-proposal-sheet__file-thumb:focus-visible {
    outline: 2px solid rgb(154 0 54);
    outline-offset: -2px;
}

.cn-proposal-sheet__paper {
    border: 1px solid rgb(226 232 240 / 0.9);
}
</style>
