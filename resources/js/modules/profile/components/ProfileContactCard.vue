<script setup>
import { computed } from 'vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileFieldList from './ProfileFieldList.vue';
import { profileFieldState } from '../utils/profileDisplay';

const props = defineProps({
    profile: { type: Object, required: true },
});

/** Liên hệ — không lặp mã NV / chức danh / phòng ban. */
const contactFields = computed(() => {
    const p = props.profile;
    return [
        {
            label: 'Email',
            value: p.email,
            href: p.email ? `mailto:${p.email}` : null,
            mono: true,
        },
        {
            label: 'Điện thoại',
            value: p.phone,
            href: p.phone ? `tel:${p.phone}` : null,
        },
        { label: 'Địa điểm', value: p.location },
    ];
});

const bioState = computed(() => profileFieldState(props.profile.bio));
const hasBio = computed(() => !bioState.value.empty);
</script>

<template>
  <ProfileInfoPanel
    title="Liên hệ"
    icon="mail"
    section-key="profile-contact"
  >
    <div
      v-if="hasBio"
      class="border-b border-slate-100 px-5 py-4"
    >
      <p class="text-[12px] font-medium text-slate-500">
        Giới thiệu
      </p>
      <p class="mt-2 text-[13px] leading-relaxed text-slate-700">
        {{ bioState.text }}
      </p>
    </div>
    <ProfileFieldList :fields="contactFields" />
  </ProfileInfoPanel>
</template>
