<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import { datetime } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';
import { useDialog } from '@/composables/useDialog';
import { normalizeEntities } from '@/composables/useNormalizeList';
import { useCommentRealtime } from '@/composables/useCommentRealtime';
import { useCommentThreadPoll } from '@/composables/useCommentThreadPoll';
import { authorFromPageUser, createPendingComment, isPendingComment } from '@/composables/useCommentOptimistic';

const props = defineProps({
    comments: { type: Array, default: () => [] },
    commentableId: { type: [Number, String], required: true },
    canComment: { type: Boolean, default: false },
    /** Tab trao đổi đang mở — bật poll fallback khi chưa realtime. */
    pollActive: { type: Boolean, default: true },
});

const page = usePage();
const toast = useToast();
const dialog = useDialog();
const body = ref('');
const replyTo = ref(null);
const editingId = ref(null);
const editBody = ref('');
const showEmoji = ref(false);
const composerRef = ref(null);

const EMOJIS = ['👍', '❤️', '🎉', '🔥', '👀', '✅', '😂', '🙏'];

const myEmployeeId = computed(() => page.props.auth?.user?.employee?.id ?? null);
const realtimeEnabled = computed(() => !!page.props.realtime?.enabled);

const threadComments = ref(normalizeEntities(props.comments));

watch(
    () => props.comments,
    (raw) => {
        threadComments.value = normalizeEntities(raw);
    },
    { deep: true },
);

watch(
    () => page.props.tasks,
    () => {
        syncCommentsFromInertia();
    },
    { deep: true },
);

const commentList = computed(() => threadComments.value);

function syncCommentsFromInertia() {
    const tasks = page.props.tasks;
    if (!Array.isArray(tasks)) return;
    const task = tasks.find((t) => Number(t.id) === Number(props.commentableId));
    if (!task) return;
    threadComments.value = normalizeEntities(task.comments);
}

function replyList(c) {
    return normalizeEntities(c?.replies);
}

function dropMatchingPending(comment) {
    const bodyText = comment?.body;
    const authorId = comment?.author?.id;
    threadComments.value = threadComments.value
        .filter((c) => !isPendingComment(c) || c.body !== bodyText || c.author?.id !== authorId)
        .map((c) => ({
            ...c,
            replies: replyList(c).filter(
                (r) => !isPendingComment(r) || r.body !== bodyText || r.author?.id !== authorId,
            ),
        }));
}

function mergeComment(comment) {
    if (!comment?.id) return;
    if (!isPendingComment(comment)) {
        dropMatchingPending(comment);
    }

    if (comment.parent_id) {
        threadComments.value = threadComments.value.map((root) => {
            if (root.id !== comment.parent_id) return root;
            const replies = replyList(root);
            if (replies.some((r) => r.id === comment.id)) return root;
            return { ...root, replies: [...replies, comment] };
        });
        return;
    }

    if (threadComments.value.some((c) => c.id === comment.id)) return;
    threadComments.value = [comment, ...threadComments.value];
}

function removeCommentLocal(commentId) {
    threadComments.value = threadComments.value
        .filter((c) => c.id !== commentId)
        .map((c) => ({
            ...c,
            replies: replyList(c).filter((r) => r.id !== commentId),
        }));
}

function patchComment(existing, incoming) {
    return {
        ...existing,
        ...incoming,
        replies: existing.replies ?? incoming.replies,
    };
}

function mergeUpdated(comment) {
    if (!comment?.id) return;

    if (comment.parent_id) {
        threadComments.value = threadComments.value.map((root) => {
            if (root.id !== comment.parent_id) return root;
            const replies = replyList(root).map((r) => (r.id === comment.id ? patchComment(r, comment) : r));
            return { ...root, replies };
        });
        return;
    }

    const idx = threadComments.value.findIndex((c) => c.id === comment.id);
    if (idx === -1) return;
    const next = [...threadComments.value];
    next[idx] = patchComment(next[idx], comment);
    threadComments.value = next;
}

function applyReactionLocal(commentId, emoji) {
    const employeeId = myEmployeeId.value;
    if (!employeeId) return;

    const toggleOnComment = (c) => {
        if (c.id !== commentId) return c;
        const reactions = { ...(c.reactions || {}) };
        const ids = [...(reactions[emoji] || [])];
        const i = ids.indexOf(employeeId);
        if (i >= 0) {
            ids.splice(i, 1);
        } else {
            ids.push(employeeId);
        }
        if (ids.length === 0) {
            delete reactions[emoji];
        } else {
            reactions[emoji] = ids;
        }
        const nextReactions = Object.keys(reactions).length ? reactions : null;
        return { ...c, reactions: nextReactions };
    };

    threadComments.value = threadComments.value.map((root) => {
        if (root.id === commentId) return toggleOnComment(root);
        const replies = replyList(root);
        if (!replies.some((r) => r.id === commentId)) return root;
        return {
            ...root,
            replies: replies.map((r) => (r.id === commentId ? toggleOnComment(r) : r)),
        };
    });
}

