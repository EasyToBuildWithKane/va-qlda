<script setup>
import { computed } from 'vue';
import Avatar from '@/shared/ui/Avatar.vue';

const props = defineProps({
    self: { type: Object, default: null },
    reports: { type: Array, default: () => [] },
    activeEmployeeId: { type: [Number, String], default: null },
});

const emit = defineEmits(['select']);

const people = computed(() => {
    const list = [];
    if (props.self?.id) {
        list.push({ ...props.self, isSelf: true });
    }
    (props.reports ?? []).forEach((p) => {
        if (!p?.id || p.id === props.self?.id) return;
        list.push({ ...p, isSelf: false });
    });
    return list;
});

const activeId = computed(() => {
    if (props.activeEmployeeId) return Number(props.activeEmployeeId);
    return props.self?.id ? Number(props.self.id) : null;
});

function onSelect(person) {
    emit('select', person.isSelf ? null : person.id);
}
</script>

<template>
  <div
    v-if="people.length > 1 || (people.length === 1 && reports.length)"
    class="flex gap-2 overflow-x-auto pb-0.5"
  >
    <button
      v-for="person in people"
      :key="person.id"
      type="button"
      class="flex shrink-0 items-center gap-2 rounded-full border px-2 py-1 pr-3 text-left transition"
      :class="activeId === person.id
        ? 'border-brand bg-brand/5 ring-1 ring-brand/20'
        : 'border-slate-200 bg-white hover:border-brand/30'"
      @click="onSelect(person)"
    >
      <Avatar
        :name="person.name"
        :src="person.avatar_path"
        :size="28"
      />
      <span class="min-w-0">
        <span class="block max-w-[9rem] truncate text-xs font-semibold text-slate-800">
          {{ person.isSelf ? 'Tôi' : person.name }}
        </span>
        <span
          v-if="person.role_title"
          class="block max-w-[9rem] truncate text-[10px] text-slate-500"
        >
          {{ person.role_title }}
        </span>
      </span>
    </button>
  </div>
</template>
