<script setup>
import { computed } from 'vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileFieldList from './ProfileFieldList.vue';
import { formatProfileProjectTeams } from '../utils/profileTeams';

const props = defineProps({
    profile: { type: Object, required: true },
});

const fields = computed(() => {
    const p = props.profile;
    return [
        {
            label: 'Vai trò hệ thống',
            value: p.account_role?.label ?? null,
        },
        {
            label: 'Cấp bậc',
            value: p.seniority?.label ?? null,
        },
        { label: 'Nhóm quản lý dự án', value: formatProfileProjectTeams(p.teams) },
        { label: 'Quản lý trực tiếp', value: p.manager?.name ?? null },
        {
            label: 'Trạng thái',
            value: p.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động',
        },
    ];
});
</script>

<template>
  <ProfileInfoPanel
    title="Vận hành trên QLDA"
    icon="briefcase"
    subtitle="Vai trò, nhóm và trạng thái trên hệ thống quản lý dự án"
    section-key="profile-work"
  >
    <ProfileFieldList :fields="fields" />
  </ProfileInfoPanel>
</template>
