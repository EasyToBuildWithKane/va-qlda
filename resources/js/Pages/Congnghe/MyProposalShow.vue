<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuroraBackground from './partials/AuroraBackground.vue';
import CongngheNavbar from './partials/CongngheNavbar.vue';
import CongngheFooter from './partials/CongngheFooter.vue';
import Badge from '@/shared/ui/Badge.vue';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    proposal: { type: Object, required: true },
});

const STATUS_TONE = {
    new: 'violet',
    triaged: 'sky',
    in_progress: 'amber',
    done: 'emerald',
    rejected: 'slate',
};

const attachments = computed(() => props.proposal.attachments ?? []);

const statusValue = computed(() => props.proposal.status?.value ?? props.proposal.status);

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
</script>

<template>
  <Head :title="proposal.reference_code ?? 'Chi tiết đề xuất'" />

  <div class="relative min-h-screen bg-[#06070f] text-white">
    <AuroraBackground />
    <CongngheNavbar />

    <main class="relative z-10 px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:pb-24">
      <div class="mx-auto max-w-3xl">
        <Link
          :href="route('congnghe.proposal.mine')"
          class="mb-6 inline-flex items-center gap-1.5 text-sm text-white/55 transition hover:text-white"
        >
          ← Danh sách đề xuất
        </Link>

        <div class="rounded-2xl border border-white/10 bg-[#0a0c16]/90 p-5 backdrop-blur-xl sm:p-8">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="font-mono text-[11px] uppercase tracking-wide text-cyan-200/50">
                {{ proposal.reference_code ?? '—' }}
              </p>
              <h1 class="mt-1 font-display text-xl font-bold text-white sm:text-2xl">
                {{ proposal.title }}
              </h1>
            </div>
            <Badge
              :tone="STATUS_TONE[statusValue] ?? 'slate'"
              class="shrink-0"
            >
              {{ proposal.status?.label }}
            </Badge>
          </div>

          <dl class="mt-6 grid gap-4 border-t border-white/10 pt-6 sm:grid-cols-2">
            <div>
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/40">
                Thời điểm gửi
              </dt>
              <dd class="mt-1 text-sm text-white/85 tabular-nums">
                {{ datetime(proposal.created_at) }}
              </dd>
            </div>
            <div>
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/40">
                Phòng ban
              </dt>
              <dd class="mt-1 text-sm text-white/85">
                {{ proposal.department }}
              </dd>
            </div>
            <div>
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/40">
                Tiếp nhận từ Phòng CN
              </dt>
              <dd class="mt-1 text-sm font-medium">
                <span :class="acknowledged ? 'text-emerald-300' : 'text-amber-300'">
                  {{ acknowledged ? 'Đã ghi nhận' : 'Chưa ghi nhận' }}
                </span>
              </dd>
            </div>
            <div>
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/40">
                Email tới Phòng CN
              </dt>
              <dd class="mt-1 text-sm font-medium">
                <span :class="proposal.email_sent_at ? 'text-emerald-300' : 'text-amber-300'">
                  {{ proposal.email_sent_at ? 'Đã gửi' : 'Chưa gửi' }}
                </span>
              </dd>
            </div>
          </dl>

          <div class="mt-6 border-t border-white/10 pt-6">
            <h2 class="text-sm font-semibold text-white">
              Nội dung đề xuất
            </h2>
            <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-white/75">
              {{ proposal.content }}
            </p>
          </div>

          <div
            v-if="attachments.length"
            class="mt-6 border-t border-white/10 pt-6"
          >
            <h2 class="text-sm font-semibold text-white">
              File đính kèm
            </h2>
            <ul class="mt-3 space-y-2">
              <li
                v-for="file in attachments"
                :key="file.id"
              >
                <a
                  v-if="file.url"
                  :href="file.url"
                  class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/[0.03] px-3 py-2.5 text-sm text-cyan-200/90 transition hover:border-cyan-500/30 hover:bg-white/[0.05]"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <span class="truncate">{{ file.original_name }}</span>
                  <span class="shrink-0 text-xs text-white/40 tabular-nums">{{ formatSize(file.size) }}</span>
                </a>
                <span
                  v-else
                  class="text-sm text-white/40"
                >{{ file.original_name }} (không tải được)</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </main>

    <CongngheFooter />
  </div>
</template>
