<script setup>
import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    articleTitle: { type: String, default: '' },
    relatedQuestions: { type: Array, default: () => [] },
});

const toast = useToast();
const summaryMode = ref(null);
const summaryLoading = ref(false);
const summaryText = ref('');
const askInput = ref('');
const askMessages = ref([]);
const askLoading = ref(false);

const defaultQuestions = [
    'Bài viết này giải quyết vấn đề gì?',
    'Ai nên đọc nội dung này?',
    'Có bước thực hiện cụ thể không?',
];

const questionChips = () => (props.relatedQuestions?.length ? props.relatedQuestions : defaultQuestions);

async function loadSummary(mode) {
    if (summaryLoading.value) return;
    summaryMode.value = mode;
    summaryLoading.value = true;
    summaryText.value = '';
    await new Promise((r) => setTimeout(r, 900));
    summaryLoading.value = false;
    summaryText.value = mode === '30s'
        ? `Tóm tắt nhanh «${props.articleTitle}»: nội dung tập trung vào quy trình và kinh nghiệm thực tế trong tổ chức. (Tính năng AI sẽ được kết nối API trong bản cập nhật tiếp theo.)`
        : `Tóm tắt 1 phút: bài viết mô tả bối cảnh, các bước chính và lưu ý khi triển khai. Bạn có thể dùng mục lục bên trái để nhảy tới từng phần. (Bản demo — chờ endpoint AI.)`;
}

async function submitQuestion() {
    const q = askInput.value.trim();
    if (!q || askLoading.value) return;
    askMessages.value.push({ role: 'user', text: q });
    askInput.value = '';
    askLoading.value = true;
    await new Promise((r) => setTimeout(r, 700));
    askLoading.value = false;
    askMessages.value.push({
        role: 'assistant',
        text: 'Câu trả lời sẽ dựa trên nội dung bài viết khi tích hợp AI. Hiện tại hãy tìm trong mục lục hoặc từ khóa trong bài.',
    });
}

function askChip(question) {
    askInput.value = question;
    submitQuestion();
}

function copyQuestion(q) {
    navigator.clipboard?.writeText(q).then(() => toast.success('Đã sao chép câu hỏi.')).catch(() => {});
}
</script>

<template>
  <section
    class="mx-auto max-w-[760px] overflow-hidden rounded-2xl border border-slate-200/80 bg-white/80 shadow-sm backdrop-blur-sm dark:border-slate-700 dark:bg-slate-900/60"
    aria-label="Trợ lý AI bài viết"
  >
    <div class="border-b border-slate-100 bg-gradient-to-r from-brand/[0.06] via-violet-500/[0.04] to-transparent px-5 py-4 dark:border-slate-800">
      <div class="flex items-center gap-2">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand/10 text-brand">
          <AppIcon
            name="sparkles"
            :size="18"
          />
        </span>
        <div>
          <h2 class="font-display text-base font-semibold text-slate-900 dark:text-slate-50">
            Trợ lý AI
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Tóm tắt · Hỏi bài viết · Gợi ý câu hỏi
          </p>
        </div>
      </div>
    </div>

    <div class="space-y-6 p-5 sm:p-6">
      <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
          AI Tóm tắt
        </p>
        <div class="flex flex-wrap gap-2">
          <button
            type="button"
            class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-brand/30 hover:text-brand dark:border-slate-600 dark:text-slate-300"
            :class="summaryMode === '30s' ? 'border-brand/40 bg-brand/5 text-brand' : ''"
            @click="loadSummary('30s')"
          >
            30 giây
          </button>
          <button
            type="button"
            class="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-brand/30 hover:text-brand dark:border-slate-600 dark:text-slate-300"
            :class="summaryMode === '1m' ? 'border-brand/40 bg-brand/5 text-brand' : ''"
            @click="loadSummary('1m')"
          >
            1 phút
          </button>
        </div>
        <div
          v-if="summaryLoading"
          class="mt-3 space-y-2"
        >
          <div class="h-3 animate-pulse rounded bg-slate-200/80 dark:bg-slate-700" />
          <div class="h-3 w-4/5 animate-pulse rounded bg-slate-200/80 dark:bg-slate-700" />
        </div>
        <p
          v-else-if="summaryText"
          class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300"
        >
          {{ summaryText }}
        </p>
      </div>

      <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
          Hỏi về bài viết
        </p>
        <div
          v-if="askMessages.length"
          class="mb-3 max-h-48 space-y-2 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-950/50"
        >
          <div
            v-for="(msg, idx) in askMessages"
            :key="idx"
            class="text-sm"
            :class="msg.role === 'user' ? 'text-right text-brand' : 'text-slate-600 dark:text-slate-300'"
          >
            {{ msg.text }}
          </div>
        </div>
        <form
          class="flex gap-2"
          @submit.prevent="submitQuestion"
        >
          <input
            v-model="askInput"
            type="text"
            class="input h-10 min-w-0 flex-1 text-sm"
            placeholder="Đặt câu hỏi về nội dung…"
            autocomplete="off"
          >
          <button
            type="submit"
            class="btn-primary h-10 shrink-0 px-4 text-sm"
            :disabled="askLoading"
          >
            Gửi
          </button>
        </form>
      </div>

      <div>
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
          Câu hỏi liên quan
        </p>
        <ul class="flex flex-wrap gap-2">
          <li
            v-for="(q, i) in questionChips()"
            :key="i"
          >
            <button
              type="button"
              class="rounded-full bg-slate-100 px-3 py-1.5 text-left text-xs text-slate-700 transition hover:bg-brand/10 hover:text-brand dark:bg-slate-800 dark:text-slate-300"
              @click="askChip(q)"
              @contextmenu.prevent="copyQuestion(q)"
            >
              {{ q }}
            </button>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
