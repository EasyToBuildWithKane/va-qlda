<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import {
    acknowledgementStatus,
    attachmentCountText,
    departmentText,
    emailPcnStatus,
    PROPOSAL_EMPTY,
    referenceCodeLabel,
    submitterEmailText,
    submitterNameText,
    submittedAtText,
} from './congngheProposalDisplay.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    proposal: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const STATUS_TONE = {
    new: 'violet',
    triaged: 'sky',
    in_progress: 'amber',
    done: 'emerald',
    rejected: 'slate',
};

const statusValue = computed(() => props.proposal?.status?.value ?? props.proposal?.status ?? '');

const statusLabel = computed(() => props.proposal?.status?.label ?? PROPOSAL_EMPTY.status);

const attachments = computed(() => props.proposal?.attachments ?? []);

const ack = computed(() => acknowledgementStatus(props.proposal));

const emailPcn = computed(() => emailPcnStatus(props.proposal));

const referenceLabel = computed(() => referenceCodeLabel(props.proposal?.reference_code));

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

function onKey(e) {
    if (e.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.show,
    (isOpen) => {
        if (typeof document === 'undefined') {
            return;
        }
        document.body.style.overflow = isOpen ? 'hidden' : '';
        if (typeof window === 'undefined') {
            return;
        }
        if (isOpen) {
            window.addEventListener('keydown', onKey);
        } else {
            window.removeEventListener('keydown', onKey);
        }
    },
);

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', onKey);
    }
});
</script>

