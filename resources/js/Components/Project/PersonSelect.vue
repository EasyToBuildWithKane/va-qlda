<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/Components/Project/Avatar.vue';

const props = defineProps({
    // [{ id, name, avatar_path }]
    options: { type: Array, default: () => [] },
    modelValue: { type: [Number, String, null], default: null },
    placeholder: { type: String, default: 'Tìm & chọn người…' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');
const root = ref(null);

const selected = computed(() => props.options.find((o) => o.id === props.modelValue) || null);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => o.name.toLowerCase().includes(q));
});

const choose = (o) => {
    emit('update:modelValue', o ? o.id : null);
    open.value = false;
    search.value = '';
};

const onBlur = (e) => {
    // Close when focus leaves the whole widget.
    if (root.value && !root.value.contains(e.relatedTarget)) open.value = false;
};
</script>

<template>
    <div ref="root" class="relative" @focusout="onBlur">
        <button
            :id="id"
            type="button"
            class="input flex w-full items-center gap-2 text-left"
            @click="open = !open"
        >
            <template v-if="selected">
                <Avatar :name="selected.name" :src="selected.avatar_path" :size="22" />
                <span class="flex-1 truncate text-slate-700">{{ selected.name }}</span>
                <span
                    class="grid h-5 w-5 place-items-center rounded text-slate-300 hover:text-rose-500"
                    role="button"
                    aria-label="Bỏ chọn"
                    @click.stop="choose(null)"
                >
                    <AppIcon name="close" :size="14" />
                </span>
            </template>
            <template v-else>
                <span class="flex-1 truncate text-slate-400">{{ placeholder }}</span>
                <AppIcon name="chevron-down" :size="16" class="text-slate-400" />
            </template>
        </button>

        <div
            v-if="open"
            class="absolute z-30 mt-1 w-full overflow-hidden rounded-card border border-slate-200 bg-white shadow-elevation-2"
        >
            <div class="border-b border-slate-100 p-2">
                <div class="relative">
                    <AppIcon name="search" :size="15" class="pointer-events-none absolute left-2.5 top-2.5 text-slate-400" />
                    <input
                        v-model="search"
                        type="text"
                        class="input py-1.5 pl-8 text-sm"
                        placeholder="Tìm theo tên…"
                        autofocus
                    />
                </div>
            </div>
            <ul class="max-h-56 overflow-y-auto py-1">
                <li
                    v-for="o in filtered"
                    :key="o.id"
                    class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50"
                    :class="o.id === modelValue ? 'bg-brand-50' : ''"
                    @click="choose(o)"
                >
                    <Avatar :name="o.name" :src="o.avatar_path" :size="24" />
                    <span class="flex-1 truncate text-slate-700">{{ o.name }}</span>
                    <AppIcon v-if="o.id === modelValue" name="check" :size="15" class="text-brand" />
                </li>
                <li v-if="filtered.length === 0" class="px-3 py-3 text-center text-sm text-slate-400">
                    Không tìm thấy.
                </li>
            </ul>
        </div>
    </div>
</template>
