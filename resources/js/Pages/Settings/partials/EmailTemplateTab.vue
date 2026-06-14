<script setup>
/* eslint-disable vue/no-v-html -- admin email template preview with sample placeholders */
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FieldsTab from './FieldsTab.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    emailFields: { type: Array, default: () => [] },
    emailTemplates: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const selectedId = ref(props.emailTemplates[0]?.id ?? null);
const previewOpen = ref(false);
const previewHtml = ref('');

const selectedTemplate = computed(() =>
    props.emailTemplates.find((t) => t.id === selectedId.value) ?? null,
);

const templateForm = useForm({
    subject: '',
    body_html: '',
    is_active: true,
});

watch(
    selectedTemplate,
    (t) => {
        if (!t) return;
        templateForm.subject = t.subject;
        templateForm.body_html = t.body_html;
        templateForm.is_active = !!t.is_active;
        templateForm.clearErrors();
        templateForm.defaults({
            subject: t.subject,
            body_html: t.body_html,
            is_active: !!t.is_active,
        });
    },
    { immediate: true },
);

function saveTemplate() {
    const t = selectedTemplate.value;
    if (!t || !props.canManage) return;
    templateForm.put(route('settings.email-templates.update', t.id), {
        preserveScroll: true,
        onSuccess: () => templateForm.defaults(),
    });
}

const sampleVars = {
    assignee_name: 'Nguyễn Văn A',
    task_name: 'Thiết kế màn hình Sprint',
    project_name: 'Dự án mẫu',
    sprint_name: 'Sprint 1',
    due_date: '20/06/2026',
    task_url: '#',
    date: '14/06/2026',
    task_count: '3',
    tasks_table: '<p><em>[Bảng công việc]</em></p>',
};

function renderPreview(text) {
    let out = text ?? '';
    Object.entries(sampleVars).forEach(([key, val]) => {
        out = out.split(`{{${key}}}`).join(val);
    });
    return out;
}

function openPreview() {
    const t = selectedTemplate.value;
    if (!t) return;
    previewHtml.value = renderPreview(t.body_html);
    previewOpen.value = true;
}

function variableToken(name) {
    return `{{${name}}}`;
}
function insertVariable(token) {
    if (!props.canManage) return;
    templateForm.body_html = `${templateForm.body_html}{{${token}}}`;
}
</script>

<template>
  <div class="space-y-8">
    <FieldsTab
      group="email"
      :title="title"
      :description="description"
      :fields="emailFields"
      :can-manage="canManage"
    />

    <div class="border-t border-slate-100 pt-8">
      <h3 class="text-[15px] font-semibold text-slate-800">
        Mẫu email
      </h3>
      <p class="mt-0.5 text-[12.5px] text-slate-400">
        Chỉnh tiêu đề và nội dung HTML; dùng biến dạng <code class="text-[11px]">&#123;&#123;tên_biến&#125;&#125;</code>.
      </p>

      <div class="mt-4 flex flex-col gap-4 lg:flex-row">
        <ul class="flex shrink-0 gap-2 overflow-x-auto lg:w-52 lg:flex-col lg:overflow-visible">
          <li
            v-for="t in emailTemplates"
            :key="t.id"
          >
            <button
              type="button"
              class="w-full rounded-card border px-3 py-2.5 text-left text-sm transition"
              :class="selectedId === t.id
                ? 'border-brand/30 bg-brand/[0.06] font-medium text-brand'
                : 'border-slate-100 hover:bg-slate-50 text-slate-700'"
              @click="selectedId = t.id"
            >
              {{ t.name }}
            </button>
          </li>
        </ul>

        <div
          v-if="selectedTemplate"
          class="min-w-0 flex-1 space-y-4 rounded-card border border-slate-100 p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ selectedTemplate.key }}</span>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">Kích hoạt</span>
              <ToggleSwitch
                v-model="templateForm.is_active"
                :disabled="!canManage"
              />
            </div>
          </div>

          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Tiêu đề</label>
            <input
              v-model="templateForm.subject"
              type="text"
              class="input w-full"
              :disabled="!canManage"
            >
            <p
              v-if="templateForm.errors.subject"
              class="mt-1 text-xs text-rose-600"
            >
              {{ templateForm.errors.subject }}
            </p>
          </div>

          <div class="flex flex-col gap-4 xl:flex-row">
            <div class="min-w-0 flex-1">
              <label class="mb-1 block text-sm font-medium text-slate-700">Nội dung HTML</label>
              <textarea
                v-model="templateForm.body_html"
                rows="14"
                class="input w-full font-mono text-[12px] leading-relaxed"
                :disabled="!canManage"
              />
              <p
                v-if="templateForm.errors.body_html"
                class="mt-1 text-xs text-rose-600"
              >
                {{ templateForm.errors.body_html }}
              </p>
            </div>
            <aside class="shrink-0 xl:w-44">
              <p class="mb-2 text-xs font-medium text-slate-500">
                Biến gợi ý
              </p>
              <div class="flex flex-wrap gap-1.5 xl:flex-col">
                <button
                  v-for="v in selectedTemplate.variables"
                  :key="v"
                  type="button"
                  class="rounded border border-slate-200 bg-slate-50 px-2 py-1 text-left font-mono text-[11px] text-slate-600 hover:border-brand/30 hover:text-brand"
                  :disabled="!canManage"
                  @click="insertVariable(v)"
                >
                  {{ variableToken(v) }}
                </button>
              </div>
            </aside>
          </div>

          <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
            <button
              type="button"
              class="btn-primary"
              :disabled="!canManage || templateForm.processing || !templateForm.isDirty"
              @click="saveTemplate"
            >
              <AppIcon
                name="save"
                :size="16"
              />
              Lưu mẫu
            </button>
            <button
              type="button"
              class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="openPreview"
            >
              <AppIcon
                name="eye"
                :size="15"
              />
              Xem trước
            </button>
          </div>
        </div>
      </div>
    </div>

    <Modal
      :show="previewOpen"
      title="Xem trước mẫu email"
      max-width="max-w-2xl"
      @close="previewOpen = false"
    >
      <div
        class="prose prose-sm max-w-none rounded border border-slate-100 bg-white p-4"
        v-html="previewHtml"
      />
    </Modal>
  </div>
</template>
