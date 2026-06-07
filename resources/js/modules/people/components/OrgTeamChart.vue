<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import OrgTeamRoster from '@/modules/people/components/OrgTeamRoster.vue';

defineProps({
    node: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['edit', 'add-child', 'delete']);

const levelShell = {
    1: {
        ring: 'ring-brand/25',
        border: 'border-brand/20',
        header: 'bg-brand text-white',
        dot: 'bg-brand',
    },
    2: {
        ring: 'ring-sky-200/80',
        border: 'border-sky-100',
        header: 'bg-sky-600 text-white',
        dot: 'bg-sky-500',
    },
    3: {
        ring: 'ring-violet-200/80',
        border: 'border-violet-100',
        header: 'bg-violet-600 text-white',
        dot: 'bg-violet-500',
    },
};

function shell(level) {
    return levelShell[level] || {
        ring: 'ring-slate-200',
        border: 'border-slate-200',
        header: 'bg-slate-600 text-white',
        dot: 'bg-slate-400',
    };
}
</script>

<template>
  <div class="flex min-w-[17rem] max-w-md flex-col items-center">
    <article
      class="w-full overflow-hidden rounded-2xl border bg-white shadow-md ring-2 transition-shadow hover:shadow-lg"
      :class="[shell(node.level).ring, shell(node.level).border]"
    >
      <header
        class="px-4 py-3"
        :class="shell(node.level).header"
      >
        <p class="text-[10px] font-bold uppercase tracking-wider text-white/80">
          {{ node.level_label }}
        </p>
        <h3 class="font-display mt-0.5 text-lg font-semibold leading-snug text-white">
          {{ node.name }}
        </h3>
      </header>

      <div class="p-4">
        <OrgTeamRoster
          :leader="node.leader"
          :members="node.members"
          :level="node.level"
        />

        <div
          v-if="canManage && node.can?.update"
          class="mt-4 flex flex-wrap gap-1.5 border-t border-slate-100 pt-3"
        >
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-700 shadow-sm hover:bg-slate-50"
            @click="emit('edit', node)"
          >
            <AppIcon
              name="edit"
              :size="13"
            />
            Sửa
          </button>
          <button
            v-if="node.level < 3"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-brand/20 bg-brand/5 px-2.5 py-1.5 text-[11px] font-medium text-brand hover:bg-brand/10"
            @click="emit('add-child', node)"
          >
            <AppIcon
              name="plus"
              :size="13"
            />
            Nhóm con
          </button>
          <button
            v-if="node.can?.delete"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-rose-100 bg-rose-50 px-2.5 py-1.5 text-[11px] font-medium text-rose-700 hover:bg-rose-100"
            @click="emit('delete', node)"
          >
            <AppIcon
              name="delete"
              :size="13"
            />
            Xoá
          </button>
        </div>
      </div>
    </article>

    <!-- Nhóm con -->
    <div
      v-if="node.children?.length"
      class="relative mt-2 flex w-full flex-col items-center"
    >
      <div
        class="flex h-10 w-px flex-col items-center"
        aria-hidden="true"
      >
        <div
          class="h-full w-px bg-gradient-to-b from-slate-300 to-slate-200"
        />
      </div>

      <p
        v-if="node.children.length > 1"
        class="mb-2 text-[10px] font-semibold uppercase tracking-wide text-slate-400"
      >
        {{ node.children.length }} nhóm con
      </p>

      <div
        class="relative flex w-full flex-wrap items-start justify-center gap-x-8 gap-y-10 px-2 pb-2"
      >
        <div
          v-if="node.children.length > 1"
          class="pointer-events-none absolute top-0 left-[12%] right-[12%] h-px bg-slate-200"
          aria-hidden="true"
        />
        <div
          v-for="child in node.children"
          :key="child.id"
          class="relative flex flex-col items-center"
        >
          <div
            class="mb-0 h-6 w-px bg-slate-200"
            aria-hidden="true"
          />
          <span
            class="mb-2 h-2 w-2 rounded-full ring-4 ring-white"
            :class="shell(child.level).dot"
            aria-hidden="true"
          />
          <OrgTeamChart
            :node="child"
            :can-manage="canManage"
            @edit="emit('edit', $event)"
            @add-child="emit('add-child', $event)"
            @delete="emit('delete', $event)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
