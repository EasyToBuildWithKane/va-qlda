<script setup>
import { computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, restoreModalDraft } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: { type: Boolean, default: false },
    team: { type: Object, default: null },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    presetParentId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const form = useForm({
    name: '',
    parent_id: null,
    leader_id: null,
    sort_order: 0,
    is_active: true,
    sections: [],
    members: [],
});

const orgTeamDraftScope = computed(() => (
    props.team ? `edit.${props.team.id}` : 'create'
));

const formDraft = useModalFormDraft('org-team', {
    getScope: () => orgTeamDraftScope.value,
    pick: (data) => ({
        name: data.name,
        parent_id: data.parent_id,
        leader_id: data.leader_id,
        sort_order: data.sort_order,
        is_active: data.is_active,
        sections: (data.sections ?? []).map((s) => ({ ...s })),
        members: (data.members ?? []).map((m) => ({ ...m })),
    }),
});

const applyFormDraft = (data) => {
    form.name = data.name ?? '';
    form.parent_id = data.parent_id ?? props.presetParentId ?? null;
    form.leader_id = data.leader_id ?? null;
    form.sort_order = data.sort_order ?? 0;
    form.is_active = data.is_active ?? true;
    form.sections = (data.sections ?? []).map((s) => ({ ...s }));
    form.members = (data.members ?? []).map((m) => ({ ...m }));
};

const saveDraftOnClose = () => {
    formDraft.saveOnClose(form.data(), buildDraftSaveMeta(props.team));
};

const isEdit = computed(() => !!props.team);

const parentChoices = computed(() => {
    let list = props.parentOptions.filter((p) => p.level < 2);
    if (isEdit.value && props.team?.id) {
        list = list.filter((p) => p.id !== props.team.id);
    }
    return list;
});

const sectionChoices = computed(() =>
    form.sections.map((s, index) => ({
        value: index,
        label: s.title?.trim() || `Nhánh ${index + 1}`,
    })),
);

function hydrateFromTeam(team) {
    const sections = toIterableList(team.sections);
    form.name = team.name;
    form.parent_id = team.parent_id;
    form.leader_id = team.leader?.id ?? null;
    form.sort_order = team.sort_order ?? 0;
    form.is_active = team.is_active ?? true;
    form.sections = sections.map((s, i) => ({
        title: s.title,
        sort_order: s.sort_order ?? i,
    }));
    form.members = toIterableList(team.members).map((m, i) => ({
        employee_id: m.employee?.id ?? m.employee_id ?? null,
        section_index: resolveSectionIndex(m.section?.id ?? m.section_id ?? null, sections),
        sort_order: m.sort_order ?? i,
    }));
}

watch(
    () => [props.show, props.team?.id ?? null],
    async ([open]) => {
        if (!open) return;
        form.clearErrors();
        const epoch = formDraft.bumpOpenEpoch();
        if (props.team) {
            hydrateFromTeam(props.team);
            await restoreModalDraft(formDraft, {
                isActive: () => props.show,
                openEpoch: epoch,
                entity: props.team,
                applyDraft: applyFormDraft,
                form,
            });
        } else {
            form.reset();
            form.parent_id = props.presetParentId != null ? Number(props.presetParentId) : null;
            form.is_active = true;
            form.sections = [];
            form.members = [];
            await restoreModalDraft(formDraft, {
                isActive: () => props.show,
                openEpoch: epoch,
                entity: null,
                applyDraft: applyFormDraft,
                form,
            });
        }
    },
);

function resolveSectionIndex(sectionId, sections) {
    if (sectionId == null) {
        return null;
    }
    const list = toIterableList(sections);
    const idx = list.findIndex((s) => s.id === sectionId);

    return idx >= 0 ? idx : null;
}

