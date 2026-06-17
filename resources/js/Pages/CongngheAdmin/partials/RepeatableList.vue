<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import FormField from '@/shared/ui/form/FormField.vue';
import TextInput from '@/shared/ui/form/TextInput.vue';
import SelectInput from '@/shared/ui/form/SelectInput.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';
import IconPicker from './IconPicker.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    fields: { type: Array, default: () => [] },
    itemLabel: { type: String, default: 'Mục' },
    min: { type: Number, default: 0 },
    max: { type: Number, default: 12 },
    icons: { type: Array, default: () => [] },
    metricKeys: { type: Array, default: () => [] },
    tones: { type: Array, default: () => [] },
    errorPrefix: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const rows = computed(() => props.modelValue ?? []);
const canAdd = computed(() => !props.disabled && rows.value.length < props.max);
const canRemove = computed(() => rows.value.length > props.min);

const toneOptions = computed(() => props.tones.map((t) => ({ value: t, label: t })));

function blankItem() {
    const item = {};
    for (const f of props.fields) {
        if (f.type === 'bool') item[f.key] = false;
        else if (f.type === 'stringlist') item[f.key] = [];
        else if (f.type === 'number') item[f.key] = null;
        else item[f.key] = '';
    }
    return item;
}

function updateField(index, key, value) {
    emit('update:modelValue', rows.value.map((it, i) => (i === index ? { ...it, [key]: value } : it)));
}

function updateStringList(index, key, text) {
    const items = text.split('\n').map((s) => s.trim()).filter(Boolean);
    updateField(index, key, items);
}

function addItem() {
    if (!canAdd.value) return;
    emit('update:modelValue', [...rows.value, blankItem()]);
}

function removeItem(index) {
    if (!canRemove.value) return;
    emit('update:modelValue', rows.value.filter((_, i) => i !== index));
}

function move(index, dir) {
    const j = index + dir;
    if (j < 0 || j >= rows.value.length) return;
    const next = [...rows.value];
    [next[index], next[j]] = [next[j], next[index]];
    emit('update:modelValue', next);
}

function errorFor(index, key) {
    return props.errors?.[`${props.errorPrefix}.${index}.${key}`] ?? null;
}

function stringListValue(item, key) {
    return Array.isArray(item[key]) ? item[key].join('\n') : '';
}
</script>

<template>
  <div class="space-y-3">
    <div
      v-for="(item, index) in rows"
      :key="index"
      class="rounded-card border border-slate-200 bg-slate-50/60 p-3.5"
    >
      <div class="mb-2.5 flex items-center justify-between">
        <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
          {{ itemLabel }} {{ index + 1 }}
        </span>
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-600 disabled:opacity-30"
            :disabled="disabled || index === 0"
            title="Lên"
            @click="move(index, -1)"
          >
            <svg
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            ><path d="m18 15-6-6-6 6" /></svg>
          </button>
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-200 hover:text-slate-600 disabled:opacity-30"
            :disabled="disabled || index === rows.length - 1"
            title="Xuống"
            @click="move(index, 1)"
          >
            <svg
              width="15"
              height="15"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            ><path d="m6 9 6 6 6-6" /></svg>
          </button>
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-md text-rose-400 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-30"
            :disabled="disabled || !canRemove"
            title="Xoá"
            @click="removeItem(index)"
          >
            <AppIcon
              name="delete"
              :size="15"
            />
          </button>
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <FormField
          v-for="f in fields"
          :key="f.key"
          :label="f.label"
          :error="errorFor(index, f.key)"
          :class="(f.type === 'textarea' || f.type === 'stringlist') ? 'sm:col-span-2' : ''"
        >
          <textarea
            v-if="f.type === 'textarea'"
            :value="item[f.key]"
            rows="2"
            class="input w-full"
            :disabled="disabled"
            @input="updateField(index, f.key, $event.target.value)"
          />
          <textarea
            v-else-if="f.type === 'stringlist'"
            :value="stringListValue(item, f.key)"
            rows="3"
            class="input w-full"
            :disabled="disabled"
            placeholder="Mỗi dòng một mục"
            @input="updateStringList(index, f.key, $event.target.value)"
          />
          <IconPicker
            v-else-if="f.type === 'icon'"
            :model-value="item[f.key]"
            :icons="icons"
            :disabled="disabled"
            @update:model-value="updateField(index, f.key, $event)"
          />
          <SelectInput
            v-else-if="f.type === 'metric'"
            :model-value="item[f.key]"
            :options="metricKeys"
            value-key="key"
            label-key="label"
            placeholder="Chọn chỉ số…"
            :disabled="disabled"
            @update:model-value="updateField(index, f.key, $event)"
          />
          <SelectInput
            v-else-if="f.type === 'tone'"
            :model-value="item[f.key]"
            :options="toneOptions"
            placeholder="Mặc định"
            :disabled="disabled"
            @update:model-value="updateField(index, f.key, $event)"
          />
          <input
            v-else-if="f.type === 'number'"
            type="number"
            :value="item[f.key]"
            :min="f.min ?? 0"
            :max="f.max ?? 100"
            step="1"
            class="input w-full"
            :disabled="disabled"
            @input="updateField(index, f.key, $event.target.value === '' ? null : Number($event.target.value))"
          >
          <div
            v-else-if="f.type === 'bool'"
            class="pt-1"
          >
            <ToggleSwitch
              :model-value="!!item[f.key]"
              :disabled="disabled"
              @update:model-value="updateField(index, f.key, $event)"
            />
          </div>
          <TextInput
            v-else
            :model-value="item[f.key]"
            :disabled="disabled"
            @update:model-value="updateField(index, f.key, $event)"
          />
        </FormField>
      </div>
    </div>

    <button
      type="button"
      class="flex w-full items-center justify-center gap-1.5 rounded-card border border-dashed border-slate-300 py-2.5 text-sm font-medium text-slate-500 transition hover:border-brand/40 hover:text-brand disabled:opacity-40"
      :disabled="!canAdd"
      @click="addItem"
    >
      <AppIcon
        name="plus"
        :size="16"
      />
      Thêm {{ itemLabel.toLowerCase() }}
    </button>
  </div>
</template>
