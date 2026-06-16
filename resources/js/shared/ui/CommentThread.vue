<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { datetime } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useCommentRealtime } from '@/composables/useCommentRealtime';
import { useCommentThreadPoll } from '@/composables/useCommentThreadPoll';
import { authorFromPageUser, createPendingComment, isPendingComment } from '@/composables/useCommentOptimistic';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    comments: { type: [Array, Object], default: () => [] },
    commentableType: { type: String, required: true },
    commentableId: { type: [Number, String], required: true },
    canComment: { type: Boolean, default: true },
    /** Xoá bình luận của người khác (vd. quản lý vướng mắc / contribute task) */
    canModerate: { type: Boolean, default: false },
    placeholder: { type: String, default: 'Viết phản hồi cho người xử lý…' },
    /** Partial reload Inertia sau POST/DELETE (vd. ['blockers'] trên /blockers) */
    partialReloadKeys: { type: Array, default: () => [] },
    heading: { type: String, default: 'Trao đổi' },
    emptyMessage: { type: String, default: 'Chưa có trao đổi nào.' },
    deleteDialogTitle: { type: String, default: 'Xoá trao đổi' },
    deleteButtonTitle: { type: String, default: 'Xoá trao đổi' },
    realtimeHint: { type: String, default: 'Người khác gửi trao đổi sẽ hiện ngay không cần tải lại trang' },
    /** Giao diện đọc bài KB — composer avatar, thẻ bình luận, phân trang */
    variant: { type: String, default: 'default' },
    hideHeading: { type: Boolean, default: false },
    /** newest | oldest — áp dụng khi variant kb */
    sortOrder: { type: String, default: 'newest' },
    pageSize: { type: Number, default: 8 },
});

const page = usePage();
const dialog = useDialog();
const toast = useToast();
const deletingId = ref(null);
const realtimeEnabled = computed(() => !!page.props.realtime?.enabled);

function normalizeList(raw) {
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
}

const threadComments = ref(normalizeList(props.comments));

function commentSortKey(c) {
    return new Date(c?.created_at || 0).getTime();
}

/** Giữ bình luận từ Socket.IO / optimistic khi Inertia props chưa kịp cập nhật. */
function syncFromServerProps(raw) {
    const server = normalizeList(raw);
    const byId = new Map();

    for (const c of server) {
        if (c?.id != null) {
            byId.set(c.id, c);
        }
    }

    for (const c of threadComments.value) {
        if (!c?.id) continue;
        if (isPendingComment(c)) {
            byId.set(c.id, c);
            continue;
        }
        if (!byId.has(c.id)) {
            byId.set(c.id, c);
        }
    }

    threadComments.value = [...byId.values()].sort((a, b) => commentSortKey(b) - commentSortKey(a));
}

watch(
    () => props.comments,
    (raw) => {
        syncFromServerProps(raw);
    },
    { deep: true },
);