function addSection() {
    form.sections.push({ title: '', sort_order: form.sections.length });
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

function addMember() {
    form.members.push({
        employee_id: null,
        section_index: null,
        sort_order: form.members.length,
    });
}

function removeMember(index) {
    form.members.splice(index, 1);
}

const submit = () => {
    const sections = form.sections
        .map((s, i) => ({
            title: (s.title || '').trim(),
            sort_order: s.sort_order ?? i,
        }))
        .filter((s) => s.title !== '');

    const payload = {
        ...form.data(),
        parent_id: form.parent_id || null,
        sections,
        members: form.members
            .filter((m) => m.employee_id)
            .map((m, i) => {
                let sectionIndex = m.section_index;
                if (sectionIndex != null && sectionIndex !== '') {
                    sectionIndex = Number(sectionIndex);
                    if (sectionIndex >= sections.length) {
                        sectionIndex = null;
                    }
                } else {
                    sectionIndex = null;
                }

                return {
                    employee_id: m.employee_id,
                    section_index: sectionIndex,
                    sort_order: m.sort_order ?? i,
                };
            }),
    };

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            formDraft.clear();
            emit('saved');
            emit('close');
        },
    };

    if (isEdit.value) {
        form.transform(() => payload).put(`/org-teams/${props.team.id}`, opts);
    } else {
        form.transform(() => payload).post('/org-teams', opts);
    }
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="isEdit ? 'Sửa nhóm' : 'Thêm nhóm'"
    max-width="max-w-2xl"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <div>
        <label class="label">Tên nhóm</label>
        <input
          v-model="form.name"
          type="text"
          class="input w-full"
          placeholder="Ví dụ: Phần mềm, Hỗ trợ dự án…"
          required
        >
        <p
          v-if="form.errors.name"
          class="mt-1 text-xs text-rose-600"
        >
          {{ form.errors.name }}
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Nằm trong nhóm</label>
          <select
            v-model="form.parent_id"
            class="input w-full"
            :disabled="isEdit && team?.children?.length > 0"
          >
            <option :value="null">
              Không — nhóm độc lập
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
        <div>
          <label class="label">Trưởng nhóm</label>
          <PersonSelect
            v-model="form.leader_id"
            :options="employees"
            placeholder="Chọn trưởng nhóm"
          />
        </div>
      </div>

      <div>
        <div class="mb-2 flex items-center justify-between gap-2">
          <label class="label mb-0">Nhánh</label>
          <button
            type="button"
            class="shrink-0 text-xs font-medium text-brand hover:underline"
            @click="addSection"
          >
            + Thêm nhánh
          </button>
        </div>
        <div
          v-for="(section, sIdx) in form.sections"
          :key="sIdx"
          class="mb-2 flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/80 p-2"
        >
          <input
            v-model="section.title"
            type="text"
            class="input min-w-0 flex-1 text-sm"
            placeholder="Tên nhánh hiển thị trên sơ đồ"
            maxlength="120"
          >
          <button
            type="button"
            class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-rose-600"
            title="Xoá nhánh"
            @click="removeSection(sIdx)"
          >
            <AppIcon
              name="delete"
              :size="16"
            />
          </button>
        </div>
        <p
          v-if="form.errors.sections"
          class="text-xs text-rose-600"
        >
          {{ form.errors.sections }}
        </p>
      </div>

      <div>
        <div class="mb-2 flex items-center justify-between">
          <label class="label mb-0">Thành viên</label>
          <button
            type="button"
            class="text-xs font-medium text-brand hover:underline"
            @click="addMember"
          >
            + Thêm thành viên
          </button>
        </div>
        <div
          v-if="!form.members.length"
          class="mb-2 rounded-lg border border-dashed border-slate-200 px-3 py-3 text-center text-xs text-slate-500"
        >
          Chưa có thành viên.
        </div>
        <div
          v-for="(row, idx) in form.members"
          :key="idx"
          class="mb-2 flex flex-wrap items-end gap-2 rounded-lg border border-slate-100 bg-slate-50/80 p-2"
        >
          <div class="min-w-[12rem] flex-1">
            <PersonSelect
              v-model="row.employee_id"
              :options="employees"
              placeholder="Chọn người"
            />
          </div>
          <div class="w-44">
            <select
              v-model="row.section_index"
              class="input w-full text-sm"
            >
              <option :value="null">
                Không chọn nhánh
              </option>
              <option
                v-for="opt in sectionChoices"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-rose-600"
            title="Xoá dòng"
            @click="removeMember(idx)"
          >
            <AppIcon
              name="delete"
              :size="16"
            />
          </button>
        </div>
        <p
          v-if="form.errors.members"
          class="text-xs text-rose-600"
        >
          {{ form.errors.members }}
        </p>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-secondary"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ isEdit ? 'Lưu' : 'Thêm' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
