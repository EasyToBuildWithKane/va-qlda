<script setup>
import {
    computed, onMounted, ref, watch,
} from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuroraBackground from './partials/AuroraBackground.vue';
import CongngheNavbar from './partials/CongngheNavbar.vue';
import CongngheFooter from './partials/CongngheFooter.vue';
import CongngheMascotAssistant from './partials/CongngheMascotAssistant.vue';
import CongngheProposalFieldLabel from './partials/CongngheProposalFieldLabel.vue';
import { CONGNGHE_PROPOSAL_HINTS as H } from './partials/congngheProposalFormHints.js';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    defaults: {
        type: Object,
        default: () => ({
            name: '', email: '', department: '', department_id: null,
        }),
    },
    departmentOptions: { type: Array, default: () => [] },
    recipientEmail: { type: String, default: 'phongcongnghe@vaschools.edu.vn' },
});

const page = usePage();
const toast = useToast();
const fileInput = ref(null);
const stagedFiles = ref([]);

const form = useForm({
    name: props.defaults.name ?? '',
    email: props.defaults.email ?? '',
    department_id: props.defaults.department_id ?? '',
    title: '',
    content: '',
    attachments: [],
});

const inputClass =
    'h-11 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25';

const hasDepartmentOptions = computed(() => props.departmentOptions.length > 0);

const selectedDepartmentName = computed(() => {
    const id = form.department_id;
    if (id === '' || id == null) {
        return '';
    }
    const dept = props.departmentOptions.find((d) => Number(d.id) === Number(id));
    return dept?.name ?? '';
});

const departmentAutoFilled = computed(
    () => props.defaults.department_id != null
        && Number(form.department_id) === Number(props.defaults.department_id),
);

function syncDepartmentFromDefaults() {
    if (props.defaults.department_id != null && props.defaults.department_id !== '') {
        form.department_id = Number(props.defaults.department_id);
    }
}

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let n = bytes;
    let i = 0;
    while (n >= 1024 && i < units.length - 1) {
        n /= 1024;
        i += 1;
    }
    return `${n.toFixed(i ? 1 : 0)} ${units[i]}`;
}

function openFilePicker() {
    fileInput.value?.click();
}

function onFilesSelected(e) {
    const files = Array.from(e.target.files ?? []);
    const maxTotal = 5;
    const maxBytes = 5 * 1024 * 1024;

    for (const file of files) {
        if (stagedFiles.value.length >= maxTotal) {
            toast.warning('Tối đa 5 tệp đính kèm.');
            break;
        }
        if (file.size > maxBytes) {
            toast.warning(`${file.name}: vượt quá 5MB.`);
            continue;
        }
        stagedFiles.value.push(file);
    }

    form.attachments = [...stagedFiles.value];
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function removeFile(index) {
    stagedFiles.value.splice(index, 1);
    form.attachments = [...stagedFiles.value];
}

function submit() {
    if (!hasDepartmentOptions.value) {
        form.setError('department_id', 'Danh sách phòng ban chưa sẵn sàng. Vui lòng thử lại sau hoặc liên hệ Phòng Công nghệ.');
        return;
    }
    if (!form.department_id) {
        form.setError('department_id', 'Vui lòng chọn phòng ban.');
        return;
    }
    form.attachments = [...stagedFiles.value];
    form
        .transform((data) => ({
            ...data,
            attachments: stagedFiles.value,
        }))
        .post(route('congnghe.proposal.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                stagedFiles.value = [];
                form.reset('title', 'content');
                form.attachments = [];
            },
        });
}

function flashToast() {
    const success = page.props.flash?.success;
    const error = page.props.flash?.error;
    if (success) toast.success(success);
    if (error) toast.error(error);
}

onMounted(() => {
    syncDepartmentFromDefaults();
    flashToast();
});

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    () => flashToast(),
);
</script>

