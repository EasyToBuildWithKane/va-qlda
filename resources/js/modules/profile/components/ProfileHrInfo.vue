<script setup>
import { computed } from 'vue';
import { date } from '@/composables/useFormat';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileFieldList from './ProfileFieldList.vue';

const props = defineProps({
    hrInfo: { type: Object, required: true },
    roleTitle: { type: String, default: null },
});

const h = computed(() => props.hrInfo ?? {});

const identityFields = computed(() => [
    { label: 'Mã nhân viên', value: h.value.code, mono: true },
    { label: 'Số điện thoại', value: h.value.phone, href: h.value.phone ? `tel:${h.value.phone}` : null },
]);

const orgFields = computed(() => [
    { label: 'Công ty', value: h.value.company_name },
    { label: 'Mã công ty', value: h.value.company_id, mono: true },
    { label: 'Phòng ban', value: h.value.department_name },
    { label: 'Mã phòng ban', value: h.value.department_code, mono: true },
    { label: 'Đơn vị / nhóm', value: h.value.unit_name },
    { label: 'Trụ sở / cơ sở', value: h.value.headquarter_name },
]);

const roleFields = computed(() => [
    { label: 'Chức danh', value: h.value.position_name ?? props.roleTitle },
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
    title="Thông tin nhân sự"
    icon="member-profiles"
    subtitle="Đồng bộ từ hệ thống nhân sự VA — chỉ xem tại đây"
    section-key="profile-hr"
  >
    <ProfileFieldList
      group-title="Cá nhân"
      :fields="identityFields"
    />
    <ProfileFieldList
      group-title="Công ty & đơn vị"
      :fields="orgFields"
    />
    <ProfileFieldList
      group-title="Vị trí công việc"
      :fields="roleFields"
    />
  </ProfileInfoPanel>
</template>
