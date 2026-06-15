<script setup>
import { computed } from 'vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileFieldList from './ProfileFieldList.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const fields = computed(() => {
    const p = props.profile;
    const primaryTeam = p.teams?.[0] ?? null;
    return [
        {
            label: 'Vai trò hệ thống',
            value: p.account_role?.label ?? null,
        },
        {
            label: 'Cấp bậc',
            value: p.seniority?.label ?? null,
        },
        { label: 'Nhóm QLDA', value: primaryTeam?.name ?? null },
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
