<script setup>
import { computed, ref } from 'vue';
import SmartContextHint from '@/modules/onboarding/components/SmartContextHint.vue';
import { useOnboarding } from '@/modules/onboarding/composables/useOnboarding';
import { useSmartContext } from '@/modules/onboarding/composables/useSmartContext';

const ob = useOnboarding();
const { hint } = useSmartContext(ob.context, ob.role);

const hintDismissed = ref(false);

const showHint = computed(() => !hintDismissed.value);
</script>

<template>
  <SmartContextHint
    :hint="showHint ? hint : null"
    @dismiss="hintDismissed = true"
  />
</template>
