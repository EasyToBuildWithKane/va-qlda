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

const levelBadge = computed(() => {
    if (props.node.level_label) {
        return props.node.level_label;
    }

    return props.node.level === 1 ? 'Cấp quản lý' : 'Đơn vị';
});

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
        people: bySectionId.get(sec.id) ?? [],
    }));

    if (unassigned.length) {
        rows.push({
            key: 'unassigned',
            title: 'Thành viên khác',
            people: unassigned,
        });
    }

    return rows;
});

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
            {{ levelBadge }}
          </span>
          <span
            v-if="node.is_active === false"
            class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-500"
          >
            Ngừng hoạt động
          </span>
        </div>
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
          Đơn vị con
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
          class="text-xs text-slate-500"
        >
          Chưa có quản lý
        </p>
      </div>

      <div v-if="sectionRows.length">
        <p
          v-if="node.level === 1"
          class="mb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400"
        >
          Cấp quản lý
        </p>
        <div class="space-y-3">
          <div
            v-for="group in sectionRows"
            :key="group.key"
            class="rounded-lg border border-slate-100 bg-slate-50/40"
          >
            <div class="border-b border-slate-100/80 px-3 py-2">
              <span class="text-xs font-semibold text-slate-700">{{ group.title }}</span>
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

      <div
        v-if="children.length"
        class="space-y-3 pt-1"
      >
        <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Đơn vị
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
    </div>
  </article>
</template>
