<script setup>
import { computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FormField from '@/shared/ui/form/FormField.vue';
import TextInput from '@/shared/ui/form/TextInput.vue';
import { useDialog } from '@/composables/useDialog';
import RepeatableList from './RepeatableList.vue';

const props = defineProps({
    section: { type: Object, required: true },
    icons: { type: Array, default: () => [] },
    metricKeys: { type: Array, default: () => [] },
    tones: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const dialog = useDialog();

// Parent gắn :key="section.key" nên component remount mỗi khi đổi mục → form
// luôn khởi tạo từ dữ liệu mới nhất.
const form = useForm({
    content: JSON.parse(JSON.stringify(props.section.data ?? {})),
    is_visible: props.section.is_visible ?? true,
});

const fields = computed(() => props.section.editor ?? []);

function errorFor(path) {
    return form.errors[`content.${path}`] ?? null;
}

function submit() {
    if (!props.canManage) return;
    form.put(`/congnghe/quan-tri/sections/${props.section.key}`, {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
}

async function resetDefault() {
    if (!props.canManage) return;
    const ok = await dialog.confirm({
        title: 'Khôi phục mặc định',
        message: `Đặt lại nội dung mục «${props.section.label}» về mặc định? Thao tác không thể hoàn tác.`,
        tone: 'danger',
        confirmText: 'Khôi phục',
    });
    if (!ok) return;
    router.post(`/congnghe/quan-tri/sections/${props.section.key}/reset`, {}, { preserveScroll: true });
}
</script>

<template>
  <form
    class="flex h-full flex-col"
    @submit.prevent="submit"
  >
    <div class="mb-5 flex items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-[15px] font-semibold text-slate-800">
          {{ section.label }}
        </h2>
        <p class="mt-0.5 text-[12.5px] text-slate-400">
          <span v-if="section.is_overridden">Đã tuỳ chỉnh</span>
          <span v-else>Đang dùng nội dung mặc định</span>
        </p>
      </div>
      <span
        v-if="section.orderable && !section.is_visible"
        class="shrink-0 inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500"
      >
        <AppIcon
          name="eye-off"
          :size="13"
        />
        Đang ẩn
      </span>
    </div>

    <div class="space-y-5">
      <template
        v-for="field in fields"
        :key="field.path || field.type"
      >
        <!-- Tiêu đề mục (eyebrow / title / subtitle) -->
        <div
          v-if="field.type === 'heading'"
          class="grid gap-3 rounded-card border border-slate-100 bg-slate-50/50 p-3.5 sm:grid-cols-2"
        >
          <p class="sm:col-span-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            {{ field.label }}
          </p>
          <FormField
            label="Nhãn nhỏ (eyebrow)"
            :error="errorFor(`${field.path}.eyebrow`)"
          >
            <TextInput
              v-model="form.content[field.path].eyebrow"
              :disabled="!canManage"
            />
          </FormField>
          <FormField
            label="Tiêu đề"
            required
            :error="errorFor(`${field.path}.title`)"
          >
            <TextInput
              v-model="form.content[field.path].title"
              :disabled="!canManage"
            />
          </FormField>
          <FormField
            class="sm:col-span-2"
            label="Mô tả"
            :error="errorFor(`${field.path}.subtitle`)"
          >
            <textarea
              v-model="form.content[field.path].subtitle"
              rows="2"
              class="input w-full"
              :disabled="!canManage"
            />
          </FormField>
        </div>

        <!-- Cặp liên kết (label + anchor) -->
        <div
          v-else-if="field.type === 'link'"
          class="grid gap-3 sm:grid-cols-2"
        >
          <FormField
            :label="`${field.label} — nhãn`"
            :error="errorFor(`${field.path}.label`)"
          >
            <TextInput
              v-model="form.content[field.path].label"
              :disabled="!canManage"
            />
          </FormField>
          <FormField
            :label="`${field.label} — neo (#id)`"
            :error="errorFor(`${field.path}.anchor`)"
          >
            <TextInput
              v-model="form.content[field.path].anchor"
              :disabled="!canManage"
            />
          </FormField>
        </div>

        <!-- Cặp khoá–giá trị cố định (vd. mô tả giai đoạn) -->
        <div
          v-else-if="field.type === 'kv'"
          class="space-y-3"
        >
          <p class="text-sm font-medium text-slate-700">
            {{ field.label }}
          </p>
          <FormField
            v-for="row in field.rows"
            :key="row.key"
            :label="row.label"
            :error="errorFor(`${field.path}.${row.key}`)"
          >
            <textarea
              v-model="form.content[field.path][row.key]"
              rows="2"
              class="input w-full"
              :disabled="!canManage"
            />
          </FormField>
        </div>

        <!-- Danh sách lặp -->
        <div v-else-if="field.type === 'list'">
          <p class="mb-2 text-sm font-medium text-slate-700">
            {{ field.label }}
          </p>
          <RepeatableList
            v-model="form.content[field.path]"
            :fields="field.fields"
            :item-label="field.item_label"
            :min="field.min ?? 0"
            :max="field.max ?? 12"
            :icons="icons"
            :metric-keys="metricKeys"
            :tones="tones"
            :error-prefix="`content.${field.path}`"
            :errors="form.errors"
            :disabled="!canManage"
          />
        </div>

        <!-- Văn bản nhiều dòng -->
        <FormField
          v-else-if="field.type === 'textarea'"
          :label="field.label"
          :required="!!field.required"
          :error="errorFor(field.path)"
        >
          <textarea
            v-model="form.content[field.path]"
            rows="3"
            class="input w-full"
            :disabled="!canManage"
          />
        </FormField>

        <!-- Văn bản một dòng -->
        <FormField
          v-else
          :label="field.label"
          :required="!!field.required"
          :error="errorFor(field.path)"
        >
          <TextInput
            v-model="form.content[field.path]"
            :disabled="!canManage"
          />
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
      <button
        type="button"
        class="btn-ghost border border-slate-200"
        :disabled="!canManage || !section.is_overridden"
        @click="resetDefault"
      >
        <AppIcon
          name="refresh"
          :size="16"
        />
        Khôi phục mặc định
      </button>
      <span
        v-if="form.isDirty"
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
        Đã lưu
      </span>
    </div>
  </form>
</template>
