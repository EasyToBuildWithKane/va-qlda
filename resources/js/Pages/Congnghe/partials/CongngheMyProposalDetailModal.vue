<script setup>
import { computed, onBeforeUnmount, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { datetime } from '@/composables/useFormat';

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

const attachments = computed(() => props.proposal?.attachments ?? []);

const acknowledged = computed(() => statusValue.value !== 'new');

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
        class="fixed inset-0 z-[120] flex items-end justify-center p-0 sm:items-center sm:p-4"
      >
        <div
          class="absolute inset-0 bg-[#05060c]/85 backdrop-blur-md"
          aria-hidden="true"
          @click="emit('close')"
        />

        <div
          class="cn-modal-panel relative flex max-h-[92vh] w-full max-w-2xl flex-col overflow-hidden rounded-t-3xl border border-white/12 bg-[#0d0e16]/98 shadow-[0_40px_120px_-30px_rgba(0,0,0,0.9)] sm:rounded-3xl"
          role="dialog"
          aria-modal="true"
          :aria-label="`Chi tiết đề xuất ${proposal.title}`"
        >
          <div class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-[radial-gradient(80%_120%_at_50%_-20%,rgba(255,77,141,0.35),transparent_70%)]" />

          <header class="relative shrink-0 border-b border-white/10 px-5 py-4 sm:px-6">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p
                  v-if="proposal.reference_code"
                  class="font-mono text-[11px] font-semibold uppercase tracking-wide text-cyan-200/60"
                >
                  {{ proposal.reference_code }}
                </p>
                <h2 class="mt-1 font-display text-lg font-bold leading-snug text-white sm:text-xl">
                  {{ proposal.title }}
                </h2>
              </div>
              <button
                type="button"
                class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-white/10 bg-white/5 text-white/60 transition hover:bg-white/10 hover:text-white"
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

          <div class="relative min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
            <dl class="grid gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/45">
                  Người gửi
                </dt>
                <dd class="mt-1 font-medium text-white">
                  {{ proposal.submitter_name || '—' }}
                </dd>
                <dd class="mt-0.5 text-sm text-white/55">
                  {{ proposal.submitter_email || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/45">
                  Phòng ban
                </dt>
                <dd class="mt-1 text-white">
                  {{ proposal.department || '—' }}
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/45">
                  Thời điểm gửi
                </dt>
                <dd class="mt-1 tabular-nums text-white">
                  {{ datetime(proposal.created_at) }}
                </dd>
              </div>
              <div>
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/45">
                  Trạng thái
                </dt>
                <dd class="mt-1 flex flex-wrap gap-2">
                  <Badge
                    :tone="STATUS_TONE[statusValue] ?? 'slate'"
                    size="sm"
                  >
                    {{ proposal.status?.label }}
                  </Badge>
                  <Badge
                    :tone="acknowledged ? 'emerald' : 'amber'"
                    size="sm"
                  >
                    {{ acknowledged ? 'Đã ghi nhận' : 'Chưa ghi nhận' }}
                  </Badge>
                </dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/45">
                  Email tới Phòng CN
                </dt>
                <dd class="mt-1">
                  <Badge
                    v-if="proposal.email_sent_at"
                    tone="emerald"
                    size="sm"
                  >
                    Đã gửi {{ datetime(proposal.email_sent_at) }}
                  </Badge>
                  <Badge
                    v-else
                    tone="amber"
                    size="sm"
                  >
                    Chưa gửi
                  </Badge>
                </dd>
              </div>
            </dl>

            <div class="mt-6 border-t border-white/10 pt-6">
              <h3 class="text-sm font-semibold text-white">
                Nội dung đề xuất
              </h3>
              <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-white/75">
                {{ proposal.content }}
              </p>
            </div>

            <div class="mt-6 border-t border-white/10 pt-6">
              <h3 class="text-sm font-semibold text-white">
                File đính kèm
                <span class="ml-1 font-normal text-white/45">({{ attachments.length }})</span>
              </h3>
              <ul
                v-if="attachments.length"
                class="mt-3 space-y-2"
              >
                <li
                  v-for="file in attachments"
                  :key="file.id"
                  class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-sm"
                >
                  <span class="min-w-0 flex-1 truncate font-medium text-white/90">{{ file.original_name }}</span>
                  <span class="shrink-0 text-xs text-white/45">{{ formatSize(file.size) }}</span>
                  <a
                    v-if="file.url"
                    :href="file.url"
                    class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-cyan-300 hover:underline"
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
                class="mt-3 text-sm text-white/45"
              >
                Không có file đính kèm.
              </p>
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
