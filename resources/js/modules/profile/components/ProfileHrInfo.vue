<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    hrInfo: { type: Object, required: true },
    roleTitle: { type: String, default: null },
});

const display = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    return value;
};

const rows = computed(() => {
    const h = props.hrInfo ?? {};
    return [
        { icon: 'account', label: 'Mã nhân sự', value: display(h.code) },
        { icon: 'phone', label: 'Điện thoại', value: display(h.phone) },
        { icon: 'building', label: 'Công ty', value: display(h.company_name) },
        { icon: 'department', label: 'Phòng ban', value: display(h.department_name) },
        { icon: 'org-teams', label: 'Đơn vị', value: display(h.unit_name) },
        { icon: 'map-pin', label: 'Trụ sở / Chi nhánh', value: display(h.headquarter_name) },
        { icon: 'briefcase', label: 'Chức danh', value: display(h.position_name ?? props.roleTitle) },
        { icon: 'briefcase', label: 'Chức danh kiêm nhiệm', value: display(h.concurrent_position_name) },
        {
            icon: 'calendar',
            label: 'Ngày bắt đầu làm việc',
            value: h.start_working_date ? date(h.start_working_date) : '—',
        },
        { icon: 'department', label: 'Mã phòng ban (CMS)', value: display(h.department_id) },
        { icon: 'building', label: 'Mã công ty (CMS)', value: display(h.company_id) },
    ];
});
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <AppIcon
        name="member-profiles"
        :size="16"
        class="text-slate-400"
      />
      <h2 class="text-sm font-semibold text-slate-800">
        Hồ sơ nhân sự (CMS)
      </h2>
    </header>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2">
      <div
        v-for="r in rows"
        :key="r.label"
        class="flex items-start gap-2.5 min-w-0"
      >
        <div class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
          <AppIcon
            :name="r.icon"
            :size="13"
          />
        </div>
        <div class="min-w-0 flex-1">
          <dt class="text-[11px] uppercase tracking-wide text-slate-400">
            {{ r.label }}
          </dt>
          <dd class="break-words text-[13px] font-medium text-slate-700">
            {{ r.value }}
          </dd>
        </div>
      </div>
    </dl>
  </section>
</template>
