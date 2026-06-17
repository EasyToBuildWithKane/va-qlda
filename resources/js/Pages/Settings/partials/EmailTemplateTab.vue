<script setup>
import { ref, computed, watch, inject } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import FieldsTab from './FieldsTab.vue';
import EmailTemplateEditor from './EmailTemplateEditor.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';
import FormField from '@/shared/ui/form/FormField.vue';
import TextInput from '@/shared/ui/form/TextInput.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import { buildTemplatePreviewDocument } from '@/composables/useEmailTemplatePreview';

const props = defineProps({
    title: { type: String, default: '' },
    emailFields: { type: Array, default: () => [] },
    emailTemplates: { type: Array, default: () => [] },
    emailPreviewBrand: { type: String, default: 'VAschools QLDA' },
    emailTestRecipient: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
    saveHotkeysEnabled: { type: Boolean, default: true },
});

const dialog = useDialog();
const toast = useToast();
const setGroupDirty = inject('setGroupDirty', null);
const emailConfigDirty = ref(false);

const SUBJECT_MAX = 60;

const activeSection = ref('config');
const selectedId = ref(props.emailTemplates[0]?.id ?? null);
const editorTab = ref('split');
const previewFullscreen = ref(false);
const testModalOpen = ref(false);

const selectedTemplate = computed(() =>
    props.emailTemplates.find((t) => t.id === selectedId.value) ?? null,
);

const templateForm = useForm({
    subject: '',
    body_html: '',
    is_active: true,
});

