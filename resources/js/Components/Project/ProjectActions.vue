<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    canManage: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    editHref: { type: String, default: '' },
});

const emit = defineEmits(['create-task', 'add-member']);

const menuOpen = ref(false);
const menuRef = ref(null);

const closeMenu = () => {
    menuOpen.value = false;
};

const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
};

const onPointerDown = (event) => {
    if (!menuRef.value) return;
    if (!menuRef.value.contains(event.target)) closeMenu();
};

const onEscape = (event) => {
    if (event.key === 'Escape') closeMenu();
};

onMounted(() => {
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onPointerDown);
    document.removeEventListener('keydown', onEscape);
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <button
            v-if="canManage"
            type="button"
            class="btn-primary text-xs sm:text-sm"
            aria-label="Tạo công việc mới"
            @click="emit('create-task')"
        >
            <AppIcon name="add" :size="13" /> <span class="hidden lg:inline">Tạo việc</span>
        </button>
        <button
            v-if="canManage"
            type="button"
            class="btn-ghost text-xs sm:text-sm"
            aria-label="Thêm thành viên vào dự án"
            @click="emit('add-member')"
        >
            <AppIcon name="people" :size="13" /> <span class="hidden xl:inline">Thêm thành viên</span>
        </button>

        <div ref="menuRef" class="relative">
            <button
                type="button"
                class="btn-ghost text-xs sm:text-sm"
                aria-label="Mở nhóm thao tác dự án"
                :aria-expanded="menuOpen"
                aria-haspopup="menu"
                @click="toggleMenu"
            >
                <span class="hidden sm:inline">Thao tác</span>
                <AppIcon name="chevron-down" :size="13" class="transition" :class="menuOpen ? 'rotate-180' : ''" />
            </button>

            <transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="translate-y-1 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="translate-y-0 opacity-100"
                leave-to-class="translate-y-1 opacity-0"
            >
                <div
                    v-if="menuOpen"
                    class="absolute right-0 z-30 mt-2 w-52 overflow-hidden rounded-card border border-slate-200 bg-white py-1 shadow-lg"
                    role="menu"
                    aria-label="Danh sách thao tác dự án"
                >
                    <Link
                        v-if="canUpdate && editHref"
                        :href="editHref"
                        role="menuitem"
                        class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 transition hover:bg-slate-50"
                        @click="closeMenu"
                    >
                        <AppIcon name="edit" :size="14" /> Chỉnh sửa dự án
                    </Link>
                    <button
                        v-if="canManage"
                        type="button"
                        role="menuitem"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                        @click="emit('create-task'); closeMenu();"
                    >
                        <AppIcon name="task" :size="14" /> Tạo công việc
                    </button>
                    <button
                        v-if="canManage"
                        type="button"
                        role="menuitem"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                        @click="emit('add-member'); closeMenu();"
                    >
                        <AppIcon name="members" :size="14" /> Thêm thành viên
                    </button>
                </div>
            </transition>
        </div>
    </div>
</template>
