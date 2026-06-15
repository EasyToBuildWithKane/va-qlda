<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Drawer from '@/Components/Ui/Drawer.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    profile: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const levels = [1, 2, 3, 4, 5];

const DEFAULT_GROUP = 'Chung';

const groupSuggestions = computed(() => {
    const fromGroups = (props.profile.skills?.groups ?? []).map((g) => g.label);
    const fromItems = (props.profile.skills?.groups ?? []).flatMap((g) =>
        g.items.map((i) => i.category || g.label).filter(Boolean),
    );
    return [...new Set([...fromGroups, ...fromItems, DEFAULT_GROUP])].sort((a, b) =>
        a.localeCompare(b, 'vi'),
    );
});

function initialSkills() {
    return (props.profile.skills?.groups || []).flatMap((g) =>
        g.items.map((i) => ({
            name: i.name,
            category: i.category || g.label || DEFAULT_GROUP,
            level: i.level ?? 3,
            years: i.years ?? '',
            note: i.note ?? '',
        })),
    );
}

const form = useForm({ skills: initialSkills() });

function addSkill() {
    if (form.skills.length < 40) {
        form.skills.push({
            name: '',
            category: DEFAULT_GROUP,
            level: 3,
            years: '',
            note: '',
        });
    }
}

function removeSkill(i) {
    form.skills.splice(i, 1);
}

function submit() {
    form
        .transform((data) => ({
            skills: data.skills
                .filter((s) => s.name.trim() !== '')
                .map((s) => ({
                    ...s,
                    category: s.category?.trim() || DEFAULT_GROUP,
                })),
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
        Khai báo kỹ năng theo <strong class="font-medium text-slate-700">nhóm do bạn đặt tên</strong>
        (vd. «Lập trình Web», «DevOps», «Ngoại ngữ»), mức độ 1–5 và số năm kinh nghiệm.
        Ma trận hiển thị thanh mức độ theo từng nhóm.
      </p>

      <datalist id="profile-skill-groups">
        <option
          v-for="g in groupSuggestions"
          :key="g"
          :value="g"
        />
      </datalist>

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
              placeholder="Tên kỹ năng (VD: Laravel)"
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

          <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
            <input
              v-model="s.category"
              type="text"
              list="profile-skill-groups"
              class="input sm:col-span-1"
              placeholder="Nhóm kỹ năng"
              aria-label="Nhóm kỹ năng"
            >
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
                Mức {{ n }}/5
              </option>
            </select>
            <input
              v-model="s.years"
              type="number"
              min="0"
              max="50"
              step="0.5"
              class="input"
              placeholder="Số năm KN"
              aria-label="Số năm kinh nghiệm"
            >
          </div>

          <input
            v-model="s.note"
            type="text"
            maxlength="200"
            class="input mt-2 w-full"
            placeholder="Ghi chú ngắn (tuỳ chọn)"
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
