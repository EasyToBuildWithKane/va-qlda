<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import FieldTooltip from '@/Components/Project/FieldTooltip.vue';
import MoneyInput from '@/Components/Project/MoneyInput.vue';
import RadioCard from '@/Components/Project/RadioCard.vue';
import MultiChips from '@/Components/Project/MultiChips.vue';
import PersonSelect from '@/Components/Project/PersonSelect.vue';
import { currency } from '@/composables/useFormat';

const props = defineProps({
    project: { type: Object, default: null },
    employees: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    typeOptions: { type: Array, default: () => [] },
    scopeOptions: { type: Array, default: () => [] },
    regionOptions: { type: Array, default: () => [] },
    departmentOptions: { type: Array, default: () => [] },
    suggestedCode: { type: String, default: '' },
});

const colors = ['brand', 'sky', 'emerald', 'violet', 'amber', 'rose', 'cyan', 'slate'];
const swatch = {
    brand: 'bg-brand', sky: 'bg-sky-500', emerald: 'bg-emerald-500', violet: 'bg-violet-500',
    amber: 'bg-amber-500', rose: 'bg-rose-500', cyan: 'bg-cyan-500', slate: 'bg-slate-400',
};

const form = useForm({
    code: props.project?.code ?? props.suggestedCode,
    name: props.project?.name ?? '',
    description: props.project?.description ?? '',
    color: props.project?.color ?? 'brand',
    status: props.project?.status?.value ?? 'planning',
    type: props.project?.type?.value ?? 'rnd',
    scope: props.project?.scope?.value ?? 'headquarters',
    scope_regions: props.project?.scope_regions ?? [],
    scope_departments: props.project?.scope_departments ?? [],
    department_id: props.project?.department_id ?? null,
    start_date: props.project?.start_date ?? null,
    due_date: props.project?.due_date ?? null,
    budget: props.project?.budget ?? null,
    actual_budget: props.project?.actual_budget ?? null,
    manager_id: props.project?.manager_id ?? null,
    is_active: props.project?.is_active ?? true,
});

// Department options shaped for the multi-chip picker.
const departmentChips = computed(() => props.departmentOptions.map((d) => ({ value: d.id, label: d.name })));

// Clear the irrelevant scope targets when the scope changes.
watch(() => form.scope, (s) => {
    if (s !== 'regional') form.scope_regions = [];
    if (s !== 'departmental') form.scope_departments = [];
});

// ---- Validation (client-side live + server) -------------------------------
const submitted = ref(false);
const touched = reactive(new Set());
const touch = (f) => touched.add(f);

const liveErrors = computed(() => {
    const e = {};
    if (!form.name?.trim()) e.name = 'Tên dự án không được để trống.';
    if (!form.scope) e.scope = 'Phải chọn ít nhất một phạm vi áp dụng.';
    if (form.budget !== null && form.budget !== '' && Number(form.budget) <= 0) e.budget = 'Ngân sách phải lớn hơn 0.';
    if (form.scope === 'regional' && form.scope_regions.length === 0) e.scope_regions = 'Chọn ít nhất một khu vực.';
    if (form.scope === 'departmental' && form.scope_departments.length === 0) e.scope_departments = 'Chọn ít nhất một phòng ban.';
    if (form.start_date && form.due_date && form.due_date < form.start_date) e.due_date = 'Ngày kết thúc phải lớn hơn ngày bắt đầu.';
    return e;
});

const errFor = (f) => form.errors[f] || ((submitted.value || touched.has(f)) ? liveErrors.value[f] : null);

// ---- Computed helpers -----------------------------------------------------
const dayCount = computed(() => {
    if (!form.start_date || !form.due_date) return null;
    const s = new Date(form.start_date);
    const d = new Date(form.due_date);
    if (Number.isNaN(s) || Number.isNaN(d) || d < s) return null;
    return Math.round((d - s) / 86400000) + 1;
});

const budgetDiff = computed(() => {
    if (form.budget === null || form.budget === '' || form.actual_budget === null || form.actual_budget === '') return null;
    return Number(form.budget) - Number(form.actual_budget);
});

