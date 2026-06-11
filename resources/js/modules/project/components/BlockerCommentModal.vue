<script setup>
import { computed } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    blocker: { type: Object, default: null },
    canComment: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const modalTitle = computed(() => {
    if (!props.blocker?.code) return 'Trao đổi vướng mắc';
    return `Trao đổi · ${props.blocker.code}`;
});

const canModerate = computed(() => !!props.blocker?.can?.update);

function normalizeComments(b) {
    if (!b) return [];
    const raw = b.comments;
    if (Array.isArray(raw)) return raw;
    if (raw?.data && Array.isArray(raw.data)) return raw.data;
    return [];
}
</script>

<template>
  <Modal
    :show="show && !!blocker"
    :title="modalTitle"
    max-width="max-w-lg"
    @close="emit('close')"
  >
    <template v-if="blocker">
      <p
        class="-mt-1 mb-4 line-clamp-2 text-sm text-slate-500"
        :title="blocker.title"
      >
        {{ blocker.title }}
      </p>

      <CommentThread
        :comments="normalizeComments(blocker)"
        commentable-type="blocker"
        :commentable-id="blocker.id"
        :can-comment="canComment"
        :can-moderate="canModerate"
        :partial-reload-keys="['blockers']"
        placeholder="VD: Đã kiểm tra log — cần team hạ tầng xem lại trước 17h hôm nay…"
      />
    </template>
  </Modal>
</template>
