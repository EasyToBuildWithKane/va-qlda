<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import OrgTeamFormCollapsibleSection from '@/modules/people/components/OrgTeamFormCollapsibleSection.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';
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
    unitDisplayName: { type: String, default: '' },
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

const SORT_ORDER_PRESETS = [
    { value: 0, label: 'Mặc định — cùng nhóm với các đơn vị khác' },
    { value: 10, label: 'Ưu tiên cao — hiển thị trước trong nhóm' },
    { value: 50, label: 'Giữa nhóm' },
    { value: 100, label: 'Ưu tiên thấp — hiển thị sau trong nhóm' },
];

const sortOrderOptions = computed(() => {
    const current = Number(form.sort_order) || 0;
    const hasPreset = SORT_ORDER_PRESETS.some((o) => o.value === current);
    if (hasPreset) {
        return SORT_ORDER_PRESETS;
    }

    return [
        ...SORT_ORDER_PRESETS,
        { value: current, label: `Thứ tự đã lưu (${current})` },
    ];
});

const sortOrderBadge = computed(() => {
    const current = Number(form.sort_order) || 0;
    const short = { 0: 'Mặc định', 10: 'Ưu tiên cao', 50: 'Giữa nhóm', 100: 'Ưu tiên thấp' };

    return short[current] ?? 'Tùy chỉnh';
});

const activeOnDiagramSummary = computed(() => (form.is_active ? 'Đang hiển thị' : 'Đã ẩn'));

const managementSectionTitle = computed(() => (isRootTeam.value ? 'Nhóm chức danh trên sơ đồ' : 'Nhóm nhân sự theo vai trò'));

const managementBadge = computed(() => {
    const n = form.sections.length;
    if (n === 0) {
        return 'Chưa có nhóm';
    }

    return `${n} nhóm`;
});

const displayOptionsBadge = computed(() => {
    const parts = [sortOrderBadge.value, activeOnDiagramSummary.value];

    return parts.join(' · ');
});

const saveLabel = computed(() => (isEdit.value ? 'Lưu thay đổi' : 'Tạo bộ phận'));

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

