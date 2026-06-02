<script setup>
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    value: { type: [String, Number], required: true },
    label: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const select = () => emit('update:modelValue', props.value);
</script>

<template>
    <button
        type="button"
        role="radio"
        :aria-checked="modelValue === value"
        class="group flex w-full items-start gap-3 rounded-card border p-3 text-left transition"
        :class="modelValue === value
            ? 'border-brand bg-brand-50 ring-1 ring-brand/40'
            : 'border-slate-200 bg-white hover:border-brand-300 hover:bg-slate-50'"
        @click="select"
    >
        <span
            class="mt-0.5 grid h-9 w-9 shrink-0 place-items-center rounded-btn transition"
            :class="modelValue === value ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-brand-100 group-hover:text-brand'"
        >
            <AppIcon v-if="icon" :name="icon" :size="18" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex items-center gap-1.5">
                <span class="text-sm font-semibold text-slate-800">{{ label }}</span>
                <span
                    v-if="modelValue === value"
                    class="grid h-4 w-4 place-items-center rounded-full bg-brand text-white"
                >
                    <AppIcon name="check" :size="11" />
                </span>
            </span>
            <span v-if="description" class="mt-0.5 block text-xs leading-snug text-slate-500">{{ description }}</span>
        </span>
    </button>
</template>
