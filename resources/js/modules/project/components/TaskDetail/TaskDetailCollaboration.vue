<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import { datetime } from '@/composables/useFormat';
import { useToast } from '@/shared/composables/useToast';
import { normalizeEntities } from '@/composables/useNormalizeList';

const props = defineProps({
    comments: { type: Array, default: () => [] },
    commentableId: { type: [Number, String], required: true },
    canComment: { type: Boolean, default: false },
});

const page = usePage();
const toast = useToast();
const body = ref('');
const replyTo = ref(null);
const editingId = ref(null);
const editBody = ref('');
const showEmoji = ref(false);
const composerRef = ref(null);

const EMOJIS = ['👍', '❤️', '🎉', '🔥', '👀', '✅', '😂', '🙏'];

const myEmployeeId = computed(() => page.props.auth?.user?.employee?.id ?? null);
const commentList = computed(() => normalizeEntities(props.comments));

function replyList(c) {
    return normalizeEntities(c?.replies);
}

const submit = () => {
    if (!body.value.trim()) return;
    router.post('/comments', {
        commentable_type: 'task',
        commentable_id: props.commentableId,
        parent_id: replyTo.value?.id ?? null,
        body: body.value.trim(),
    }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => {
            body.value = '';
            replyTo.value = null;
            toast.success('Đã gửi bình luận');
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
        onSuccess: () => { editingId.value = null; },
    });
};

const remove = (c) => {
    if (!confirm('Xoá bình luận này?')) return;
    router.delete(`/comments/${c.id}`, { preserveScroll: true, only: ['tasks'] });
};

const toggleReaction = (c, emoji) => {
    router.post(`/comments/${c.id}/react`, { emoji }, { preserveScroll: true, only: ['tasks'] });
};

const iReacted = (c, emoji) => myEmployeeId.value && (c.reactions?.[emoji] || []).includes(myEmployeeId.value);

const focusComposer = () => composerRef.value?.focus();

defineExpose({ focusComposer });
</script>

<template>
  <div class="space-y-4">
    <form
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
        placeholder="Trao đổi… Markdown · @mention (sắp có)"
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
          v-if="canComment"
          type="submit"
          class="btn-primary text-xs"
          :disabled="!body.trim()"
        >
          Gửi bình luận
        </button>
      </div>
    </form>

    <div
      v-for="c in commentList"
      :key="c.id"
      class="space-y-2"
    >
      <article class="rounded-xl border border-slate-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900/80">
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
                @click="toggleReaction(c, emoji)"
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
                @click="toggleReaction(c, em)"
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

      <!-- Replies thread -->
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
