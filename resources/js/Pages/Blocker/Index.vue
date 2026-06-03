<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import BlockerFormModal from '@/modules/project/components/BlockerFormModal.vue';
import { date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    blockers: { type: Object, required: true }, // { data: [...] }
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const dialog = useDialog();
const modal = ref(false);
const editing = ref(null);

const open = (b = null) => { editing.value = b; modal.value = true; };

const filterForm = reactive({
    status: props.filters.status ?? '',
    severity: props.filters.severity ?? '',
    project_id: props.filters.project_id ?? '',
    mine: props.filters.mine ? '1' : '',
});

const activeCount = computed(() =>
    Object.values(filterForm).filter((v) => v !== '' && v != null).length,
);

watch(filterForm, () => {
    router.get('/blockers', {
        status: filterForm.status || undefined,
        severity: filterForm.severity || undefined,
        project_id: filterForm.project_id || undefined,
        mine: filterForm.mine || undefined,
    }, { preserveState: true, replace: true });
});

const clearFilters = () => {
    filterForm.status = '';
    filterForm.severity = '';
    filterForm.project_id = '';
    filterForm.mine = '';
};

const resolve = (b) => router.put(`/blockers/${b.id}`, { status: 'resolved' }, { preserveScroll: true });
const remove = async (b) => {
    if (await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${b.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/blockers/${b.id}`, { preserveScroll: true });
};
</script>

<template>
  <Head title="Vướng mắc" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Vướng mắc cần xử lý"
        subtitle="Theo dõi và giải quyết các vướng mắc trong dự án"
        icon="blockers"
        icon-color="amber"
        :badge="summary.open ?? null"
      />
    </template>

    <!-- Toolbar: filters + primary action -->
    <div class="card mb-4 p-3">
      <div class="flex flex-wrap items-center gap-2">
        <div>
          <label class="mb-1 block text-[11px] font-medium text-slate-400">Trạng thái</label>
          <select
            v-model="filterForm.status"
            class="input w-40"
            title="Lọc theo trạng thái xử lý"
          >
            <option value="">
              Mọi trạng thái
            </option>
            <option
              v-for="o in options.status"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-[11px] font-medium text-slate-400">Mức độ</label>
          <select
            v-model="filterForm.severity"
            class="input w-40"
            title="Lọc theo mức độ nghiêm trọng"
          >
            <option value="">
              Mọi mức độ
            </option>
            <option
              v-for="o in options.severity"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-[11px] font-medium text-slate-400">Dự án</label>
          <select
            v-model="filterForm.project_id"
            class="input w-52"
            title="Lọc theo dự án"
          >
            <option value="">
              Mọi dự án
            </option>
            <option
              v-for="p in options.projects"
              :key="p.id"
              :value="p.id"
            >
              {{ p.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-[11px] font-medium text-slate-400">Phạm vi</label>
          <label
            class="flex h-10 items-center gap-2 rounded-input border border-slate-300 px-3 text-sm text-slate-600"
            title="Chỉ hiện vướng mắc do bạn phụ trách"
          >
            <input
              v-model="filterForm.mine"
              true-value="1"
              false-value=""
              type="checkbox"
              class="rounded"
            >
            Tôi xử lý
          </label>
        </div>

        <button
          v-if="activeCount"
          type="button"
          class="btn-ghost self-end text-sm text-slate-500"
          title="Bỏ tất cả bộ lọc"
          @click="clearFilters"
        >
          Xoá lọc
        </button>

        <button
          v-if="can.create"
          class="btn-primary ml-auto self-end gap-1.5"
          title="Ghi nhận một vướng mắc mới"
          @click="open()"
        >
          <AppIcon
            name="add"
            :size="16"
          /> Ghi nhận vướng mắc
        </button>
      </div>
    </div>

    <!-- List -->
    <div class="space-y-2.5">
      <div
        v-for="b in blockers.data"
        :key="b.id"
        class="card p-4 transition hover:border-slate-300"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <Badge
                :label="b.severity.label"
                :color="b.severity.color"
              />
              <Badge
                :label="b.status.label"
                :color="b.status.color"
              />
              <span
                v-if="b.project"
                class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500"
              >
                {{ b.project.name }}
              </span>
              <span class="font-medium text-slate-800">{{ b.title }}</span>
            </div>
            <p
              v-if="b.description"
              class="mt-1.5 line-clamp-2 text-sm text-slate-500"
            >
              {{ b.description }}
            </p>
            <p class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs text-slate-400">
              <AppIcon
                name="calendar"
                :size="13"
              />
              {{ date(b.raised_at) }}
              <span class="text-slate-300">·</span>
              Báo bởi {{ b.raised_by?.name || '—' }}
              <template v-if="b.owner">
                <span class="text-slate-300">·</span>
                Xử lý: {{ b.owner.name }}
              </template>
            </p>
          </div>
          <div class="flex shrink-0 items-center gap-1">
            <button
              v-if="b.can?.update && b.status.value !== 'resolved'"
              class="btn-ghost gap-1 text-xs text-emerald-600"
              title="Đánh dấu vướng mắc đã được xử lý"
              @click="resolve(b)"
            >
              <AppIcon
                name="done"
                :size="14"
              /> Đã xử lý
            </button>
            <button
              v-if="b.can?.update"
              class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700"
              title="Chỉnh sửa vướng mắc"
              @click="open(b)"
            >
              <AppIcon
                name="edit"
                :size="15"
              />
            </button>
            <button
              v-if="b.can?.delete"
              class="grid h-7 w-7 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600"
              title="Xoá vướng mắc"
              @click="remove(b)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </div>
        </div>
      </div>

      <p
        v-if="!blockers.data.length"
        class="rounded-card border border-dashed border-slate-200 py-16 text-center text-sm text-slate-400"
      >
        Không có vướng mắc nào.
      </p>
    </div>

    <BlockerFormModal
      :show="modal"
      :blocker="editing"
      :projects="options.projects"
      :employees="options.employees"
      :severity-options="options.severity"
      :status-options="options.status"
      @close="modal = false"
    />
  </AppLayout>
</template>