// ---- Live "data status" panel ---------------------------------------------
const tracked = computed(() => [
    { key: 'name', label: 'Tên dự án', required: true, filled: !!form.name?.trim() },
    { key: 'code', label: 'Mã dự án', filled: !!form.code?.trim() },
    { key: 'type', label: 'Loại dự án', required: true, filled: !!form.type },
    { key: 'scope', label: 'Phạm vi áp dụng', required: true, filled: !!form.scope },
    { key: 'department_id', label: 'Phòng ban phụ trách', filled: !!form.department_id },
    { key: 'budget', label: 'Ngân sách dự kiến', filled: form.budget !== null && form.budget !== '' },
    { key: 'start_date', label: 'Ngày bắt đầu', filled: !!form.start_date },
    { key: 'due_date', label: 'Ngày kết thúc', filled: !!form.due_date },
    { key: 'manager_id', label: 'Chủ dự án', filled: !!form.manager_id },
    { key: 'description', label: 'Mô tả', filled: !!form.description?.trim() },
]);
const filledCount = computed(() => tracked.value.filter((t) => t.filled).length);
const completion = computed(() => Math.round((filledCount.value / tracked.value.length) * 100));
const missingRequired = computed(() => tracked.value.filter((t) => t.required && !t.filled));

const steps = [
    'Chọn loại dự án',
    'Chọn phạm vi áp dụng',
    'Chọn phòng ban phụ trách',
    'Khai báo ngân sách',
    'Thiết lập thời gian',
    'Chọn người chịu trách nhiệm',
    'Lưu dự án',
];
const tips = [
    'Tên dự án nên ngắn gọn, dễ nhận diện.',
    'Ngân sách nhập số nguyên (VNĐ).',
    'Chọn đúng phạm vi triển khai.',
    'Đính kèm/ghi chú tài liệu nếu có ở phần mô tả.',
];

// ---- Submit ---------------------------------------------------------------
const submit = (after = 'close') => {
    submitted.value = true;
    if (after === 'draft') {
        form.status = 'planning';
        after = 'close';
    }
    form.transform((data) => ({ ...data, after }));
    if (props.project) form.put(`/projects/${props.project.id}`);
    else form.post('/projects');
};
</script>

