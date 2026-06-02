<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';

const props = defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: [Number, String, null], default: null },
    placeholder: { type: String, default: 'Tìm & chọn người…' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');
const trigger = ref(null);
const panel = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const selected = computed(() => props.options.find((o) => o.id === props.modelValue) || null);

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => o.name.toLowerCase().includes(q));
});

const choose = (o) => {
    emit('update:modelValue', o ? o.id : null);
    open.value = false;
    search.value = '';
};

const positionPanel = async () => {
    await nextTick();
    const el = trigger.value;
    if (!el) return;

    const rect = el.getBoundingClientRect();
    const panelMaxH = 280;
    const gap = 4;
    const spaceBelow = window.innerHeight - rect.bottom - gap;
    const spaceAbove = rect.top - gap;
    openUp.value = spaceBelow < panelMaxH && spaceAbove > spaceBelow;

    dropdownStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: 50,
        ...(openUp.value
            ? { bottom: `${window.innerHeight - rect.top + gap}px` }
            : { top: `${rect.bottom + gap}px` }),
    };
};

const onPointerDownOutside = (e) => {
    const t = e.target;
    if (trigger.value?.contains(t) || panel.value?.contains(t)) return;
    open.value = false;
};

let scrollListener = null;

watch(open, async (isOpen) => {
    if (isOpen) {
        await positionPanel();
        scrollListener = () => positionPanel();
        window.addEventListener('scroll', scrollListener, true);
        window.addEventListener('resize', scrollListener);
        document.addEventListener('mousedown', onPointerDownOutside);
    } else {
        if (scrollListener) {
            window.removeEventListener('scroll', scrollListener, true);
            window.removeEventListener('resize', scrollListener);
            scrollListener = null;
        }
        document.removeEventListener('mousedown', onPointerDownOutside);
    }
});

onBeforeUnmount(() => {
    if (scrollListener) {
        window.removeEventListener('scroll', scrollListener, true);
        window.removeEventListener('resize', scrollListener);
    }
    document.removeEventListener('mousedown', onPointerDownOutside);
});
</script>

<template>
  <div class="relative">
    <button
      :id="id"
      ref="trigger"
      type="button"
      class="input flex w-full items-center gap-2 text-left"
      @click="open = !open"
    >
      <template v-if="selected">
        <Avatar
          :name="selected.name"
          :src="selected.avatar_path"
          :size="22"
        />
        <span class="flex-1 truncate text-slate-700">{{ selected.name }}</span>
        <span
          class="grid h-5 w-5 place-items-center rounded text-slate-300 hover:text-rose-500"
          role="button"
          aria-label="Bỏ chọn"
          @click.stop="choose(null)"
        >
          <AppIcon
            name="close"
            :size="14"
          />
        </span>
      </template>
      <template v-else>
        <span class="flex-1 truncate text-slate-400">{{ placeholder }}</span>
        <AppIcon
          name="chevron-down"
          :size="16"
          class="text-slate-400"
        />
      </template>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        ref="panel"
        :style="dropdownStyle"
        class="overflow-hidden rounded-card border border-slate-200 bg-white shadow-elevation-2"
      >
        <div class="border-b border-slate-100 p-2">
          <div class="relative">
            <AppIcon
              name="search"
              :size="15"
              class="pointer-events-none absolute left-2.5 top-2.5 text-slate-400"
            />
            <input
              v-model="search"
              type="text"
              class="input py-1.5 pl-8 text-sm"
              placeholder="Tìm theo tên…"
              autofocus
            >
          </div>
        </div>
        <ul class="max-h-56 overflow-y-auto py-1">
          <li
            v-for="o in filtered"
            :key="o.id"
            class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50"
            :class="o.id === modelValue ? 'bg-brand-50' : ''"
            @click="choose(o)"
          >
            <Avatar
              :name="o.name"
              :src="o.avatar_path"
              :size="24"
            />
            <span class="flex-1 truncate text-slate-700">{{ o.name }}</span>
            <AppIcon
              v-if="o.id === modelValue"
              name="check"
              :size="15"
              class="text-brand"
            />
          </li>
          <li
            v-if="filtered.length === 0"
            class="px-3 py-3 text-center text-sm text-slate-400"
          >
            Không tìm thấy.
          </li>
        </ul>
      </div>
    </Teleport>
  </div>
</template>
