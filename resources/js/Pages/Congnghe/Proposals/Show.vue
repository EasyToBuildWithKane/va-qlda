<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import { datetime } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    proposal: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const page = usePage();
const toast = useToast();

const form = useForm({
    status: props.proposal.status?.value ?? 'new',
});

const attachments = computed(() => props.proposal.attachments ?? []);

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

function saveStatus() {
    form.put(route('congnghe.proposals.update', props.proposal.id), {
        preserveScroll: true,
        onSuccess: () => toast.success(page.props.flash?.success ?? 'Đã lưu.'),
    });
}
</script>

<template>
  <Head :title="proposal.reference_code ?? 'Đề xuất PM'" />

  <AppLayout>
    <template #header>
      <PageHeader
        :title="proposal.title"
        :subtitle="proposal.reference_code ? `Mã ${proposal.reference_code}` : 'Chi tiết đề xuất'"
        icon="rocket"
        icon-color="brand"
        :back-href="route('congnghe.proposals.index')"
      />
    </template>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
      <div class="card p-5 sm:p-6">
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Người gửi
            </dt>
            <dd class="mt-1 font-medium text-slate-900">
              {{ proposal.submitter_name }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Email
            </dt>
            <dd class="mt-1">
              <a
                :href="`mailto:${proposal.submitter_email}`"
                class="text-brand hover:underline"
              >{{ proposal.submitter_email }}</a>
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Phòng ban
            </dt>
            <dd class="mt-1 text-slate-800">
              {{ proposal.department }}
            </dd>
          </div>
          <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
              Thời điểm gửi
            </dt>
            <dd class="mt-1 text-slate-800 tabular-nums">
              {{ datetime(proposal.created_at) }}
            </dd>
          </div>
        </dl>

        <div class="mt-6 border-t border-slate-100 pt-6">
          <h2 class="text-sm font-semibold text-slate-900">
            Nội dung đề xuất
          </h2>
          <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap">
            {{ proposal.content }}
          </div>
        </div>

        <div
          v-if="attachments.length"
          class="mt-6 border-t border-slate-100 pt-6"
        >
          <h2 class="text-sm font-semibold text-slate-900">
            Tệp đính kèm
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
              >File không còn</span>
            </li>
          </ul>
        </div>
      </div>

      <aside class="space-y-4">
        <div class="card p-5">
          <h2 class="text-sm font-semibold text-slate-900">
            Trạng thái xử lý
          </h2>
          <p class="mt-1 text-xs text-slate-500">
            Hiện tại:
            <Badge
              tone="sky"
              size="sm"
              class="ml-1"
            >
              {{ proposal.status?.label }}
            </Badge>
          </p>
          <select
            v-if="proposal.can?.update"
            v-model="form.status"
            class="input mt-3 h-10 w-full text-sm"
          >
            <option
              v-for="opt in options.statuses"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
          <button
            v-if="proposal.can?.update"
            type="button"
            class="btn-primary mt-3 h-10 w-full text-sm font-semibold"
            :disabled="form.processing"
            @click="saveStatus"
          >
            {{ form.processing ? 'Đang lưu…' : 'Cập nhật trạng thái' }}
          </button>
        </div>

        <div class="card p-5 text-sm">
          <h2 class="font-semibold text-slate-900">
            Email hệ thống
          </h2>
          <div class="mt-3 space-y-2 text-slate-600">
            <p>
              Gửi tới phòng:
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
            <p
              v-if="proposal.email_error"
              class="text-xs text-rose-600"
            >
              {{ proposal.email_error }}
            </p>
          </div>
        </div>

        <Link
          href="/congnghe/de-xuat"
          class="block text-center text-sm text-slate-500 hover:text-brand"
        >
          Mở form đề xuất công khai →
        </Link>
      </aside>
    </div>
  </AppLayout>
</template>
