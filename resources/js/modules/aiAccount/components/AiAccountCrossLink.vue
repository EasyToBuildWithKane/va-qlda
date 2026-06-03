<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    /** 'to-proposals' | 'to-accounts' */
    direction: { type: String, required: true },
    pendingCount: { type: Number, default: 0 },
    accountCount: { type: Number, default: 0 },
});
</script>

<template>
  <div
    class="mb-4 flex flex-col gap-3 rounded-xl border border-slate-200 bg-gradient-to-r from-slate-50 to-white px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="min-w-0">
      <template v-if="direction === 'to-proposals'">
        <p class="text-sm font-semibold text-slate-800">
          Phiếu đề xuất mua AI (PDX)
        </p>
        <p class="mt-0.5 text-xs text-slate-500">
          Gửi phiếu → Ban duyệt → sau khi duyệt, ghi nhận tài khoản tại tab «Tài khoản AI».
          <span
            v-if="pendingCount > 0"
            class="font-medium text-amber-700"
          >
            {{ pendingCount }} phiếu đang chờ duyệt.
          </span>
        </p>
      </template>
      <template v-else>
        <p class="text-sm font-semibold text-slate-800">
          Tài khoản AI đang vận hành
        </p>
        <p class="mt-0.5 text-xs text-slate-500">
          Danh sách license/tool đã được duyệt và đang theo dõi chi phí · hạn dùng.
          <span
            v-if="accountCount > 0"
            class="font-medium text-slate-700"
          >
            {{ accountCount }} tài khoản.
          </span>
        </p>
      </template>
    </div>
    <Link
      v-if="direction === 'to-proposals'"
      :href="route('ai-accounts.cost-report')"
      class="btn-secondary inline-flex shrink-0 items-center gap-1.5 text-sm"
      preserve-scroll
    >
      <AppIcon
        name="performance"
        :size="15"
      />
      Mở phiếu đề xuất
      <AppIcon
        name="chevron-right"
        :size="14"
        class="opacity-60"
      />
    </Link>
    <Link
      v-else
      :href="route('ai-accounts.index')"
      class="btn-secondary inline-flex shrink-0 items-center gap-1.5 text-sm"
      preserve-scroll
    >
      <AppIcon
        name="account"
        :size="15"
      />
      Xem tài khoản AI
      <AppIcon
        name="chevron-right"
        :size="14"
        class="opacity-60"
      />
    </Link>
  </div>
</template>
