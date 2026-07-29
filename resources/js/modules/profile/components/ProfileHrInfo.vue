<script setup>
import { computed } from 'vue';
import { date } from '@/composables/useFormat';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileFieldList from './ProfileFieldList.vue';

const props = defineProps({
    hrInfo: { type: Object, required: true },
});

const h = computed(() => props.hrInfo ?? {});

/** Org + employment from HRM — không lặp mã NV / chức danh (đã ở Hero). */
const orgFields = computed(() => [
    { label: 'Công ty', value: h.value.company_name },
    { label: 'Mã công ty', value: h.value.company_id, mono: true },
    { label: 'Phòng ban', value: h.value.department_name },
    { label: 'Mã phòng ban', value: h.value.department_code, mono: true },
    { label: 'Đơn vị / nhóm', value: h.value.unit_name },
    { label: 'Trụ sở / cơ sở', value: h.value.headquarter_name },
]);

const roleFields = computed(() => [
    { label: 'Kiêm nhiệm', value: h.value.concurrent_position_name },
    {
        label: 'Ngày vào làm',
        value: h.value.start_working_date ? date(h.value.start_working_date) : null,
        mono: true,
    },
]);
</script>

<template>
  <ProfileInfoPanel
    title="Tổ chức & công việc"
    icon="member-profiles"
    section-key="profile-hr"
  >
    <ProfileFieldList
      group-title="Công ty & đơn vị"
      :fields="orgFields"
    />
    <ProfileFieldList
      group-title="Bổ sung"
      :fields="roleFields"
    />
  </ProfileInfoPanel>
</template>
