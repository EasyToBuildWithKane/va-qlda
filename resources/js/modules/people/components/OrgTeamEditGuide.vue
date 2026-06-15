<script setup>
import AppIcon from '@/Components/AppIcon.vue';

const steps = [
    { icon: 'members', text: 'Chọn đơn vị cần sửa ở danh sách phía dưới.' },
    { icon: 'edit', text: 'Điền tên, người phụ trách và danh sách nhân sự.' },
    { icon: 'save', text: 'Bấm «Lưu thay đổi» — mỗi đơn vị lưu riêng, không cần sửa hết một lúc.' },
];
</script>

<template>
  <div class="rounded-xl border border-sky-100 bg-gradient-to-br from-sky-50/90 to-white px-4 py-4 sm:px-5">
    <div class="flex gap-3">
      <div
        class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-sky-100 text-sky-700"
        aria-hidden="true"
      >
        <AppIcon
          name="info"
          :size="20"
        />
      </div>
      <div class="min-w-0 flex-1">
        <h2 class="font-display text-sm font-semibold text-slate-900">
          Cách cập nhật sơ đồ
        </h2>
        <ol class="mt-2 space-y-2">
          <li
            v-for="(step, idx) in steps"
            :key="idx"
            class="flex gap-2 text-sm text-slate-600"
          >
            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-white text-[11px] font-bold text-brand ring-1 ring-brand/20">
              {{ idx + 1 }}
            </span>
            <span>{{ step.text }}</span>
          </li>
        </ol>
      </div>
    </div>
  </div>
</template>
