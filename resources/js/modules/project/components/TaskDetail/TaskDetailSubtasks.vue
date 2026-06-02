<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/shared/composables/useToast';
import { normalizeEntities } from '@/composables/useNormalizeList';

const props = defineProps({
    taskId: { type: Number, required: true },
    projectId: { type: Number, required: true },
    subtasks: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
    /** Cha đã là subtask → không cho thêm con. */
    isParentSubtask: { type: Boolean, default: false },
});

const subtaskList = computed(() => normalizeEntities(props.subtasks));

const totalHours = computed(() =>
    subtaskList.value.reduce((s, t) => s + (Number(t.estimate_hours) || 0), 0),
);

const emit = defineEmits(['open-task', 'created']);

const toast = useToast();
const title = ref('');
const estimateHours = ref(null);
const adding = ref(false);

const addSubtask = () => {
    const t = title.value.trim();
    if (!t) return;
    if (props.isParentSubtask) {
        toast.warning('Chỉ hỗ trợ 1 cấp công việc con.');
        return;
    }
    adding.value = true;
    router.post(`/projects/${props.projectId}/tasks/${props.taskId}/subtasks`, {
        title: t,
        estimate_hours: estimateHours.value || null,
    }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => {
            title.value = '';
            estimateHours.value = null;
            toast.success('Đã thêm công việc con');
            emit('created');
        },
        onError: () => toast.error('Không tạo được công việc con'),
        onFinish: () => { adding.value = false; },
    });
};

const toggleDone = (st) => {
    const next = st.status?.value === 'done' ? 'todo' : 'done';
    router.patch(`/projects/${props.projectId}/tasks/${st.id}`, { status: next }, {
        preserveScroll: true,
        only: ['tasks'],
    });
};

const patchHours = (st, raw) => {
    const val = raw === '' ? null : Number(raw);
    router.patch(`/projects/${props.projectId}/tasks/${st.id}`, {
        estimate_hours: Number.isFinite(val) ? val : null,
    }, {
        preserveScroll: true,
        only: ['tasks'],
    });
};
</script>

<template>
  <section class="rounded-xl border border-slate-200/80 dark:border-slate-700">
    <div class="flex items-center justify-between border-b border-slate-100 px-3 py-2 dark:border-slate-800">
      <div>
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">
          Công việc con
          <span class="font-normal text-slate-400">({{ subtaskList.length }})</span>
        </h3>
        <p class="mt-0.5 text-[10px] text-slate-400">
          Chỉ ước tính giờ · ngày bắt đầu/hạn theo công việc cha
          <span v-if="totalHours"> · Tổng {{ totalHours }}h</span>
        </p>
      </div>
    </div>

    <ul
      v-if="subtaskList.length"
      class="divide-y divide-slate-100 dark:divide-slate-800"
    >
      <li
        v-for="st in subtaskList"
        :key="st.id"
        class="flex items-center gap-2 px-3 py-2"
      >
        <button
          v-if="canEdit"
          type="button"
          class="grid h-5 w-5 shrink-0 place-items-center rounded border border-slate-300 dark:border-slate-600"
          :class="st.status?.value === 'done' ? 'border-emerald-500 bg-emerald-500 text-white' : ''"
          @click="toggleDone(st)"
        >
          <AppIcon
            v-if="st.status?.value === 'done'"
            name="check"
            :size="12"
          />
        </button>
        <div class="min-w-0 flex-1">
          <p
            class="truncate text-sm font-medium"
            :class="st.status?.value === 'done' ? 'text-slate-400 line-through' : 'text-slate-800 dark:text-slate-100'"
          >
            {{ st.title }}
          </p>
        </div>
        <label
          v-if="canEdit"
          class="flex shrink-0 items-center gap-1 text-[11px] text-slate-500"
        >
          <input
            type="number"
            :value="st.estimate_hours ?? ''"
            min="0"
            step="0.5"
            class="input w-14 py-0.5 text-center text-xs"
            placeholder="h"
            @change="patchHours(st, $event.target.value)"
          >
          <span>h</span>
        </label>
        <span
          v-else-if="st.estimate_hours != null"
          class="shrink-0 text-xs font-medium text-brand"
        >{{ st.estimate_hours }}h</span>
      </li>
    </ul>

    <form
      v-if="canEdit && !isParentSubtask"
      class="flex flex-wrap gap-2 border-t border-slate-100 p-3 dark:border-slate-800"
      @submit.prevent="addSubtask"
    >
      <input
        v-model="title"
        type="text"
        class="input min-w-[10rem] flex-1 text-sm"
        placeholder="Tiêu đề công việc con…"
      >
      <input
        v-model.number="estimateHours"
        type="number"
        min="0"
        step="0.5"
        class="input w-20 text-sm"
        placeholder="Giờ"
        title="Ước tính giờ"
      >
      <button
        type="submit"
        class="btn-primary text-xs"
        :disabled="adding || !title.trim()"
      >
        <AppIcon
          name="add"
          :size="14"
        />
      </button>
    </form>
    <p
      v-else-if="isParentSubtask"
      class="px-3 py-3 text-center text-xs text-slate-400"
    >
      Công việc này là việc con — không thể thêm cấp con thêm.
    </p>
    <p
      v-else-if="!subtaskList.length"
      class="px-3 py-4 text-center text-xs text-slate-400"
    >
      Chưa có công việc con.
    </p>
  </section>
</template>
