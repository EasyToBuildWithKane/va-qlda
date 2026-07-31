<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Ui/Modal.vue';
import PositionAutocomplete from '@/modules/evaluation-template/components/PositionAutocomplete.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';
import { useConfirmClose } from '@/composables/useConfirmClose';

const props = defineProps({
    show: { type: Boolean, default: false },
    template: { type: Object, default: null },
    positions: { type: Array, default: () => [] },
    criteriaOptions: { type: Array, default: () => [] },
    nextCode: { type: String, default: 'MDG001' },
});

const emit = defineEmits(['close']);

const isEdit = computed(() => !!props.template?.id);
const title = computed(() => (isEdit.value ? 'Sửa mẫu đánh giá' : 'Thêm mẫu đánh giá'));

const codeLocked = ref(true);
const criteriaQuery = ref('');

const form = useForm({
    template_code: '',
    name: '',
    description: '',
    position_code: '',
    position_name: '',
    is_active: true,
    criteria: [],
});

const requestClose = useConfirmClose(() => emit('close'));

function resetForm() {
    const t = props.template;
    form.clearErrors();
    form.template_code = t?.template_code || props.nextCode;
    form.name = t?.name || '';
    form.description = t?.description || '';
    form.position_code = t?.position_code || '';
    form.position_name = t?.position_name || '';
    form.is_active = t ? t.is_active !== false : true;
    form.criteria = Array.isArray(t?.criteria)
        ? t.criteria.map((c, i) => ({
            criterion_id: c.criterion_id,
            weight: c.weight ?? 1,
            required_score_label: c.required_score_label || '',
            include_in_total: c.include_in_total !== false,
            sort_order: c.sort_order ?? i,
        }))
        : [];
    codeLocked.value = !isEdit.value;
    criteriaQuery.value = '';
    form.defaults();
}

watch(() => props.show, (open) => {
    if (open) resetForm();
});

function close() {
    requestClose(form.isDirty);
}

function onPositionSelect(pos) {
    form.position_code = pos?.code || '';
    form.position_name = pos?.name || '';
}

const selectedIds = computed(() => new Set(form.criteria.map((c) => Number(c.criterion_id))));

const filteredCriteria = computed(() => {
    const q = criteriaQuery.value.trim();
    const list = props.criteriaOptions || [];
    if (!q) return list.slice(0, 60);
    return list.filter((c) => matchesSearchQuery([c.criteria_name, c.criteria_code, c.category], q)).slice(0, 60);
});

function addCriterion(opt) {
    if (selectedIds.value.has(Number(opt.id))) return;
    form.criteria.push({
        criterion_id: opt.id,
        weight: 1,
        required_score_label: `Điểm yêu cầu ${form.criteria.length + 1}`,
        include_in_total: true,
        sort_order: form.criteria.length,
    });
}

function removeCriterion(index) {
    form.criteria.splice(index, 1);
    form.criteria.forEach((c, i) => { c.sort_order = i; });
}

function criterionLabel(id) {
    const opt = props.criteriaOptions.find((c) => Number(c.id) === Number(id));
    return opt ? `${opt.criteria_name} (${opt.criteria_code})` : `#${id}`;
}

function criterionCategory(id) {
    return props.criteriaOptions.find((c) => Number(c.id) === Number(id))?.category || '';
}

