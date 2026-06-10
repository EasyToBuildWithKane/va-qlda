<script setup>
import { computed, inject, watch, nextTick, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { datetime } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, restoreModalDraft } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    canComment: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);
const modalClose = inject('modalClose', () => emit('close'));

const page = usePage();
const dialog = useDialog();
const bodyRef = ref(null);
const deletingId = ref(null);

const myEmployeeId = computed(() => {
    const u = page.props.auth?.user;
    return u?.employee_id ?? u?.employee?.id ?? null;
});

const canModerate = computed(() => !!props.blocker?.can?.update);

const form = useForm({
    commentable_type: 'blocker',
    commentable_id: null,
    body: '',
});

const formDraft = useModalFormDraft('blocker-comment', {
    getScope: () => props.blocker?.id ?? 'none',
    fields: ['body'],
});

const saveDraftOnClose = () => {
    formDraft.saveOnClose({ body: form.body }, buildDraftSaveMeta(props.blocker));
};

watch(
    () => [props.show, props.blocker?.id],
    async ([open, id]) => {
        if (!open || !id) return;
        form.commentable_id = id;
        form.clearErrors();
        form.reset('body');
        const epoch = formDraft.bumpOpenEpoch();
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: props.blocker,
            applyDraft: (data) => {
                form.body = data.body ?? '';
            },
            form,
        });
        await nextTick();
        bodyRef.value?.focus?.();
    },
);

const comments = computed(() => {
    const b = props.blocker;
    if (!b) return [];
    const raw = b.comments;
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
});

const modalDirty = computed(() => form.isDirty);

const modalTitle = computed(() => {
    if (!props.blocker?.code) return 'Bình luận vướng mắc';
    return `Bình luận · ${props.blocker.code}`;
});

const closeAfterSubmit = () => {
    formDraft.clear();
    form.clearErrors();
    form.reset('body');
    emit('close');
};

const submit = () => {
    if (!form.body.trim() || !props.blocker) return;
    form.post('/comments', {
        preserveScroll: true,
        onSuccess: closeAfterSubmit,
        onFinish: () => {
            if (!form.hasErrors && props.show) {
                closeAfterSubmit();
            }
        },
    });
};

function canDeleteComment(c) {
    if (!c?.id) return false;
    if (canModerate.value) return true;
    const authorId = c.author?.id;
    return authorId != null && myEmployeeId.value != null && authorId === myEmployeeId.value;
}

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
  <Modal
    :show="show && !!blocker"
    :dirty="modalDirty"
    :title="modalTitle"
    max-width="max-w-lg"
    close-confirm-title="Đóng bình luận?"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <template v-if="blocker">
      <p
        class="-mt-1 mb-4 line-clamp-2 text-sm text-slate-500"
        :title="blocker.title"
      >
        {{ blocker.title }}
      </p>

      <div
        v-if="comments.length"
        class="mb-4 max-h-40 space-y-3 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50/60 p-3"
      >
        <div
          v-for="c in comments.slice(-4)"
          :key="c.id"
          class="group flex gap-2.5"
        >
          <Avatar
            :name="c.author?.name"
            :src="c.author?.avatar_path"
            :size="28"
            class="shrink-0"
          />
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-1">
              <p class="min-w-0 text-xs font-medium text-slate-700">
                {{ c.author?.name || '—' }}
                <span class="font-normal text-slate-400"> · {{ datetime(c.created_at) }}</span>
              </p>
              <button
                v-if="canDeleteComment(c)"
                type="button"
                class="shrink-0 rounded p-0.5 text-slate-400 opacity-0 transition hover:text-rose-600 group-hover:opacity-100 focus:opacity-100 disabled:opacity-40"
                :disabled="deletingId === c.id"
                title="Xoá"
                @click="removeComment(c)"
              >
                <AppIcon
                  name="delete"
                  :size="13"
                />
              </button>
            </div>
            <p class="mt-0.5 line-clamp-3 whitespace-pre-wrap text-xs text-slate-600">
              {{ c.body }}
            </p>
          </div>
        </div>
        <p
          v-if="comments.length > 4"
          class="text-center text-[11px] text-slate-400"
        >
          và {{ comments.length - 4 }} bình luận trước đó — xem đầy đủ trong «Xem chi tiết»
        </p>
      </div>

      <form
        v-if="canComment"
        class="space-y-3"
        @submit.prevent="submit"
      >
        <label
          for="blocker-comment-body"
          class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500"
        >
          <AppIcon
            name="comment"
            :size="14"
            class="text-brand"
          />
          Nội dung trao đổi
        </label>
        <textarea
          id="blocker-comment-body"
          ref="bodyRef"
          v-model="form.body"
          rows="5"
          class="input min-h-[7.5rem] resize-y text-sm leading-relaxed"
          placeholder="VD: Đã kiểm tra log server — lỗi timeout khi gọi API thanh toán. Cần team hạ tầng xem lại cấu hình LB trước 17h hôm nay…"
        />
        <p
          v-if="form.errors.body"
          class="text-xs text-danger"
        >
          {{ form.errors.body }}
        </p>
        <div class="flex flex-wrap justify-end gap-2 pt-1">
          <button
            type="button"
            class="btn-ghost"
            @click="modalClose()"
          >
            Huỷ
          </button>
          <button
            type="submit"
            class="btn-primary gap-1.5"
            :disabled="form.processing || !form.body.trim()"
          >
            <AppIcon
              name="send"
              :size="15"
            />
            Gửi bình luận
          </button>
        </div>
      </form>

      <p
        v-else
        class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500"
      >
        Bạn không có quyền bình luận trên vướng mắc này.
      </p>
    </template>
  </Modal>
</template>
