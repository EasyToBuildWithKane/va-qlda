<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import OrgTeamStructureNode from '@/modules/people/components/OrgTeamStructureNode.vue';
import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';

const props = defineProps({
    node: { type: Object, required: true },
    depth: { type: Number, default: 0 },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'add-child', 'delete', 'select-person']);

const children = computed(() => toIterableList(props.node.children));

const leader = computed(() => props.node.leader ?? null);

const sectionRows = computed(() => {
    const sections = toIterableList(props.node.sections)
        .slice()
        .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
    const members = toIterableList(props.node.members);
    const leaderId = leader.value?.id ?? null;

    const bySectionId = new Map();
    const unassigned = [];

    for (const m of members) {
        const emp = m.employee;
        if (!emp?.id || emp.id === leaderId) {
            continue;
        }
        const sectionId = m.section?.id ?? m.section_id ?? null;
        const row = {
            id: m.id,
            employee: emp,
            sectionTitle: m.section?.title ?? null,
            branchLabel: m.branch?.label ?? null,
        };
        if (sectionId == null) {
            unassigned.push(row);
        } else {
            if (!bySectionId.has(sectionId)) {
                bySectionId.set(sectionId, []);
            }
            bySectionId.get(sectionId).push(row);
        }
    }

    const rows = sections.map((sec) => ({
        key: `sec-${sec.id}`,
        title: (sec.title ?? '').trim() || 'Nhánh',
        empty: !(bySectionId.get(sec.id)?.length),
        people: bySectionId.get(sec.id) ?? [],
    }));

    if (unassigned.length) {
        rows.push({
            key: 'unassigned',
            title: 'Chưa gán nhánh',
            empty: false,
            people: unassigned,
        });
    }

    return rows;
});

const memberTotal = computed(() =>
    sectionRows.value.reduce((sum, g) => sum + g.people.length, 0),
);

function personPayload(emp, extra = {}) {
    return {
        name: emp.name,
        avatar: emp.avatar_path ?? null,
        email: emp.email ?? null,
        code: emp.code ?? null,
        roleTitle: emp.role_title ?? null,
        isLeader: false,
        ...extra,
    };
}

function openLeader() {
    if (!leader.value) {
        return;
    }
    emit('select-person', personPayload(leader.value, {
        isLeader: true,
        teamName: props.node.name,
        branchLabel: 'Quản lý',
    }));
}

function openMember(row) {
    emit('select-person', personPayload(row.employee, {
        teamName: props.node.name,
        sectionTitle: row.sectionTitle,
        branchLabel: row.branchLabel,
    }));
}

const depthPad = computed(() => ({
    paddingLeft: props.depth > 0 ? `${Math.min(props.depth, 3) * 0.75}rem` : undefined,
}));
</script>

