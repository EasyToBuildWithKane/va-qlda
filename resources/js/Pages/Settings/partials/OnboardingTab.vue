<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FieldsTab from './FieldsTab.vue';
import WelcomePanel from '@/modules/onboarding/components/WelcomePanel.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { useOnboardingWelcome } from '@/modules/onboarding/composables/useOnboardingWelcome';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
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
const enabled = computed(() => {
    const field = props.fields.find((f) => f.name === 'welcome_enabled');
    if (field) return !!field.value;
    return Boolean(liveWelcome.value?.enabled ?? true);
});

const previewData = computed(() => {
    if (props.welcomePreview?.employee_name) return props.welcomePreview;
    if (liveWelcome.value?.employee_name) return liveWelcome.value;
    return {
        enabled: true,
        seen: false,
        employee_name: page.props.auth?.user?.display_name || 'Người dùng',
        role_label: 'Thành viên',
        department: { name: 'Phòng ban mẫu', color: '#9A0036' },
        coworkers: [],
        coworker_total: 0,
    };
});

const seenLabel = computed(() => {
    if (liveWelcome.value?.seen) return 'Bạn đã xem màn hình này';
    if (!enabled.value) return 'Đang tắt toàn hệ thống';
    return 'Sẽ hiện ở lần vào hệ thống tới (nếu chưa xem)';
});

function showFullPreview() {
    openPreview(previewData.value);
}

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
  <div class="flex h-full flex-col gap-6">
    <!-- Hero + preview -->
    <section
      class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-gradient-to-br from-slate-50 via-white to-brand/[0.04]"
      aria-label="Xem trước màn hình chào mừng"
    >
      <div
        class="pointer-events-none absolute inset-0"
        aria-hidden="true"
      >
        <div class="absolute -right-16 -top-20 h-56 w-56 rounded-full bg-brand/10 blur-3xl" />
        <div class="absolute -bottom-24 -left-10 h-48 w-48 rounded-full bg-rose-200/30 blur-3xl" />
      </div>

      <div class="relative grid gap-6 p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,20rem)] md:p-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,22rem)]">
        <div class="flex min-w-0 flex-col justify-center">
          <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-brand/80">
            Onboarding
          </p>
          <h2 class="mt-1.5 font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
            {{ title || 'Chào mừng nhân viên' }}
          </h2>
          <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-500">
            {{ description || 'Màn hình chào mừng toàn màn hình hiện một lần khi nhân viên vào hệ thống lần đầu — giới thiệu phòng ban, vai trò và đồng nghiệp.' }}
          </p>

          <dl class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200/80 bg-white/80 px-3.5 py-2.5 shadow-sm">
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                Trạng thái
              </dt>
              <dd class="mt-0.5 flex items-center gap-1.5 text-sm font-semibold text-slate-800">
                <span
                  class="inline-block h-2 w-2 rounded-full"
                  :class="enabled ? 'bg-emerald-500' : 'bg-slate-300'"
                />
                {{ enabled ? 'Đang bật' : 'Đang tắt' }}
              </dd>
            </div>
            <div class="rounded-xl border border-slate-200/80 bg-white/80 px-3.5 py-2.5 shadow-sm">
              <dt class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                Tài khoản của bạn
              </dt>
              <dd class="mt-0.5 text-sm font-medium text-slate-700">
                {{ seenLabel }}
              </dd>
            </div>
          </dl>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
              type="button"
              class="btn-primary h-9 gap-1.5 px-3 text-xs"
              @click="showFullPreview"
            >
              <AppIcon
                name="sparkles"
                :size="15"
              />
              Xem thử toàn màn hình
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
          </div>
        </div>

        <div class="relative mx-auto w-full max-w-sm md:mx-0">
          <div class="absolute -inset-2 rounded-[1.35rem] bg-gradient-to-br from-brand/20 via-transparent to-rose-200/30 blur-sm" />
          <div class="relative scale-[0.92] origin-top sm:scale-100">
            <WelcomePanel
              :welcome="previewData"
              compact
              :show-actions="false"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- Toggle settings -->
    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm md:p-6">
      <FieldsTab
        group="onboarding"
        hide-header
        :save-hotkeys-enabled="saveHotkeysEnabled"
        :title="title"
        :description="description"
        :fields="fields"
        :can-manage="canManage"
      />
    </section>

    <!-- Danger zone -->
    <section class="rounded-2xl border border-rose-100 bg-gradient-to-br from-rose-50/80 to-white px-5 py-5 md:px-6">
      <div class="flex items-start gap-3">
        <span class="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-600 ring-1 ring-rose-200/80">
          <AppIcon
            name="refresh"
            :size="18"
          />
        </span>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-slate-800">
            Đặt lại cho mọi người
          </p>
          <p class="mt-1 text-[12.5px] leading-relaxed text-slate-500">
            Xoá trạng thái «đã xem» của tất cả tài khoản — mọi người sẽ thấy lại màn hình chào mừng ở lần đăng nhập kế tiếp.
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
    </section>
  </div>
</template>
