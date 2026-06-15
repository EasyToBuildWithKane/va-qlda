<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuroraBackground from './partials/AuroraBackground.vue';
import CongngheNavbar from './partials/CongngheNavbar.vue';
import CongngheFooter from './partials/CongngheFooter.vue';
import Badge from '@/shared/ui/Badge.vue';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    proposals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const STATUS_TONE = {
    new: 'violet',
    triaged: 'sky',
    in_progress: 'amber',
    done: 'emerald',
    rejected: 'slate',
};

const items = props.proposals.data ?? [];

function isAcknowledged(proposal) {
    const status = proposal.status?.value ?? proposal.status;
    return status !== 'new';
}

function emailSent(proposal) {
    return Boolean(proposal.email_sent_at);
}
</script>

<template>
  <Head title="Đề xuất của tôi" />

  <div class="relative min-h-screen bg-[#06070f] text-white">
    <AuroraBackground />
    <CongngheNavbar />

    <main class="relative z-10 px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:pb-24">
      <div class="mx-auto max-w-4xl">
        <header class="mb-8 text-center sm:mb-10">
          <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-200/55">
            Phòng Công Nghệ · VAS
          </p>
          <h1 class="mt-2 font-display text-2xl font-bold text-white sm:text-3xl">
            Đề xuất đã gửi
          </h1>
          <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-white/60">
            Theo dõi trạng thái xử lý, tiếp nhận từ Phòng Công Nghệ và tình trạng gửi email.
          </p>
        </header>

        <div
          v-if="items.length === 0"
          class="rounded-2xl border border-white/10 bg-[#0a0c16]/90 p-8 text-center backdrop-blur-xl"
        >
          <p class="text-white/70">
            Bạn chưa gửi đề xuất nào.
          </p>
          <Link
            :href="route('congnghe.proposal')"
            class="mt-4 inline-flex h-10 items-center rounded-full border border-brand/45 bg-brand/25 px-5 text-sm font-semibold text-white transition hover:bg-brand/35"
          >
            Gửi đề xuất mới
          </Link>
        </div>

        <ul
          v-else
          class="space-y-3"
        >
          <li
            v-for="proposal in items"
            :key="proposal.id"
          >
            <Link
              :href="route('congnghe.proposal.mine.show', proposal.id)"
              class="block rounded-2xl border border-white/10 bg-[#0a0c16]/90 p-4 transition hover:border-brand/35 hover:bg-[#0c0e18] sm:p-5"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                  <p class="font-mono text-[10px] uppercase tracking-wide text-cyan-200/45">
                    {{ proposal.reference_code ?? '—' }}
                  </p>
                  <h2 class="mt-1 font-display text-base font-semibold text-white sm:text-lg">
                    {{ proposal.title }}
                  </h2>
                  <p class="mt-1 text-xs text-white/45 tabular-nums">
                    Gửi {{ datetime(proposal.created_at) }}
                  </p>
                </div>
                <Badge
                  :tone="STATUS_TONE[proposal.status?.value] ?? 'slate'"
                  class="shrink-0"
                >
                  {{ proposal.status?.label ?? proposal.status }}
                </Badge>
              </div>

              <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-white/8 bg-white/[0.03] px-3 py-2">
                  <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/40">
                    Tiếp nhận PCN
                  </dt>
                  <dd class="mt-0.5 text-sm font-medium">
                    <span :class="isAcknowledged(proposal) ? 'text-emerald-300' : 'text-amber-300'">
                      {{ isAcknowledged(proposal) ? 'Đã ghi nhận' : 'Chưa ghi nhận' }}
                    </span>
                  </dd>
                </div>
                <div class="rounded-xl border border-white/8 bg-white/[0.03] px-3 py-2">
                  <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/40">
                    Email tới PCN
                  </dt>
                  <dd class="mt-0.5 text-sm font-medium">
                    <span :class="emailSent(proposal) ? 'text-emerald-300' : 'text-amber-300'">
                      {{ emailSent(proposal) ? 'Đã gửi' : 'Chưa gửi' }}
                    </span>
                  </dd>
                </div>
                <div class="rounded-xl border border-white/8 bg-white/[0.03] px-3 py-2">
                  <dt class="text-[10px] font-semibold uppercase tracking-wide text-white/40">
                    File đính kèm
                  </dt>
                  <dd class="mt-0.5 text-sm font-medium text-white/80 tabular-nums">
                    {{ proposal.attachments_count ?? 0 }}
                  </dd>
                </div>
              </dl>
            </Link>
          </li>
        </ul>

        <div
          v-if="proposals.meta && proposals.meta.last_page > 1"
          class="mt-6 flex justify-center gap-2"
        >
          <Link
            v-if="proposals.links?.prev"
            :href="proposals.links.prev"
            class="rounded-lg border border-white/10 px-4 py-2 text-sm text-white/80 hover:bg-white/5"
            preserve-scroll
          >
            Trước
          </Link>
          <span class="px-2 py-2 text-sm text-white/45 tabular-nums">
            {{ proposals.meta.current_page }} / {{ proposals.meta.last_page }}
          </span>
          <Link
            v-if="proposals.links?.next"
            :href="proposals.links.next"
            class="rounded-lg border border-white/10 px-4 py-2 text-sm text-white/80 hover:bg-white/5"
            preserve-scroll
          >
            Sau
          </Link>
        </div>
      </div>
    </main>

    <CongngheFooter />
  </div>
</template>
