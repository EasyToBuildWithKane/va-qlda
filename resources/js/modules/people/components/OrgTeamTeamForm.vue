<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import {
    applyTechDeptSectionPresets,
    buildOrgTeamSubmitPayload,
    emptyOrgTeamMemberRow,
    hydrateOrgTeamFormFromTeam,
    TECH_DEPT_CHILD_NAME_SUGGESTIONS,
} from '@/modules/people/composables/useOrgTeamForm.js';

const props = defineProps({
    team: { type: Object, default: null },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
    presetParentId: { type: [Number, String, null], default: null },
    forceRoot: { type: Boolean, default: false },
    showActions: { type: Boolean, default: true },
    showCancel: { type: Boolean, default: false },
    showAdvanced: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['saved', 'cancel']);

const form = useForm({
    name: '',
    parent_id: null,
    leader_id: null,
    sort_order: 0,
    is_active: true,
    sections: [],
    members: [],
});

const isEdit = computed(() => !!props.team?.id);
const isRootTeam = computed(() => isEdit.value
    ? props.team?.level === 1
    : props.forceRoot || props.presetParentId == null);

const showParentField = computed(() => !props.forceRoot && (isEdit.value || props.presetParentId == null));

const parentChoices = computed(() => {
    let list = props.parentOptions.filter((p) => p.level < 2);
    if (isEdit.value && props.team?.id) {
        list = list.filter((p) => p.id !== props.team.id);
    }

    return list;
});

const membersBySection = computed(() => {
    const map = new Map();
    map.set('unassigned', []);

    form.sections.forEach((_, idx) => {
        map.set(idx, []);
    });

    form.members.forEach((row, memberIndex) => {
        const key = row.section_index == null || row.section_index === ''
            ? 'unassigned'
            : Number(row.section_index);
        if (!map.has(key)) {
            map.set(key, []);
        }
        map.get(key).push({ row, memberIndex });
    });

    return map;
});

function resetForContext() {
    form.clearErrors();
    if (props.team) {
        hydrateOrgTeamFormFromTeam(props.team, form);
    } else {
        form.reset();
        form.parent_id = props.forceRoot
            ? null
            : (props.presetParentId != null ? Number(props.presetParentId) : null);
        form.is_active = true;
        form.sections = [];
        form.members = [];
        if (props.presetParentId != null && !form.name) {
            const suggestion = TECH_DEPT_CHILD_NAME_SUGGESTIONS.find(
                (name) => !props.parentOptions.some((p) => p.name === name),
            );
            if (suggestion) {
                form.name = suggestion;
            }
        }
    }
}

watch(
    () => [props.team?.id ?? null, props.presetParentId, props.forceRoot],
    () => resetForContext(),
    { immediate: true },
);

function addSection(title = '') {
    form.sections.push({ title, sort_order: form.sections.length });
}

function removeSection(index) {
    form.members.forEach((row) => {
        if (row.section_index === index) {
            row.section_index = null;
        } else if (row.section_index != null && row.section_index > index) {
            row.section_index -= 1;
        }
    });
    form.sections.splice(index, 1);
}

function addMemberToSection(sectionIndex = null) {
    const rows = membersBySection.value.get(sectionIndex ?? 'unassigned') ?? [];
    form.members.push({
        ...emptyOrgTeamMemberRow(sectionIndex),
        sort_order: rows.length,
    });
}

function removeMemberAt(index) {
    form.members.splice(index, 1);
}

function applyTechTemplate() {
    if (applyTechDeptSectionPresets(form, isRootTeam.value)) {
        return;
    }
}

function submit() {
    const payload = buildOrgTeamSubmitPayload(form.data(), { forceRoot: props.forceRoot });
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            emit('saved');
        },
    };

    if (isEdit.value) {
        form.transform(() => payload).put(`/org-teams/${props.team.id}`, opts);
    } else {
        form.transform(() => payload).post('/org-teams', opts);
    }
}

defineExpose({ form, submit, isDirty: () => form.isDirty });
</script>

