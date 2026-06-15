<script setup>
defineProps({
    metrics: { type: Object, default: () => ({}) },
});

const highlights = [
    { key: 'projects', label: 'Dự án' },
    { key: 'members', label: 'Nhân sự' },
    { key: 'departments', label: 'Phòng ban' },
];
</script>

<template>
  <section
    id="top"
    class="relative flex min-h-screen items-center overflow-hidden pt-16"
  >
    <!-- Background glow -->
    <div
      class="pointer-events-none absolute inset-0"
      aria-hidden="true"
    >
      <div class="absolute -left-32 top-10 h-[420px] w-[420px] rounded-full bg-brand/30 blur-[120px]" />
      <div class="absolute right-0 top-1/3 h-[360px] w-[360px] rounded-full bg-[#ff4d8d]/20 blur-[120px]" />
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_120%,rgba(154,0,54,0.18),transparent_60%)]" />
    </div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 sm:px-8 lg:grid-cols-[1.15fr_0.85fr]">
      <div>
        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3.5 py-1.5 text-[12.5px] font-medium text-white/80 backdrop-blur">
          <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400" />
          Vietnam America Schools · Đổi mới giáo dục bằng công nghệ
        </span>

        <h1 class="mt-6 font-display text-4xl font-extrabold leading-[1.08] tracking-tight text-white sm:text-5xl lg:text-6xl">
          Phòng Công Nghệ
          <span class="mt-2 block bg-gradient-to-r from-[#ff85b3] via-[#ff4d8d] to-brand bg-clip-text text-transparent">
            Kiến tạo nền tảng số
          </span>
        </h1>

        <p class="mt-6 max-w-xl text-base leading-relaxed text-white/65 sm:text-lg">
          Chúng tôi xây dựng hạ tầng dữ liệu, sản phẩm phần mềm và năng lực AI
          phục vụ toàn hệ thống giáo dục — biến mỗi quy trình thành sản phẩm
          thật, vận hành được và đo lường được.
        </p>

        <div class="mt-8 flex flex-wrap items-center gap-3">
          <a
            href="#san-pham"
            class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-brand to-[#d4145a] px-6 py-3 text-sm font-semibold text-white shadow-xl shadow-brand/30 transition hover:shadow-brand/50"
          >
            Khám phá sản phẩm
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </a>
          <a
            href="#to-chuc"
            class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-6 py-3 text-sm font-semibold text-white/85 backdrop-blur transition hover:bg-white/10"
          >
            Đội ngũ &amp; tổ chức
          </a>
        </div>

        <dl class="mt-12 grid max-w-md grid-cols-3 gap-6">
          <div
            v-for="h in highlights"
            :key="h.key"
          >
            <dt class="font-display text-3xl font-bold text-white">
              {{ metrics[h.key] ?? 0 }}<span class="text-brand">+</span>
            </dt>
            <dd class="mt-1 text-[12.5px] text-white/50">
              {{ h.label }}
            </dd>
          </div>
        </dl>
      </div>

      <!-- Orbit visual -->
      <div class="relative hidden aspect-square w-full max-w-md lg:block">
        <div class="absolute inset-0 rounded-full border border-white/10" />
        <div class="absolute inset-8 rounded-full border border-white/10" />
        <div class="absolute inset-16 rounded-full border border-dashed border-white/10" />
        <div class="absolute inset-0 grid place-items-center">
          <div class="grid h-32 w-32 place-items-center rounded-3xl bg-gradient-to-br from-brand to-[#ff4d8d] shadow-2xl shadow-brand/40">
            <span class="font-display text-4xl font-black text-white">CN</span>
          </div>
        </div>
        <span class="absolute left-1/2 top-2 -translate-x-1/2 rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 backdrop-blur">AI</span>
        <span class="absolute right-2 top-1/2 -translate-y-1/2 rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 backdrop-blur">Web</span>
        <span class="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 backdrop-blur">Data</span>
        <span class="absolute left-2 top-1/2 -translate-y-1/2 rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-white/80 backdrop-blur">Cloud</span>
      </div>
    </div>

    <a
      href="#gioi-thieu"
      class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/40 transition hover:text-white"
      aria-label="Cuộn xuống"
    >
      <svg
        width="26"
        height="26"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        class="animate-bounce"
      ><path d="M12 5v14M6 13l6 6 6-6" /></svg>
    </a>
  </section>
</template>
