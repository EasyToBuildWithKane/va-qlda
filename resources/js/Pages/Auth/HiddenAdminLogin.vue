<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import ToastContainer from '@/Components/Ui/ToastContainer.vue';
import FormField from '@/shared/ui/form/FormField.vue';
import PasswordInput from '@/shared/ui/form/PasswordInput.vue';
import TextInput from '@/shared/ui/form/TextInput.vue';
import { useToast } from '@/shared/composables/useToast';

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const toast = useToast();

const shakeCard = ref(false);
/** Sau lần vào đầu hoặc khi có lỗi — tắt stagger opacity để form không “biến mất”. */
const formReady = ref(false);

const loginErrorMessage = computed(() => {
    const u = form.errors.username;
    if (typeof u === 'string' && u.length) return u;
    const p = form.errors.password;
    if (typeof p === 'string' && p.length) return p;
    return null;
});

function triggerShake() {
    shakeCard.value = false;
    void nextTick(() => {
        shakeCard.value = true;
    });
}

function markFormReady() {
    formReady.value = true;
}

watch(loginErrorMessage, (msg) => {
    if (!msg || form.processing) {
        return;
    }
    markFormReady();
    triggerShake();
});

watch(
    flash,
    (f) => {
        if (f?.error) toast.error(f.error);
        if (f?.success) toast.success(f.success);
    },
    { immediate: true, deep: true },
);

onMounted(() => {
    window.setTimeout(markFormReady, 1100);
});

