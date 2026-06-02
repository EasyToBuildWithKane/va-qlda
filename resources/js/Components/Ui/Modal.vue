<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-lg' },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 py-10"
                @click.self="emit('close')"
                @keydown.esc="emit('close')"
            >
                <div class="card w-full p-6 shadow-elevation-3" :class="maxWidth">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <h2 class="font-display text-lg font-semibold text-slate-800">{{ title }}</h2>
                        <button
                            type="button"
                            class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                            @click="emit('close')"
                        >
                            <AppIcon name="close" :size="18" />
                        </button>
                    </div>
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