<template>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-4">
        <!-- ===== Left guide sidebar ===== -->
        <aside class="lg:col-span-1">
            <div class="space-y-4 lg:sticky lg:top-4">
                <!-- Quy trình -->
                <div class="card p-4">
                    <h3 class="mb-3 flex items-center gap-2 font-display text-sm font-semibold text-slate-800">
                        <AppIcon name="info" :size="16" class="text-brand" /> Hướng dẫn nhanh
                    </h3>
                    <ol class="space-y-2">
                        <li v-for="(s, i) in steps" :key="i" class="flex items-start gap-2.5 text-sm text-slate-600">
                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-50 text-[11px] font-semibold text-brand">{{ i + 1 }}</span>
                            <span class="leading-snug">{{ s }}</span>
                        </li>
                    </ol>
                </div>

                <!-- Mẹo -->
                <div class="card p-4">
                    <h3 class="mb-2 font-display text-sm font-semibold text-slate-800">Mẹo nhập liệu</h3>
                    <ul class="space-y-1.5">
                        <li v-for="(t, i) in tips" :key="i" class="flex items-start gap-2 text-xs leading-snug text-slate-500">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-accent"></span>{{ t }}
                        </li>
                    </ul>
                </div>

                <!-- Trạng thái dữ liệu -->
                <div class="card p-4">
                    <h3 class="mb-3 font-display text-sm font-semibold text-slate-800">Trạng thái dữ liệu</h3>
                    <div class="mb-2 flex items-end justify-between">
                        <span class="text-xs text-slate-500">Hoàn thành</span>
                        <span class="font-display text-lg font-bold text-brand">{{ completion }}%</span>
                    </div>
                    <ProgressBar :value="completion" :show-label="false" />
                    <div class="mt-3 flex items-center justify-between text-xs text-slate-500">
                        <span>Đã nhập: <b class="text-slate-700">{{ filledCount }}</b>/{{ tracked.length }}</span>
                        <span>Thiếu: <b :class="missingRequired.length ? 'text-danger' : 'text-emerald-600'">{{ missingRequired.length }}</b> bắt buộc</span>
                    </div>
                    <ul v-if="missingRequired.length" class="mt-3 space-y-1 border-t border-slate-100 pt-3">
                        <li v-for="m in missingRequired" :key="m.key" class="flex items-center gap-1.5 text-xs text-danger">
                            <AppIcon name="close" :size="12" /> Thiếu: {{ m.label }}
                        </li>
                    </ul>
                    <p v-else class="mt-3 flex items-center gap-1.5 border-t border-slate-100 pt-3 text-xs text-emerald-600">
                        <AppIcon name="check" :size="13" /> Đã đủ trường bắt buộc.
                    </p>
                </div>
            </div>
        </aside>

        <!-- ===== Right form column ===== -->
        <form class="space-y-5 lg:col-span-3" @submit.prevent="submit('close')">
            <!-- Section 1: Thông tin cơ bản -->
            <section class="card p-6">
                <h2 class="mb-4 flex items-center gap-2 font-display text-base font-semibold text-slate-800">
                    <span class="grid h-7 w-7 place-items-center rounded-btn bg-brand-50 text-brand"><AppIcon name="edit" :size="16" /></span>
                    Thông tin cơ bản
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="label flex items-center gap-1.5">
                            Tên dự án <span class="text-danger">*</span>
                            <FieldTooltip text="Tên ngắn gọn, dễ nhận diện dự án trong toàn hệ thống." />
                        </label>
                        <input
                            v-model="form.name" type="text" class="input"
                            :class="errFor('name') ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
                            placeholder="Ví dụ: Triển khai hệ thống ERP toàn hệ thống năm 2026"
                            @blur="touch('name')"
                        />
                        <p v-if="errFor('name')" class="mt-1 flex items-center gap-1 text-xs text-danger"><AppIcon name="close" :size="12" /> {{ errFor('name') }}</p>
                    </div>
                    <div>
                        <label class="label flex items-center gap-1.5">
                            Mã dự án
                            <FieldTooltip text="Mã định danh duy nhất, tự sinh nhưng có thể chỉnh sửa." />
                        </label>
                        <input v-model="form.code" type="text" class="input font-mono" placeholder="PRJ-001" />
                        <p v-if="form.errors.code" class="mt-1 text-xs text-danger">{{ form.errors.code }}</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label flex items-center gap-1.5">
                            Loại dự án <span class="text-danger">*</span>
                            <FieldTooltip text="Vòng đời dự án: nghiên cứu, triển khai hoặc vận hành." />
                        </label>
                        <select v-model="form.type" class="input">
                            <option v-for="o in typeOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1 text-xs text-danger">{{ form.errors.type }}</p>
                    </div>
                    <div>
                        <label class="label">Màu nhãn</label>
                        <div class="flex flex-wrap gap-2 pt-1.5">
                            <button
                                v-for="c in colors" :key="c" type="button"
                                class="h-7 w-7 rounded-full ring-offset-2 transition"
                                :class="[swatch[c], form.color === c ? 'ring-2 ring-brand' : '']"
                                :aria-label="c" @click="form.color = c"
                            ></button>
                        </div>
                    </div>
                    <div class="sm:col-span-3">
                        <label class="label flex items-center gap-1.5">
                            Mô tả
                            <FieldTooltip text="Mục tiêu, phạm vi và ghi chú tài liệu liên quan." />
                        </label>
                        <textarea v-model="form.description" rows="3" class="input resize-none" placeholder="Mô tả mục tiêu, phạm vi, kết quả kỳ vọng…"></textarea>
                    </div>
                </div>
            </section>

            <!-- Section 2: Phạm vi triển khai -->
            <section class="card p-6">
                <h2 class="mb-1 flex items-center gap-2 font-display text-base font-semibold text-slate-800">
                    <span class="grid h-7 w-7 place-items-center rounded-btn bg-brand-50 text-brand"><AppIcon name="globe" :size="16" /></span>
                    Phạm vi triển khai
                </h2>
                <p class="mb-4 text-xs text-slate-500">Chọn nơi dự án được áp dụng. <span class="text-danger">*</span></p>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" role="radiogroup">
                    <RadioCard
                        v-for="o in scopeOptions" :key="o.value"
                        v-model="form.scope" :value="o.value" :label="o.label" :description="o.description" :icon="o.icon"
                    />
                </div>
                <p v-if="errFor('scope')" class="mt-2 flex items-center gap-1 text-xs text-danger"><AppIcon name="close" :size="12" /> {{ errFor('scope') }}</p>

                <!-- Conditional: regions -->
                <div v-if="form.scope === 'regional'" class="mt-4 rounded-card border border-amber-200 bg-amber-50/60 p-4">
                    <label class="label flex items-center gap-1.5">Khu vực áp dụng <FieldTooltip text="Chọn các khu vực/chi nhánh dự án sẽ triển khai." /></label>
                    <MultiChips v-model="form.scope_regions" :options="regionOptions" count-label="khu vực" />
                    <p v-if="errFor('scope_regions')" class="mt-2 flex items-center gap-1 text-xs text-danger"><AppIcon name="close" :size="12" /> {{ errFor('scope_regions') }}</p>
                </div>

                <!-- Conditional: departments -->
                <div v-if="form.scope === 'departmental'" class="mt-4 rounded-card border border-cyan-200 bg-cyan-50/60 p-4">
                    <label class="label flex items-center gap-1.5">Phòng ban áp dụng <FieldTooltip text="Chọn các phòng ban nằm trong phạm vi dự án." /></label>
                    <MultiChips v-model="form.scope_departments" :options="departmentChips" count-label="phòng ban" />
                    <p v-if="errFor('scope_departments')" class="mt-2 flex items-center gap-1 text-xs text-danger"><AppIcon name="close" :size="12" /> {{ errFor('scope_departments') }}</p>
                </div>

                <!-- Owning department (phụ trách) — distinct from scope -->
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <label class="label flex items-center gap-1.5">
                        Phòng ban phụ trách
                        <FieldTooltip text="Phòng ban chịu trách nhiệm chính (khác với phạm vi áp dụng)." />
                    </label>
                    <select v-model="form.department_id" class="input sm:max-w-sm">
                        <option :value="null">— Chưa gán —</option>
                        <option v-for="d in departmentOptions" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <p v-if="form.errors.department_id" class="mt-1 text-xs text-danger">{{ form.errors.department_id }}</p>
                </div>
            </section>

            <!-- Section 3: Tài chính -->
            <section class="card p-6">
                <h2 class="mb-4 flex items-center gap-2 font-display text-base font-semibold text-slate-800">
                    <span class="grid h-7 w-7 place-items-center rounded-btn bg-brand-50 text-brand"><AppIcon name="budget" :size="16" /></span>
                    Tài chính
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label flex items-center gap-1.5">
                            Ngân sách dự kiến
                            <FieldTooltip text="Tổng chi phí ước tính của dự án trước khi triển khai." />
                        </label>
                        <MoneyInput v-model="form.budget" :invalid="!!errFor('budget')" />
                        <p v-if="errFor('budget')" class="mt-1 flex items-center gap-1 text-xs text-danger"><AppIcon name="close" :size="12" /> {{ errFor('budget') }}</p>
                    </div>
                    <div>
                        <label class="label flex items-center gap-1.5">
                            Ngân sách thực tế
                            <FieldTooltip text="Chi phí thực tế đã/được duyệt cho dự án." />
                        </label>
                        <MoneyInput v-model="form.actual_budget" :invalid="!!form.errors.actual_budget" />
                        <p v-if="form.errors.actual_budget" class="mt-1 text-xs text-danger">{{ form.errors.actual_budget }}</p>
                    </div>
                    <div>
                        <label class="label">Chênh lệch</label>
                        <div
                            class="flex h-[42px] items-center justify-end rounded-input border px-3 text-right font-semibold tabular-nums"
                            :class="budgetDiff === null ? 'border-slate-200 bg-slate-50 text-slate-400'
                                : budgetDiff >= 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700'"
                        >
                            {{ budgetDiff === null ? '—' : currency(budgetDiff) }}
                        </div>
                        <p class="mt-1 text-xs text-slate-400">
                            {{ budgetDiff === null ? 'Dự kiến − thực tế' : budgetDiff >= 0 ? 'Trong ngân sách' : 'Vượt ngân sách' }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Section 4: Thời gian -->
            <section class="card p-6">
                <h2 class="mb-4 flex items-center gap-2 font-display text-base font-semibold text-slate-800">
                    <span class="grid h-7 w-7 place-items-center rounded-btn bg-brand-50 text-brand"><AppIcon name="calendar-clock" :size="16" /></span>
                    Thời gian thực hiện
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">Ngày bắt đầu</label>
                        <input v-model="form.start_date" type="date" class="input" @blur="touch('due_date')" />
                    </div>
                    <div>
                        <label class="label">Ngày kết thúc</label>
                        <input
                            v-model="form.due_date" type="date" class="input"
                            :class="errFor('due_date') ? 'border-danger focus:border-danger focus:ring-danger/30' : ''"
                            @blur="touch('due_date')"
                        />
                    </div>
                    <div>
                        <label class="label">Số ngày thực hiện</label>
                        <div class="flex h-[42px] items-center gap-2 rounded-input border border-slate-200 bg-slate-50 px-3">
                            <AppIcon name="clock" :size="16" class="text-slate-400" />
                            <span class="font-semibold text-slate-700">{{ dayCount === null ? '—' : dayCount + ' ngày' }}</span>
                        </div>
                    </div>
                </div>
                <p v-if="errFor('due_date')" class="mt-2 flex items-center gap-1 text-xs text-danger"><AppIcon name="close" :size="12" /> {{ errFor('due_date') }}</p>
            </section>

            <!-- Section 5: Nhân sự -->
            <section class="card p-6">
                <h2 class="mb-4 flex items-center gap-2 font-display text-base font-semibold text-slate-800">
                    <span class="grid h-7 w-7 place-items-center rounded-btn bg-brand-50 text-brand"><AppIcon name="members" :size="16" /></span>
                    Nhân sự
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label flex items-center gap-1.5">
                            Chủ dự án / Quản lý
                            <FieldTooltip text="Người chịu trách nhiệm chính, theo dõi tiến độ & ngân sách." />
                        </label>
                        <PersonSelect v-model="form.manager_id" :options="employees" />
                        <p v-if="form.errors.manager_id" class="mt-1 text-xs text-danger">{{ form.errors.manager_id }}</p>
                    </div>
                    <div>
                        <label class="label">Trạng thái dự án</label>
                        <select v-model="form.status" class="input">
                            <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                    </div>
                </div>
                <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.is_active" type="checkbox" class="rounded" /> Dự án đang hoạt động
                </label>
                <p class="mt-3 flex items-start gap-1.5 rounded-btn bg-slate-50 p-2.5 text-xs text-slate-500">
                    <AppIcon name="info" :size="14" class="mt-0.5 shrink-0 text-slate-400" />
                    Thành viên tham gia được quản lý ở trang chi tiết dự án sau khi tạo.
                </p>
            </section>

            <!-- Sticky footer action bar -->
            <div class="sticky bottom-0 z-20 -mx-1">
                <div class="card flex flex-wrap items-center gap-2 px-4 py-3 shadow-elevation-2">
                    <p v-if="form.processing" class="text-xs text-slate-400">Đang lưu…</p>
                    <p v-else-if="completion < 100" class="hidden text-xs text-slate-400 sm:block">Hoàn thành {{ completion }}% biểu mẫu</p>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <a href="/projects" class="btn-ghost">Hủy</a>
                        <button type="button" class="btn-ghost" :disabled="form.processing" @click="submit('draft')">Lưu nháp</button>
                        <button type="button" class="btn-ghost border border-slate-200" :disabled="form.processing" @click="submit('continue')">Lưu &amp; tiếp tục</button>
                        <button type="button" class="btn-primary" :disabled="form.processing" @click="submit('close')">
                            <AppIcon name="save" :size="16" /> {{ project ? 'Lưu & đóng' : 'Tạo & đóng' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>
