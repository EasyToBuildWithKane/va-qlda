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
    description: { type: String, default: '' },
    emailFields: { type: Array, default: () => [] },
    emailTemplates: { type: Array, default: () => [] },
    emailPreviewBrand: { type: String, default: 'VAschools QLDA' },
    emailTestRecipient: { type: String, default: '' },
    canManage: { type: Boolean, default: false },
});

const dialog = useDialog();
const toast = useToast();
const setGroupDirty = inject('setGroupDirty', null);
const emailConfigRef = ref(null);
const emailConfigDirty = ref(false);

const SUBJECT_WARN = 50;
const SUBJECT_MAX = 60;

const sectionNav = [
    {
        key: 'config',
        label: 'Cấu hình gửi',
        description: 'Bật email & lịch tổng hợp',
        icon: 'settings',
    },
    {
        key: 'templates',
        label: 'Mẫu thông báo',
        description: 'Soạn & xem trước email',
        icon: 'mail',
    },
];

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

const activeTemplateCount = computed(() =>
    props.emailTemplates.filter((t) => t.is_active).length,
);

const subjectLength = computed(() => templateForm.subject.length);

const subjectCounterClass = computed(() => {
    if (subjectLength.value > SUBJECT_MAX) return 'text-rose-600';
    if (subjectLength.value > SUBJECT_WARN) return 'text-amber-600';
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

function sectionNavClass(active) {
    return active
        ? 'border-brand/30 bg-brand/[0.06]'
        : 'border-transparent hover:bg-slate-50';
}

function sectionIconClass(active) {
    return active ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200';
}
</script>

<template>
  <div class="space-y-6">
    <!-- Section header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-3">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand text-white">
            <AppIcon
              name="mail"
              :size="18"
            />
          </span>
          <div>
            <h2 class="text-[15px] font-semibold text-slate-800">
              {{ title || 'Email & Thông báo' }}
            </h2>
            <p class="mt-0.5 max-w-2xl text-[12.5px] leading-relaxed text-slate-400">
              {{ description || 'Cấu hình gửi email công việc và chỉnh sửa mẫu thông báo tự động.' }}
            </p>
          </div>
        </div>
      </div>
      <div
        v-if="emailTemplates.length"
        class="inline-flex flex-wrap items-center gap-2"
      >
        <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-1.5 text-[11px] text-slate-500">
          <AppIcon
            name="mail"
            :size="13"
          />
          {{ emailTemplates.length }} mẫu
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-1.5 text-[11px] font-medium text-emerald-700">
          {{ activeTemplateCount }}/{{ emailTemplates.length }} mẫu đang bật
        </span>
      </div>
    </div>

    <!-- Horizontal sub-nav (same pattern as settings rail) -->
    <nav
      class="-mx-1 flex shrink-0 gap-2 overflow-x-auto px-1 pb-1"
      aria-label="Email và thông báo"
    >
      <button
        v-for="item in sectionNav"
        :key="item.key"
        type="button"
        class="group flex shrink-0 items-center gap-3 rounded-card border px-3.5 py-3 text-left transition-colors"
        :class="sectionNavClass(activeSection === item.key)"
        @click="activeSection = item.key"
      >
        <span
          class="grid h-9 w-9 shrink-0 place-items-center rounded-lg transition-colors"
          :class="sectionIconClass(activeSection === item.key)"
        >
          <AppIcon
            :name="item.icon"
            :size="17"
          />
        </span>
        <span class="min-w-0 pr-1">
          <span
            class="block whitespace-nowrap text-[13.5px] font-semibold leading-tight"
            :class="activeSection === item.key ? 'text-brand' : 'text-slate-700'"
          >{{ item.label }}</span>
          <span class="mt-0.5 hidden truncate text-[11.5px] text-slate-400 sm:block">{{ item.description }}</span>
        </span>
      </button>
    </nav>

    <!-- Cấu hình gửi -->
    <div
      v-show="activeSection === 'config'"
      class="rounded-card border border-slate-100 bg-slate-50/30 p-4 md:p-5"
    >
      <FieldsTab
        ref="emailConfigRef"
        group="email"
        hide-header
        suppress-dirty-report
        :fields="emailFields"
        :can-manage="canManage"
        @dirty-change="onEmailConfigDirty"
      />
    </div>

    <!-- Mẫu thông báo -->
    <div
      v-show="activeSection === 'templates'"
      class="space-y-5"
    >
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-xl text-[12.5px] leading-relaxed text-slate-500">
          Chọn loại thông báo, soạn nội dung bằng trình soạn thảo hoặc HTML.
          Email gửi đi có header/footer thương hiệu — khung bên phải là bản thật.
        </p>
        <div class="flex rounded-lg border border-slate-200 p-0.5">
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition"
            :class="editorTab === 'split' ? 'bg-brand text-white' : 'text-slate-500 hover:text-slate-800'"
            @click="editorTab = 'split'"
          >
            Soạn + xem trước
          </button>
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition"
            :class="editorTab === 'edit' ? 'bg-brand text-white' : 'text-slate-500 hover:text-slate-800'"
            @click="editorTab = 'edit'"
          >
            Chỉ soạn
          </button>
          <button
            type="button"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition"
            :class="editorTab === 'preview' ? 'bg-brand text-white' : 'text-slate-500 hover:text-slate-800'"
            @click="editorTab = 'preview'"
          >
            Chỉ xem trước
          </button>
        </div>
      </div>

      <!-- Template rail — always horizontal -->
      <nav
        class="-mx-1 flex shrink-0 gap-2 overflow-x-auto px-1 pb-1"
        aria-label="Loại mẫu email"
      >
        <button
          v-for="t in emailTemplates"
          :key="t.id"
          type="button"
          class="group flex shrink-0 items-center gap-2.5 rounded-card border px-3 py-2.5 text-left transition-colors"
          :class="selectedId === t.id
            ? 'border-brand/30 bg-brand/[0.06]'
            : 'border-slate-100 bg-white hover:bg-slate-50'"
          @click="selectedId = t.id"
        >
          <span
            class="grid h-8 w-8 shrink-0 place-items-center rounded-lg transition-colors"
            :class="selectedId === t.id ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200'"
          >
            <AppIcon
              name="mail"
              :size="15"
            />
          </span>
          <span class="min-w-0">
            <span
              class="block max-w-[12rem] truncate text-[13px] font-semibold leading-tight sm:max-w-none"
              :class="selectedId === t.id ? 'text-brand' : 'text-slate-800'"
            >{{ t.name }}</span>
            <span class="mt-0.5 block font-mono text-[10px] text-slate-400">{{ t.key }}</span>
            <span
              v-if="t.updated_at"
              class="mt-0.5 block text-[10px] text-slate-400"
            >{{ t.updated_at }}</span>
          </span>
          <span
            v-if="t.is_active"
            class="ml-1 hidden shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium text-emerald-700 sm:inline"
          >Đang bật</span>
          <span
            v-else
            class="ml-1 hidden shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-medium text-rose-700 sm:inline"
          >Tắt</span>
        </button>
      </nav>

      <div
        v-if="selectedTemplate"
        class="grid min-w-0 gap-5"
        :class="editorTab === 'split' ? 'xl:grid-cols-2' : 'grid-cols-1'"
      >
        <!-- Editor column -->
        <div
          v-show="editorTab !== 'preview'"
          class="space-y-4 rounded-card border border-slate-100 bg-white p-4 shadow-sm"
        >
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
            <div class="min-w-0">
              <p class="text-sm font-semibold text-slate-800">
                {{ selectedTemplate.name }}
              </p>
              <p class="text-[11px] text-slate-400">
                Chỉnh sửa tiêu đề và nội dung mẫu
              </p>
            </div>
            <div class="flex items-center gap-2 rounded-lg border border-slate-100 bg-slate-50/80 px-2.5 py-1.5">
              <span class="text-xs text-slate-500">{{ templateForm.is_active ? 'Đang bật' : 'Tắt' }}</span>
              <ToggleSwitch
                v-model="templateForm.is_active"
                :disabled="!canManage"
              />
            </div>
          </div>

          <div>
            <div class="mb-1 flex items-center justify-between gap-2">
              <label class="text-sm font-medium text-slate-700">Tiêu đề email</label>
              <span
                class="text-[11px] font-medium tabular-nums"
                :class="subjectCounterClass"
              >{{ subjectLength }}/{{ SUBJECT_MAX }}</span>
            </div>
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

          <details class="rounded-lg border border-slate-100 bg-slate-50/50">
            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-slate-600">
              Biến có thể dùng trong mẫu
            </summary>
            <ul class="space-y-1.5 border-t border-slate-100 px-3 py-2.5 text-[11px] text-slate-500">
              <li
                v-for="v in selectedTemplate.variables"
                :key="v.key"
              >
                <span class="font-medium text-slate-700">{{ v.label }}:</span> {{ v.hint }}
              </li>
            </ul>
          </details>

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
            <span
              v-if="templateForm.isDirty && canManage"
              class="text-xs text-amber-600"
            >Có thay đổi chưa lưu</span>
          </div>
        </div>

        <!-- Preview column -->
        <div
          v-show="editorTab !== 'edit'"
          class="flex min-h-[26rem] flex-col rounded-card border border-slate-200 bg-gradient-to-b from-slate-100/80 to-slate-50/50 p-3"
          :class="editorTab === 'preview' ? 'col-span-full' : ''"
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
              class="h-full min-h-[22rem] w-full border-0 bg-white"
              sandbox="allow-same-origin"
            />
          </div>
        </div>
      </div>

      <p
        v-else-if="!emailTemplates.length"
        class="rounded-card border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500"
      >
        Chưa có mẫu email trong hệ thống.
      </p>
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
