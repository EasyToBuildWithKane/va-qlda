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
            label: 'Quyền truy cập',
            value: p.account_role?.label ?? null,
        },
        {
            label: 'Cấp bậc',
            value: p.seniority?.label ?? null,
        },
        { label: 'Nhóm dự án', value: formatProfileProjectTeams(p.teams) },
        { label: 'Người quản lý', value: p.manager?.name ?? null },
    ];
});
</script>

<template>
  <ProfileInfoPanel
    title="Trên hệ thống dự án"
    icon="briefcase"
    subtitle="Quyền, nhóm và quản lý trên QLDA"
    section-key="profile-work"
  >
    <ProfileFieldList :fields="fields" />
  </ProfileInfoPanel>
</template>