const commentableType = computed(() => 'task');
const commentableId = computed(() => props.commentableId);

const { subscribed: realtimeSubscribed } = useCommentRealtime(commentableType, commentableId, {
    onCreated: mergeComment,
    onUpdated: mergeUpdated,
    onDeleted: removeCommentLocal,
});

useCommentThreadPoll({
    active: computed(() => props.pollActive),
    enabled: realtimeEnabled,
    subscribed: realtimeSubscribed,
    reloadKeys: computed(() => ['tasks']),
});

const submit = () => {
    if (!props.canComment || !body.value.trim()) return;
    const text = body.value.trim();
    const parentId = replyTo.value?.id ?? null;
    const pending = createPendingComment(
        text,
        parentId,
        authorFromPageUser(page.props.auth?.user),
    );
    mergeComment(pending);
    body.value = '';
    const savedReply = replyTo.value;
    replyTo.value = null;
    router.post('/comments', {
        commentable_type: 'task',
        commentable_id: props.commentableId,
        parent_id: parentId,
        body: text,
    }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => {
            syncCommentsFromInertia();
            toast.success('Đã gửi bình luận');
        },
        onError: () => {
            removeCommentLocal(pending.id);
            body.value = text;
            replyTo.value = savedReply;
            toast.error('Không gửi được bình luận');
        },
    });
};

const startReply = (c) => {
    replyTo.value = c;
    composerRef.value?.focus();
};

const startEdit = (c) => {
    editingId.value = c.id;
    editBody.value = c.body;
};

const saveEdit = (c) => {
    router.put(`/comments/${c.id}`, { body: editBody.value }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => {
            editingId.value = null;
            syncCommentsFromInertia();
        },
    });
};

const remove = async (c) => {
    const ok = await dialog.confirm({
        title: 'Xoá trao đổi',
        message: 'Bình luận này sẽ bị xoá vĩnh viễn. Tiếp tục?',
        tone: 'danger',
        confirmText: 'Xoá',
    });
    if (!ok) return;
    router.delete(`/comments/${c.id}`, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => syncCommentsFromInertia(),
    });
};

const toggleReaction = (c, emoji) => {
    applyReactionLocal(c.id, emoji);
    router.post(`/comments/${c.id}/react`, { emoji }, {
        preserveScroll: true,
        onError: () => {
            threadComments.value = normalizeEntities(props.comments);
        },
    });
};

const iReacted = (c, emoji) => myEmployeeId.value && (c.reactions?.[emoji] || []).includes(myEmployeeId.value);

const focusComposer = () => composerRef.value?.focus();

defineExpose({ focusComposer });
</script>