<template>
  <Teleport to="body">
    <Transition name="cn-modal">
      <div
        v-if="show && proposal"
        class="fixed inset-0 z-[120] flex items-end justify-center p-0 sm:items-center sm:p-4 lg:p-6"
      >
        <div
          class="absolute inset-0 bg-[#05060c]/85 backdrop-blur-md"
          aria-hidden="true"
          @click="emit('close')"
        />

        <div
          class="cn-modal-panel relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-t-3xl border border-white/12 bg-[#0d0e16]/98 shadow-[0_40px_120px_-30px_rgba(0,0,0,0.9)] sm:rounded-3xl"
          role="dialog"
          aria-modal="true"
          :aria-label="`Chi tiết đề xuất ${proposal.title || PROPOSAL_EMPTY.title}`"
        >
          <div class="pointer-events-none absolute inset-x-0 top-0 h-40 bg-[radial-gradient(80%_120%_at_50%_-20%,rgba(255,77,141,0.35),transparent_70%)]" />

          <header class="relative shrink-0 border-b border-white/10 px-5 py-5 sm:px-8">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.12em] text-cyan-200/65">
                  {{ referenceLabel }}
                </p>
                <h2 class="mt-2 font-display text-xl font-bold leading-snug text-white sm:text-2xl">
                  {{ proposal.title?.trim() || PROPOSAL_EMPTY.title }}
                </h2>
                <p class="mt-2 text-sm text-white/50">
                  Gửi lúc {{ submittedAtText(proposal.created_at) }}
                </p>
              </div>
              <button
                type="button"
                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-white/10 bg-white/5 text-white/60 transition hover:bg-white/10 hover:text-white"
                aria-label="Đóng"
                @click="emit('close')"
              >
                <AppIcon
                  name="close"
                  :size="18"
                />
              </button>
            </div>
          </header>

          <div class="relative min-h-0 flex-1 overflow-y-auto px-5 py-6 sm:px-8">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_min(100%,20rem)] lg:gap-8">
              <div class="min-w-0 space-y-6">
                <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:p-6">
                  <h3 class="text-sm font-semibold text-white">
                    Nội dung đề xuất
                  </h3>
                  <p class="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-white/80">
                    {{ proposal.content?.trim() || PROPOSAL_EMPTY.content }}
                  </p>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 sm:p-6">
                  <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-white">
                      File đính kèm
                    </h3>
                    <span class="text-xs text-white/45">{{ attachmentCountText(attachments.length) }}</span>
                  </div>
                  <ul
                    v-if="attachments.length"
                    class="mt-4 space-y-2"
                  >
                    <li
                      v-for="file in attachments"
                      :key="file.id"
                      class="flex flex-wrap items-center gap-3 rounded-xl border border-white/10 bg-[#0a0c16]/60 px-4 py-3 text-sm"
                    >
                      <AppIcon
                        name="documents"
                        :size="16"
                        class="shrink-0 text-cyan-300/80"
                      />
                      <span class="min-w-0 flex-1 truncate font-medium text-white/90">{{ file.original_name }}</span>
                      <span class="shrink-0 text-xs text-white/45">{{ formatSize(file.size) }}</span>
                      <a
                        v-if="file.url"
                        :href="file.url"
                        class="inline-flex shrink-0 items-center gap-1 rounded-lg border border-cyan-400/30 bg-cyan-500/10 px-2.5 py-1 text-xs font-semibold text-cyan-200 transition hover:bg-cyan-500/20"
                        target="_blank"
                        rel="noopener"
                      >
                        <AppIcon
                          name="download"
                          :size="14"
                        />
                        Tải xuống
                      </a>
                      <span
                        v-else
                        class="shrink-0 text-xs text-rose-300"
                      >File không còn trên hệ thống</span>
                    </li>
                  </ul>
                  <p
                    v-else
                    class="mt-4 text-sm text-white/45"
                  >
                    {{ PROPOSAL_EMPTY.attachments }}
                  </p>
                </section>
              </div>

              <aside class="space-y-4">
                <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                  <h3 class="text-xs font-semibold uppercase tracking-wide text-white/45">
                    Người gửi
                  </h3>
                  <p class="mt-2 font-medium text-white">
                    {{ submitterNameText(proposal.submitter_name) }}
                  </p>
                  <p class="mt-1 text-sm text-white/55">
                    {{ submitterEmailText(proposal.submitter_email) }}
                  </p>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                  <h3 class="text-xs font-semibold uppercase tracking-wide text-white/45">
                    Phòng ban
                  </h3>
                  <p class="mt-2 text-sm text-white/85">
                    {{ departmentText(proposal.department) }}
                  </p>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                  <h3 class="text-xs font-semibold uppercase tracking-wide text-white/45">
                    Trạng thái xử lý
                  </h3>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <Badge
                      :tone="STATUS_TONE[statusValue] ?? 'slate'"
                      size="sm"
                    >
                      {{ statusLabel }}
                    </Badge>
                    <Badge
                      :tone="ack.tone"
                      size="sm"
                    >
                      {{ ack.label }}
                    </Badge>
                  </div>
                  <p class="mt-2 text-[11px] leading-snug text-white/45">
                    {{ ack.detail }}
                  </p>
                </section>

                <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
                  <h3 class="text-xs font-semibold uppercase tracking-wide text-white/45">
                    Email tới Phòng Công nghệ
                  </h3>
                  <div class="mt-3">
                    <Badge
                      :tone="emailPcn.tone"
                      size="sm"
                    >
                      {{ emailPcn.label }}
                    </Badge>
                  </div>
                  <p class="mt-2 text-sm text-white/70">
                    {{ emailPcn.detail }}
                  </p>
                </section>
              </aside>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cn-modal-enter-active,
.cn-modal-leave-active {
    transition: opacity 0.25s ease;
}

.cn-modal-enter-from,
.cn-modal-leave-to {
    opacity: 0;
}

.cn-modal-enter-active .cn-modal-panel,
.cn-modal-leave-active .cn-modal-panel {
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.25s ease;
}

.cn-modal-enter-from .cn-modal-panel,
.cn-modal-leave-to .cn-modal-panel {
    transform: translateY(1.25rem) scale(0.98);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .cn-modal-enter-active,
    .cn-modal-leave-active,
    .cn-modal-enter-active .cn-modal-panel,
    .cn-modal-leave-active .cn-modal-panel {
        transition: none;
    }
}
</style>
