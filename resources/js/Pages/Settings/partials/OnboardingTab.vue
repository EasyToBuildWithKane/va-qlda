<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FieldsTab from './FieldsTab.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    fields: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    saveHotkeysEnabled: { type: Boolean, default: true },
});

const dialog = useDialog();
const toast = useToast();
const resetting = ref(false);

async function resetForAll() {
    if (!props.canManage || resetting.value) return;

    const ok = await dialog.confirm({
        title: 'Đặt lại cho tất cả tài khoản?',
        message: 'Mọi tài khoản đã xem màn hình chào mừng sẽ thấy lại ở lần đăng nhập tới. Hành động này áp dụng ngay cho toàn hệ thống.',
        confirmText: 'Đặt lại cho tất cả',
        tone: 'danger',
    });
    if (!ok) return;

    resetting.value = true;
    router.post('/settings/onboarding/reset', {}, {
        preserveScroll: true,
        onError: () => toast.error('Không đặt lại được. Vui lòng thử lại.'),
        onFinish: () => { resetting.value = false; },
    });
}
</script>

<template>
  <div class="flex h-full flex-col">
    <FieldsTab
      group="onboarding"
      :save-hotkeys-enabled="saveHotkeysEnabled"
      :title="title"
      :description="description"
      :fields="fields"
      :can-manage="canManage"
    />

    <div class="mt-6 rounded-card border border-rose-100 bg-rose-50/40 px-4 py-4">
      <div class="flex items-start gap-3">
        <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-rose-100 text-rose-600">
          <AppIcon
            name="refresh"
            :size="16"
          />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-slate-700">
            Đặt lại cho mọi người
          </p>
          <p class="mt-0.5 text-[12.5px] leading-relaxed text-slate-500">
            Xoá trạng thái "đã xem" của tất cả tài khoản — mọi người sẽ thấy lại màn hình chào mừng ở lần đăng nhập kế tiếp.
          </p>
          <button
            type="button"
            class="btn-ghost mt-3 border border-rose-200 text-rose-600 hover:bg-rose-100"
            :disabled="!canManage || resetting"
            @click="resetForAll"
          >
            <AppIcon
              name="refresh"
              :size="15"
            />
            Cho mọi người xem lại
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
