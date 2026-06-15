<script setup>
import { computed, inject } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import OrgTeamTeamForm from '@/modules/people/components/OrgTeamTeamForm.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    team: { type: Object, default: null },
    parentOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    branchOptions: { type: Array, default: () => [] },
    presetParentId: { type: [Number, String, null], default: null },
    forceRoot: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'saved']);
inject('modalClose', () => emit('close'));

const isEdit = computed(() => !!props.team);

const modalTitle = computed(() => {
    if (isEdit.value) {
        return 'Sửa cấu trúc';
    }
    if (props.forceRoot) {
        return 'Tạo cấu trúc';
    }

    return props.presetParentId != null ? 'Thêm đơn vị' : 'Thêm đơn vị';
});

function onSaved() {
    emit('saved');
    emit('close');
}
</script>

<template>
  <Modal
    :show="show"
    :title="modalTitle"
    max-width="max-w-3xl"
    @close="emit('close')"
  >
    <OrgTeamTeamForm
      v-if="show"
      :team="team"
      :parent-options="parentOptions"
      :employees="employees"
      :branch-options="branchOptions"
      :preset-parent-id="presetParentId"
      :force-root="forceRoot"
      compact
      show-cancel
      @saved="onSaved"
      @cancel="emit('close')"
    />
  </Modal>
</template>
