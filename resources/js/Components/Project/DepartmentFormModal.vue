<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    department: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const colors = ['brand', 'sky', 'emerald', 'violet', 'amber', 'rose', 'cyan', 'slate'];
const swatch = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};

const form = useForm({ code: '', name: '', color: 'slate', manager_id: null, sort_order: 0, is_active: true });

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.department) {
        form.code = props.department.code;
        form.name = props.department.name;
        form.color = props.department.color ?? 'slate';
        form.manager_id = props.department.manager?.id ?? null;
        form.sort_order = props.department.sort_order ?? 0;
        form.is_active = props.department.is_active;
    } else {
        form.reset();
    }
});

const submit = () => {
    const opts = { preserveScroll: true, onSuccess: () => { emit('saved'); emit('close'); } };
    if (props.department) form.put(`/departments/${props.department.id}`, opts);
    else form.post('/departments', opts);
};
</script>

<template>
    <Modal :show="show" :title="department ? 'Chỉnh sửa phòng ban' : 'Thêm phòng ban'" @close="emit('close')">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="label">Mã</label>
                    <input v-model="form.code" type="text" class="input" placeholder="PB-CNT" />
                    <p v-if="form.errors.code" class="mt-1 text-xs text-danger">{{ form.errors.code }}</p>
                </div>
                <div class="col-span-2">
                    <label class="label">Tên phòng ban</label>
                    <input v-model="form.name" type="text" class="input" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-danger">{{ form.errors.name }}</p>
                </div>
            </div>

            <div>
                <label class="label">Trưởng phòng</label>
                <select v-model="form.manager_id" class="input">
                    <option :value="null">— Chưa chọn —</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Thứ tự</label>
                    <input v-model.number="form.sort_order" type="number" min="0" class="input" />
                </div>
                <div>
                    <label class="label">Màu nhãn</label>
                    <div class="flex flex-wrap gap-2 pt-1.5">
                        <button
                            v-for="c in colors"
                            :key="c"
                            type="button"
                            class="h-7 w-7 rounded-full ring-offset-2 transition"
                            :class="[swatch[c], form.color === c ? 'ring-2 ring-brand' : '']"
                            @click="form.color = c"
                        ></button>
                    </div>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="form.is_active" type="checkbox" class="rounded" /> Đang hoạt động
            </label>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn-ghost" @click="emit('close')">Huỷ</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">{{ department ? 'Lưu' : 'Thêm' }}</button>
            </div>
        </form>
    </Modal>
</template>
