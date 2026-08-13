<script setup>
import { ref, watch, onUnmounted, inject, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FormField from '@/shared/ui/form/FormField.vue';
import TextInput from '@/shared/ui/form/TextInput.vue';
import PasswordInput from '@/shared/ui/form/PasswordInput.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';
import TagInput from './TagInput.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    group: { type: String, required: true },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    fields: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    hideHeader: { type: Boolean, default: false },
    hideFieldHints: { type: Boolean, default: false },
    suppressDirtyReport: { type: Boolean, default: false },
    saveHotkeysEnabled: { type: Boolean, default: true },
});

const emit = defineEmits(['dirty-change']);
const toast = useToast();
const setGroupDirty = inject('setGroupDirty', null);
const savedAt = ref(null);

function buildModel() {
    const data = {};
    for (const f of props.fields) {
        if (f.type === 'list') data[f.name] = [...(f.value ?? [])];
        else if (f.type === 'bool') data[f.name] = !!f.value;
        else data[f.name] = f.value ?? '';
    }
    return data;
}

const form = useForm(buildModel());

const hasSecretFields = computed(() => props.fields.some((f) => f.type === 'secret'));

watch(
    () => form.isDirty,
    (dirty) => {
        if (props.suppressDirtyReport) {
            emit('dirty-change', dirty);
            return;
        }
        if (typeof setGroupDirty === 'function') {
            setGroupDirty(props.group, dirty);
        }
    },
    { immediate: true },
);

function isDirty() {
    return form.isDirty;
}

defineExpose({ isDirty });

watch(
    () => props.fields,
    () => {
        const data = buildModel();
        Object.keys(data).forEach((k) => {
            form[k] = data[k];
        });
        form.defaults(data);
    },
    { deep: true },
);

