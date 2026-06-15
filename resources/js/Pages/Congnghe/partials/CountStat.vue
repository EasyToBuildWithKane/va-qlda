<script setup>
import { toRef } from 'vue';
import { useCountUp } from './motion.js';

const props = defineProps({
    value: { type: Number, default: 0 },
    active: { type: Boolean, default: false },
});

const display = useCountUp(toRef(props, 'value'), toRef(props, 'active'));
</script>

<template>
  <span>{{ display.toLocaleString('vi-VN') }}</span>
</template>
