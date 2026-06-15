<script setup>
import {
    computed, onMounted, ref, watch,
} from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuroraBackground from './partials/AuroraBackground.vue';
import CongngheNavbar from './partials/CongngheNavbar.vue';
import CongngheFooter from './partials/CongngheFooter.vue';
import CongngheMascotAssistant from './partials/CongngheMascotAssistant.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    defaults: {
        type: Object,
        default: () => ({ name: '', email: '', department: '' }),
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
    department: props.defaults.department ?? '',
    title: '',
    content: '',
    attachments: [],
});

const departmentSelectId = computed(() => {
    const match = props.departmentOptions.find((d) => d.name === form.department);
    return match?.id ?? '';
});

function onDepartmentPick(e) {
    const id = e.target.value;
    const dept = props.departmentOptions.find((d) => String(d.id) === id);
    if (dept) {
        form.department = dept.name;
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
    let added = 0;

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
        added += 1;
    }

    form.attachments = [...stagedFiles.value];
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    if (added === 0 && files.length > 0 && stagedFiles.value.length >= maxTotal) {
        return;
    }
}

function removeFile(index) {
    stagedFiles.value.splice(index, 1);
    form.attachments = [...stagedFiles.value];
}

function submit() {
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

onMounted(flashToast);

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
      <div class="mx-auto max-w-3xl">
        <header class="mb-8 text-center sm:mb-10">
          <p class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-cyan-200/55">
            Phòng Công Nghệ · VAS
          </p>
          <h1 class="mt-2 font-display text-2xl font-bold text-white sm:text-3xl">
            Đề xuất giải pháp phần mềm
          </h1>
          <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-white/60 sm:text-base">
            Gửi ý tưởng hoặc nhu cầu phần mềm tới Phòng Công Nghệ. Thư sẽ được chuyển tới
            <span class="text-cyan-200/80">{{ recipientEmail }}</span>
            — bạn nhận phản hồi qua email đã nhập.
          </p>
        </header>

        <form
          class="rounded-2xl border border-white/10 bg-[#0a0c16]/90 p-5 shadow-[0_24px_80px_-24px_rgba(154,0,54,0.35)] backdrop-blur-xl sm:p-8"
          @submit.prevent="submit"
        >
          <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-1">
              <label
                for="proposal-name"
                class="text-xs font-semibold uppercase tracking-wide text-white/50"
              >Họ tên</label>
              <input
                id="proposal-name"
                v-model="form.name"
                type="text"
                required
                autocomplete="name"
                class="mt-1.5 h-11 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25"
                placeholder="Nguyễn Văn A"
              >
              <p
                v-if="form.errors.name"
                class="mt-1 text-xs text-rose-300"
              >
                {{ form.errors.name }}
              </p>
            </div>

            <div class="sm:col-span-1">
              <label
                for="proposal-email"
                class="text-xs font-semibold uppercase tracking-wide text-white/50"
              >Email</label>
              <input
                id="proposal-email"
                v-model="form.email"
                type="email"
                required
                autocomplete="email"
                class="mt-1.5 h-11 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25"
                placeholder="ten@vaschools.edu.vn"
              >
              <p
                v-if="form.errors.email"
                class="mt-1 text-xs text-rose-300"
              >
                {{ form.errors.email }}
              </p>
            </div>

            <div class="sm:col-span-2">
              <label
                for="proposal-dept-pick"
                class="text-xs font-semibold uppercase tracking-wide text-white/50"
              >Phòng ban</label>
              <div class="mt-1.5 flex flex-col gap-2 sm:flex-row sm:items-center">
                <select
                  id="proposal-dept-pick"
                  class="h-11 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25 sm:max-w-xs"
                  :value="departmentSelectId"
                  @change="onDepartmentPick"
                >
                  <option value="">
                    Chọn phòng ban (tuỳ chọn)
                  </option>
                  <option
                    v-for="dept in departmentOptions"
                    :key="dept.id"
                    :value="dept.id"
                  >
                    {{ dept.name }}
                  </option>
                </select>
                <input
                  v-model="form.department"
                  type="text"
                  required
                  class="h-11 w-full flex-1 rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25"
                  placeholder="Hoặc nhập tên phòng ban"
                >
              </div>
              <p
                v-if="form.errors.department"
                class="mt-1 text-xs text-rose-300"
              >
                {{ form.errors.department }}
              </p>
            </div>

            <div class="sm:col-span-2">
              <label
                for="proposal-title"
                class="text-xs font-semibold uppercase tracking-wide text-white/50"
              >Tiêu đề đề xuất</label>
              <input
                id="proposal-title"
                v-model="form.title"
                type="text"
                required
                maxlength="200"
                class="mt-1.5 h-11 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25"
                placeholder="VD: Phần mềm theo dõi thiết bị IT"
              >
              <p
                v-if="form.errors.title"
                class="mt-1 text-xs text-rose-300"
              >
                {{ form.errors.title }}
              </p>
            </div>

            <div class="sm:col-span-2">
              <label
                for="proposal-content"
                class="text-xs font-semibold uppercase tracking-wide text-white/50"
              >Nội dung</label>
              <textarea
                id="proposal-content"
                v-model="form.content"
                required
                rows="8"
                maxlength="10000"
                class="mt-1.5 w-full rounded-xl border border-white/10 bg-white/[0.04] px-3 py-3 text-sm leading-relaxed text-white placeholder:text-white/30 focus:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/25"
                placeholder="Mô tả bài toán, người dùng, kết quả mong muốn, thời hạn…"
              />
              <p
                v-if="form.errors.content"
                class="mt-1 text-xs text-rose-300"
              >
                {{ form.errors.content }}
              </p>
            </div>

            <div class="sm:col-span-2">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-white/50">
                  Tệp đính kèm
                </p>
                <p class="text-[11px] text-white/40">
                  Word, Excel, PDF, hình — tối đa 5 tệp, 5MB/tệp
                </p>
              </div>
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
                class="mt-2 flex h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed border-white/20 bg-white/[0.03] text-sm font-medium text-white/75 transition hover:border-brand/40 hover:bg-brand/10 hover:text-white sm:w-auto sm:px-5"
                @click="openFilePicker"
              >
                Chọn tệp
              </button>
              <ul
                v-if="stagedFiles.length"
                class="mt-3 space-y-2"
              >
                <li
                  v-for="(file, index) in stagedFiles"
                  :key="`${file.name}-${index}`"
                  class="flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-white/[0.03] px-3 py-2 text-sm"
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

          <div class="mt-8 flex flex-col gap-3 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
            <a
              href="/congnghe"
              class="text-center text-sm text-white/50 transition hover:text-white/80 sm:text-left"
            >
              ← Quay lại cổng Phòng Công Nghệ
            </a>
            <button
              type="submit"
              class="btn-primary h-11 gap-2 px-6 text-sm font-semibold disabled:opacity-60"
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