<template>
  <article
    class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm"
    :class="depth === 0 ? 'ring-1 ring-brand/10' : ''"
  >
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 bg-slate-50/60 px-4 py-3 sm:px-5">
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="font-display text-sm font-semibold text-slate-900 sm:text-base">
            {{ node.name }}
          </h3>
          <span class="rounded-md bg-slate-200/80 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
            {{ node.level_label || (node.level === 1 ? 'Nhóm chính' : 'Nhóm con') }}
          </span>
          <span
            v-if="node.is_active === false"
            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500"
          >
            Ngừng hoạt động
          </span>
        </div>
        <p class="mt-0.5 text-xs text-slate-500">
          {{ leader ? '1 quản lý' : 'Chưa có quản lý' }}
          <span v-if="memberTotal"> · {{ memberTotal }} thành viên</span>
          <span v-if="children.length"> · {{ children.length }} nhóm con</span>
        </p>
      </div>
      <div
        v-if="canManage"
        class="flex shrink-0 flex-wrap items-center gap-1.5"
      >
        <button
          v-if="node.level < 2"
          type="button"
          class="btn-ghost flex h-8 items-center gap-1 px-2 text-xs"
          @click="emit('add-child', node)"
        >
          <AppIcon
            name="plus"
            :size="14"
          />
          Nhóm con
        </button>
        <button
          type="button"
          class="btn-ghost flex h-8 items-center gap-1 px-2 text-xs"
          @click="emit('edit', node)"
        >
          <AppIcon
            name="edit"
            :size="14"
          />
          Sửa
        </button>
        <button
          v-if="node.can?.delete"
          type="button"
          class="btn-ghost flex h-8 items-center gap-1 px-2 text-xs text-rose-600 hover:bg-rose-50"
          @click="emit('delete', node)"
        >
          <AppIcon
            name="delete"
            :size="14"
          />
          Xóa
        </button>
      </div>
    </header>

    <div class="space-y-4 px-4 py-4 sm:px-5">
      <div>
        <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Quản lý
        </p>
        <button
          v-if="leader"
          type="button"
          class="flex w-full max-w-md items-center gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2.5 text-left transition-colors hover:border-brand/25 hover:bg-brand/5"
          @click="openLeader()"
        >
          <Avatar
            :name="leader.name"
            :src="leader.avatar_path"
            :size="40"
          />
          <span class="min-w-0">
            <span class="block truncate text-sm font-semibold text-slate-800">{{ leader.name }}</span>
            <span class="block truncate text-xs text-slate-500">{{ leader.role_title || leader.code }}</span>
          </span>
          <Badge
            label="Quản lý"
            color="amber"
          />
        </button>
        <p
          v-else
          class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-500"
        >
          Chưa chọn quản lý — bấm «Sửa» để gán (vd. Giám đốc CNTT kiêm Trưởng phòng).
        </p>
      </div>

      <div v-if="sectionRows.length">
        <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Nhánh / vị trí ngang hàng
        </p>
        <div class="space-y-3">
          <div
            v-for="group in sectionRows"
            :key="group.key"
            class="rounded-lg border border-slate-100 bg-slate-50/40"
          >
            <div class="flex items-center justify-between gap-2 border-b border-slate-100/80 px-3 py-2">
              <span class="text-xs font-semibold text-slate-700">{{ group.title }}</span>
              <span
                v-if="group.empty"
                class="text-[10px] text-slate-400"
              >
                Chưa có người — thêm trong «Sửa»
              </span>
            </div>
            <ul
              v-if="group.people.length"
              class="divide-y divide-slate-100"
            >
              <li
                v-for="row in group.people"
                :key="row.id"
              >
                <button
                  type="button"
                  class="flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-white"
                  @click="openMember(row)"
                >
                  <Avatar
                    :name="row.employee.name"
                    :src="row.employee.avatar_path"
                    :size="36"
                  />
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-slate-800">{{ row.employee.name }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ row.employee.role_title || row.employee.code }}</span>
                  </span>
                  <Badge
                    v-if="row.branchLabel"
                    :label="row.branchLabel"
                    color="violet"
                  />
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
      <p
        v-else-if="depth === 0"
        class="rounded-lg border border-dashed border-brand/20 bg-brand/5 px-3 py-3 text-xs leading-relaxed text-slate-600"
      >
        Thêm <strong class="font-semibold text-slate-800">Nhánh</strong> trong «Sửa» cho các vị trí ngang hàng
        (vd. Trưởng ban CNTT, Phó Phòng Công nghệ), rồi gán nhân sự vào từng nhánh.
      </p>

      <div
        v-if="children.length"
        class="space-y-3 pt-1"
      >
        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Nhóm con
        </p>
        <div
          class="space-y-3"
          :style="depthPad"
        >
          <OrgTeamStructureNode
            v-for="child in children"
            :key="child.id"
            :node="child"
            :depth="depth + 1"
            :can-manage="canManage"
            @edit="emit('edit', $event)"
            @add-child="emit('add-child', $event)"
            @delete="emit('delete', $event)"
            @select-person="emit('select-person', $event)"
          />
        </div>
      </div>
      <p
        v-else-if="node.level === 1"
        class="rounded-lg border border-dashed border-slate-200 px-3 py-3 text-xs text-slate-500"
      >
        Chưa có nhóm con — bấm «Nhóm con» để tạo Phần mềm, Phần cứng, …
      </p>
    </div>
  </article>
</template>
