<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import ToastContainer from '@/Components/Ui/ToastContainer.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    googleEnabled: { type: Boolean, default: false },
    googleAuthUrl: { type: String, required: true },
});

function onGoogleClick(event) {
    if (!props.googleEnabled) {
        event.preventDefault();
    }
}

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const toast = useToast();

watch(
    flash,
    (f) => {
        if (f?.error) toast.error(f.error);
        if (f?.success) toast.success(f.success);
    },
    { immediate: true, deep: true },
);
</script>

<template>
  <Head title="Đăng nhập" />

  <ToastContainer placement="top-end" />

  <div
    class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-10"
    style="background-color: #9a0036"
  >
    <!-- Watermark: PNG tối trên đen → invert thành họa tiết sáng trên nền brand -->
    <img
      src="/images/background/background-logo.png"
      alt=""
      class="pointer-events-none absolute inset-0 h-full w-full scale-105 object-cover object-center brightness-0 invert opacity-[0.5]"
      aria-hidden="true"
    >
    <div
      class="pointer-events-none absolute inset-0 bg-gradient-to-b from-[#9a0036]/5 via-transparent to-[#9a0036]/20"
      aria-hidden="true"
    />

    <div class="relative z-10 flex w-full max-w-lg flex-col items-center">
      <!-- Header thương hiệu (mockup VAS) -->
      <header class="mb-8 px-4">
        <img
          src="/images/logo-2.png"
          alt="Vietnam America Schools — Trường học của sự lắng nghe"
          class="mx-auto h-auto w-full max-w-[min(100%,300px)] object-contain drop-shadow-md sm:max-w-[320px]"
          width="320"
          height="92"
        >
      </header>

      <!-- Thẻ đăng nhập -->
      <div class="w-full max-w-md rounded-2xl bg-white px-8 py-10 shadow-xl sm:px-10 sm:py-12">
        <h1 class="text-center font-display text-2xl font-bold text-slate-900">
          Đăng nhập
        </h1>

        <p class="mx-auto mt-4 max-w-xs text-center text-sm leading-relaxed text-slate-500">
          Đăng nhập thông qua tài khoản mail do nhà trường cung cấp
        </p>

        <div class="mt-8 flex flex-col items-center gap-3">
          <a
            :href="googleEnabled ? googleAuthUrl : '#'"
            class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white p-3 shadow-md transition focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2"
            :class="googleEnabled ? 'hover:shadow-lg' : 'cursor-not-allowed opacity-80'"
            aria-label="Đăng nhập bằng Google"
            @click="onGoogleClick"
          >
            <img
              src="/images/google.png"
              alt=""
              class="block h-12 w-12 sm:h-14 sm:w-14"
              width="56"
              height="56"
              loading="eager"
              decoding="async"
            >
          </a>

          <p
            v-if="!googleEnabled"
            class="max-w-xs text-center text-xs text-slate-500"
          >
            Đăng nhập Google chưa được cấu hình (thiếu GOOGLE_CLIENT_ID trong .env).
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
