<script setup>
import { computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/Components/Project/FieldTooltip.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    show:                { type: Boolean, default: false },
    department:          { type: Object,  default: null },
    employees:           { type: Array,   default: () => [] },
    existingDepartments: { type: Array,   default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const colors = [
    { key: 'brand',   cls: 'bg-brand',       label: 'Xanh thương hiệu' },
    { key: 'sky',     cls: 'bg-sky-500',      label: 'Xanh trời'        },
    { key: 'emerald', cls: 'bg-emerald-500',  label: 'Xanh lá'          },
    { key: 'violet',  cls: 'bg-violet-500',   label: 'Tím'              },
    { key: 'amber',   cls: 'bg-amber-500',    label: 'Vàng'             },
    { key: 'rose',    cls: 'bg-rose-500',     label: 'Đỏ hồng'         },
    { key: 'cyan',    cls: 'bg-cyan-500',     label: 'Xanh cyan'        },
    { key: 'slate',   cls: 'bg-slate-400',    label: 'Xám'              },
];

const swatch = Object.fromEntries(colors.map(c => [c.key, c.cls]));

const genCode = () => {
    const nums = props.existingDepartments
        .map(d => parseInt((d.code ?? '').replace(/\D/g, '')) || 0);
    const max = nums.length ? Math.max(...nums) : 0;
    return 'PB-' + String(max + 1).padStart(3, '0');
};

const form = useForm({ code: '', name: '', color: 'brand', manager_id: null, is_active: true });

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.department) {
        form.code       = props.department.code;
        form.name       = props.department.name;
        form.color      = props.department.color ?? 'brand';
        form.manager_id = props.department.manager?.id ?? null;
        form.is_active  = props.department.is_active;
    } else {
        form.reset();
        form.code  = genCode();
        form.color = 'brand';
        form.is_active = true;
    }
});

const isEdit = computed(() => !!props.department);

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (isEdit.value) form.put(`/departments/${props.department.id}`, opts);
    else              form.post('/departments', opts);
};
</script>

<template>
    <Modal :show="show" :dirty="form.isDirty" :title="isEdit ? 'Chỉnh sửa phòng ban' : 'Thêm phòng ban mới'" max-width="max-w-xl" @close="emit('close')">
        <form class="space-y-5" @submit.prevent="submit">

            <!-- Code (auto-generated, read-only) -->
            <div>
                <label class="label flex items-center gap-1">
                    Mã phòng ban
                    <FieldTooltip text="Mã được tự động sinh ra theo thứ tự, không thể chỉnh sửa." />
                </label>
                <div class="relative">
                    <input
                        :value="form.code"
                        type="text"
                        class="input pr-10 bg-slate-50 text-slate-500 cursor-not-allowed font-mono tracking-wider"
                        placeholder="PB-001"
                        disabled
                        readonly
                    />
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                        <AppIcon name="archive" :size="15" />
                    </span>
                </div>
                <p class="mt-1 text-xs text-slate-400">Mã tự động, dùng để tra cứu nội bộ.</p>
            </div>

            <!-- Name -->
            <div>
                <label class="label flex items-center gap-1">
                    Tên phòng ban <span class="text-danger">*</span>
                    <FieldTooltip text="Tên đầy đủ của phòng ban, ví dụ: Phòng Công nghệ thông tin." />
                </label>
                <input
                    v-model="form.name"
                    type="text"
                    class="input"
                    placeholder="Ví dụ: Phòng Công nghệ thông tin"
                    required
                    autocomplete="off"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-danger">{{ form.errors.name }}</p>
            </div>

            <!-- Manager -->
            <div>
                <label class="label flex items-center gap-1">
                    Trưởng phòng
                    <FieldTooltip text="Người phụ trách chính của phòng ban. Có thể để trống và cập nhật sau." />
                </label>
                <select v-model="form.manager_id" class="input">
                    <option :value="null">— Chưa phân công —</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <p v-if="form.errors.manager_id" class="mt-1 text-xs text-danger">{{ form.errors.manager_id }}</p>
            </div>

            <!-- Color + Status row -->
            <div class="grid grid-cols-2 gap-5">
                <!-- Color -->
                <div>
                    <label class="label flex items-center gap-1">
                        Màu nhãn
                        <FieldTooltip text="Màu hiển thị nhận diện phòng ban trên danh sách và biểu đồ." />
                    </label>
                    <div class="flex flex-wrap gap-2 pt-1.5">
                        <button
                            v-for="c in colors"
                            :key="c.key"
                            type="button"
                            :title="c.label"
                            class="h-7 w-7 rounded-full ring-offset-2 transition-all hover:scale-110 focus:outline-none"
                            :class="[c.cls, form.color === c.key ? 'ring-2 ring-brand scale-110' : 'ring-0']"
                            @click="form.color = c.key"
                        />
                    </div>
                    <p class="mt-1.5 text-xs text-slate-400 capitalize">
                        {{ colors.find(c => c.key === form.color)?.label ?? '' }}
                    </p>
                </div>

                <!-- Status toggle -->
                <div>
                    <label class="label flex items-center gap-1">
                        Trạng thái
                        <FieldTooltip text="Phòng ban ngừng hoạt động sẽ không hiển thị khi tạo dự án mới." />
                    </label>
                    <button
                        type="button"
                        class="mt-1.5 flex items-center gap-3 rounded-lg border px-4 py-2.5 w-full transition-colors"
                        :class="form.is_active
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : 'border-slate-200 bg-slate-50 text-slate-500'"
                        @click="form.is_active = !form.is_active"
                    >
                        <!-- Toggle pill -->
                        <span
                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors duration-200"
                            :class="form.is_active ? 'bg-emerald-500' : 'bg-slate-300'"
                        >
                            <span
                                class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform duration-200"
                                :class="form.is_active ? 'translate-x-4' : 'translate-x-0'"
                            />
                        </span>
                        <span class="text-sm font-medium">{{ form.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}</span>
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2 pt-1 border-t border-slate-100">
                <button type="button" class="btn-ghost" @click="modalClose()">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    <AppIcon v-if="form.processing" name="refresh" :size="15" class="animate-spin" />
                    {{ isEdit ? 'Lưu thay đổi' : 'Thêm phòng ban' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
