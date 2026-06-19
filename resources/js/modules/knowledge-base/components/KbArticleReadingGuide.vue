<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
</script>

<template>
  <section
    class="rounded-card border border-brand/10 bg-gradient-to-r from-brand/[0.04] via-white to-slate-50/80 px-4 py-3 shadow-sm dark:border-brand/20 dark:from-brand/10 dark:via-slate-900 dark:to-slate-900/80 sm:px-5"
    aria-label="Hướng dẫn đọc bài"
  >
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex min-w-0 items-start gap-2.5">
        <span
          class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand"
          aria-hidden="true"
        >
          <AppIcon
            name="info"
            :size="16"
          />
        </span>
        <div class="min-w-0">
          <p class="text-sm font-medium text-slate-800 dark:text-slate-100">
            Mẹo đọc nhanh
          </p>
          <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            Thanh tiến độ trên cùng · mục lục bên phải (màn hình lớn) · bấm tiêu đề mục để nhảy tới đoạn.
          </p>
        </div>
      </div>
      <ul class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-600 dark:text-slate-300 sm:justify-end">
        <li class="inline-flex items-center gap-1">
          <span class="font-medium text-slate-500">Mục lục</span>
          <FieldTooltip text="Danh sách tiêu đề H2/H3 trong bài. Bấm để cuộn mượt tới đoạn; URL có #muc-… để chia sẻ đúng vị trí." />
        </li>
        <li class="inline-flex items-center gap-1">
          <span class="font-medium text-slate-500">Lưu bài</span>
          <FieldTooltip text="Thêm vào danh sách yêu thích cá nhân để tìm lại trên blog hoặc hồ sơ." />
        </li>
        <li class="inline-flex items-center gap-1">
          <span class="font-medium text-slate-500">Đã đọc</span>
          <FieldTooltip text="Đánh dấu khi bạn hoàn thành — giúp theo dõi tiến độ học nội bộ." />
        </li>
      </ul>
    </div>
  </section>
</template>