function formatSavedTime(date) {
    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

function submit() {
    if (!props.canManage) return;
    form
        .transform((data) => {
            const out = { ...data };
            for (const f of props.fields) {
                if (f.type === 'list') {
                    out[f.name] = Array.isArray(out[f.name])
                        ? out[f.name].map((s) => String(s).trim()).filter(Boolean)
                        : [];
                }
            }
            return out;
        })
        .put(`/settings/${props.group}`, {
            preserveScroll: true,
            onSuccess: () => {
                for (const f of props.fields) {
                    if (f.type === 'secret') form[f.name] = '';
                }
                form.defaults();
                savedAt.value = new Date();
            },
            onError: () => {
                toast.error('Không lưu được. Phiên có thể đã hết hạn — trang sẽ tải lại.');
            },
        });
}

function onKeydownSave(e) {
    if (!props.saveHotkeysEnabled) return;
    if (!(e.ctrlKey || e.metaKey) || e.key !== 's') return;
    if (!props.canManage || !form.isDirty || form.processing) return;
    e.preventDefault();
    submit();
}

function bindSaveHotkeys() {
    document.addEventListener('keydown', onKeydownSave);
}

function unbindSaveHotkeys() {
    document.removeEventListener('keydown', onKeydownSave);
}

watch(
    () => props.saveHotkeysEnabled,
    (enabled) => {
        unbindSaveHotkeys();
        if (enabled) bindSaveHotkeys();
    },
    { immediate: true },
);

onUnmounted(() => unbindSaveHotkeys());

async function copyField(name) {
    const val = String(form[name] ?? '');
    if (!val) return;
    try {
        await navigator.clipboard.writeText(val);
        toast.success('Đã sao chép');
    } catch {
        toast.error('Không sao chép được');
    }
}

const secretPlaceholder = (f) => (f.has_value ? '•••••••• (đã lưu — để trống nếu không đổi)' : 'Chưa thiết lập');
</script>

<template>
  <form
    class="relative flex h-full flex-col"
    @submit.prevent="submit"
  >
    <!-- Chrome/password-manager: secret fields need a username hint (one hidden field per form). -->
    <input
      v-if="hasSecretFields"
      type="text"
      name="username"
      autocomplete="username"
      tabindex="-1"
      aria-hidden="true"
      class="pointer-events-none absolute h-0 w-0 opacity-0"
      value="vaschools-settings"
      readonly
    >
    <div
      v-if="!hideHeader"
      class="mb-5"
    >
      <h2 class="text-[15px] font-semibold text-slate-800">
        {{ title }}
      </h2>
      <p
        v-if="description"
        class="mt-0.5 text-[12.5px] text-slate-400"
      >
        {{ description }}
      </p>
    </div>

    <div class="space-y-5">
      <template
        v-for="f in fields"
        :key="f.key"
      >
        <div
          v-if="f.type === 'bool'"
          class="flex items-start justify-between gap-4 rounded-card border border-slate-100 bg-slate-50/50 px-3.5 py-3"
        >
          <div class="min-w-0">
            <label
              :for="f.key"
              class="block text-sm font-medium text-slate-700"
            >{{ f.label }}</label>
            <p
              v-if="f.help && !hideFieldHints"
              class="mt-0.5 text-xs text-slate-400"
            >
              {{ f.help }}
            </p>
          </div>
          <ToggleSwitch
            :id="f.key"
            v-model="form[f.name]"
            :disabled="!canManage"
          />
        </div>

        <FormField
          v-else-if="f.type === 'secret'"
          :id="f.key"
          :label="f.label"
          :hint="hideFieldHints ? null : f.help"
          :error="form.errors[f.name]"
        >
          <PasswordInput
            :id="f.key"
            v-model="form[f.name]"
            :placeholder="secretPlaceholder(f)"
            autocomplete="new-password"
          />
        </FormField>

        <FormField
          v-else-if="f.type === 'list'"
          :id="f.key"
          :label="f.label"
          :hint="hideFieldHints ? null : f.help"
          :error="form.errors[f.name]"
        >
          <TagInput
            :id="f.key"
            v-model="form[f.name]"
            :disabled="!canManage"
            placeholder="vaschools.edu.vn"
          />
        </FormField>

        <FormField
          v-else-if="f.type === 'select'"
          :id="f.key"
          :label="f.label"
          :hint="hideFieldHints ? null : f.help"
          :error="form.errors[f.name]"
        >
          <select
            :id="f.key"
            v-model="form[f.name]"
            class="input w-full"
            :disabled="!canManage"
          >
            <option
              v-for="opt in (f.options ?? [])"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </option>
          </select>
        </FormField>

        <FormField
          v-else-if="f.type === 'textarea'"
          :id="f.key"
          :label="f.label"
          :hint="hideFieldHints ? null : f.help"
          :error="form.errors[f.name]"
        >
          <textarea
            :id="f.key"
            v-model="form[f.name]"
            rows="12"
            class="input min-h-[12rem] w-full resize-y font-mono text-xs leading-relaxed"
            :disabled="!canManage"
            placeholder="Để trống nếu dùng prompt mặc định của hệ thống"
          />
        </FormField>

        <FormField
          v-else
          :id="f.key"
          :label="f.label"
          :hint="hideFieldHints ? null : f.help"
          :error="form.errors[f.name]"
        >
          <div class="relative [&_input]:pr-10">
            <TextInput
              :id="f.key"
              v-model="form[f.name]"
              :disabled="!canManage"
            />
            <button
              v-if="canManage && String(form[f.name] ?? '').length"
              type="button"
              class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
              title="Sao chép"
              @click="copyField(f.name)"
            >
              <AppIcon
                name="copy"
                :size="15"
              />
            </button>
          </div>
        </FormField>
      </template>
    </div>

    <div class="mt-7 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
      <button
        type="submit"
        class="btn-primary"
        :disabled="!canManage || form.processing || !form.isDirty"
      >
        <AppIcon
          name="save"
          :size="16"
        />
        Lưu thay đổi
      </button>
      <span
        v-if="form.isDirty && canManage"
        class="text-xs text-amber-600"
      >Có thay đổi chưa lưu</span>
      <span
        v-else-if="form.recentlySuccessful"
        class="inline-flex items-center gap-1 text-xs text-emerald-600"
      >
        <AppIcon
          name="check"
          :size="14"
        />
        Đã lưu<span v-if="savedAt"> lúc {{ formatSavedTime(savedAt) }}</span>
      </span>
      <span
        v-if="canManage"
        class="text-[11px] text-slate-400"
      >Ctrl+S để lưu</span>
    </div>
  </form>
</template>
