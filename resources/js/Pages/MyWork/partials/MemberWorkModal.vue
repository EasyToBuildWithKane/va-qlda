<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import { useToast } from '@/shared/composables/useToast';
import { useMyWork } from '@/composables/useMyWork';
import { exportMyWorkTasks } from '@/composables/useMyWorkExport';

/**
 * Modal "Xem nhanh" việc của một thành viên (chế độ nhóm) — bản wide, đọc dữ liệu
 * qua JSON (route my-work.member) để không rời trang. Có nút mở trang đầy đủ +
 * xuất Excel danh sách việc của thành viên.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    member: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const toast = useToast();
const { goTo } = useMyWork();

const loading = ref(false);
const error = ref('');
const summary = ref(null);
const buckets = ref(null);
const resolvedMember = ref(null);

const BUCKET_META = [
    { key: 'overdue', label: 'Quá hạn', icon: 'alert', tone: 'rose' },
    { key: 'today', label: 'Hôm nay', icon: 'calendar-clock', tone: 'amber' },
    { key: 'upcoming', label: 'Sắp tới', icon: 'clock', tone: 'sky' },
    { key: 'no_due', label: 'Chưa có hạn', icon: 'task', tone: 'slate' },
];

const toneDot = {
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    sky: 'bg-sky-500',
    slate: 'bg-slate-400',
};

const headerMember = computed(() => resolvedMember.value ?? props.member);

const kpis = computed(() => {
    const s = summary.value ?? {};
    return [
        { label: 'Đang mở', value: s.open ?? 0, tone: 'text-slate-800' },
        { label: 'Quá hạn', value: s.overdue ?? 0, tone: 'text-rose-600' },
        { label: 'Hôm nay', value: s.dueToday ?? 0, tone: 'text-amber-700' },
        { label: 'Đang làm', value: s.inProgress ?? 0, tone: 'text-sky-700' },
        { label: 'Giờ hôm nay', value: s.hoursLoggedToday ?? 0, tone: 'text-emerald-600' },
    ];
});

const totalTasks = computed(() => {
    if (!buckets.value) return 0;
    return BUCKET_META.reduce((acc, b) => acc + (buckets.value[b.key]?.length ?? 0), 0);
});

function bucketTasks(key) {
    return buckets.value?.[key] ?? [];
}

function fmtDate(value) {
    if (!value) return null;
    return new Date(value + 'T00:00:00').toLocaleDateString('vi-VN', {
        day: '2-digit', month: '2-digit', year: 'numeric',
    });
}

async function load() {
    if (!props.member?.id) return;
    loading.value = true;
    error.value = '';
    summary.value = null;
    buckets.value = null;
    resolvedMember.value = null;
    try {
        const { data } = await axios.get(route('my-work.member', props.member.id));
        summary.value = data.summary;
        buckets.value = data.buckets;
        resolvedMember.value = data.member;
    } catch {
        error.value = 'Không tải được dữ liệu công việc của thành viên này.';
    } finally {
        loading.value = false;
    }
}

watch(() => props.open, (v) => {
    if (v) load();
});

function openFullPage() {
    if (!props.member?.id) return;
    emit('close');
    goTo({ member: props.member.id });
}

function exportExcel() {
    if (!buckets.value || totalTasks.value === 0) {
        toast.error('Không có việc để xuất.');
        return;
    }
    exportMyWorkTasks({
        buckets: buckets.value,
        ownerName: headerMember.value?.name ?? 'Thanh vien',
        summary: summary.value,
    });
    toast.success('Đã xuất Excel.');
}
</script>

<template>
  <Modal
    :show="open"
    max-width="max-w-5xl"
    @close="emit('close')"
  >
    <!-- Header tuỳ biến (thay cho title mặc định) -->
    <template #default>
      <div class="-mt-2 mb-4 flex flex-wrap items-center gap-3">
        <Avatar
          :name="headerMember?.name"
          :src="headerMember?.avatar_path"
          :size="44"
        />
        <div class="min-w-0 flex-1">
          <h2 class="truncate font-display text-lg font-semibold text-slate-800">
            {{ headerMember?.name ?? 'Thành viên' }}
          </h2>
          <p class="truncate text-[13px] text-slate-500">
            {{ headerMember?.role_title || 'Thành viên nhóm' }} · Chi tiết công việc
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs font-medium"
            :disabled="loading"
            @click="exportExcel"
          >
            <AppIcon
              name="download"
              :size="15"
            />
            Xuất Excel
          </button>
          <button
            type="button"
            class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs font-medium"
            @click="openFullPage"
          >
            Mở trang đầy đủ
            <AppIcon
              name="external-link"
              :size="14"
            />
          </button>
        </div>
      </div>

      <!-- KPI strip -->
      <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-5">
        <div
          v-for="k in kpis"
          :key="k.label"
          class="rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2.5 text-center dark:border-slate-700 dark:bg-slate-800/40"
        >
          <p
            class="text-xl font-bold tabular-nums"
            :class="k.tone"
          >
            {{ k.value }}
          </p>
          <p class="mt-0.5 text-[11px] font-medium text-slate-500">
            {{ k.label }}
          </p>
        </div>
      </div>

      <!-- Loading / error / empty -->
      <div
        v-if="loading"
        class="grid place-items-center py-16 text-sm text-slate-400"
      >
        <AppIcon
          name="refresh"
          :size="28"
          class="mb-2 animate-spin"
        />
        Đang tải công việc…
      </div>
      <div
        v-else-if="error"
        class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-8 text-center text-sm text-rose-600"
      >
        {{ error }}
      </div>
      <div
        v-else-if="totalTasks === 0"
        class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-4 py-12 text-center"
      >
        <AppIcon
          name="check-circle"
          :size="30"
          class="mx-auto mb-2 text-emerald-400"
        />
        <p class="text-sm font-medium text-slate-600">
          Thành viên này không có việc nào đang chờ.
        </p>
      </div>

      <!-- Buckets — 2 cột cho bố cục wide -->
      <div
        v-else
        class="grid max-h-[58vh] grid-cols-1 gap-4 overflow-y-auto pr-1 lg:grid-cols-2"
      >
        <section
          v-for="b in BUCKET_META"
          v-show="bucketTasks(b.key).length > 0"
          :key="b.key"
          class="rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
        >
          <header class="flex items-center gap-2 border-b border-slate-100 px-3.5 py-2.5 dark:border-slate-800">
            <span
              class="h-2.5 w-2.5 shrink-0 rounded-full"
              :class="toneDot[b.tone]"
            />
            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ b.label }}</span>
            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-800">
              {{ bucketTasks(b.key).length }}
            </span>
          </header>
          <ul class="divide-y divide-slate-100 dark:divide-slate-800">
            <li
              v-for="t in bucketTasks(b.key)"
              :key="t.id"
              class="px-3.5 py-2.5"
            >
              <div class="flex items-start gap-2">
                <span
                  class="mt-1 h-2 w-2 shrink-0 rounded-full"
                  :style="{ backgroundColor: t.project?.color || '#94a3b8' }"
                />
                <div class="min-w-0 flex-1">
                  <p
                    v-if="t.project"
                    class="truncate text-[11px] font-semibold text-brand"
                  >
                    {{ [t.project.code, t.project.name].filter(Boolean).join(' · ') }}
                  </p>
                  <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                    {{ t.title }}
                  </p>
                  <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    <Badge
                      v-if="t.priority"
                      :label="t.priority.label"
                      :color="t.priority.color"
                    />
                    <Badge
                      v-if="t.status"
                      :label="t.status.label"
                      :color="t.status.color"
                    />
                    <span
                      v-if="fmtDate(t.due_date)"
                      class="inline-flex items-center gap-1 text-[11px]"
                      :class="t.is_late ? 'font-semibold text-rose-600' : 'text-slate-500'"
                    >
                      <AppIcon
                        name="clock"
                        :size="11"
                      />
                      {{ fmtDate(t.due_date) }}
                    </span>
                  </div>
                </div>
              </div>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </Modal>
</template>
