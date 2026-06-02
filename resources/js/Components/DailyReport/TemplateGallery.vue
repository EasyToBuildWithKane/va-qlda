<script setup>
import { ref, computed, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDialog } from '@/composables/useDialog';

const dialog = useDialog();

const props = defineProps({
    open: { type: Boolean, default: false },
    builtins: { type: Array, default: () => [] },
    // Current form values — used when saving "my template".
    content: { type: Object, default: () => ({}) },
    // Per-user localStorage key for saved templates.
    storageKey: { type: String, required: true },
    initialTab: { type: String, default: 'builtin' },
});

const emit = defineEmits(['update:open', 'apply']);

const tab = ref('builtin'); // 'builtin' | 'mine'
const saved = ref([]);
const newName = ref('');

const load = () => {
    try {
        saved.value = JSON.parse(localStorage.getItem(props.storageKey) || '[]');
    } catch {
        saved.value = [];
    }
};
const persist = () => localStorage.setItem(props.storageKey, JSON.stringify(saved.value));

watch(() => props.open, (v) => {
    if (!v) return;
    load();
    tab.value = props.initialTab;
}, { immediate: true });

const hasContent = computed(() =>
    Object.values(props.content).some((v) => typeof v === 'string' && v.trim().length > 0),
);

const close = () => emit('update:open', false);

const apply = (tpl) => {
    emit('apply', tpl.content);
    close();
};

const saveCurrent = () => {
    const name = newName.value.trim();
    if (!name || !hasContent.value) return;
    saved.value.unshift({
        id: `u-${Date.now()}`,
        name,
        savedAt: new Date().toISOString(),
        content: { ...props.content },
    });
    persist();
    newName.value = '';
    tab.value = 'mine';
};

const remove = async (tpl) => {
    const ok = await dialog.confirm({
        title: 'Xoá mẫu?',
        message: `Xoá mẫu “${tpl.name}”? Thao tác này không thể hoàn tác.`,
        confirmText: 'Xoá',
        cancelText: 'Huỷ',
        tone: 'danger',
    });
    if (!ok) return;
    saved.value = saved.value.filter((t) => t.id !== tpl.id);
    persist();
};

const fmtDate = (iso) =>
    new Date(iso).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
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
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 sm:p-8"
                @click.self="close"
                @keydown.esc="close"
            >
                <div class="card w-full max-w-2xl shadow-elevation-3">
                    <!-- Header -->
                    <div class="flex items-start justify-between border-b border-slate-100 p-5">
                        <div>
                            <h2 class="font-display text-lg font-semibold text-slate-800">
                                Thư viện mẫu báo cáo
                            </h2>
                            <p class="mt-0.5 text-sm text-slate-500">
                                Chọn một mẫu rồi bấm <b>Áp dụng</b> để điền nhanh vào biểu mẫu.
                            </p>
                        </div>
                        <button class="btn-ghost px-2 py-1 text-slate-400" @click="close" aria-label="Đóng">✕</button>
                    </div>

                    <!-- Tabs -->
                    <div class="flex gap-1 border-b border-slate-100 px-5 pt-3">
                        <button
                            class="rounded-t-btn px-4 py-2 text-sm font-medium transition"
                            :class="tab === 'builtin' ? 'bg-brand-50 text-brand' : 'text-slate-500 hover:text-slate-700'"
                            @click="tab = 'builtin'"
                        >
                            Mẫu có sẵn
                        </button>
                        <button
                            class="rounded-t-btn px-4 py-2 text-sm font-medium transition"
                            :class="tab === 'mine' ? 'bg-brand-50 text-brand' : 'text-slate-500 hover:text-slate-700'"
                            @click="tab = 'mine'"
                        >
                            Mẫu của tôi
                            <span v-if="saved.length" class="ml-1 rounded-full bg-slate-200 px-1.5 text-xs text-slate-600">{{ saved.length }}</span>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="max-h-[60vh] overflow-y-auto p-5">
                        <!-- Built-in templates -->
                        <div v-if="tab === 'builtin'" class="space-y-3">
                            <div
                                v-for="tpl in builtins"
                                :key="tpl.id"
                                class="flex items-center gap-4 rounded-card border border-slate-200 bg-white p-4 transition hover:border-brand-200 hover:shadow-elevation-1"
                            >
                                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-card bg-brand-50 text-brand">
                                    <AppIcon name="template" :size="20" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="truncate font-semibold text-slate-800">{{ tpl.name }}</h3>
                                        <span v-if="tpl.badge" class="rounded-full bg-accent/15 px-2 py-0.5 text-[10px] font-semibold text-brand">{{ tpl.badge }}</span>
                                    </div>
                                    <p class="mt-0.5 text-sm text-slate-500">{{ tpl.desc }}</p>
                                </div>
                                <button class="btn-primary shrink-0" @click="apply(tpl)">Áp dụng</button>
                            </div>
                        </div>

                        <!-- My templates -->
                        <div v-else class="space-y-4">
                            <div class="rounded-card border border-dashed border-slate-300 bg-slate-50 p-4">
                                <p class="mb-2 text-sm font-medium text-slate-700"> Lưu nội dung hiện tại thành mẫu</p>
                                <div class="flex gap-2">
                                    <input
                                        v-model="newName"
                                        type="text"
                                        class="input"
                                        placeholder="Tên mẫu, vd: Mẫu báo cáo Sprint"
                                        @keyup.enter="saveCurrent"
                                    />
                                    <button class="btn-primary shrink-0" :disabled="!newName.trim() || !hasContent" @click="saveCurrent">
                                        Lưu mẫu
                                    </button>
                                </div>
                                <p v-if="!hasContent" class="mt-1.5 text-xs text-amber-600">
                                    Hãy nhập nội dung báo cáo trước khi lưu thành mẫu.
                                </p>
                                <p class="mt-1.5 text-xs text-slate-400">Mẫu được lưu trên trình duyệt của thiết bị này.</p>
                            </div>

                            <div v-if="!saved.length" class="py-6 text-center text-sm text-slate-400">
                                Bạn chưa lưu mẫu nào. Hãy tạo mẫu đầu tiên ở trên
                            </div>
                            <div
                                v-for="tpl in saved"
                                :key="tpl.id"
                                class="flex items-center gap-4 rounded-card border border-slate-200 bg-white p-4"
                            >
                                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-card bg-emerald-50 text-emerald-600">
                                    <AppIcon name="template" :size="20" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-semibold text-slate-800">{{ tpl.name }}</h3>
                                    <p class="mt-0.5 text-xs text-slate-400">Đã lưu ngày {{ fmtDate(tpl.savedAt) }}</p>
                                </div>
                                <button class="btn-ghost shrink-0 text-danger hover:bg-danger/10" @click="remove(tpl)">Xóa</button>
                                <button class="btn-primary shrink-0" @click="apply(tpl)">Áp dụng</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
