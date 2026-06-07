<script setup>
import { computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    team: { type: Object, default: null },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
    levelHints: { type: Object, default: () => ({}) },
    presetParentId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['close', 'saved']);
inject('modalClose', () => emit('close'));

const form = useForm({
    name: '',
    parent_id: null,
    leader_id: null,
    sort_order: 0,
    is_active: true,
    members: [],
});

const isEdit = computed(() => !!props.team);

const effectiveLevel = computed(() => {
    if (isEdit.value && !form.isDirty) {
        return props.team?.level ?? 1;
    }
    const pid = form.parent_id;
    if (!pid) return 1;
    const parent = props.parentOptions.find((p) => p.id === Number(pid));
    return parent ? parent.level + 1 : 1;
});

const parentChoices = computed(() => {
    let list = props.parentOptions.filter((p) => p.level < 3);
    if (isEdit.value && props.team?.id) {
        list = list.filter((p) => p.id !== props.team.id);
    }
    return list;
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    if (props.team) {
        form.name = props.team.name;
        form.parent_id = props.team.parent_id;
        form.leader_id = props.team.leader?.id ?? null;
        form.sort_order = props.team.sort_order ?? 0;
        form.is_active = props.team.is_active ?? true;
        form.members = (props.team.members ?? []).map((m, i) => ({
            employee_id: m.employee?.id ?? null,
            branch: m.branch?.value ?? '',
            sort_order: m.sort_order ?? i,
        }));
    } else {
        form.reset();
        form.parent_id = props.presetParentId != null ? Number(props.presetParentId) : null;
        form.is_active = true;
        form.members = [];
    }
});

function addMember() {
    form.members.push({ employee_id: null, branch: '', sort_order: form.members.length });
}

function removeMember(index) {
    form.members.splice(index, 1);
}

const submit = () => {
    const payload = {
        ...form.data(),
        parent_id: form.parent_id || null,
        members: form.members
            .filter((m) => m.employee_id)
            .map((m, i) => ({
                employee_id: m.employee_id,
                branch: effectiveLevel.value === 3 ? (m.branch || null) : null,
                sort_order: m.sort_order ?? i,
            })),
    };

    const opts = {
        preserveScroll: true,
        onSuccess: () => {
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
    :title="isEdit ? 'Chỉnh sửa nhóm' : 'Thêm nhóm mới'"
    max-width="max-w-2xl"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
        {{ levelHints[effectiveLevel] || `Cấp ${effectiveLevel}` }}
      </p>

      <div>
        <label class="label">Tên nhóm</label>
        <input
          v-model="form.name"
          type="text"
          class="input w-full"
          placeholder="vd. Leader Phần Mềm, Đội ngũ Dev, Nhánh GVS…"
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
          <label class="label">Nhóm cha (tuỳ chọn)</label>
          <select
            v-model="form.parent_id"
            class="input w-full"
            :disabled="isEdit && team?.children?.length > 0"
          >
            <option :value="null">
              — Cấp 1 (gốc) —
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
          class="rounded-lg border border-dashed border-slate-200 px-3 py-4 text-center text-xs text-slate-500"
        >
          Chưa có thành viên. Thêm dev, BA, trợ lý…
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
              placeholder="Nhân sự"
            />
          </div>
          <div
            v-if="effectiveLevel === 3"
            class="w-48"
          >
            <select
              v-model="row.branch"
              class="input w-full text-sm"
            >
              <option value="">
                Chọn nhánh
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
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ isEdit ? 'Lưu' : 'Tạo nhóm' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