function submit() {
    const payload = {
        ...form.data(),
        template_code: codeLocked.value && !isEdit.value ? null : (form.template_code || null),
        criteria: form.criteria.map((c, i) => ({
            criterion_id: c.criterion_id,
            weight: Number(c.weight) || 1,
            required_score_label: c.required_score_label || null,
            include_in_total: c.include_in_total !== false,
            sort_order: i,
        })),
    };

    if (isEdit.value) {
        form.transform(() => payload).put(route('workspace.evaluation-templates.update', props.template.id), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    } else {
        form.transform(() => payload).post(route('workspace.evaluation-templates.store'), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
}
</script>

<template>
  <Modal
    :show="show"
    :title="title"
    max-width="max-w-4xl"
    @close="close"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Tên mẫu đánh giá *</label>
          <input
            v-model="form.name"
            type="text"
            class="input h-10 w-full text-sm"
            :class="form.errors.name ? 'border-rose-300' : ''"
            placeholder="Vd. Đánh giá chuyên viên kinh doanh"
          >
          <p
            v-if="form.errors.name"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.name }}
          </p>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Mã mẫu đánh giá</label>
          <div class="flex gap-2">
            <input
              v-model="form.template_code"
              type="text"
              class="input h-10 flex-1 font-mono text-sm uppercase"
              :disabled="codeLocked && !isEdit"
              :class="form.errors.template_code ? 'border-rose-300' : ''"
            >
            <button
              type="button"
              class="btn-ghost h-10 px-3"
              :title="codeLocked ? 'Mở khóa sửa mã' : 'Khóa mã'"
              @click="codeLocked = !codeLocked"
            >
              <AppIcon
                :name="codeLocked ? 'lock' : 'unlock'"
                :size="15"
              />
            </button>
          </div>
          <p
            v-if="form.errors.template_code"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors.template_code }}
          </p>
        </div>
        <div>
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Vị trí đánh giá</label>
          <PositionAutocomplete
            v-model="form.position_code"
            :options="positions"
            @select="onPositionSelect"
          />
        </div>
        <div class="flex items-end">
          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input
              v-model="form.is_active"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand"
            >
            Đang hoạt động
          </label>
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-xs font-medium text-slate-600">Mô tả</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="input w-full text-sm"
            placeholder="Mô tả ngắn về mẫu đánh giá…"
          />
        </div>
      </div>

      <div class="rounded-xl border border-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div>
            <p class="text-sm font-semibold text-slate-800">
              Tiêu chí trong mẫu
            </p>
            <p class="text-xs text-slate-500">
              Chọn từ bộ tiêu chí đã cấu hình · {{ form.criteria.length }} tiêu chí
            </p>
          </div>
          <input
            v-model="criteriaQuery"
            type="search"
            class="input h-9 w-full max-w-xs text-sm"
            placeholder="Tìm tiêu chí để thêm…"
          >
        </div>

        <div
          v-if="criteriaQuery.trim()"
          class="max-h-40 overflow-y-auto border-b border-slate-100 bg-slate-50/80 px-2 py-2"
        >
          <button
            v-for="opt in filteredCriteria"
            :key="opt.id"
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-white"
            :disabled="selectedIds.has(Number(opt.id))"
            :class="selectedIds.has(Number(opt.id)) ? 'opacity-40' : ''"
            @click="addCriterion(opt)"
          >
            <span class="min-w-0">
              <span class="block truncate font-medium text-slate-800">{{ opt.criteria_name }}</span>
              <span class="block truncate text-[11px] text-slate-400">{{ opt.criteria_code }} · {{ opt.category }}</span>
            </span>
            <AppIcon
              name="plus"
              :size="14"
              class="text-brand"
            />
          </button>
          <p
            v-if="!filteredCriteria.length"
            class="px-3 py-2 text-center text-xs text-slate-400"
          >
            Không tìm thấy tiêu chí.
          </p>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-4 py-2 font-semibold">
                  Tiêu chí
                </th>
                <th class="w-24 px-3 py-2 font-semibold">
                  Trọng số
                </th>
                <th class="px-3 py-2 font-semibold">
                  Điểm yêu cầu
                </th>
                <th class="w-28 px-3 py-2 font-semibold">
                  Tính tổng
                </th>
                <th class="w-12 px-2 py-2" />
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, idx) in form.criteria"
                :key="`${line.criterion_id}-${idx}`"
                class="border-t border-slate-100"
              >
                <td class="px-4 py-2">
                  <p class="font-medium text-slate-800">
                    {{ criterionLabel(line.criterion_id) }}
                  </p>
                  <p class="text-[11px] text-slate-400">
                    {{ criterionCategory(line.criterion_id) }}
                  </p>
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model.number="line.weight"
                    type="number"
                    min="0"
                    step="0.5"
                    class="input h-8 w-20 text-sm"
                  >
                </td>
                <td class="px-3 py-2">
                  <input
                    v-model="line.required_score_label"
                    type="text"
                    class="input h-8 w-full min-w-[8rem] text-sm"
                    placeholder="Điểm yêu cầu…"
                  >
                </td>
                <td class="px-3 py-2">
                  <label class="inline-flex items-center gap-1.5 text-xs text-slate-600">
                    <input
                      v-model="line.include_in_total"
                      type="checkbox"
                      class="rounded border-slate-300 text-brand focus:ring-brand"
                    >
                    Có
                  </label>
                </td>
                <td class="px-2 py-2 text-center">
                  <button
                    type="button"
                    class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600"
                    @click="removeCriterion(idx)"
                  >
                    <AppIcon
                      name="trash"
                      :size="14"
                    />
                  </button>
                </td>
              </tr>
              <tr v-if="!form.criteria.length">
                <td
                  colspan="5"
                  class="px-4 py-8 text-center text-sm text-slate-400"
                >
                  Chưa gắn tiêu chí — tìm và thêm phía trên.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-9 text-sm"
          @click="close"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary h-9 text-sm disabled:opacity-50"
          :disabled="form.processing"
        >
          {{ isEdit ? 'Lưu thay đổi' : 'Tạo mẫu' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
