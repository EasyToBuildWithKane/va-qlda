<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { route } from 'ziggy-js';
import { useToast } from '@/shared/composables/useToast';

const emit = defineEmits(['close']);
const toast = useToast();

const loading = ref(true);
const saving = ref(false);
const prefs = ref({
    channel_in_app: true,
    channel_email: false,
    channel_push: false,
    disabled_types: [],
});
const types = ref([]);

onMounted(async () => {
    try {
        const { data } = await axios.get(route('notifications.preferences'));
        prefs.value = { ...prefs.value, ...data.preferences };
        types.value = data.types ?? [];
    } finally {
        loading.value = false;
    }
});

function isEnabled(typeValue) {
    return !prefs.value.disabled_types.includes(typeValue);
}

function toggleType(typeValue) {
    const set = new Set(prefs.value.disabled_types);
    if (set.has(typeValue)) set.delete(typeValue);
    else set.add(typeValue);
    prefs.value.disabled_types = [...set];
}

async function save() {
    saving.value = true;
    try {
        const { data } = await axios.put(route('notifications.preferences.update'), prefs.value);
        prefs.value = { ...prefs.value, ...data.preferences };
        toast.success(data.message ?? 'Đã lưu cài đặt.');
        emit('close');
    } catch {
        toast.error('Không lưu được cài đặt.');
    } finally {
        saving.value = false;
    }
}

const groupedTypes = () => {
    const map = {};
    for (const t of types.value) {
        map[t.category] ??= [];
        map[t.category].push(t);
    }
    return map;
};
</script>

<template>
  <div class="space-y-4">
    <p class="text-[13px] text-slate-500 dark:text-slate-400">
      Chọn kênh nhận thông báo và loại sự kiện bạn quan tâm.
    </p>

    <div
      v-if="loading"
      class="py-8 text-center text-sm text-slate-400"
    >
      Đang tải…
    </div>

    <template v-else>
      <div class="space-y-2 rounded-xl border border-slate-100 p-3 dark:border-slate-800">
        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">
          Kênh
        </h3>
        <label class="flex items-center gap-2 text-[13px]">
          <input
            v-model="prefs.channel_in_app"
            type="checkbox"
            class="rounded text-brand"
          >
          Trong ứng dụng
        </label>
        <label class="flex items-center gap-2 text-[13px] text-slate-400">
          <input
            v-model="prefs.channel_email"
            type="checkbox"
            disabled
            class="rounded"
          >
          Email <span class="text-[10px]">(sắp ra mắt)</span>
        </label>
        <label class="flex items-center gap-2 text-[13px] text-slate-400">
          <input
            v-model="prefs.channel_push"
            type="checkbox"
            disabled
            class="rounded"
          >
          Browser Push <span class="text-[10px]">(sắp ra mắt)</span>
        </label>
      </div>

      <div class="max-h-[50vh] overflow-y-auto space-y-3">
        <div
          v-for="(list, category) in groupedTypes()"
          :key="category"
          class="rounded-xl border border-slate-100 p-3 dark:border-slate-800"
        >
          <h3 class="mb-2 text-xs font-semibold uppercase text-slate-500">
            {{ category }}
          </h3>
          <label
            v-for="t in list"
            :key="t.value"
            class="flex items-center gap-2 py-1 text-[12px] text-slate-700 dark:text-slate-300"
          >
            <input
              type="checkbox"
              class="rounded text-brand"
              :checked="isEnabled(t.value)"
              @change="toggleType(t.value)"
            >
            {{ t.label }}
          </label>
        </div>
      </div>

      <div class="flex gap-2 pt-2">
        <button
          type="button"
          class="btn-secondary flex-1"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="button"
          class="btn-primary flex-1"
          :disabled="saving"
          @click="save"
        >
          {{ saving ? 'Đang lưu…' : 'Lưu' }}
        </button>
      </div>
    </template>
  </div>
</template>
