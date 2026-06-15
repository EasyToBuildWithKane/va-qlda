<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
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

  <AppLayout>
    <template #header>
      <PageHeader
        :title="proposal.title"
        :subtitle="proposal.reference_code ? `Mã ${proposal.reference_code}` : 'Chi tiết đề xuất'"
        icon="template"
        icon-color="brand"
        :back-href="route('congnghe.proposal.mine')"
      />
    </template>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
      <div class="card p-5 sm:p-6">
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Thời điểm gửi
            </dt>
            <dd class="mt-1 text-slate-900 tabular-nums">
              {{ datetime(proposal.created_at) }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Phòng ban
            </dt>
            <dd class="mt-1 text-slate-900">
              {{ proposal.department }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Trạng thái
            </dt>
            <dd class="mt-1">
              <Badge
                :tone="STATUS_TONE[statusValue] ?? 'slate'"
                size="sm"
              >
                {{ proposal.status?.label }}
              </Badge>
            </dd>
          </div>
        </dl>

        <div class="mt-6 border-t border-slate-100 pt-6">
          <h2 class="text-sm font-semibold text-slate-900">
            Nội dung đề xuất
          </h2>
          <p class="mt-3 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">
            {{ proposal.content }}
          </p>
        </div>

        <div
          v-if="attachments.length"
          class="mt-6 border-t border-slate-100 pt-6"
        >
          <h2 class="text-sm font-semibold text-slate-900">
            File đính kèm
          </h2>
          <ul class="mt-3 space-y-2">
            <li
              v-for="file in attachments"
              :key="file.id"
              class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm"
            >
              <span class="min-w-0 truncate font-medium text-slate-800">{{ file.original_name }}</span>
              <span class="shrink-0 text-xs text-slate-500">{{ formatSize(file.size) }}</span>
              <a
                v-if="file.url"
                :href="file.url"
                class="shrink-0 text-xs font-semibold text-brand hover:underline"
                target="_blank"
                rel="noopener"
              >
                Tải xuống
              </a>
              <span
                v-else
                class="shrink-0 text-xs text-rose-600"
              >File không còn trên hệ thống</span>
            </li>
          </ul>
        </div>
        <p
          v-else
          class="mt-6 border-t border-slate-100 pt-6 text-sm text-slate-500"
        >
          Không có file đính kèm.
        </p>
      </div>

      <aside class="space-y-4">
        <div class="card p-5 text-sm">
          <h2 class="font-semibold text-slate-900">
            Tiếp nhận Phòng CN
          </h2>
          <p class="mt-3">
            <Badge
              :tone="acknowledged ? 'emerald' : 'amber'"
              size="sm"
            >
              {{ acknowledged ? 'Đã ghi nhận' : 'Chưa ghi nhận' }}
            </Badge>
          </p>
          <p class="mt-2 text-xs text-slate-500">
            Trạng thái khác «Mới» nghĩa là Phòng Công nghệ đã cập nhật tiến độ.
          </p>
        </div>

        <div class="card p-5 text-sm">
          <h2 class="font-semibold text-slate-900">
            Email hệ thống
          </h2>
          <p class="mt-3">
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
          </p>
        </div>

        <Link
          :href="route('congnghe.proposal')"
          class="block text-center text-sm text-slate-500 hover:text-brand"
        >
          Gửi đề xuất mới →
        </Link>
      </aside>
    </div>
  </AppLayout>
</template>