<template>
  <form
    class="space-y-5"
    :class="compact ? 'text-sm' : ''"
    @submit.prevent="submit"
  >
    <div
      :class="compact ? 'grid gap-3 sm:grid-cols-2' : 'grid gap-4 sm:grid-cols-2'"
    >
      <div :class="showParentField ? '' : 'sm:col-span-2'">
        <label class="label">{{ isRootTeam ? 'Tên cấu trúc' : 'Tên đơn vị' }}</label>
        <input
          v-model="form.name"
          type="text"
          class="input w-full"
          :placeholder="isRootTeam ? 'Ví dụ: Phòng Công nghệ' : 'Ví dụ: Phần mềm, Phần cứng…'"
          required
        >
        <p
          v-if="form.errors.name"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.name }}
        </p>
      </div>

      <div v-if="showParentField">
        <label class="label">Thuộc cấu trúc</label>
        <select
          v-model="form.parent_id"
          class="input w-full"
          :disabled="isEdit && team?.children?.length > 0"
        >
          <option :value="null">
            Cấu trúc độc lập
          </option>
          <option
            v-for="p in parentChoices"
            :key="p.id"
            :value="p.id"
          >
            {{ p.label }}
          </option>
        </select>
        <p
          v-if="form.errors.parent_id"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.parent_id }}
        </p>
      </div>
    </div>

    <div>
      <label class="label">Quản lý</label>
      <PersonSelect
        v-model="form.leader_id"
        :options="employees"
        placeholder="Chọn quản lý"
      />
    </div>

    <div
      v-if="showAdvanced"
      class="grid gap-3 sm:grid-cols-3"
    >
      <div>
        <label class="label">Thứ tự hiển thị</label>
        <input
          v-model.number="form.sort_order"
          type="number"
          min="0"
          max="9999"
          class="input w-full"
        >
      </div>
      <div class="flex items-end pb-1 sm:col-span-2">
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
          <input
            v-model="form.is_active"
            type="checkbox"
            class="rounded border-slate-300 text-brand focus:ring-brand"
          >
          Đang hoạt động trên sơ đồ
        </label>
      </div>
    </div>

    <div class="rounded-xl border border-slate-200/90 bg-slate-50/50 p-4">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
          {{ isRootTeam ? 'Cấp quản lý' : 'Nhánh' }}
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            v-if="isRootTeam"
            type="button"
            class="btn-ghost h-8 px-2 text-xs"
            @click="applyTechTemplate"
          >
            Mẫu Phòng CNTT
          </button>
          <button
            type="button"
            class="text-xs font-medium text-brand hover:underline"
            @click="addSection()"
          >
            + Thêm nhánh
          </button>
        </div>
      </div>

      <div
        v-if="!form.sections.length"
        class="rounded-lg border border-dashed border-slate-200 bg-white px-3 py-4 text-center text-xs text-slate-500"
      >
        Chưa có mục nào.
      </div>

      <div
        v-for="(section, sIdx) in form.sections"
        :key="`sec-${sIdx}`"
        class="mb-3 overflow-hidden rounded-lg border border-slate-200 bg-white last:mb-0"
      >
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/80 px-3 py-2">
          <input
            v-model="section.title"
            type="text"
            class="input min-w-0 flex-1 border-0 bg-transparent text-sm font-medium shadow-none focus:ring-0"
            placeholder="Tên nhánh"
            maxlength="120"
          >
          <button
            type="button"
            class="rounded-lg p-1.5 text-slate-400 hover:bg-white hover:text-rose-600"
            title="Xoá nhánh"
            @click="removeSection(sIdx)"
          >
            <AppIcon
              name="delete"
              :size="15"
            />
          </button>
        </div>

        <div class="space-y-2 px-3 py-3">
          <div
            v-for="{ row, memberIndex } in membersBySection.get(sIdx) ?? []"
            :key="`m-${memberIndex}`"
            class="flex flex-wrap items-end gap-2"
          >
            <div class="min-w-[10rem] flex-1">
              <PersonSelect
                v-model="row.employee_id"
                :options="employees"
                placeholder="Chọn người"
              />
            </div>
            <div
              v-if="branchOptions.length"
              class="w-40"
            >
              <select
                v-model="row.branch"
                class="input w-full text-sm"
              >
                <option :value="null">
                  Vai trò phụ
                </option>
                <option
                  v-for="b in branchOptions"
                  :key="b.value"
                  :value="b.value"
                >
                  {{ b.label }}
                </option>
              </select>
            </div>
            <button
              type="button"
              class="rounded-lg p-2 text-slate-400 hover:text-rose-600"
              @click="removeMemberAt(memberIndex)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </div>
          <button
            type="button"
            class="text-xs font-medium text-brand hover:underline"
            @click="addMemberToSection(sIdx)"
          >
            + Thêm vào nhánh này
          </button>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-slate-200/90 p-4">
      <div class="mb-3 flex items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
          Thành viên khác
        </p>
        <button
          type="button"
          class="text-xs font-medium text-brand hover:underline"
          @click="addMemberToSection(null)"
        >
          + Thêm thành viên
        </button>
      </div>

      <div
        v-if="!(membersBySection.get('unassigned') ?? []).length"
        class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-center text-xs text-slate-500"
      >
        —
      </div>

      <div
        v-for="{ row, memberIndex } in membersBySection.get('unassigned') ?? []"
        :key="`u-${memberIndex}`"
        class="mb-2 flex flex-wrap items-end gap-2 border-b border-slate-100 pb-2 last:border-0"
      >
        <div class="min-w-[10rem] flex-1">
          <PersonSelect
            v-model="row.employee_id"
            :options="employees"
            placeholder="Chọn người"
          />
        </div>
        <div
          v-if="branchOptions.length"
          class="w-40"
        >
          <select
            v-model="row.branch"
            class="input w-full text-sm"
          >
            <option :value="null">
              Vai trò phụ
            </option>
            <option
              v-for="b in branchOptions"
              :key="b.value"
              :value="b.value"
            >
              {{ b.label }}
            </option>
          </select>
        </div>
        <button
          type="button"
          class="rounded-lg p-2 text-slate-400 hover:text-rose-600"
          @click="removeMemberAt(memberIndex)"
        >
          <AppIcon
            name="delete"
            :size="15"
          />
        </button>
      </div>

      <p
        v-if="form.errors.members"
        class="mt-2 text-xs text-rose-600"
      >
        {{ form.errors.members }}
      </p>
      <p
        v-if="form.errors.sections"
        class="mt-2 text-xs text-rose-600"
      >
        {{ form.errors.sections }}
      </p>
    </div>

    <div
      v-if="showActions"
      class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4"
    >
      <button
        v-if="showCancel"
        type="button"
        class="btn-secondary"
        @click="emit('cancel')"
      >
        Huỷ
      </button>
      <button
        type="submit"
        class="btn-primary"
        :disabled="form.processing"
      >
        {{ isEdit ? 'Lưu' : 'Tạo' }}
      </button>
    </div>
  </form>
</template>
