<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { matchesSearchKey } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    options: { type: Array, default: () => [] },
    modelValue: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Tìm & thêm người…' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const search = ref('');
const trigger = ref(null);
const panel = ref(null);
const dropdownStyle = ref({});
const openUp = ref(false);

const selectedIds = computed(() => props.modelValue ?? []);

const selectedPeople = computed(() =>
    props.options.filter((o) => selectedIds.value.includes(o.id)),
);

const filtered = computed(() => {
    const q = search.value.trim();
    if (!q) return props.options;
    return props.options.filter((o) => matchesSearchKey(o.name, q));
});

const isOn = (id) => selectedIds.value.includes(id);

const toggle = (o) => {
    const next = isOn(o.id)
        ? selectedIds.value.filter((x) => x !== o.id)
        : [...selectedIds.value, o.id];
    emit('update:modelValue', next);
};

const remove = (id) => {
    emit('update:modelValue', selectedIds.value.filter((x) => x !== id));
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
        zIndex: 70,
        ...(openUp.value
            ? { bottom: `${window.innerHeight - rect.top + gap}px` }
            : { top: `${rect.bottom + gap}px` }),
    };
};

const onPointerDownOutside = (e) => {
    const t = e.target;
    if (trigger.value?.contains(t) || panel.value?.contains(t)) return;
    open.value = false;
    search.value = '';
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
        search.value = '';
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
  <div class="space-y-2">
    <div
      v-if="selectedPeople.length"
      class="flex flex-wrap gap-1.5"
    >
      <span
        v-for="p in selectedPeople"
        :key="p.id"
        class="inline-flex items-center gap-1 rounded-full border border-brand/25 bg-brand-50 py-0.5 pl-1 pr-1.5 text-sm text-brand-800 dark:bg-brand-950/40 dark:text-brand-100"
      >
        <Avatar
          :name="p.name"
          :src="p.avatar_path"
          :size="20"
        />
        <span class="max-w-[10rem] truncate">{{ p.name }}</span>
        <button
          type="button"
          class="grid h-5 w-5 place-items-center rounded-full text-brand/60 hover:bg-brand/10 hover:text-brand"
          :aria-label="`Bỏ ${p.name}`"
          @click="remove(p.id)"
        >
          <AppIcon
            name="close"
            :size="12"
          />
        </button>
      </span>
    </div>

    <div class="relative">
      <button
        :id="id"
        ref="trigger"
        type="button"
        class="input flex w-full items-center gap-2 text-left"
        @click="open = !open"
      >
        <AppIcon
          name="search"
          :size="16"
          class="shrink-0 text-slate-400"
        />
        <span class="flex-1 truncate text-slate-400">{{ placeholder }}</span>
        <span
          v-if="selectedPeople.length"
          class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800"
        >
          {{ selectedPeople.length }}
        </span>
        <AppIcon
          name="chevron-down"
          :size="16"
          class="shrink-0 text-slate-400"
        />
      </button>

      <Teleport to="body">
        <div
          v-if="open"
          ref="panel"
          :style="dropdownStyle"
          class="overflow-hidden rounded-card border border-slate-200 bg-white shadow-elevation-2 dark:border-slate-700 dark:bg-slate-900"
        >
          <div class="border-b border-slate-100 p-2 dark:border-slate-700">
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
              class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
              :class="isOn(o.id) ? 'bg-brand-50 dark:bg-brand-950/30' : ''"
              @click="toggle(o)"
            >
              <input
                type="checkbox"
                class="rounded accent-brand"
                :checked="isOn(o.id)"
                tabindex="-1"
                @click.stop
                @change="toggle(o)"
              >
              <Avatar
                :name="o.name"
                :src="o.avatar_path"
                :size="24"
              />
              <span class="flex-1 truncate text-slate-700 dark:text-slate-200">{{ o.name }}</span>
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
  </div>
</template>
