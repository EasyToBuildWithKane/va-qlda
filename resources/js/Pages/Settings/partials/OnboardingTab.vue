<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';
import WelcomePanel from '@/modules/onboarding/components/WelcomePanel.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { useOnboardingWelcome } from '@/modules/onboarding/composables/useOnboardingWelcome';

const props = defineProps({
    fields: { type: Array, default: () => [] },
    welcomePreview: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
    saveHotkeysEnabled: { type: Boolean, default: true },
});

const page = usePage();
const dialog = useDialog();
const toast = useToast();
const { openPreview } = useOnboardingWelcome();

const resetting = ref(false);
const resettingSelf = ref(false);

const liveWelcome = computed(() => page.props.onboarding?.welcome || null);

function fieldEnabled() {
    const field = props.fields.find((f) => f.name === 'welcome_enabled');
    if (field) return !!field.value;
    return Boolean(liveWelcome.value?.enabled ?? true);
}

const form = useForm({ welcome_enabled: fieldEnabled() });

watch(
    () => props.fields,
    () => {
        const value = fieldEnabled();
        form.welcome_enabled = value;
        form.defaults({ welcome_enabled: value });
    },
    { deep: true },
);

const enabled = computed(() => form.welcome_enabled);

const previewData = computed(() => {
    if (props.welcomePreview?.employee_name) return props.welcomePreview;
    if (liveWelcome.value?.employee_name) return liveWelcome.value;
    return {
        enabled: true,
        seen: false,
        employee_name: page.props.auth?.user?.display_name || 'Người dùng',
        role: 'member',
        role_label: 'Thành viên',
        role_title: '',
        department: { name: 'Phòng ban mẫu', color: '#9A0036' },
        coworkers: [],
        coworker_total: 0,
    };
});

const seenLabel = computed(() => (liveWelcome.value?.seen ? 'Đã xem' : 'Chưa xem'));

function save() {
    if (!props.canManage || form.processing || !form.isDirty) return;
    form.put('/settings/onboarding', {
        preserveScroll: true,
        onError: () => {
            toast.error('Không lưu được. Phiên có thể đã hết hạn — trang sẽ tải lại.');
        },
    });
}

function onKeydownSave(e) {
    if (!props.saveHotkeysEnabled) return;
    if (!(e.ctrlKey || e.metaKey) || e.key !== 's') return;
    e.preventDefault();
    save();
}

watch(
    () => props.saveHotkeysEnabled,
    (on) => {
        document.removeEventListener('keydown', onKeydownSave);
        if (on) document.addEventListener('keydown', onKeydownSave);
    },
    { immediate: true },
);

onUnmounted(() => document.removeEventListener('keydown', onKeydownSave));

function showFullPreview() {
    openPreview(previewData.value);
}

async function resetForAll() {
    if (!props.canManage || resetting.value) return;

    const ok = await dialog.confirm({
        title: 'Đặt lại cho tất cả tài khoản?',
        message: 'Mọi tài khoản đã xem màn hình chào mừng sẽ thấy lại ở lần đăng nhập tới.',
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

async function resetForSelf() {
    if (!props.canManage || resettingSelf.value) return;

    const ok = await dialog.confirm({
        title: 'Xem lại màn hình chào mừng?',
        message: 'Chỉ tài khoản của bạn sẽ thấy lại màn hình chào mừng sau khi chuyển trang.',
        confirmText: 'Đặt lại cho tôi',
        tone: 'default',
    });
    if (!ok) return;

    resettingSelf.value = true;
    router.post('/settings/onboarding/reset-self', {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã đặt lại — chuyển sang Bảng điều khiển để xem.');
            router.visit('/dashboard');
        },
        onError: () => toast.error('Không đặt lại được. Vui lòng thử lại.'),
        onFinish: () => { resettingSelf.value = false; },
    });
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col gap-3">
    <form
      class="flex shrink-0 flex-nowrap items-center gap-2 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      @submit.prevent="save"
    >
      <span class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200/80 bg-white px-3 text-xs font-semibold text-slate-700">
        <span
          class="inline-block h-2 w-2 rounded-full"
          :class="enabled ? 'bg-emerald-500' : 'bg-slate-300'"
        />
        {{ enabled ? 'Đang bật' : 'Đang tắt' }}
      </span>
      <span class="inline-flex h-9 items-center rounded-lg border border-slate-200/80 bg-white px-3 text-xs font-medium text-slate-600">
        {{ seenLabel }}
      </span>

      <div class="ml-auto inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200/80 bg-white px-3 text-sm font-medium text-slate-700">
        Bật màn hình chào mừng
        <ToggleSwitch
          id="onboarding-welcome-enabled"
          v-model="form.welcome_enabled"
          :disabled="!canManage"
        />
      </div>

      <button
        type="submit"
        class="btn-primary h-9 gap-1.5 px-3 text-xs"
        :disabled="!canManage || form.processing || !form.isDirty"
      >
        <AppIcon
          name="save"
          :size="15"
        />
        Lưu
      </button>

      <button
        type="button"
        class="btn-ghost h-9 gap-1.5 border border-slate-200 px-3 text-xs"
        @click="showFullPreview"
      >
        <AppIcon
          name="sparkles"
          :size="15"
        />
        Xem thử
      </button>
      <button
        type="button"
        class="btn-ghost h-9 gap-1.5 border border-slate-200 px-3 text-xs"
        :disabled="!canManage || resettingSelf"
        @click="resetForSelf"
      >
        <AppIcon
          name="rocket"
          :size="15"
        />
        Hiện lại cho tôi
      </button>
      <button
        type="button"
        class="btn-ghost h-9 gap-1.5 border border-rose-200 px-3 text-xs text-rose-600 hover:bg-rose-50"
        :disabled="!canManage || resetting"
        @click="resetForAll"
      >
        <AppIcon
          name="refresh"
          :size="15"
        />
        Cho mọi người
      </button>
    </form>

    <section
      class="flex min-h-0 flex-1 items-stretch justify-center overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-brand/[0.04] p-3 sm:p-4"
      aria-label="Xem trước màn hình chào mừng"
    >
      <WelcomePanel
        class="h-full min-h-0 w-full max-w-6xl"
        layout="horizontal"
        :welcome="previewData"
        :show-actions="false"
      />
    </section>
  </div>
</template>
