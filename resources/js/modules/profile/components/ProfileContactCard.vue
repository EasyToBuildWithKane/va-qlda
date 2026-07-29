<script setup>
import { computed } from 'vue';
import ProfileInfoPanel from './ProfileInfoPanel.vue';
import ProfileFieldList from './ProfileFieldList.vue';
import { profileFieldState } from '../utils/profileDisplay';

const props = defineProps({
    profile: { type: Object, required: true },
});

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
</script>

<template>
  <ProfileInfoPanel
    title="Liên hệ & giới thiệu"
    icon="account"
    subtitle="Thông tin bạn có thể chỉnh sửa trong «Chỉnh sửa hồ sơ»"
    section-key="profile-contact"
  >
    <div class="border-b border-slate-100 px-5 py-4">
      <p class="text-[12px] font-medium text-slate-500">
        Giới thiệu
      </p>
      <p
        class="mt-2 text-[13px] leading-relaxed"
        :class="bioState.empty ? 'italic text-slate-400' : 'text-slate-700'"
      >
        {{ bioState.text }}
      </p>
    </div>
    <ProfileFieldList :fields="contactFields" />
  </ProfileInfoPanel>
</template>
