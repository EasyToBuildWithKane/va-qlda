<script setup>
import { useForm } from '@inertiajs/vue3';
import Drawer from '@/Components/Ui/Drawer.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    profile: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const categories = [
    { value: 'backend', label: 'Backend' },
    { value: 'frontend', label: 'Frontend' },
    { value: 'mobile', label: 'Mobile' },
    { value: 'data', label: 'Dữ liệu & CSDL' },
    { value: 'ai', label: 'AI Engineering' },
    { value: 'devops', label: 'DevOps & Hạ tầng' },
    { value: 'design', label: 'Thiết kế & UX' },
    { value: 'management', label: 'Quản lý & Quy trình' },
    { value: 'other', label: 'Khác' },
];

const levels = [1, 2, 3, 4, 5];

function initialSkills() {
    return (props.profile.skills?.groups || []).flatMap((g) => g.items.map((i) => ({
        name: i.name,
        category: i.category || g.key || '',
        level: i.level ?? 3,
        years: i.years ?? '',
        certified: !!i.certified,
        note: i.note ?? '',
    })));
}

const form = useForm({ skills: initialSkills() });

function addSkill() {
    if (form.skills.length < 40) {
        form.skills.push({ name: '', category: '', level: 3, years: '', certified: false, note: '' });
    }
}

function removeSkill(i) {
    form.skills.splice(i, 1);
}

function submit() {
    form
        .transform((data) => ({
            skills: data.skills.filter((s) => s.name.trim() !== ''),
        }))
        .put('/profile', {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
}
</script>

<template>
  <Drawer
    :show="show"
    title="Quản lý ma trận kỹ năng"
    width="max-w-2xl"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <p class="text-[13px] leading-relaxed text-slate-500">
        Khai báo kỹ năng theo lĩnh vực, mức độ (1–5), số năm kinh nghiệm và chứng chỉ.
        Dữ liệu này dựng nên bản đồ năng lực, điểm kỹ năng và gợi ý phát triển.
      </p>

      <div class="space-y-3">
        <div
          v-for="(s, i) in form.skills"
          :key="i"
          class="rounded-xl border border-slate-200/80 bg-slate-50/60 p-3"
        >
          <div class="flex items-center gap-2">
            <input
              v-model="s.name"
              type="text"
              class="input flex-1"
              placeholder="VD: Laravel"
            >
            <button
              type="button"
              class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-500"
              aria-label="Xoá kỹ năng"
              @click="removeSkill(i)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </div>

          <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <select
              v-model="s.category"
              class="input"
              aria-label="Lĩnh vực"
            >
              <option value="">
                Tự động
              </option>
              <option
                v-for="c in categories"
                :key="c.value"
                :value="c.value"
              >
                {{ c.label }}
              </option>
            </select>
            <select
              v-model.number="s.level"
              class="input"
              aria-label="Mức độ"
            >
              <option
                v-for="n in levels"
                :key="n"
                :value="n"
              >
                Cấp {{ n }}/5
              </option>
            </select>
            <input
              v-model="s.years"
              type="number"
              min="0"
              max="50"
              step="0.5"
              class="input"
              placeholder="Số năm"
              aria-label="Số năm kinh nghiệm"
            >
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-[12.5px] font-medium text-slate-600">
              <input
                v-model="s.certified"
                type="checkbox"
                class="h-4 w-4 rounded border-slate-300 text-brand focus:ring-brand/40"
              >
              Chứng chỉ
            </label>
          </div>

          <input
            v-model="s.note"
            type="text"
            maxlength="200"
            class="input mt-2 w-full"
            placeholder="Minh chứng / ghi chú (tuỳ chọn)"
            aria-label="Ghi chú"
          >
        </div>

        <p
          v-if="!form.skills.length"
          class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-[13px] text-slate-400"
        >
          Chưa có kỹ năng nào. Bấm «Thêm kỹ năng» để bắt đầu.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-brand/40 px-3 py-2 text-[13px] font-medium text-brand transition-colors hover:bg-brand/5"
        :disabled="form.skills.length >= 40"
        @click="addSkill"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm kỹ năng
      </button>
      <p
        v-if="form.errors.skills"
        class="text-xs text-danger"
      >
        {{ form.errors.skills }}
      </p>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2">
        <button
          type="button"
          class="rounded-lg px-4 py-2 text-[13px] font-medium text-slate-600 hover:bg-slate-100"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary"
          :disabled="form.processing"
          @click="submit"
        >
          {{ form.processing ? 'Đang lưu...' : 'Lưu ma trận kỹ năng' }}
        </button>
      </div>
    </template>
  </Drawer>
</template>