function submit() {
    form.post(route('auth.hidden-login.store'), {
        preserveScroll: true,
        onError: () => {
            markFormReady();
            triggerShake();
        },
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
  <Head title="Đăng nhập quản trị" />

  <ToastContainer placement="top-end" />

  <div
    class="relative flex min-h-dvh flex-col items-center justify-center overflow-hidden px-4 py-8 pb-[max(2rem,env(safe-area-inset-bottom))] pt-[max(2rem,env(safe-area-inset-top))] sm:py-10"
    style="background-color: #9a0036"
  >
    <!-- Watermark: giống trang /login -->
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="lh36-bg pointer-events-none absolute inset-0 h-full w-full scale-105 object-cover object-center brightness-0 invert opacity-[0.72]"
      aria-hidden="true"
    >
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-b from-[#9a0036]/5 via-transparent to-[#9a0036]/20"
      aria-hidden="true"
    />

    <div class="relative z-10 flex w-full max-w-lg flex-col items-center">
      <header class="lh36-enter-logo mb-6 w-full px-2 sm:mb-8 sm:px-4">
        <img
          src="/images/logo-2.png"
          alt="Vietnam America Schools — Trường học của sự lắng nghe"
          class="mx-auto h-auto w-full max-w-[min(100%,260px)] object-contain drop-shadow-md sm:max-w-[320px]"
          width="320"
          height="92"
        >
      </header>

      <div
        class="lh36-enter-card w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-black/5 sm:rounded-3xl"
        :class="{ 'lh36-shake': shakeCard }"
      >
        <div
          class="lh36-enter-bar h-1 origin-left bg-brand"
          aria-hidden="true"
        />

        <div class="px-5 py-8 sm:px-10 sm:py-12">
          <div class="flex flex-col items-center text-center">
            <div
              class="lh36-enter-icon mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-brand/10 text-brand sm:h-14 sm:w-14"
              aria-hidden="true"
            >
              <AppIcon
                name="system-config"
                :size="26"
              />
            </div>
            <h1 class="lh36-enter-title font-display text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
              Đăng nhập quản trị
            </h1>
            <p class="lh36-enter-subtitle mx-auto mt-3 max-w-sm text-sm leading-relaxed text-slate-500">
              Dùng tài khoản hệ thống do quản trị cấp.
            </p>
          </div>

          <Transition name="lh36-error">
            <div
              v-if="loginErrorMessage && !form.processing"
              key="login-error"
              role="alert"
              aria-live="assertive"
              class="lh36-error-banner mt-6 flex gap-3 rounded-xl border border-danger/25 bg-danger/5 px-4 py-3 text-left"
            >
              <span
                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-danger/10 text-danger"
                aria-hidden="true"
              >
                <AppIcon
                  name="alert"
                  :size="18"
                />
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-danger">
                  Đăng nhập không thành công
                </p>
                <p class="mt-1 text-sm leading-relaxed text-danger/90">
                  {{ loginErrorMessage }}
                </p>
              </div>
            </div>
          </Transition>

          <form
            class="lh36-form mt-8 space-y-5"
            :class="formReady ? 'lh36-form-ready' : 'lh36-enter-form'"
            novalidate
            @submit.prevent="submit"
          >
            <FormField
              id="lh36-username"
              label="Tên đăng nhập"
              :error="form.errors.username"
              required
            >
              <TextInput
                id="lh36-username"
                v-model="form.username"
                type="text"
                name="username"
                autocomplete="username"
                placeholder="vd: usr_01"
                :invalid="!!form.errors.username"
                :disabled="form.processing"
              />
            </FormField>

            <FormField
              id="lh36-password"
              label="Mật khẩu"
              :error="form.errors.password"
              required
            >
              <div :class="{ 'lh36-field-invalid': !!form.errors.password }">
                <PasswordInput
                  id="lh36-password"
                  v-model="form.password"
                  autocomplete="current-password"
                  placeholder="Nhập mật khẩu"
                  :required="true"
                />
              </div>
            </FormField>

            <label
              class="flex min-h-11 cursor-pointer select-none items-center gap-3 rounded-lg py-1 text-sm text-slate-600 active:bg-slate-50 sm:min-h-0"
            >
              <input
                v-model="form.remember"
                type="checkbox"
                class="h-4 w-4 shrink-0 rounded border-slate-300 text-brand focus:ring-brand/40"
                :disabled="form.processing"
              >
              <span>Ghi nhớ đăng nhập trên thiết bị này</span>
            </label>

            <button
              type="submit"
              class="lh36-enter-submit btn-primary flex min-h-11 w-full items-center justify-center gap-2 rounded-full text-base font-semibold shadow-md transition duration-200 hover:scale-[1.02] hover:shadow-lg active:scale-[0.98] focus:ring-offset-2 disabled:opacity-70 disabled:hover:scale-100 sm:min-h-10 sm:text-sm"
              :disabled="form.processing"
            >
              <span
                v-if="form.processing"
                class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
                aria-hidden="true"
              />
              {{ form.processing ? 'Đang đăng nhập…' : 'Đăng nhập' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Ô nhập ≥16px trên mobile — tránh zoom iOS Safari */
.lh36-form :deep(.input) {
    @apply h-11 text-base sm:h-10 sm:text-sm;
}

.lh36-form :deep(.input::placeholder) {
    @apply text-slate-400;
}

.lh36-field-invalid :deep(.input) {
    @apply border-danger focus:border-danger focus:ring-danger/30;
}

/* —— Entrance & ambient motion —— */
.lh36-bg {
    animation: lh36-bg-drift 28s ease-in-out infinite alternate;
}

.lh36-enter-logo {
    opacity: 0;
    animation: lh36-fade-down 0.65s cubic-bezier(0.22, 1, 0.36, 1) 0.05s forwards;
}

.lh36-enter-card {
    opacity: 0;
    animation: lh36-fade-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) 0.15s forwards;
}

.lh36-enter-bar {
    transform: scaleX(0);
    animation: lh36-bar-grow 0.55s cubic-bezier(0.22, 1, 0.36, 1) 0.35s forwards;
}

.lh36-enter-icon {
    opacity: 0;
    animation: lh36-icon-pop 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) 0.4s forwards;
}

.lh36-enter-title {
    opacity: 0;
    animation: lh36-fade-up 0.5s ease-out 0.48s forwards;
}

.lh36-enter-subtitle {
    opacity: 0;
    animation: lh36-fade-up 0.5s ease-out 0.58s forwards;
}

.lh36-enter-form > * {
    opacity: 0;
    animation: lh36-fade-up 0.45s ease-out forwards;
}

.lh36-form-ready > * {
    opacity: 1;
    animation: none;
    transform: none;
}

.lh36-enter-form > *:nth-child(1) {
    animation-delay: 0.68s;
}

.lh36-enter-form > *:nth-child(2) {
    animation-delay: 0.76s;
}

.lh36-enter-form > *:nth-child(3) {
    animation-delay: 0.84s;
}

.lh36-enter-submit {
    animation-delay: 0.92s;
}

.lh36-shake {
    animation: lh36-shake 0.45s ease-in-out;
}

/* Banner lỗi đăng nhập */
.lh36-error-enter-active {
    animation: lh36-error-in 0.4s cubic-bezier(0.22, 1, 0.36, 1);
}

.lh36-error-leave-active {
    animation: lh36-error-out 0.25s ease-in forwards;
}

@keyframes lh36-error-in {
    from {
        opacity: 0;
        transform: translate3d(0, -8px, 0) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translate3d(0, 0, 0) scale(1);
    }
}

@keyframes lh36-error-out {
    to {
        opacity: 0;
        transform: translate3d(0, -4px, 0);
    }
}

@keyframes lh36-bg-drift {
    from {
        transform: scale(1.05) translate3d(0, 0, 0);
    }

    to {
        transform: scale(1.1) translate3d(-1%, 1%, 0);
    }
}

@keyframes lh36-fade-down {
    from {
        opacity: 0;
        transform: translate3d(0, -14px, 0);
    }

    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

@keyframes lh36-fade-up {
    from {
        opacity: 0;
        transform: translate3d(0, 14px, 0);
    }

    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

@keyframes lh36-bar-grow {
    to {
        transform: scaleX(1);
    }
}

@keyframes lh36-icon-pop {
    0% {
        opacity: 0;
        transform: scale(0.55);
    }

    70% {
        transform: scale(1.06);
    }

    100% {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes lh36-shake {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }

    20%,
    60% {
        transform: translate3d(-5px, 0, 0);
    }

    40%,
    80% {
        transform: translate3d(5px, 0, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .lh36-bg,
    .lh36-enter-logo,
    .lh36-enter-card,
    .lh36-enter-bar,
    .lh36-enter-icon,
    .lh36-enter-title,
    .lh36-enter-subtitle,
    .lh36-enter-form > *,
    .lh36-shake,
    .lh36-error-enter-active,
    .lh36-error-leave-active {
        animation: none !important;
        opacity: 1 !important;
        transform: none !important;
    }

    .lh36-form-ready > * {
        opacity: 1 !important;
    }
}
</style>
