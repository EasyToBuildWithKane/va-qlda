<script setup>
import { ref, computed, watch } from 'vue';
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
    description: { type: String, default: '' },
    emailFields: { type: Array, default: () => [] },
    emailTemplates: { type: Array, default: () => [] },
    emailPreviewBrand: { type: String, default: 'VAschools QLDA' },
    emailTestRecipient: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
});

const dialog = useDialog();
const toast = useToast();
const selectedId = ref(props.emailTemplates[0]?.id ?? null);
const editorTab = ref('edit');
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
        message: 'Nội dung hiện tại sẽ được thay bằng mẫu production mặc định của hệ thống.',
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
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="text-[15px] font-semibold text-slate-800">
            Mẫu email
          </h3>
          <p class="mt-0.5 max-w-2xl text-[12.5px] leading-relaxed text-slate-400">
            Soạn bằng trình soạn thảo hoặc chèn khối mẫu — không cần viết HTML thuần.
            Email gửi đi có header/footer thương hiệu; xem trước bên phải là bản thật.
          </p>
        </div>
        <div class="flex rounded-lg border border-slate-200 p-0.5">
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition"
            :class="editorTab === 'edit' ? 'bg-brand text-white' : 'text-slate-500 hover:text-slate-800'"
            @click="editorTab = 'edit'"
          >
            Chỉnh sửa
          </button>
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition"
            :class="editorTab === 'preview' ? 'bg-brand text-white' : 'text-slate-500 hover:text-slate-800'"
            @click="editorTab = 'preview'"
          >
            Xem trước
          </button>
        </div>
      </div>

      <div class="mt-5 flex flex-col gap-5 xl:flex-row">
        <ul class="flex shrink-0 gap-2 overflow-x-auto xl:w-56 xl:flex-col xl:overflow-visible">
          <li
            v-for="t in emailTemplates"
            :key="t.id"
          >
            <button
              type="button"
              class="w-full rounded-card border px-3 py-3 text-left transition"
              :class="selectedId === t.id
                ? 'border-brand/30 bg-brand/[0.06]'
                : 'border-slate-100 hover:bg-slate-50'"
              @click="selectedId = t.id"
            >
              <span
                class="block text-sm font-semibold"
                :class="selectedId === t.id ? 'text-brand' : 'text-slate-800'"
              >{{ t.name }}</span>
              <span class="mt-0.5 block font-mono text-[10px] text-slate-400">{{ t.key }}</span>
            </button>
          </li>
        </ul>

        <div
          v-if="selectedTemplate"
          class="grid min-w-0 flex-1 gap-5 lg:grid-cols-2"
        >
          <div
            v-show="editorTab === 'edit'"
            class="space-y-4 rounded-card border border-slate-100 bg-white p-4"
            :class="editorTab !== 'edit' ? 'hidden lg:block' : ''"
          >
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
              <span class="text-xs font-medium text-slate-500">Trạng thái gửi</span>
              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500">{{ templateForm.is_active ? 'Đang bật' : 'Tắt' }}</span>
                <ToggleSwitch
                  v-model="templateForm.is_active"
                  :disabled="!canManage"
                />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tiêu đề email</label>
              <input
                v-model="templateForm.subject"
                type="text"
                class="input w-full"
                :disabled="!canManage"
                placeholder="[QLDA] …"
              >
              <p
                v-if="templateForm.errors.subject"
                class="mt-1 text-xs text-rose-600"
              >
                {{ templateForm.errors.subject }}
              </p>
            </div>

            <div>
              <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <label class="text-sm font-medium text-slate-700">Nội dung</label>
                <button
                  v-if="canManage"
                  type="button"
                  class="text-[11px] font-medium text-brand hover:underline"
                  @click="applyDefaultToEditor"
                >
                  Tải mẫu chuẩn
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

            <ul class="space-y-1.5 rounded-lg border border-dashed border-slate-200 bg-slate-50/50 p-3 text-[11px] text-slate-500">
              <li
                v-for="v in selectedTemplate.variables"
                :key="v.key"
              >
                <span class="font-medium text-slate-700">{{ v.label }}:</span> {{ v.hint }}
              </li>
            </ul>

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
                <AppIcon
                  name="send"
                  :size="15"
                />
                Gửi thử
              </button>
              <button
                v-if="canManage"
                type="button"
                class="inline-flex h-9 items-center gap-1 rounded-btn border border-slate-200 px-3 text-xs font-medium text-slate-600 hover:bg-slate-50"
                @click="resetTemplate"
              >
                <AppIcon
                  name="refresh"
                  :size="15"
                />
                Khôi phục chuẩn
              </button>
            </div>
          </div>

          <div
            class="flex min-h-[28rem] flex-col rounded-card border border-slate-200 bg-slate-100/60 p-3"
            :class="editorTab === 'preview' ? 'col-span-full lg:col-span-2' : ''"
          >
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2 px-1">
              <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  Hộp thư — xem trước
                </p>
                <p class="truncate text-sm font-medium text-slate-800">
                  {{ livePreview.subject || '(Chưa có tiêu đề)' }}
                </p>
                <p class="text-[11px] text-slate-400">
                  Từ: {{ emailPreviewBrand }} · Dữ liệu mẫu
                </p>
              </div>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-btn border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                @click="previewFullscreen = true"
              >
                <AppIcon
                  name="eye"
                  :size="14"
                />
                Phóng to
              </button>
            </div>

            <div class="min-h-0 flex-1 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
              <iframe
                :srcdoc="livePreview.html"
                title="Xem trước email"
                class="h-full min-h-[24rem] w-full border-0 bg-white"
                sandbox="allow-same-origin"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <Modal
      :show="previewFullscreen"
      title="Xem trước email (toàn màn hình)"
      max-width="max-w-4xl"
      @close="previewFullscreen = false"
    >
      <p class="mb-2 text-sm font-medium text-slate-700">
        {{ livePreview.subject }}
      </p>
      <div class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
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
      <p class="mb-4 text-sm text-slate-600">
        Gửi bản xem trước (dữ liệu mẫu) tới hộp thư của bạn. Tiêu đề email có tiền tố
        <code class="text-xs">[TEST]</code>.
      </p>
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
        <p class="text-[11px] text-slate-400">
          Nội dung gửi theo form hiện tại (kể cả chưa lưu). Cần cấu hình MAIL_* trên server.
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
            <AppIcon
              name="send"
              :size="15"
            />
            Gửi thử
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>