function mergeComment(comment) {
    if (!comment?.id) return;
    if (!isPendingComment(comment)) {
        threadComments.value = threadComments.value.filter(
            (c) => !isPendingComment(c) || c.body !== comment.body || c.author?.id !== comment.author?.id,
        );
    }
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

const { subscribed: realtimeSubscribed } = useCommentRealtime(typeRef, idRef, {
    onCreated: mergeComment,
    onUpdated: mergeUpdated,
    onDeleted: removeCommentLocal,
});

const reloadKeysRef = computed(() => props.partialReloadKeys || []);

const pollActive = computed(() => reloadKeysRef.value.length > 0);

useCommentThreadPoll({
    active: pollActive,
    enabled: computed(() => true),
    subscribed: realtimeSubscribed,
    reloadKeys: reloadKeysRef,
    fastIntervalMs: 15000,
});

const list = computed(() => threadComments.value);

const isKbVariant = computed(() => props.variant === 'kb');

const sortedList = computed(() => {
    const arr = [...list.value];
    const mult = props.sortOrder === 'oldest' ? 1 : -1;
    return arr.sort((a, b) => mult * (commentSortKey(a) - commentSortKey(b)));
});

const showAllComments = ref(false);

watch(
    () => props.sortOrder,
    () => {
        showAllComments.value = false;
    },
);

const displayList = computed(() => {
    if (!isKbVariant.value || showAllComments.value) {
        return sortedList.value;
    }
    return sortedList.value.slice(0, props.pageSize);
});

const hiddenCommentCount = computed(() => {
    if (!isKbVariant.value || showAllComments.value) return 0;
    return Math.max(0, sortedList.value.length - props.pageSize);
});

const myDisplayName = computed(() => {
    const u = page.props.auth?.user;
    if (!u) return 'Bạn';
    return (u.name || u.employee?.full_name || 'Bạn').trim();
});

const myAvatarSrc = computed(() => {
    const u = page.props.auth?.user;
    return u?.avatar_path ?? u?.employee?.avatar_path ?? null;
});

const KB_QUICK_EMOJI = ['👍', '💡', '✅', '🙏'];

function appendEmoji(emoji) {
    form.body = `${form.body}${emoji}`;
}

function onComposerKeydown(event) {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        submit();
    }
}

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
    const text = form.body.trim();
    const pending = createPendingComment(
        text,
        null,
        authorFromPageUser(page.props.auth?.user),
    );
    mergeComment(pending);
    form.body = '';
    form.transform((data) => ({ ...data, body: text })).post('/comments', {
        preserveScroll: true,
        ...(props.partialReloadKeys.length ? { only: props.partialReloadKeys } : {}),
        onSuccess: () => form.reset('body'),
        onError: () => {
            removeCommentLocal(pending.id);
            toast.error('Không gửi được bình luận');
        },
    });
};

async function removeComment(c) {
    if (!canDeleteComment(c) || deletingId.value) return;
    const ok = await dialog.confirm({
        title: props.deleteDialogTitle,
        message: 'Bình luận này sẽ bị xoá vĩnh viễn. Tiếp tục?',
        tone: 'danger',
        confirmText: 'Xoá',
    });
    if (!ok) return;

    deletingId.value = c.id;
    router.delete(`/comments/${c.id}`, {
        preserveScroll: true,
        ...(props.partialReloadKeys.length ? { only: props.partialReloadKeys } : {}),
        onFinish: () => {
            deletingId.value = null;
        },
    });
}
</script>

