<script setup>
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FormField from '@/shared/ui/form/FormField.vue';
import TextInput from '@/shared/ui/form/TextInput.vue';
import PasswordInput from '@/shared/ui/form/PasswordInput.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';

// Generic editor for a scalar settings group (general / auth / telegram).
// Field defs come from the backend SettingsSchema so this stays single-sourced.
const props = defineProps({
    group: { type: String, required: true },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    fields: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

// Build the form model from field values. Lists are edited as newline text.
function buildModel() {
    const data = {};
    for (const f of props.fields) {
        if (f.type === 'list') data[f.name] = (f.value ?? []).join('\n');
        else if (f.type === 'bool') data[f.name] = !!f.value;
        else data[f.name] = f.value ?? '';
    }
    return data;
}

const form = useForm(buildModel());

function submit() {
    if (!props.canManage) return;
    form
        .transform((data) => {
            const out = { ...data };
            for (const f of props.fields) {
                if (f.type === 'list') {
                    out[f.name] = String(out[f.name] ?? '')
                        .split('\n')
                        .map((s) => s.trim())
                        .filter(Boolean);
                }
            }
            return out;
        })
        .put(`/settings/${props.group}`, {
            preserveScroll: true,
            onSuccess: () => {
                // Clear typed secrets, then treat current state as clean.
                for (const f of props.fields) {
                    if (f.type === 'secret') form[f.name] = '';
                }
                form.defaults();
            },
        });
}

const secretPlaceholder = (f) => (f.has_value ? '•••••••• (đã lưu — để trống nếu không đổi)' : 'Chưa thiết lập');
</script>

<template>
  <form
    class="flex h-full flex-col"
    @submit.prevent="submit"
  >
    <div class="mb-5">
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
        <!-- Boolean: label/help left, switch right -->
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
              v-if="f.help"
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

        <!-- Secret -->
        <FormField
          v-else-if="f.type === 'secret'"
          :id="f.key"
          :label="f.label"
          :hint="f.help"
          :error="form.errors[f.name]"
        >
          <PasswordInput
            :id="f.key"
            v-model="form[f.name]"
            :placeholder="secretPlaceholder(f)"
          />
        </FormField>

        <!-- List (newline-separated) -->
        <FormField
          v-else-if="f.type === 'list'"
          :id="f.key"
          :label="f.label"
          :hint="f.help"
          :error="form.errors[f.name]"
        >
          <textarea
            :id="f.key"
            v-model="form[f.name]"
            rows="3"
            :disabled="!canManage"
            class="input w-full font-mono text-[13px] leading-relaxed disabled:bg-slate-50"
            placeholder="vaschools.edu.vn"
          />
        </FormField>

        <!-- String / default -->
        <FormField
          v-else
          :id="f.key"
          :label="f.label"
          :hint="f.help"
          :error="form.errors[f.name]"
        >
          <TextInput
            :id="f.key"
            v-model="form[f.name]"
            :disabled="!canManage"
          />
        </FormField>
      </template>
    </div>

    <!-- Save bar -->
    <div class="mt-7 flex items-center gap-3 border-t border-slate-100 pt-4">
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
        /> Đã lưu
      </span>
    </div>
  </form>
</template>
