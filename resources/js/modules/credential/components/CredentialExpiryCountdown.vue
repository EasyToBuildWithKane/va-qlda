<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import {
    formatCredentialExpiryCountdown,
    isCredentialExpiringWithinDays,
} from '@/modules/credential/utils/credentialExpiry';

const props = defineProps({
    expiresAt: { type: String, default: null },
    now: { type: Number, required: true },
    withinDays: { type: Number, default: 7 },
});

const visible = computed(() => isCredentialExpiringWithinDays(props.expiresAt, props.withinDays));

const countdown = computed(() => {
    if (!visible.value) return null;
    return formatCredentialExpiryCountdown(props.expiresAt, props.now);
});
</script>

<template>
  <div
    v-if="countdown"
    class="mt-1 flex items-center gap-1 text-[11px] font-medium tabular-nums"
    :class="countdown.expired ? 'text-rose-600' : 'text-amber-800'"
  >
    <AppIcon
      name="clock"
      :size="11"
      class="shrink-0 opacity-80"
      aria-hidden="true"
    />
    <span>{{ countdown.text }}</span>
  </div>
</template>
