<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Drawer from '@/Components/Ui/Drawer.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { skillItemTitle } from '../utils/skillItemTitle';

const props = defineProps({
    show: { type: Boolean, default: false },
    profile: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const levels = [1, 2, 3, 4, 5];
const DEFAULT_GROUP = 'Chung';
const MAX_SKILLS = 40;
const NAME_MAX = 50;
const GROUP_MAX = 80;
const NOTE_MAX = 200;

let seq = 0;
const uid = () => `s${Date.now().toString(36)}-${(seq += 1).toString(36)}`;

/** Build the editable group model from the presented skill matrix. */
function initialGroups() {
    return (props.profile.skills?.groups || []).map((g) => ({
        id: uid(),
        name: g.label || DEFAULT_GROUP,
        collapsed: false,
        skills: (g.items || []).map((i) => ({
            id: uid(),
            name: skillItemTitle(i) ?? '',
            level: i.level ?? 3,
            years: i.years ?? '',
            note: i.note ?? '',
        })),
    }));
}

const form = useForm({ groups: initialGroups() });

watch(
    () => props.show,
    (open) => {
        if (open) {
            form.clearErrors();
            form.groups = initialGroups();
        }
    },
);

const totalSkills = computed(() =>
    form.groups.reduce((n, g) => n + g.skills.filter((s) => s.name.trim() !== '').length, 0),
);

const canAddMore = computed(() => totalSkills.value < MAX_SKILLS);

const groupOptions = computed(() =>
    form.groups.map((g) => ({ id: g.id, label: g.name.trim() || DEFAULT_GROUP })),
);

// Surface nested validation errors (skills.0.name, …) the old UI swallowed.
const errorMessages = computed(() => {
    const seen = new Set();
    return Object.values(form.errors || {}).filter((m) => {
        if (!m || seen.has(m)) {
            return false;
        }
        seen.add(m);
        return true;
    });
});

function addGroup() {
    const group = { id: uid(), name: '', collapsed: false, skills: [] };
    if (canAddMore.value) {
        group.skills.push(blankSkill());
    }
    form.groups.push(group);
}

function removeGroup(groupId) {
    form.groups = form.groups.filter((g) => g.id !== groupId);
}

function blankSkill() {
    return { id: uid(), name: '', level: 3, years: '', note: '' };
}

function addSkill(group) {
    if (canAddMore.value) {
        group.skills.push(blankSkill());
    }
}

function removeSkill(group, skillId) {
    group.skills = group.skills.filter((s) => s.id !== skillId);
}

function moveSkill(fromGroup, skillId, toGroupId) {
    if (fromGroup.id === toGroupId) {
        return;
    }
    const target = form.groups.find((g) => g.id === toGroupId);
    if (!target) {
        return;
    }
    const idx = fromGroup.skills.findIndex((s) => s.id === skillId);
    if (idx === -1) {
        return;
    }
    const [moved] = fromGroup.skills.splice(idx, 1);
    target.skills.push(moved);
}

function submit() {
    form
        .transform((data) => ({
            skills: data.groups.flatMap((g) => {
                const category = g.name.trim() || DEFAULT_GROUP;
                return g.skills
                    .filter((s) => s.name.trim() !== '')
                    .map((s) => ({
                        name: s.name.trim(),
                        category,
                        level: s.level,
                        years: s.years,
                        note: s.note,
                    }));
            }),
        }))
        .put(route('profile.update'), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
}
</script>

<template>
  <Drawer
    :show="show"
    title="Quản lý kỹ năng"
    width="max-w-2xl"
    @close="emit('close')"
  >
    <div class="space-y-4">
      <div class="flex items-start justify-between gap-3">
        <p class="text-[13px] leading-relaxed text-slate-500">
          Tổ chức kỹ năng theo <strong class="font-medium text-slate-700">nhóm do bạn đặt tên</strong>
          (vd. «Lập trình Web», «DevOps», «Ngoại ngữ»). Đổi tên nhóm để di chuyển cả nhóm,
          hoặc chuyển từng kỹ năng sang nhóm khác.
        </p>
        <span
          class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold tabular-nums text-slate-500"
          :class="canAddMore ? '' : 'bg-amber-50 text-amber-600'"
        >
          {{ totalSkills }}/{{ MAX_SKILLS }}
        </span>
      </div>

      <ul
        v-if="errorMessages.length"
        class="space-y-1 rounded-xl border border-danger/30 bg-danger/5 px-3.5 py-2.5 text-xs text-danger"
      >
        <li
          v-for="(msg, idx) in errorMessages"
          :key="idx"
          class="flex items-start gap-1.5"
        >
          <AppIcon
            name="alert"
            :size="13"
            class="mt-0.5 shrink-0"
          />
          <span>{{ msg }}</span>
        </li>
      </ul>

      <div class="space-y-3">
        <section
          v-for="group in form.groups"
          :key="group.id"
          class="overflow-hidden rounded-xl border border-slate-200/80 bg-slate-50/40"
        >
          <header class="flex items-center gap-2 border-b border-slate-200/70 bg-white px-3 py-2.5">
            <button
              type="button"
              class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              :aria-label="group.collapsed ? 'Mở nhóm' : 'Thu gọn nhóm'"
              :aria-expanded="!group.collapsed"
              @click="group.collapsed = !group.collapsed"
            >
              <AppIcon
                :name="group.collapsed ? 'chevron-right' : 'chevron-down'"
                :size="16"
              />
            </button>
            <input
              v-model="group.name"
              type="text"
              :maxlength="GROUP_MAX"
              class="input h-9 flex-1 font-display text-[13px] font-semibold"
              placeholder="Tên nhóm kỹ năng"
              aria-label="Tên nhóm kỹ năng"
            >
            <span class="shrink-0 text-[11px] tabular-nums text-slate-400">
              {{ group.skills.length }} kỹ năng
            </span>
            <button
              type="button"
              class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-500"
              aria-label="Xoá nhóm"
              @click="removeGroup(group.id)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </header>

          <div
            v-show="!group.collapsed"
            class="space-y-2.5 p-3"
          >
            <div
              v-for="skill in group.skills"
              :key="skill.id"
              class="rounded-lg border border-slate-200/70 bg-white p-2.5"
            >
              <div class="flex items-center gap-2">
                <input
                  v-model="skill.name"
                  type="text"
                  :maxlength="NAME_MAX"
                  class="input h-9 flex-1 text-sm"
                  placeholder="Tên kỹ năng (bắt buộc)"
                  aria-label="Tên kỹ năng"
                >
                <button
                  type="button"
                  class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-500"
                  aria-label="Xoá kỹ năng"
                  @click="removeSkill(group, skill.id)"
                >
                  <AppIcon
                    name="delete"
                    :size="15"
                  />
                </button>
              </div>

              <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                <select
                  v-model.number="skill.level"
                  class="input h-9 text-sm"
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
                  v-model="skill.years"
                  type="number"
                  min="0"
                  max="50"
                  step="0.5"
                  class="input h-9 text-sm"
                  placeholder="Số năm KN"
                  aria-label="Số năm kinh nghiệm"
                >
                <select
                  v-if="groupOptions.length > 1"
                  class="input h-9 text-sm sm:col-span-1 col-span-2"
                  :value="group.id"
                  aria-label="Chuyển sang nhóm"
                  @change="moveSkill(group, skill.id, $event.target.value)"
                >
                  <option
                    v-for="opt in groupOptions"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.id === group.id ? `Nhóm: ${opt.label}` : `→ ${opt.label}` }}
                  </option>
                </select>
              </div>

              <input
                v-model="skill.note"
                type="text"
                :maxlength="NOTE_MAX"
                class="input mt-2 h-9 w-full text-sm"
                placeholder="Ghi chú ngắn (tuỳ chọn)"
                aria-label="Ghi chú"
              >
            </div>

            <p
              v-if="!group.skills.length"
              class="rounded-lg border border-dashed border-slate-200 py-3 text-center text-[12px] text-slate-400"
            >
              Nhóm trống — thêm kỹ năng bên dưới.
            </p>

            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-brand/40 px-2.5 py-1.5 text-[12px] font-medium text-brand transition-colors hover:bg-brand/5 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="!canAddMore"
              @click="addSkill(group)"
            >
              <AppIcon
                name="add"
                :size="14"
              />
              Thêm kỹ năng
            </button>
          </div>
        </section>

        <p
          v-if="!form.groups.length"
          class="rounded-xl border border-dashed border-slate-200 py-6 text-center text-[13px] text-slate-400"
        >
          Chưa có nhóm kỹ năng nào. Bấm «Thêm nhóm» để bắt đầu.
        </p>
      </div>

      <button
        type="button"
        class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2.5 text-[13px] font-medium text-slate-600 transition-colors hover:border-brand/40 hover:bg-brand/5 hover:text-brand"
        @click="addGroup"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm nhóm
      </button>

      <p
        v-if="!canAddMore"
        class="text-center text-[11.5px] text-amber-600"
      >
        Đã đạt tối đa {{ MAX_SKILLS }} kỹ năng.
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
          {{ form.processing ? 'Đang lưu...' : 'Lưu kỹ năng' }}
        </button>
      </div>
    </template>
  </Drawer>
</template>
