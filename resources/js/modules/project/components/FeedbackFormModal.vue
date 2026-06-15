<script setup>
import { ref, computed, inject, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import PersonSelect from '@/modules/project/components/PersonSelect.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import BlockerFormSection from '@/modules/project/components/BlockerFormSection.vue';
import { valueLabelOptions } from '@/shared/utils/selectOptions';
import { useModalFormDraft } from '@/composables/useModalFormDraft';
import { buildDraftSaveMeta, restoreModalDraft } from '@/composables/useModalDraftHelpers';

const props = defineProps({
    show: { type: Boolean, default: false },
    feedback: { type: Object, default: null },
    projects: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    categoryOptions: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
    priorityOptions: { type: Array, default: () => [] },
    defaultProjectId: { type: Number, default: null },
    lockProject: { type: Boolean, default: false },
    /** Gửi kèm khi tạo — quay lại tab dự án */
    returnTo: { type: String, default: null },
});

const emit = defineEmits(['close', 'saved']);
const modalClose = inject('modalClose', () => emit('close'));

const reporterType = ref('external');
const form = useForm({
    project_id: null, category: 'improvement', title: '', description: '', rating: null,
    priority: 'medium', status: 'new',
    reporter_employee_id: null, reporter_name: '', reporter_email: '', assignee_id: null,
});

const feedbackDraftScope = computed(() => (
    props.feedback ? `edit.${props.feedback.id}` : `create.${props.defaultProjectId ?? 'global'}`
));

const formDraft = useModalFormDraft('feedback', {
    getScope: () => feedbackDraftScope.value,
    fields: [
        'project_id', 'category', 'title', 'description', 'rating', 'priority', 'status',
        'reporter_employee_id', 'reporter_name', 'reporter_email', 'assignee_id',
    ],
});

const applyFormDraft = (data, meta) => {
    form.project_id = data.project_id ?? props.defaultProjectId;
    form.category = data.category ?? 'improvement';
    form.title = data.title ?? '';
    form.description = data.description ?? '';
    form.rating = data.rating ?? null;
    form.priority = data.priority ?? 'medium';
    form.status = data.status ?? 'new';
    form.reporter_employee_id = data.reporter_employee_id ?? null;
    form.reporter_name = data.reporter_name ?? '';
    form.reporter_email = data.reporter_email ?? '';
    form.assignee_id = data.assignee_id ?? null;
    if (meta?.reporterType) reporterType.value = meta.reporterType;
};

const saveDraftOnClose = () => {
    formDraft.saveOnClose(form.data(), buildDraftSaveMeta(props.feedback, { reporterType: reporterType.value }));
};

const lockedProjectLabel = computed(() =>
    props.projects.find((p) => p.id === props.defaultProjectId)?.name ?? '—',
);

watch(() => props.show, async (open) => {
    if (!open) return;
    form.clearErrors();
    const epoch = formDraft.bumpOpenEpoch();
    if (props.feedback) {
        form.project_id = props.feedback.project_id;
        form.category = props.feedback.category.value;
        form.title = props.feedback.title;
        form.description = props.feedback.description;
        form.rating = props.feedback.rating;
        form.priority = props.feedback.priority.value;
        form.status = props.feedback.status.value;
        form.assignee_id = props.feedback.assignee?.id ?? null;
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: props.feedback,
            applyDraft: applyFormDraft,
            form,
        });
    } else {
        form.reset();
        reporterType.value = 'external';
        form.project_id = props.defaultProjectId;
        await restoreModalDraft(formDraft, {
            isActive: () => props.show,
            openEpoch: epoch,
            entity: null,
            applyDraft: applyFormDraft,
            form,
        });
    }
});

watch(reporterType, (t) => {
    if (t === 'internal') { form.reporter_name = ''; form.reporter_email = ''; }
    else { form.reporter_employee_id = null; }
});

const categorySelectOptions = computed(() => valueLabelOptions(props.categoryOptions));
const statusSelectOptions = computed(() => valueLabelOptions(props.statusOptions));
const prioritySelectOptions = computed(() => valueLabelOptions(props.priorityOptions));
const ratingSelectOptions = computed(() =>
    [1, 2, 3, 4, 5].map((n) => ({ id: n, name: `${n} ★` })),
);

const submit = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            formDraft.clear();
            emit('saved');
            emit('close');
        },
    };
    if (props.feedback) {
        form.put(`/feedback/${props.feedback.id}`, opts);
    } else {
        const payload = { ...form.data() };
        if (props.returnTo === 'project') {
            payload.return_to = 'project';
        }
        form.transform(() => payload).post('/feedback', opts);
    }
};
</script>

