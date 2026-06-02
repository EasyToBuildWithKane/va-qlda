<script setup>
import { toastList, dismissToast } from '@/composables/useToast';
import AppIcon from '@/Components/AppIcon.vue';

const cfg = {
    success: { wrap: 'bg-emerald-50 border-emerald-200 text-emerald-800', icon: 'check', ic: 'text-emerald-500' },
    error:   { wrap: 'bg-rose-50 border-rose-200 text-rose-800',       icon: 'close', ic: 'text-rose-500'    },
    info:    { wrap: 'bg-sky-50 border-sky-200 text-sky-800',           icon: 'info',  ic: 'text-sky-500'     },
    warning: { wrap: 'bg-amber-50 border-amber-200 text-amber-800',     icon: 'flag',  ic: 'text-amber-500'   },
};
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-5 right-5 z-[70] flex flex-col-reverse gap-2 items-end pointer-events-none">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-3 scale-95"
                leave-active-class="transition duration-200 ease-in absolute"
                leave-to-class="opacity-0 translate-y-3 scale-95"
                move-class="transition duration-200"
            >
                <div
                    v-for="t in toastList"
                    :key="t.id"
                    class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg min-w-[300px] max-w-sm"
                    :class="cfg[t.type]?.wrap"
                >
                    <span class="mt-0.5 shrink-0" :class="cfg[t.type]?.ic">
                        <AppIcon :name="cfg[t.type]?.icon" :size="16" />
                    </span>
                    <p class="text-sm font-medium flex-1 leading-snug">{{ t.message }}</p>
                    <button
                        type="button"
                        class="shrink-0 opacity-50 hover:opacity-100 transition"
                        @click="dismissToast(t.id)"
                    >
                        <AppIcon name="close" :size="13" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
