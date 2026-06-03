<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    proposals: { type: Array, default: () => [] },
    modelValue: { type: [String, null], default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'pick']);

const inputRef = ref(null);
const panelRef = ref(null);
const open = ref(false);
const query = ref('');
const panelStyle = ref({});

const options = computed(() =>
    (props.proposals ?? []).map((p) => ({
        ...p,
        subtitle: [p.group_label, p.cost_unit_label].filter(Boolean).join(' · '),
    })),
);

const selected = computed(() =>
    options.value.find((p) => String(p.id) === String(props.modelValue)) ?? null,
);

const filtered = computed(() => {
    const q = query.value.trim();
    const list = options.value;
    if (!q) return list.slice(0, 40);
    return list
        .filter((p) => matchesSearchQuery(
            [p.label, p.proposal_code, p.tool_name, p.group_label],
            q,
        ))
        .slice(0, 40);
});

function syncQueryFromSelection() {
    if (selected.value) {
        query.value = selected.value.label ?? '';
        return;
    }
    if (!props.modelValue) {
        query.value = '';
    }
}

watch(() => [props.modelValue, props.proposals], syncQueryFromSelection, { immediate: true });

function pick(item) {
    emit('update:modelValue', item.id);
    emit('pick', item);
    query.value = item.label ?? '';
    open.value = false;
}

function onInput() {
    open.value = true;
    const q = query.value.trim();
    if (!q) {
        emit('update:modelValue', null);
        emit('pick', null);
        return;
    }
    const exact = options.value.find(
        (p) => p.label?.trim().toLowerCase() === q.toLowerCase()
            || p.proposal_code?.trim().toLowerCase() === q.toLowerCase(),
    );
    if (exact) {
        emit('update:modelValue', exact.id);
        emit('pick', exact);
    } else {
        emit('update:modelValue', null);
        emit('pick', null);
    }
}

function positionPanel() {
    const el = inputRef.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    panelStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        zIndex: 9999,
    };
}

async function onFocus() {
    if (props.disabled) return;
    open.value = true;
    await nextTick();
    positionPanel();
}

function onDocMousedown(e) {
    if (panelRef.value?.contains(e.target) || inputRef.value?.contains(e.target)) return;
    open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onDocMousedown);
        window.addEventListener('resize', positionPanel);
        window.addEventListener('scroll', positionPanel, true);
    } else {
        document.removeEventListener('mousedown', onDocMousedown);
        window.removeEventListener('resize', positionPanel);
        window.removeEventListener('scroll', positionPanel, true);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocMousedown);
    window.removeEventListener('resize', positionPanel);
    window.removeEventListener('scroll', positionPanel, true);
});
</script>

<template>
  <div class="relative min-w-0">
    <label
      for="approved-proposal-pick"
      class="label mb-0 flex min-h-[1.25rem] items-center gap-1"
    >
      Mã phiếu — tên sản phẩm
      <span class="text-danger">*</span>
    </label>
    <div class="relative">
      <input
        id="approved-proposal-pick"
        ref="inputRef"
        v-model="query"
        type="text"
        class="input h-10 w-full pr-10"
        placeholder="VD: PDX-20260603-001 hoặc UX Pilot AI"
        autocomplete="off"
        :disabled="disabled"
        required
        @focus="onFocus"
        @input="onInput"
      >
      <AppIcon
        name="search"
        :size="16"
        class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"
      />
    </div>
    <p
      v-if="!proposals.length && !disabled"
      class="mt-1.5 text-xs text-amber-700"
    >
      Chưa có phiếu đã duyệt chờ lập tài khoản. Duyệt phiếu tại
      <a
        :href="route('ai-accounts.cost-report')"
        class="font-medium underline"
      >Chi phí AI</a>.
    </p>

    <Teleport to="body">
      <ul
        v-if="open && filtered.length"
        ref="panelRef"
        class="max-h-56 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        :style="panelStyle"
        role="listbox"
      >
        <li
          v-for="item in filtered"
          :key="item.id"
          role="option"
          class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-soft"
          @mousedown.prevent="pick(item)"
        >
          <div class="font-medium text-slate-800">
            {{ item.proposal_code }} — {{ item.tool_name }}
          </div>
          <div class="text-xs text-slate-500">
            {{ item.subtitle }}
          </div>
        </li>
      </ul>
    </Teleport>
  </div>
</template>
