<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    completedTours: { type: Number, default: 0 },
    totalTours: { type: Number, default: 0 },
});

const emit = defineEmits(['close']);

const percent = computed(() => (props.totalTours ? Math.round((props.completedTours / props.totalTours) * 100) : 0));

const suggestions = [
    { icon: 'projects', label: 'Tạo dự án đầu tiên', href: '/projects/create' },
    { icon: 'daily', label: 'Viết báo cáo hôm nay', href: '/daily-reports/today' },
    { icon: 'knowledge', label: 'Đọc cơ sở tri thức', href: '/knowledge-base' },
];
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm"
      >
        <div class="card w-full max-w-md p-7 text-center shadow-elevation-3">
          <div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-50 text-3xl">
            🎉
          </div>
          <h2 class="mt-4 font-display text-xl font-bold text-slate-800">
            Chúc mừng, bạn đã hoàn thành hướng dẫn!
          </h2>
          <p class="mt-1.5 text-sm text-slate-500">
            Bạn đã sẵn sàng sử dụng hệ thống. Có thể xem lại hướng dẫn bất cứ lúc nào ở nút trợ giúp.
          </p>

          <div class="mt-5 rounded-xl border border-slate-100 bg-slate-50/60 p-4">
            <div class="flex items-center justify-between text-sm">
              <span class="font-medium text-slate-600">Tiến độ onboarding</span>
              <span class="font-bold text-brand tabular-nums">{{ completedTours }}/{{ totalTours }}</span>
            </div>
            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-200">
              <div
                class="h-full rounded-full bg-brand transition-[width] duration-500 ease-out"
                :style="{ width: `${percent}%` }"
              />
            </div>
          </div>

          <p class="mt-5 text-left text-xs font-semibold uppercase tracking-wide text-slate-400">
            Khám phá tiếp theo
          </p>
          <div class="mt-2 space-y-2">
            <Link
              v-for="s in suggestions"
              :key="s.href"
              :href="s.href"
              class="flex items-center gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2.5 text-left transition-colors hover:border-brand/30 hover:bg-brand/[0.03]"
              @click="emit('close')"
            >
              <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand">
                <AppIcon
                  :name="s.icon"
                  :size="16"
                />
              </span>
              <span class="flex-1 text-sm font-medium text-slate-700">{{ s.label }}</span>
              <AppIcon
                name="chevron-right"
                :size="16"
                class="text-slate-300"
              />
            </Link>
          </div>

          <button
            type="button"
            class="btn-primary mt-6 w-full"
            @click="emit('close')"
          >
            Bắt đầu sử dụng hệ thống
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
