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
    { label: 'Mã nhân sự', value: h.value.code, mono: true },
    { label: 'Điện thoại', value: h.value.phone, href: h.value.phone ? `tel:${h.value.phone}` : null },
]);

const orgFields = computed(() => [
    { label: 'Công ty', value: h.value.company_name },
    { label: 'Mã công ty', value: h.value.company_id, mono: true },
    { label: 'Phòng ban', value: h.value.department_name },
    { label: 'Mã phòng ban', value: h.value.department_code, mono: true },
    { label: 'Đơn vị', value: h.value.unit_name },
    { label: 'Trụ sở / Chi nhánh', value: h.value.headquarter_name },
]);

const roleFields = computed(() => [
    { label: 'Chức danh', value: h.value.position_name ?? props.roleTitle },
    { label: 'Chức danh kiêm nhiệm', value: h.value.concurrent_position_name },
    {
        label: 'Ngày bắt đầu làm việc',
        value: h.value.start_working_date ? date(h.value.start_working_date) : null,
        mono: true,
    },
]);
</script>

<template>
  <ProfileInfoPanel
    title="Hồ sơ nhân sự (CMS)"
    icon="member-profiles"
    subtitle="Dữ liệu đồng bộ từ hệ thống nhân sự — chỉ đọc trên QLDA"
    section-key="profile-hr"
  >
    <ProfileFieldList
      group-title="Định danh"
      :fields="identityFields"
    />
    <ProfileFieldList
      group-title="Tổ chức"
      :fields="orgFields"
    />
    <ProfileFieldList
      group-title="Chức vụ"
      :fields="roleFields"
    />
  </ProfileInfoPanel>
</template>
