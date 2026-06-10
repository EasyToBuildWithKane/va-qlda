<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { datetime } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useCommentRealtime } from '@/composables/useCommentRealtime';

const props = defineProps({
    comments: { type: [Array, Object], default: () => [] },
    commentableType: { type: String, required: true },
    commentableId: { type: [Number, String], required: true },
    canComment: { type: Boolean, default: true },
    /** Xoá bình luận của người khác (vd. quản lý vướng mắc / contribute task) */
    canModerate: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Viết phản hồi cho người xử lý…' },
});

const page = usePage();
const dialog = useDialog();
const deletingId = ref(null);

function normalizeList(raw) {
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
}

const threadComments = ref(normalizeList(props.comments));

watch(
    () => props.comments,
    (raw) => {
        threadComments.value = normalizeList(raw);
    },
    { deep: true },
);

function mergeComment(comment) {
    if (!comment?.id) return;
    if (threadComments.value.some((c) => c.id === comment.id)) return;
    threadComments.value = [comment, ...threadComments.value];
}

function removeCommentLocal(commentId) {
    threadComments.value = threadComments.value.filter((c) => c.id !== commentId);
}

function mergeUpdated(comment) {
    if (!comment?.id) return;
    const idx = threadComments.value.findIndex((c) => c.id === comment.id);
    if (idx === -1) return;
    const next = [...threadComments.value];
    next[idx] = { ...next[idx], ...comment };
    threadComments.value = next;
}

const typeRef = computed(() => props.commentableType);
const idRef = computed(() => props.commentableId);

useCommentRealtime(typeRef, idRef, {
    onCreated: mergeComment,
    onUpdated: mergeUpdated,
    onDeleted: removeCommentLocal,
});

const list = computed(() => threadComments.value);

const form = useForm({
    commentable_type: props.commentableType,
    commentable_id: props.commentableId,
    body: '',
});

watch(
    () => [props.commentableType, props.commentableId],
    ([type, id]) => {
        form.commentable_type = type;
        form.commentable_id = id;
    },
);

const myEmployeeId = computed(() => {
    const u = page.props.auth?.user;
    return u?.employee_id ?? u?.employee?.id ?? null;
});

function canDeleteComment(c) {
    if (!c?.id) return false;
    if (props.canModerate) return true;
    const authorId = c.author?.id;
    return authorId != null && myEmployeeId.value != null && authorId === myEmployeeId.value;
}

const submit = () => {
    if (!form.body.trim()) return;
    form.post('/comments', {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};

async function removeComment(c) {
    if (!canDeleteComment(c) || deletingId.value) return;
    const ok = await dialog.confirm({
        title: 'Xoá trao đổi',
        message: 'Bình luận này sẽ bị xoá vĩnh viễn. Tiếp tục?',
        tone: 'danger',
        confirmText: 'Xoá',
    });
    if (!ok) return;

    deletingId.value = c.id;
    router.delete(`/comments/${c.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}
</script>

<template>
  <div>
    <h3 class="mb-3 flex items-center gap-2 font-display text-sm font-semibold text-slate-800 dark:text-slate-100">
      <AppIcon
        name="comment"
        :size="18"
      /> Trao đổi
      <span class="text-sm font-normal text-slate-400">({{ list.length }})</span>
    </h3>

    <form
      v-if="canComment"
      class="mb-5 flex gap-2"
      @submit.prevent="submit"
    >
      <textarea
        v-model="form.body"
        rows="2"
        class="input flex-1 resize-none text-sm"
        :placeholder="placeholder"
      />
      <button
        type="submit"
        class="btn-primary self-end text-sm"
        :disabled="form.processing || !form.body.trim()"
      >
        Gửi
      </button>
    </form>

    <ul class="space-y-3">
      <li
        v-for="c in list"
        :key="c.id"
        class="group flex gap-3 rounded-lg border border-transparent px-1 py-0.5 transition hover:border-slate-100 hover:bg-slate-50/80"
      >
        <Avatar
          :name="c.author?.name"
          :src="c.author?.avatar_path"
          :size="34"
          class="shrink-0"
        />
        <div class="min-w-0 flex-1">
          <div class="flex items-start justify-between gap-2">
            <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0">
              <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ c.author?.name || '—' }}</span>
              <span class="text-xs text-slate-400">{{ datetime(c.created_at) }}</span>
            </div>
            <button
              v-if="canDeleteComment(c)"
              type="button"
              class="shrink-0 rounded-md p-1 text-slate-400 opacity-0 transition hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100 focus:opacity-100 disabled:opacity-40"
              :disabled="deletingId === c.id"
              title="Xoá trao đổi"
              :aria-label="`Xoá bình luận của ${c.author?.name || 'người dùng'}`"
              @click="removeComment(c)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </div>
          <p class="mt-0.5 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">
            {{ c.body }}
          </p>
        </div>
      </li>
      <li
        v-if="list.length === 0"
        class="text-sm text-slate-400"
      >
        Chưa có trao đổi nào.
      </li>
    </ul>
  </div>
</template>
