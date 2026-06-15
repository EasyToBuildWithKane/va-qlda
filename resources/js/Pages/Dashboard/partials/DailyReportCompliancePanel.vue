<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';

const props = defineProps({
    compliance: { type: Object, default: () => ({}) },
});

const people = computed(() => props.compliance.people ?? []);
const summary = computed(() => props.compliance.summary ?? {});
const period = computed(() => props.compliance.period ?? {});

const teamRate = computed(() => summary.value.teamRate ?? 0);
</script>

<template>
  <section class="card p-5">
    <header class="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="flex items-center gap-2 font-display font-semibold text-slate-800">
          <AppIcon
            name="daily"
            :size="16"
            class="text-brand"
          />
          Tuân thủ báo cáo ngày
        </h3>
        <p class="mt-1 text-xs text-slate-500">
          Tuần {{ period.label }} — đã nộp (gửi/duyệt) / ngày làm việc kỳ vọng
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-lg bg-brand/10 px-3 py-1.5 text-xs font-semibold text-brand">
          {{ summary.completeCount ?? 0 }}/{{ summary.totalPeople ?? 0 }} nhân sự đủ
        </span>
        <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">
          Tỉ lệ chung {{ teamRate }}%
        </span>
        <Link
          href="/daily-reports"
          class="text-xs font-medium text-brand hover:underline"
        >
          Lịch sử báo cáo →
        </Link>
      </div>
    </header>

    <div
      v-if="people.length"
      class="overflow-x-auto"
    >
      <table class="w-full min-w-[520px] text-left text-sm">
        <thead>
          <tr class="border-b border-slate-100 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            <th class="pb-2 pr-3 font-semibold">
              Nhân sự
            </th>
            <th class="pb-2 pr-3 font-semibold">
              Đã nộp
            </th>
            <th class="pb-2 pr-3 font-semibold">
              Tỉ lệ
            </th>
            <th class="pb-2 font-semibold">
              Trạng thái
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr
            v-for="row in people"
            :key="row.id"
            class="group"
          >
            <td class="py-2.5 pr-3">
              <div class="flex items-center gap-2.5">
                <Avatar
                  :name="row.name"
                  :src="row.avatar"
                  :size="28"
                />
                <span class="font-medium text-slate-800">{{ row.name }}</span>
              </div>
            </td>
            <td class="py-2.5 pr-3 tabular-nums text-slate-700">
              <span class="font-semibold">{{ row.submitted }}</span>
              <span class="text-slate-400">/{{ row.expected }}</span>
            </td>
            <td class="py-2.5 pr-3">
              <div class="flex min-w-[8rem] items-center gap-2">
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100">
                  <div
                    class="h-full rounded-full transition-all"
                    :class="row.ok ? 'bg-emerald-500' : 'bg-amber-500'"
                    :style="{ width: Math.min(100, row.rate) + '%' }"
                  />
                </div>
                <span class="w-10 shrink-0 text-right text-xs font-semibold tabular-nums text-slate-600">
                  {{ row.rate }}%
                </span>
              </div>
            </td>
            <td class="py-2.5">
              <span
                v-if="row.ok"
                class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700"
              >
                Đủ
              </span>
              <span
                v-else
                class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
              >
                Thiếu {{ row.missing }} ngày
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <EmptyState
      v-else
      icon="people"
      title="Chưa có nhân sự hoạt động"
      description="Thống kê tuân thủ sẽ hiển thị khi có nhân sự đang hoạt động."
    />
  </section>
</template>