<template>
  <div :class="isKbVariant ? 'kb-comment-thread' : ''">
    <h3
      v-if="!hideHeading"
      class="mb-3 flex flex-wrap items-center gap-2 font-display text-sm font-semibold text-slate-800 dark:text-slate-100"
    >
      <AppIcon
        name="comment"
        :size="18"
      /> {{ heading }}
      <span class="text-sm font-normal text-slate-400">({{ list.length }})</span>
      <span
        v-if="realtimeEnabled && realtimeSubscribed"
        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
        :title="realtimeHint"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
        Realtime
      </span>
    </h3>

    <p
      v-if="isKbVariant && realtimeEnabled && realtimeSubscribed"
      class="mb-4 inline-flex items-center gap-1.5 rounded-full border border-emerald-200/80 bg-emerald-50/80 px-2.5 py-1 text-[11px] font-medium text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/30 dark:text-emerald-300"
      :title="realtimeHint"
    >
      <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
      Đang đồng bộ realtime
    </p>

    <form
      v-if="canComment"
      class="mb-6"
      :class="isKbVariant ? 'rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/50 sm:p-5' : 'flex gap-2'"
      @submit.prevent="submit"
    >
      <div
        v-if="isKbVariant"
        class="flex gap-3 sm:gap-4"
      >
        <Avatar
          :name="myDisplayName"
          :src="myAvatarSrc"
          :size="44"
          class="hidden shrink-0 sm:block"
        />
        <div class="min-w-0 flex-1 space-y-3">
          <textarea
            v-model="form.body"
            rows="3"
            class="input w-full resize-none text-sm leading-relaxed"
            :placeholder="placeholder"
            @keydown="onComposerKeydown"
          />
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-1.5">
              <button
                v-for="emoji in KB_QUICK_EMOJI"
                :key="emoji"
                type="button"
                class="grid h-8 w-8 place-items-center rounded-lg border border-slate-200/90 bg-slate-50/80 text-base transition hover:border-brand/30 hover:bg-brand/[0.04] dark:border-slate-700 dark:bg-slate-800/60"
                :aria-label="`Thêm ${emoji}`"
                @click="appendEmoji(emoji)"
              >
                {{ emoji }}
              </button>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
              <span class="hidden text-[11px] text-slate-400 sm:inline">Ctrl+Enter để gửi</span>
              <button
                type="submit"
                class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-sm"
                :disabled="form.processing || !form.body.trim()"
              >
                <AppIcon
                  name="send"
                  :size="15"
                />
                Gửi bình luận
              </button>
            </div>
          </div>
        </div>
      </div>
      <template v-else>
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
      </template>
    </form>

    <ul
      class="space-y-3"
      :class="isKbVariant ? 'space-y-4' : ''"
    >
      <li
        v-for="c in displayList"
        :key="c.id"
        class="group flex gap-3 transition"
        :class="isKbVariant
          ? 'rounded-xl border border-slate-200/90 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900/40 sm:gap-4'
          : 'rounded-lg border border-transparent px-1 py-0.5 hover:border-slate-100 hover:bg-slate-50/80 dark:hover:border-slate-800 dark:hover:bg-slate-900/30'"
      >
        <Avatar
          :name="c.author?.name"
          :src="c.author?.avatar_path"
          :size="isKbVariant ? 40 : 34"
          class="shrink-0"
        />
        <div class="min-w-0 flex-1">
          <div class="flex items-start justify-between gap-2">
            <div class="flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-0">
              <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ c.author?.name || '—' }}</span>
              <span
                class="text-xs text-slate-400"
                :title="datetime(c.created_at)"
              >{{ datetime(c.created_at) }}</span>
              <span
                v-if="isPendingComment(c)"
                class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
              >
                Đang gửi…
              </span>
            </div>
            <button
              v-if="canDeleteComment(c)"
              type="button"
              class="shrink-0 rounded-md p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
              :class="isKbVariant ? 'opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100' : 'opacity-0 group-hover:opacity-100 focus:opacity-100'"
              :disabled="deletingId === c.id"
              :title="deleteButtonTitle"
              :aria-label="`Xoá bình luận của ${c.author?.name || 'người dùng'}`"
              @click="removeComment(c)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </div>
          <p
            class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-slate-600 dark:text-slate-300"
            :class="isKbVariant ? 'text-[0.9375rem]' : 'mt-0.5'"
          >
            {{ c.body }}
          </p>
        </div>
      </li>
      <li
        v-if="sortedList.length === 0"
        class="text-sm text-slate-400"
        :class="isKbVariant ? 'rounded-xl border border-dashed border-slate-200/90 bg-slate-50/50 px-6 py-10 text-center dark:border-slate-700 dark:bg-slate-900/30' : ''"
      >
        <template v-if="isKbVariant">
          <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-brand/[0.08] text-brand">
            <AppIcon
              name="comment"
              :size="22"
            />
          </span>
          <p class="text-sm text-slate-500 dark:text-slate-400">
            {{ emptyMessage }}
          </p>
        </template>
        <template v-else>
          {{ emptyMessage }}
        </template>
      </li>
    </ul>

    <button
      v-if="hiddenCommentCount > 0"
      type="button"
      class="mt-4 w-full rounded-lg border border-slate-200/90 bg-slate-50/80 py-2.5 text-sm font-medium text-slate-700 transition hover:border-brand/25 hover:bg-brand/[0.04] hover:text-brand dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200"
      @click="showAllComments = true"
    >
      Xem thêm {{ hiddenCommentCount }} bình luận
    </button>
  </div>
</template>