<template>
  <Modal
    :show="show"
    :dirty="form.isDirty"
    :title="feedback ? `Chỉnh sửa ${feedback.code}` : 'Phản hồi mới'"
    max-width="max-w-6xl"
    :on-save-draft="saveDraftOnClose"
    @close="emit('close')"
  >
    <form
      class="flex flex-col"
      @submit.prevent="submit"
    >
      <p
        v-if="feedback"
        class="-mt-1 mb-3 text-xs text-slate-500"
      >
        Cập nhật thông tin xử lý và nội dung phản hồi.
      </p>
      <p
        v-else
        class="-mt-1 mb-3 text-xs text-slate-500"
      >
        Ghi nhận ý kiến người dùng — điền đủ nội dung để đội xử lý nhanh hơn.
      </p>

      <div class="rounded-lg border border-slate-200 p-3 sm:p-4">
        <div class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3 xl:items-start">
          <BlockerFormSection
            plain
            title="Phạm vi & phân công"
            hint="Dự án và người xử lý."
          >
            <div
              v-if="lockProject"
              class="mb-3 flex items-center gap-2 rounded-lg border border-slate-200/80 bg-slate-50/60 px-3 py-2.5 text-sm"
            >
              <AppIcon
                name="projects"
                :size="16"
                class="shrink-0 text-brand"
              />
              <span class="text-slate-500">Dự án:</span>
              <span class="min-w-0 truncate font-medium text-slate-800">{{ lockedProjectLabel }}</span>
            </div>
            <div class="space-y-3">
              <div v-if="!lockProject">
                <label class="label flex items-center gap-1.5">
                  Dự án
                  <span class="font-normal text-slate-400">(tuỳ chọn)</span>
                  <FieldTooltip
                    wide
                    text="Gắn phản hồi với dự án để đội triển khai theo dõi. Để trống nếu là ý kiến chung về hệ thống."
                  />
                </label>
                <SearchSelect
                  v-model="form.project_id"
                  :options="projects"
                  placeholder="Chọn dự án liên quan…"
                  search-placeholder="Tìm theo tên hoặc mã dự án…"
                />
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Người xử lý
                  <span class="font-normal text-slate-400">(tuỳ chọn)</span>
                  <FieldTooltip text="Người được giao xử lý và phản hồi lại người gửi." />
                </label>
                <PersonSelect
                  v-model="form.assignee_id"
                  :options="employees"
                  placeholder="Tìm & chọn người xử lý…"
                />
              </div>
            </div>
          </BlockerFormSection>

          <BlockerFormSection
            plain
            title="Nội dung phản hồi"
            hint="Tiêu đề và mô tả chi tiết."
            :class="feedback ? 'lg:col-span-2 xl:col-span-2' : 'lg:col-span-2 xl:col-span-1'"
          >
            <div class="space-y-3">
              <div>
                <label class="label flex items-center gap-1.5">
                  Tiêu đề
                  <span class="text-danger">*</span>
                  <FieldTooltip text="Tóm tắt ngắn, dễ nhận diện trong danh sách phản hồi." />
                </label>
                <input
                  v-model="form.title"
                  type="text"
                  class="input"
                  placeholder="VD: Giao diện báo cáo ngày khó đọc trên mobile"
                >
                <p
                  v-if="form.errors.title"
                  class="mt-1 text-xs text-danger"
                >
                  {{ form.errors.title }}
                </p>
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Nội dung chi tiết
                  <span class="text-danger">*</span>
                  <FieldTooltip
                    wide
                    text="Mô tả đầy đủ: bối cảnh, bước tái hiện, kỳ vọng mong muốn hoặc link ảnh chụp màn hình."
                  />
                </label>
                <textarea
                  v-model="form.description"
                  rows="8"
                  class="input min-h-[10rem] resize-y text-sm"
                  placeholder="Mô tả chi tiết vấn đề hoặc đề xuất. Nếu là lỗi, ghi rõ các bước tái hiện…"
                />
                <p
                  v-if="form.errors.description"
                  class="mt-1 text-xs text-danger"
                >
                  {{ form.errors.description }}
                </p>
              </div>
            </div>
          </BlockerFormSection>

          <BlockerFormSection
            plain
            title="Phân loại & theo dõi"
            hint="Loại, ưu tiên, trạng thái, đánh giá."
          >
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
              <div class="sm:col-span-2 xl:col-span-1">
                <label class="label flex items-center gap-1.5">
                  Phân loại
                  <span class="text-danger">*</span>
                  <FieldTooltip text="Loại ý kiến — giúp lọc và báo cáo (đề xuất, phàn nàn, khen ngợi…)." />
                </label>
                <SearchSelect
                  v-model="form.category"
                  :options="categorySelectOptions"
                  placeholder="Chọn phân loại phản hồi…"
                  :clearable="false"
                />
                <p
                  v-if="form.errors.category"
                  class="mt-1 text-xs text-danger"
                >
                  {{ form.errors.category }}
                </p>
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Ưu tiên
                  <span class="text-danger">*</span>
                  <FieldTooltip text="Mức độ cần xử lý — tham chiếu khi sắp xếp việc trong đội." />
                </label>
                <SearchSelect
                  v-model="form.priority"
                  :options="prioritySelectOptions"
                  placeholder="Chọn mức ưu tiên…"
                  :clearable="false"
                />
                <p
                  v-if="form.errors.priority"
                  class="mt-1 text-xs text-danger"
                >
                  {{ form.errors.priority }}
                </p>
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Trạng thái
                  <FieldTooltip text="Tiến trình xử lý: mới, đang xử lý, đã xử lý…" />
                </label>
                <SearchSelect
                  v-model="form.status"
                  :options="statusSelectOptions"
                  placeholder="Chọn trạng thái…"
                  :clearable="false"
                />
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Đánh giá
                  <span class="font-normal text-slate-400">(tuỳ chọn)</span>
                  <FieldTooltip text="Điểm trải nghiệm từ người gửi, thang 1–5 sao." />
                </label>
                <SearchSelect
                  v-model="form.rating"
                  :options="ratingSelectOptions"
                  placeholder="Chưa có đánh giá…"
                />
              </div>
            </div>
          </BlockerFormSection>
        </div>

        <BlockerFormSection
          v-if="!feedback"
          plain
          title="Người gửi phản hồi"
          hint="Nội bộ (nhân viên) hoặc người dùng bên ngoài."
          class="mt-5 border-t border-slate-100 pt-5"
        >
          <div class="grid gap-4 lg:grid-cols-3 lg:items-start">
            <div class="flex flex-wrap gap-3 text-sm lg:col-span-3">
              <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 transition has-[:checked]:border-brand/40 has-[:checked]:bg-brand/5">
                <input
                  v-model="reporterType"
                  type="radio"
                  value="internal"
                  class="text-brand"
                >
                <span class="font-medium text-slate-700">Nội bộ</span>
              </label>
              <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 transition has-[:checked]:border-brand/40 has-[:checked]:bg-brand/5">
                <input
                  v-model="reporterType"
                  type="radio"
                  value="external"
                  class="text-brand"
                >
                <span class="font-medium text-slate-700">Người dùng / bên ngoài</span>
              </label>
            </div>
            <div
              v-if="reporterType === 'internal'"
              class="lg:col-span-2"
            >
              <label class="label flex items-center gap-1.5">
                Nhân viên gửi
                <FieldTooltip text="Chọn nhân viên trong hệ thống là người gửi phản hồi." />
              </label>
              <PersonSelect
                v-model="form.reporter_employee_id"
                :options="employees"
                placeholder="Tìm & chọn nhân viên…"
              />
            </div>
            <template v-else>
              <div>
                <label class="label flex items-center gap-1.5">
                  Họ tên
                  <FieldTooltip text="Tên người gửi — hiển thị trên danh sách và thông báo." />
                </label>
                <input
                  v-model="form.reporter_name"
                  type="text"
                  class="input"
                  placeholder="Họ và tên người gửi"
                >
              </div>
              <div>
                <label class="label flex items-center gap-1.5">
                  Email
                  <FieldTooltip text="Email liên hệ khi cần làm rõ hoặc phản hồi kết quả xử lý." />
                </label>
                <input
                  v-model="form.reporter_email"
                  type="email"
                  class="input"
                  placeholder="email@example.com"
                >
              </div>
            </template>
            <p
              v-if="form.errors.reporter_name"
              class="text-xs text-danger lg:col-span-3"
            >
              {{ form.errors.reporter_name }}
            </p>
            <p
              v-if="form.errors.reporter_email"
              class="text-xs text-danger lg:col-span-3"
            >
              {{ form.errors.reporter_email }}
            </p>
          </div>
        </BlockerFormSection>
      </div>

      <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
        <button
          type="button"
          class="btn-ghost"
          @click="modalClose()"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ feedback ? 'Lưu thay đổi' : 'Gửi phản hồi' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