const testForm = useForm({
    email: '',
    subject: '',
    body_html: '',
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

const livePreview = computed(() => buildTemplatePreviewDocument(
    templateForm.subject,
    templateForm.body_html,
    props.emailPreviewBrand,
));

const subjectLength = computed(() => templateForm.subject.length);

const subjectCounterClass = computed(() => {
    if (subjectLength.value > SUBJECT_MAX) return 'text-rose-600';
    if (subjectLength.value > 50) return 'text-amber-600';
    return 'text-slate-400';
});

function syncEmailTabDirty() {
    if (typeof setGroupDirty !== 'function') return;
    setGroupDirty('email', emailConfigDirty.value || templateForm.isDirty);
}

function onEmailConfigDirty(dirty) {
    emailConfigDirty.value = dirty;
    syncEmailTabDirty();
}

watch(
    () => templateForm.isDirty,
    () => syncEmailTabDirty(),
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

async function resetTemplate() {
    const t = selectedTemplate.value;
    if (!t || !props.canManage) return;
    const ok = await dialog.confirm({
        title: 'Khôi phục mẫu chuẩn?',
        message: 'Nội dung hiện tại sẽ được thay bằng mẫu mặc định của hệ thống.',
        tone: 'warning',
        confirmText: 'Khôi phục',
    });
    if (!ok) return;
    router.post(route('settings.email-templates.reset', t.id), {}, { preserveScroll: true });
}

function applyDefaultToEditor() {
    const t = selectedTemplate.value;
    if (!t || !props.canManage) return;
    templateForm.subject = t.default_subject ?? t.subject;
    templateForm.body_html = t.default_body_html ?? t.body_html;
}

function openTestModal() {
    const t = selectedTemplate.value;
    if (!t || !props.canManage) return;
    testForm.clearErrors();
    testForm.email = props.emailTestRecipient || '';
    testForm.subject = templateForm.subject;
    testForm.body_html = templateForm.body_html;
    testModalOpen.value = true;
}

function sendTestEmail() {
    const t = selectedTemplate.value;
    if (!t) return;
    testForm.post(route('settings.email-templates.test', t.id), {
        preserveScroll: true,
        onSuccess: () => {
            testModalOpen.value = false;
            toast.success('Đã gửi email thử.');
        },
    });
}

const sectionTabs = [
    { key: 'config', label: 'Cấu hình gửi' },
    { key: 'templates', label: 'Mẫu thông báo' },
];
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h2 class="text-[15px] font-semibold text-slate-800">
        {{ title || 'Email & Thông báo' }}
      </h2>
      <div
        v-if="emailTemplates.length && activeSection === 'templates'"
        class="text-xs text-slate-500"
      >
        {{ emailTemplates.filter((t) => t.is_active).length }}/{{ emailTemplates.length }} mẫu đang bật
      </div>
    </div>

    <nav
      class="inline-flex max-w-full rounded-lg border border-slate-200 bg-slate-50/80 p-0.5"
      aria-label="Email và thông báo"
    >
      <button
        v-for="item in sectionTabs"
        :key="item.key"
        type="button"
        class="rounded-md px-3 py-1.5 text-xs font-medium transition sm:px-4"
        :class="activeSection === item.key ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-800'"
        @click="activeSection = item.key"
      >
        {{ item.label }}
      </button>
    </nav>

    <div v-show="activeSection === 'config'">
      <FieldsTab
        group="email"
        hide-header
        hide-field-hints
        suppress-dirty-report
        :save-hotkeys-enabled="saveHotkeysEnabled && activeSection === 'config'"
        :fields="emailFields"
        :can-manage="canManage"
        @dirty-change="onEmailConfigDirty"
      />
    </div>

    <div
      v-show="activeSection === 'templates'"
      class="space-y-4"
    >
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div
          class="flex min-w-0 flex-1 flex-wrap gap-1.5"
          role="tablist"
          aria-label="Loại mẫu email"
        >
          <button
            v-for="t in emailTemplates"
            :key="t.id"
            type="button"
            role="tab"
            class="inline-flex max-w-full items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
            :class="selectedId === t.id
              ? 'border-brand/35 bg-brand/[0.06] text-brand'
              : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300'"
            :aria-selected="selectedId === t.id"
            @click="selectedId = t.id"
          >
            <span class="truncate">{{ t.name }}</span>
            <span
              class="h-1.5 w-1.5 shrink-0 rounded-full"
              :class="t.is_active ? 'bg-emerald-500' : 'bg-slate-300'"
              :title="t.is_active ? 'Đang bật' : 'Đang tắt'"
            />
          </button>
        </div>
        <div class="flex shrink-0 rounded-lg border border-slate-200 p-0.5">
          <button
            type="button"
            class="rounded-md px-2.5 py-1 text-[11px] font-medium transition sm:px-3 sm:text-xs"
            :class="editorTab === 'split' ? 'bg-brand text-white' : 'text-slate-500'"
            @click="editorTab = 'split'"
          >
            Soạn + xem
          </button>
          <button
            type="button"
            class="rounded-md px-2.5 py-1 text-[11px] font-medium transition sm:px-3 sm:text-xs"
            :class="editorTab === 'edit' ? 'bg-brand text-white' : 'text-slate-500'"
            @click="editorTab = 'edit'"
          >
            Soạn
          </button>
          <button
            type="button"
            class="rounded-md px-2.5 py-1 text-[11px] font-medium transition sm:px-3 sm:text-xs"
            :class="editorTab === 'preview' ? 'bg-brand text-white' : 'text-slate-500'"
            @click="editorTab = 'preview'"
          >
            Xem trước
          </button>
        </div>
      </div>

      <div
        v-if="selectedTemplate"
        class="grid min-w-0 gap-4"
        :class="editorTab === 'split' ? 'xl:grid-cols-2' : 'grid-cols-1'"
      >
        <div
          v-show="editorTab !== 'preview'"
          class="space-y-4 rounded-xl border border-slate-100 bg-white p-4"
        >
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <p class="text-sm font-semibold text-slate-800">
              {{ selectedTemplate.name }}
            </p>
            <div class="flex items-center gap-2">
              <span class="text-xs text-slate-500">{{ templateForm.is_active ? 'Bật' : 'Tắt' }}</span>
              <ToggleSwitch
                v-model="templateForm.is_active"
                :disabled="!canManage"
              />
            </div>
          </div>

          <div>
            <div class="mb-1 flex items-center justify-between gap-2">
              <label class="text-sm font-medium text-slate-700">Tiêu đề</label>
              <span
                class="text-[11px] tabular-nums"
                :class="subjectCounterClass"
              >{{ subjectLength }}/{{ SUBJECT_MAX }}</span>
            </div>
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

          <div>
            <div class="mb-2 flex items-center justify-between gap-2">
              <label class="text-sm font-medium text-slate-700">Nội dung</label>
              <button
                v-if="canManage"
                type="button"
                class="text-xs font-medium text-brand hover:underline"
                @click="applyDefaultToEditor"
              >
                Mẫu chuẩn
              </button>
            </div>
            <EmailTemplateEditor
              v-model="templateForm.body_html"
              :disabled="!canManage"
              :snippets="selectedTemplate.snippets ?? []"
              :variables="selectedTemplate.variables ?? []"
              :error="templateForm.errors.body_html"
            />
          </div>

          <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
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
              v-if="canManage"
              type="button"
              class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="openTestModal"
            >
              Gửi thử
            </button>
            <button
              v-if="canManage"
              type="button"
              class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="resetTemplate"
            >
              Khôi phục
            </button>
            <span
              v-if="templateForm.isDirty && canManage"
              class="text-xs text-amber-600"
            >Chưa lưu</span>
          </div>
        </div>

        <div
          v-show="editorTab !== 'edit'"
          class="flex min-h-[24rem] flex-col rounded-xl border border-slate-200 bg-slate-50/50 p-3"
          :class="editorTab === 'preview' ? 'col-span-full' : ''"
        >
          <div class="mb-2 flex items-center justify-between gap-2 px-0.5">
            <p class="min-w-0 truncate text-sm font-medium text-slate-800">
              {{ livePreview.subject || '(Chưa có tiêu đề)' }}
            </p>
            <button
              type="button"
              class="inline-flex h-8 shrink-0 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
              @click="previewFullscreen = true"
            >
              <AppIcon
                name="eye"
                :size="14"
              />
              Phóng to
            </button>
          </div>
          <div class="min-h-0 flex-1 overflow-hidden rounded-lg border border-slate-200 bg-white">
            <iframe
              :srcdoc="livePreview.html"
              title="Xem trước email"
              class="h-full min-h-[20rem] w-full border-0 bg-white"
              sandbox="allow-same-origin"
            />
          </div>
        </div>
      </div>

      <p
        v-else-if="!emailTemplates.length"
        class="rounded-xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500"
      >
        Chưa có mẫu email.
      </p>
    </div>

    <Modal
      :show="previewFullscreen"
      title="Xem trước email"
      max-width="max-w-4xl"
      @close="previewFullscreen = false"
    >
      <p class="mb-2 text-sm font-medium text-slate-700">
        {{ livePreview.subject }}
      </p>
      <div class="overflow-hidden rounded-lg border border-slate-200">
        <iframe
          :srcdoc="livePreview.html"
          title="Xem trước email phóng to"
          class="h-[70vh] w-full border-0 bg-white"
          sandbox="allow-same-origin"
        />
      </div>
    </Modal>

    <Modal
      :show="testModalOpen"
      title="Gửi email thử"
      max-width="max-w-md"
      @close="testModalOpen = false"
    >
      <form
        class="space-y-4"
        @submit.prevent="sendTestEmail"
      >
        <FormField
          id="test-email"
          label="Email nhận"
          :error="testForm.errors.email"
        >
          <TextInput
            id="test-email"
            v-model="testForm.email"
            type="email"
            placeholder="name@vaschools.edu.vn"
            required
          />
        </FormField>
        <p class="text-xs text-slate-500">
          Tiêu đề có tiền tố <code class="rounded bg-slate-100 px-1">[TEST]</code>.
        </p>
        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button
            type="button"
            class="btn-ghost border border-slate-200 text-sm"
            @click="testModalOpen = false"
          >
            Huỷ
          </button>
          <button
            type="submit"
            class="btn-primary text-sm"
            :disabled="testForm.processing"
          >
            Gửi thử
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>