<template>
  <div class="space-y-4">
    <div
      v-if="realtimeEnabled"
      class="flex flex-wrap items-center gap-2"
    >
      <span
        v-if="realtimeSubscribed"
        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
        title="Đồng bộ trao đổi theo thời gian thực với team"
      >
        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
        Realtime
      </span>
      <span
        v-else
        class="text-[10px] text-slate-400"
      >
        Đang kết nối realtime…
      </span>
    </div>
    <form
      v-if="canComment"
      class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 dark:border-slate-700 dark:bg-slate-800/50"
      @submit.prevent="submit"
    >
      <p
        v-if="replyTo"
        class="mb-2 flex items-center gap-2 text-xs text-brand"
      >
        Trả lời {{ replyTo.author?.name }}
        <button
          type="button"
          class="text-slate-400 hover:text-slate-600"
          @click="replyTo = null"
        >
          ✕
        </button>
      </p>
      <textarea
        ref="composerRef"
        v-model="body"
        rows="3"
        class="input w-full resize-none border-0 bg-white text-sm shadow-sm dark:bg-slate-900"
        placeholder="Trao đổi với team về công việc này…"
      />
      <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <div class="relative">
          <button
            type="button"
            class="btn-ghost px-2 py-1 text-xs"
            @click="showEmoji = !showEmoji"
          >
            😀
          </button>
          <div
            v-if="showEmoji"
            class="absolute bottom-full left-0 z-10 mb-1 flex gap-1 rounded-lg border bg-white p-2 shadow-lg dark:border-slate-700 dark:bg-slate-900"
          >
            <button
              v-for="em in EMOJIS"
              :key="em"
              type="button"
              class="text-lg hover:scale-110"
              @click="body += em; showEmoji = false"
            >
              {{ em }}
            </button>
          </div>
        </div>
        <button
          type="submit"
          class="btn-primary text-xs"
          :disabled="!body.trim()"
        >
          Gửi bình luận
        </button>
      </div>
    </form>
    <p
      v-else
      class="rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-800/40"
    >
      Bạn có thể xem trao đổi. Chỉ thành viên được phép đóng góp mới gửi bình luận.
    </p>

    <div
      v-for="c in commentList"
      :key="c.id"
      class="space-y-2"
    >
      <article
        class="comment-enter rounded-xl border border-slate-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900/80"
        :class="isPendingComment(c) ? 'opacity-80' : ''"
      >
        <div class="flex gap-3">
          <Avatar
            :name="c.author?.name"
            :src="c.author?.avatar_path"
            :size="36"
          />
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-baseline gap-2">
              <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ c.author?.name }}</span>
              <span class="text-xs text-slate-400">{{ datetime(c.created_at) }}</span>
              <span
                v-if="c.updated_at && c.updated_at !== c.created_at"
                class="text-[10px] text-slate-400"
              >(đã sửa)</span>
            </div>

            <textarea
              v-if="editingId === c.id"
              v-model="editBody"
              rows="3"
              class="input mt-2 w-full text-sm"
            />
            <p
              v-else
              class="mt-1 whitespace-pre-wrap text-sm leading-relaxed text-slate-600 dark:text-slate-300"
            >
              {{ c.body }}
            </p>

            <div
              v-if="c.reactions && Object.keys(c.reactions).length"
              class="mt-2 flex flex-wrap gap-1"
            >
              <button
                v-for="(ids, emoji) in c.reactions"
                :key="emoji"
                type="button"
                class="rounded-full px-2 py-0.5 text-xs"
                :class="iReacted(c, emoji) ? 'bg-brand/15 text-brand ring-1 ring-brand/30' : 'bg-slate-100 text-slate-600 dark:bg-slate-800'"
                @click="canComment && toggleReaction(c, emoji)"
              >
                {{ emoji }} {{ ids.length }}
              </button>
            </div>

            <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
              <button
                v-if="canComment"
                type="button"
                class="font-medium text-slate-500 hover:text-brand"
                @click="startReply(c)"
              >
                Trả lời
              </button>
              <button
                v-for="em in ['👍', '❤️', '🎉']"
                :key="em"
                type="button"
                class="hover:scale-110"
                :disabled="!canComment"
                @click="canComment && toggleReaction(c, em)"
              >
                {{ em }}
              </button>
              <template v-if="canComment && c.author?.id === myEmployeeId">
                <button
                  type="button"
                  class="text-slate-500 hover:text-brand"
                  @click="startEdit(c)"
                >
                  Sửa
                </button>
                <button
                  v-if="editingId === c.id"
                  type="button"
                  class="text-brand"
                  @click="saveEdit(c)"
                >
                  Lưu
                </button>
                <button
                  type="button"
                  class="text-rose-500 hover:underline"
                  @click="remove(c)"
                >
                  Xoá
                </button>
              </template>
            </div>
          </div>
        </div>
      </article>

      <div
        v-for="r in replyList(c)"
        :key="r.id"
        class="ml-10 rounded-lg border border-slate-100 bg-slate-50/80 p-2.5 dark:border-slate-800 dark:bg-slate-800/40"
      >
        <div class="flex gap-2">
          <Avatar
            :name="r.author?.name"
            :src="r.author?.avatar_path"
            :size="28"
          />
          <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-2">
              <span class="text-xs font-semibold">{{ r.author?.name }}</span>
              <span class="text-[10px] text-slate-400">{{ datetime(r.created_at) }}</span>
            </div>
            <p class="mt-0.5 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">
              {{ r.body }}
            </p>
            <button
              v-if="canComment"
              type="button"
              class="mt-1 text-[10px] text-brand"
              @click="startReply(c)"
            >
              Trả lời
            </button>
          </div>
        </div>
      </div>
    </div>

    <p
      v-if="!commentList.length"
      class="rounded-xl border border-dashed border-slate-200 py-10 text-center text-sm text-slate-400"
    >
      Chưa có trao đổi. Bắt đầu cuộc hội thoại với team.
    </p>
  </div>
</template>

<style scoped>
.comment-enter {
    animation: comment-slide-in 0.35s ease backwards;
}

@keyframes comment-slide-in {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .comment-enter {
        animation: none;
    }
}
</style>
