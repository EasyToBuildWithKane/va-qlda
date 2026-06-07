<script setup>
import Drawer from '@/Components/Ui/Drawer.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    show: { type: Boolean, default: false },
    /** @type {{ name: string, avatar?: string|null, isLeader?: boolean, teamName?: string|null, sectionTitle?: string|null, branchLabel?: string|null, roleTitle?: string|null, email?: string|null, code?: string|null }} */
    person: { type: Object, default: null },
});

const emit = defineEmits(['close']);
</script>

<template>
  <Drawer
    :show="show"
    title="Thành viên"
    width="max-w-sm"
    @close="emit('close')"
  >
    <div
      v-if="person"
      class="space-y-5"
    >
      <div class="flex flex-col items-center rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-5 text-center">
        <Avatar
          :src="person.avatar"
          :name="person.name"
          :size="72"
        />
        <p class="mt-3 font-display text-base font-semibold text-slate-900">
          {{ person.name }}
        </p>
        <p
          v-if="person.roleTitle"
          class="mt-1 text-sm text-slate-600"
        >
          {{ person.roleTitle }}
        </p>
        <span
          v-if="person.isLeader"
          class="mt-2 inline-flex items-center gap-1 rounded-full bg-brand/10 px-2.5 py-0.5 text-[11px] font-semibold text-brand"
        >
          <AppIcon
            name="members"
            :size="12"
          />
          Trưởng nhóm
        </span>
      </div>

      <dl class="space-y-3 text-sm">
        <div
          v-if="person.teamName"
          class="flex gap-3"
        >
          <dt class="w-24 shrink-0 text-slate-500">
            Nhóm
          </dt>
          <dd class="min-w-0 flex-1 font-medium text-slate-800">
            {{ person.teamName }}
          </dd>
        </div>
        <div
          v-if="person.sectionTitle"
          class="flex gap-3"
        >
          <dt class="w-24 shrink-0 text-slate-500">
            Mảng
          </dt>
          <dd class="min-w-0 flex-1 font-medium text-slate-800">
            {{ person.sectionTitle }}
          </dd>
        </div>
        <div
          v-if="person.branchLabel"
          class="flex gap-3"
        >
          <dt class="w-24 shrink-0 text-slate-500">
            Vai trò
          </dt>
          <dd class="min-w-0 flex-1 font-medium text-slate-800">
            {{ person.branchLabel }}
          </dd>
        </div>
        <div
          v-if="person.code"
          class="flex gap-3"
        >
          <dt class="w-24 shrink-0 text-slate-500">
            Mã NV
          </dt>
          <dd class="min-w-0 flex-1 font-mono text-xs text-slate-800">
            {{ person.code }}
          </dd>
        </div>
        <div
          v-if="person.email"
          class="flex gap-3"
        >
          <dt class="w-24 shrink-0 text-slate-500">
            Email
          </dt>
          <dd class="min-w-0 flex-1 break-all text-slate-800">
            <a
              :href="`mailto:${person.email}`"
              class="text-brand hover:underline"
            >{{ person.email }}</a>
          </dd>
        </div>
      </dl>
    </div>
  </Drawer>
</template>
