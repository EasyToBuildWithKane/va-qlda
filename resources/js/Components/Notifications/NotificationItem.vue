<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { CATEGORY_ICONS, formatTime, PRIORITY_STYLES } from '@/composables/notificationMeta';

const props = defineProps({
    notification: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    selectable: { type: Boolean, default: false },
});

const emit = defineEmits(['click', 'acknowledge', 'toggle-select']);

const icon = computed(() => CATEGORY_ICONS[props.notification.category] ?? 'notifications');
const priorityClass = computed(() => PRIORITY_STYLES[props.notification.priority] ?? PRIORITY_STYLES.medium);

const metaLine = computed(() => {
    const parts = [];
    if (props.notification.project?.name) parts.push(props.notification.project.name);
    parts.push(formatTime(props.notification.created_at));
    return parts.join(' · ');
});

const bodyLines = computed(() => {
    const raw = props.notification.body?.trim();
    if (!raw) return [];
    return raw.split('\n').map((line) => line.trim()).filter(Boolean);
});
</script>

<template>
  <article
    class="group relative flex gap-2 rounded-lg border px-2 py-2 transition-colors cursor-pointer"
    :class="[
      notification.is_read
        ? 'border-transparent bg-transparent hover:bg-slate-50 dark:hover:bg-slate-800/60'
        : 'border-brand/15 bg-brand/[0.04] hover:bg-brand/[0.07] dark:border-brand/25 dark:bg-brand/10',
      notification.is_critical && !notification.acknowledged_at ? 'ring-1 ring-rose-200 dark:ring-rose-900' : '',
    ]"
    @click="emit('click', notification)"
  >
    <div
      v-if="selectable"
      class="flex items-center shrink-0"
      @click.stop
    >
      <input
        type="checkbox"
        class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand/30"
        :checked="selected"
        @change="emit('toggle-select', notification.id)"
      >
    </div>

    <div
      class="relative grid h-7 w-7 shrink-0 place-items-center rounded-md"
      :class="notification.is_read ? 'bg-slate-100 dark:bg-slate-800' : 'bg-brand/10'"
    >
      <AppIcon
        :name="icon"
        :size="14"
        :class="notification.is_read ? 'text-slate-400' : 'text-brand'"
      />
      <span
        v-if="!notification.is_read"
        class="absolute -right-0.5 -top-0.5 h-1.5 w-1.5 rounded-full bg-brand ring-1 ring-white dark:ring-slate-900"
        aria-hidden="true"
      />
    </div>

    <div class="min-w-0 flex-1">
      <div class="flex items-start gap-1.5">
        <p class="flex-1 break-words text-[12px] font-medium leading-snug text-slate-800 dark:text-slate-100">
          {{ notification.title }}
        </p>
        <span
          v-if="notification.priority === 'critical' || notification.priority === 'high'"
          class="shrink-0 rounded px-1 py-px text-[9px] font-bold uppercase ring-1 ring-inset"
          :class="priorityClass"
        >
          {{ notification.priority === 'critical' ? '!' : '↑' }}
        </span>
      </div>
      <div
        v-if="bodyLines.length"
        class="mt-0.5 space-y-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
      >
        <p
          v-for="(line, idx) in bodyLines"
          :key="idx"
          class="break-words"
        >
          {{ line }}
        </p>
      </div>
      <p class="mt-0.5 break-words text-[10px] text-slate-400 dark:text-slate-500">
        {{ metaLine }}
      </p>
    </div>

    <button
      v-if="notification.is_critical && !notification.acknowledged_at"
      type="button"
      class="shrink-0 self-center rounded px-1.5 py-0.5 text-[9px] font-medium text-rose-600 opacity-0 hover:bg-rose-50 group-hover:opacity-100 dark:hover:bg-rose-950/50"
      @click.stop="emit('acknowledge', notification)"
    >
      OK
    </button>
  </article>
</template>