const unassignedCount = computed(() => (membersBySection.value.get('unassigned') ?? []).length);

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
    class="space-y-4"
    :class="compact ? 'text-sm' : ''"
    @submit.prevent="submit"
  >
    <OrgTeamFormCollapsibleSection
      step="1"
      :title="isRootTeam ? 'Tên phòng / ban' : 'Tên bộ phận'"
      hint="Tên hiển thị trên sơ đồ và người đứng đầu"
      :default-open="true"
    >
      <div class="space-y-4">
        <div
          :class="compact ? 'grid gap-3 sm:grid-cols-2' : 'grid gap-4 sm:grid-cols-2'"
        >
          <div :class="showParentField ? '' : 'sm:col-span-2'">
            <label class="label">{{ isRootTeam ? 'Tên trên sơ đồ' : 'Tên bộ phận' }}</label>
            <input
              v-model="form.name"
              type="text"
              class="input w-full"
              :placeholder="isRootTeam ? 'Ví dụ: Phòng Công nghệ' : 'Ví dụ: Phần mềm, Phần cứng…'"
              required
            >
            <p class="mt-1 text-xs text-slate-500">
              {{ isRootTeam ? 'Thường là tên phòng, ban hoặc khối.' : 'Tên tổ, nhóm hoặc mảng công việc.' }}
            </p>
            <p
              v-if="form.errors.name"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.name }}
            </p>
          </div>

          <div v-if="showParentField">
            <label class="label">Thuộc phòng / ban nào?</label>
            <select
              v-model="form.parent_id"
              class="input w-full"
              :disabled="isEdit && team?.children?.length > 0"
            >
              <option :value="null">
                Đứng riêng (cấp cao nhất)
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
          <label class="label">Người phụ trách</label>
          <PersonSelect
            v-model="form.leader_id"
            :options="employees"
            placeholder="Chọn tên trong danh sách nhân sự"
          />
          <p class="mt-1 text-xs text-slate-500">
            Người đại diện đơn vị trên sơ đồ (trưởng phòng, trưởng ban…).
          </p>
        </div>
      </div>
    </OrgTeamFormCollapsibleSection>

    <OrgTeamFormCollapsibleSection
      step="2"
      :title="managementSectionTitle"
      :hint="isRootTeam
        ? 'Ví dụ: Trưởng ban, Phó phòng — mỗi nhóm gắn đúng người'
        : 'Chia nhân sự theo vai trò nếu cần'"
      :badge="managementBadge"
      :default-open="true"
    >
      <p class="mb-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
        {{ isRootTeam
          ? 'Mỗi dòng là một ô trên sơ đồ. Thêm nhóm, đặt tên (ví dụ «Trưởng ban»), rồi chọn người.'
          : 'Tuỳ chọn — chỉ cần khi muốn nhóm người theo vai trò trên sơ đồ.' }}
      </p>
      <div class="mb-3 flex justify-end">
        <button
          type="button"
          class="btn-secondary h-8 px-3 text-xs"
          @click="addSection()"
        >
          <AppIcon
            name="plus"
            :size="14"
            class="mr-1 inline"
          />
          Thêm nhóm
        </button>
      </div>

      <div
        v-if="!form.sections.length"
        class="rounded-lg border border-dashed border-slate-200 bg-slate-50/50 px-3 py-5 text-center text-sm text-slate-500"
      >
        Chưa có nhóm nào. Bấm «Thêm nhóm» nếu cần chia vai trò trên sơ đồ.
      </div>

      <div
        v-for="(section, sIdx) in form.sections"
        :key="`sec-${sIdx}`"
        class="mb-3 overflow-hidden rounded-lg border border-slate-200 bg-slate-50/30 last:mb-0"
      >
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-white px-3 py-2">
          <input
            v-model="section.title"
            type="text"
            class="input min-w-0 flex-1 border-0 bg-transparent text-sm font-medium shadow-none focus:ring-0"
            placeholder="Tên hiển thị (ví dụ: Trưởng ban CNTT)"
            maxlength="120"
          >
          <button
            type="button"
            class="rounded-lg p-1.5 text-slate-400 hover:bg-white hover:text-rose-600"
            title="Xóa nhóm này"
            @click="removeSection(sIdx)"
          >
            <AppIcon
              name="delete"
              :size="15"
            />
          </button>
        </div>

        <div class="space-y-2 bg-white px-3 py-3">
          <div
            v-for="{ row, memberIndex } in membersBySection.get(sIdx) ?? []"
            :key="`m-${memberIndex}`"
            class="flex flex-wrap items-end gap-2"
          >
            <div class="min-w-[10rem] flex-1">
              <label
                v-if="membersBySection.get(sIdx)?.length > 1"
                class="mb-1 block text-[11px] text-slate-500"
              >Người trong nhóm</label>
              <PersonSelect
                v-model="row.employee_id"
                :options="employees"
                placeholder="Chọn người"
              />
            </div>
            <div
              v-if="branchOptions.length"
              class="w-full sm:w-44"
            >
              <label class="mb-1 block text-[11px] text-slate-500">Kiêm nhiệm</label>
              <select
                v-model="row.branch"
                class="input w-full text-sm"
              >
                <option :value="null">
                  Không
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
              title="Xóa người này"
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
            + Thêm người vào nhóm này
          </button>
        </div>
      </div>

      <div class="mt-4 border-t border-slate-100 pt-4">
        <p class="text-xs font-semibold text-slate-700">
          Người thuộc đơn vị (chưa xếp nhóm)
        </p>
        <p class="mt-0.5 text-[11px] text-slate-500">
          Dùng khi cần liệt kê thêm nhân sự không gắn vai trò cụ thể trên sơ đồ.
        </p>
        <div class="mb-2 mt-3 flex justify-end">
          <button
            type="button"
            class="text-xs font-medium text-brand hover:underline"
            @click="addMemberToSection(null)"
          >
            + Thêm người
          </button>
        </div>

        <div
          v-if="!unassignedCount"
          class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-center text-xs text-slate-500"
        >
          Chưa có ai — bỏ qua nếu không cần.
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
            class="w-full sm:w-44"
          >
            <select
              v-model="row.branch"
              class="input w-full text-sm"
            >
              <option :value="null">
                Kiêm nhiệm: không
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
      </div>

      <p
        v-if="form.errors.sections"
        class="mt-2 text-xs text-rose-600"
      >
        {{ form.errors.sections }}
      </p>
      <p
        v-if="form.errors.members"
        class="mt-2 text-xs text-rose-600"
      >
        {{ form.errors.members }}
      </p>
    </OrgTeamFormCollapsibleSection>

    <OrgTeamFormCollapsibleSection
      v-if="showAdvanced"
      step="3"
      title="Cách hiển thị trên sơ đồ"
      hint="Thứ tự và ẩn/hiện — ít khi cần chỉnh"
      optional
      :badge="displayOptionsBadge"
    >
      <div class="space-y-4">
        <div>
          <label class="label">Xếp chỗ trên sơ đồ</label>
          <select
            :value="form.sort_order"
            class="input w-full"
            @change="form.sort_order = Number($event.target.value)"
          >
            <option
              v-for="opt in sortOrderOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </div>
        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-100 bg-slate-50/60 px-4 py-3">
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-800">
              Cho phép hiện trên sơ đồ
            </p>
            <p class="mt-0.5 text-xs text-slate-500">
              Tắt nếu tạm ẩn đơn vị (không xóa dữ liệu).
            </p>
          </div>
          <ToggleSwitch v-model="form.is_active" />
        </div>
      </div>
    </OrgTeamFormCollapsibleSection>

    <OrgTeamFormCollapsibleSection
      v-if="isRootTeam"
      title="Bắt đầu từ mẫu Phòng CNTT"
      hint="Gợi ý sẵn nhóm chức danh — chỉ dùng lần đầu"
      optional
      badge="Mẫu"
    >
      <div class="rounded-lg border border-dashed border-brand/25 bg-brand/[0.03] px-4 py-4">
        <p class="text-sm text-slate-700">
          Hệ thống thêm các nhóm «Trưởng ban CNTT», «Phó Phòng Công nghệ» nếu bạn chưa tạo.
        </p>
        <button
          type="button"
          class="btn-secondary mt-3 h-9 px-3 text-xs"
          @click="applyTechTemplate"
        >
          Dùng mẫu này
        </button>
      </div>
    </OrgTeamFormCollapsibleSection>

    <div
      v-if="showActions"
      class="rounded-xl border border-brand/15 bg-brand/[0.04] p-4"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600">
          <template v-if="isEdit && (unitDisplayName || form.name)">
            Thay đổi áp dụng cho <span class="font-semibold text-slate-800">«{{ unitDisplayName || form.name }}»</span>.
          </template>
          <template v-else>
            Kiểm tra lại thông tin trước khi tạo.
          </template>
        </p>
        <div class="flex shrink-0 flex-wrap justify-end gap-2">
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
            class="btn-primary inline-flex min-w-[10rem] items-center justify-center gap-1.5"
            :disabled="form.processing"
          >
            <AppIcon
              name="save"
              :size="16"
            />
            {{ saveLabel }}
          </button>
        </div>
      </div>
    </div>
  </form>
</template>
