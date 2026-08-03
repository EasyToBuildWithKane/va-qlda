<script setup>
import { computed, ref } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import EvaluationTemplateForm from '@/modules/evaluation-template/components/EvaluationTemplateForm.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    template: { type: Object, default: null },
    criteriaOptions: { type: Array, default: () => [] },
    jobTitles: { type: Array, default: () => [] },
    jobRanks: { type: Array, default: () => [] },
    fieldTypeOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const formRef = ref(null);

const title = computed(() => (
    props.template?.id ? 'Chỉnh sửa mẫu đánh giá' : 'Tạo mẫu đánh giá'
));

function onModalClose() {
    if (formRef.value?.requestClose) {
        formRef.value.requestClose();
        return;
    }
    emit('close');
}
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="4xl"
    @close="onModalClose"
  >
    <EvaluationTemplateForm
      v-if="show"
      ref="formRef"
      layout="modal"
      :active="show"
      :template="template"
      :criteria-options="criteriaOptions"
      :job-titles="jobTitles"
      :job-ranks="jobRanks"
      :field-type-options="fieldTypeOptions"
      @cancel="emit('close')"
      @success="emit('close')"
    />
  </Modal>
</template>
