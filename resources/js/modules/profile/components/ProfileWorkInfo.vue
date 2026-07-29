<script setup>
import { computed } from 'vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileFieldList from './ProfileFieldList.vue';
import { formatProfileProjectTeams } from '../utils/profileTeams';

const props = defineProps({
    profile: { type: Object, required: true },
});

/** QLDA-only — không lặp quyền (Hero) / cấp bậc (Hero badge). */
const fields = computed(() => {
    const p = props.profile;
    return [
        { label: 'Nhóm dự án', value: formatProfileProjectTeams(p.teams) },
        { label: 'Người quản lý', value: p.manager?.name ?? null },
    ];
});
</script>

<template>
  <ProfileInfoPanel
    title="Trên hệ thống dự án"
    icon="briefcase"
    section-key="profile-work"
  >
    <ProfileFieldList :fields="fields" />
  </ProfileInfoPanel>
</template>