<template>
  <Head title="Đề xuất phần mềm" />

  <div
    class="relative min-h-screen overflow-x-hidden bg-[#05060c] font-sans text-white antialiased selection:bg-brand/40 selection:text-white"
  >
    <AuroraBackground />
    <CongngheNavbar />

    <main class="relative z-10 px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:pb-24">
      <div class="mx-auto max-w-6xl">
        <header class="mb-8 text-center sm:mb-10">
          <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-200/55">
            Phòng Công Nghệ · VAS
          </p>
          <h1 class="mt-2 font-display text-2xl font-bold text-white sm:text-3xl">
            Đề xuất giải pháp phần mềm
          </h1>
          <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-white/60 sm:text-base">
            Gửi ý tưởng hoặc nhu cầu phần mềm tới Phòng Công Nghệ. Thư chuyển tới
            <span class="text-cyan-200/80">{{ recipientEmail }}</span>
            — phản hồi qua email bạn nhập bên dưới.
          </p>
        </header>

        <form
          class="rounded-2xl border border-white/10 bg-[#0a0c16]/90 p-5 shadow-[0_24px_80px_-24px_rgba(154,0,54,0.35)] backdrop-blur-xl sm:p-8 lg:p-10"
          @submit.prevent="submit"
        >
          <fieldset class="min-w-0 border-0 p-0">
            <legend class="mb-4 font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-brand-200/70">
              Thông tin người gửi
            </legend>
            <div class="grid gap-5 lg:grid-cols-3 lg:gap-6">
              <div>
                <CongngheProposalFieldLabel
                  for-id="proposal-name"
                  label="Họ tên"
                  required
                  :tooltip="H.name"
                />
                <input
                  id="proposal-name"
                  v-model="form.name"
                  type="text"
                  required
                  autocomplete="name"
                  :class="inputClass"
                  placeholder="Nguyễn Văn A"
                >
                <p
                  v-if="form.errors.name"
                  class="mt-1 text-xs text-rose-300"
                >
                  {{ form.errors.name }}
                </p>
              </div>

              <div>
                <CongngheProposalFieldLabel
                  for-id="proposal-email"
                  label="Email"
                  required
                  :tooltip="H.email"
                />
                <input
                  id="proposal-email"
                  v-model="form.email"
                  type="email"
                  required
                  autocomplete="email"
                  :class="inputClass"
                  placeholder="ten@vaschools.edu.vn"
                >
                <p
                  v-if="form.errors.email"
                  class="mt-1 text-xs text-rose-300"
                >
                  {{ form.errors.email }}
                </p>
              </div>

              <div class="min-w-0">
                <CongngheProposalFieldLabel
                  for-id="proposal-dept-pick"
                  label="Phòng ban"
                  required
                  :tooltip="H.department"
                  :hint="departmentAutoFilled && selectedDepartmentName
                    ? `Tự động từ hồ sơ: ${selectedDepartmentName}`
                    : (selectedDepartmentName ? selectedDepartmentName : '')"
                />
                <select
                  v-if="hasDepartmentOptions"
                  id="proposal-dept-pick"
                  v-model="form.department_id"
                  required
                  class="h-11 w-full rounded-xl border border-white/10 bg-[#0c0e18] px-3 text-sm text-white focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25"
                >
                  <option
                    value=""
                    disabled
                  >
                    Chọn phòng ban
                  </option>
                  <option
                    v-for="dept in departmentOptions"
                    :key="dept.id"
                    :value="dept.id"
                  >
                    {{ dept.name }}
                  </option>
                </select>
                <p
                  v-else
                  class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2.5 text-sm text-amber-100/90"
                >
                  Chưa tải được danh sách phòng ban. Vui lòng tải lại trang hoặc liên hệ Phòng Công nghệ.
                </p>
                <p
                  v-if="form.errors.department_id"
                  class="mt-1 text-xs text-rose-300"
                >
                  {{ form.errors.department_id }}
                </p>
              </div>
            </div>
          </fieldset>

          <fieldset class="mt-8 min-w-0 border-0 border-t border-white/10 p-0 pt-8">
            <legend class="mb-4 font-mono text-[10px] font-semibold uppercase tracking-[0.16em] text-brand-200/70">
              Nội dung đề xuất
            </legend>
            <div class="grid gap-5 lg:grid-cols-5 lg:gap-6">
              <div class="lg:col-span-5">
                <CongngheProposalFieldLabel
                  for-id="proposal-title"
                  label="Tiêu đề đề xuất"
                  required
                  :tooltip="H.title"
                />
                <input
                  id="proposal-title"
                  v-model="form.title"
                  type="text"
                  required
                  maxlength="200"
                  :class="inputClass"
                  placeholder="VD: Phần mềm theo dõi thiết bị IT"
                >
                <p
                  v-if="form.errors.title"
                  class="mt-1 text-xs text-rose-300"
                >
                  {{ form.errors.title }}
                </p>
              </div>

              <div class="lg:col-span-3">
                <CongngheProposalFieldLabel
                  for-id="proposal-content"
                  label="Nội dung"
                  required
                  :tooltip="H.content"
                />
                <textarea
                  id="proposal-content"
                  v-model="form.content"
                  required
                  rows="10"
                  maxlength="10000"
                  class="w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-3 text-sm leading-relaxed text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25 lg:min-h-[14rem]"
                  placeholder="Mô tả bài toán, người dùng, kết quả mong muốn, thời hạn…"
                />
                <p
                  v-if="form.errors.content"
                  class="mt-1 text-xs text-rose-300"
                >
                  {{ form.errors.content }}
                </p>
              </div>

              <div class="lg:col-span-2">
                <CongngheProposalFieldLabel
                  label="Tệp đính kèm"
                  :tooltip="H.attachments"
                  hint="Word, Excel, PDF, hình — tối đa 5 tệp, 5MB/tệp"
                />
                <input
                  ref="fileInput"
                  type="file"
                  class="hidden"
                  multiple
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,image/*"
                  @change="onFilesSelected"
                >
                <button
                  type="button"
                  class="flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-white/20 bg-white/[0.03] text-sm font-medium text-white/75 transition hover:border-brand/40 hover:bg-brand/10 hover:text-white"
                  @click="openFilePicker"
                >
                  Chọn tệp
                </button>
                <ul
                  v-if="stagedFiles.length"
                  class="mt-3 max-h-48 space-y-2 overflow-y-auto"
                >
                  <li
                    v-for="(file, index) in stagedFiles"
                    :key="`${file.name}-${index}`"
                    class="flex items-center justify-between gap-3 rounded-lg bg-white/[0.04] px-3 py-2 text-sm"
                  >
                    <span class="min-w-0 truncate text-white/85">{{ file.name }}</span>
                    <span class="shrink-0 text-xs text-white/40">{{ formatSize(file.size) }}</span>
                    <button
                      type="button"
                      class="shrink-0 text-xs font-medium text-rose-300 hover:text-rose-200"
                      @click="removeFile(index)"
                    >
                      Gỡ
                    </button>
                  </li>
                </ul>
                <p
                  v-if="form.errors.attachments"
                  class="mt-1 text-xs text-rose-300"
                >
                  {{ form.errors.attachments }}
                </p>
              </div>
            </div>
          </fieldset>

          <div class="mt-8 flex flex-col gap-3 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <a
              href="/congnghe"
              class="text-center text-sm text-white/50 transition hover:text-white/80 sm:text-left"
            >
              ← Quay lại cổng Phòng Công Nghệ
            </a>
            <button
              type="submit"
              class="btn-primary h-11 gap-2 px-8 text-sm font-semibold disabled:opacity-60"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Đang gửi…' : 'Gửi đề xuất' }}
            </button>
          </div>
        </form>
      </div>
    </main>

    <CongngheFooter />
    <CongngheMascotAssistant proposal-page />
  </div>
</template>
