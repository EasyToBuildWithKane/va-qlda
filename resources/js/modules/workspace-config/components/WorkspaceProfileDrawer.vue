<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import Drawer from '@/Components/Ui/Drawer.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    show: { type: Boolean, default: false },
    workspace: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const dialog = useDialog();

const notesForm = useForm({
    notes: '',
});

watch(
    () => props.workspace,
    (ws) => {
        notesForm.notes = ws?.notes ?? '';
        notesForm.clearErrors();
    },
    { immediate: true },
);

const readiness = computed(() => props.workspace?.readiness ?? {
    key: 'empty',
    label: 'Chưa có nội dung',
    percent: 0,
    configured: 0,
    total: 0,
});

const checklist = computed(() => props.workspace?.checklist ?? []);
const actionableChecklist = computed(() => checklist.value.filter((s) => !s.planned));
const checklistDone = computed(() => actionableChecklist.value.filter((s) => s.done).length);

const statusClass = {
    active: 'bg-emerald-50 text-emerald-700 ring-emerald-200/80',
    draft: 'bg-amber-50 text-amber-800 ring-amber-200/80',
    missing: 'bg-slate-100 text-slate-600 ring-slate-200/80',
    archived: 'bg-rose-50 text-rose-700 ring-rose-200/80',
};

const saving = ref(false);

function saveNotes() {
    if (!props.workspace?.can_update) return;
    notesForm.patch(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}`,
        { preserveScroll: true },
    );
}

function ensureWorkspace() {
    if (!props.workspace) return;
    router.post(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}/ensure`,
        {},
        { preserveScroll: true },
    );
}

async function archiveWorkspace() {
    if (!props.workspace?.can_update) return;
    const ok = await dialog.confirm({
        title: 'Lưu trữ workspace?',
        message: `Workspace «${props.workspace.department_name}» sẽ chuyển sang trạng thái lưu trữ và ẩn khỏi hub mặc định.`,
        confirmText: 'Lưu trữ',
        cancelText: 'Huỷ',
        tone: 'danger',
    });
    if (!ok) return;
    saving.value = true;
    router.patch(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}`,
        { status: 'archived' },
        {
            preserveScroll: true,
            onFinish: () => { saving.value = false; },
        },
    );
}

async function restoreWorkspace() {
    if (!props.workspace?.can_update) return;
    saving.value = true;
    router.patch(
        `/workspace-config/w/${encodeURIComponent(props.workspace.department_code)}`,
        { status: 'active' },
        {
            preserveScroll: true,
            onFinish: () => { saving.value = false; },
        },
    );
}
</script>

<template>
  <Drawer
    :show="show && Boolean(workspace)"
    :title="workspace?.department_name || 'Workspace'"
    width="max-w-lg"
    @close="emit('close')"
  >
    <div
      v-if="workspace"
      class="space-y-5"
    >
      <div class="flex flex-wrap items-center gap-2">
        <span
          class="inline-flex items-center rounded-md px-2 py-1 text-[10px] font-semibold uppercase tracking-wide ring-1"
          :class="statusClass[workspace.status] ?? statusClass.missing"
        >
          {{ workspace.status_label }}
        </span>
        <span class="inline-flex items-center rounded-md bg-sky-50 px-2 py-1 text-[10px] font-semibold text-sky-700 ring-1 ring-sky-200/80">
          {{ readiness.label }} · {{ readiness.percent }}%
        </span>
        <span class="font-mono text-[11px] text-slate-400">
          {{ workspace.department_code }}
        </span>
      </div>

      <div>
        <div class="mb-1.5 flex items-center justify-between text-[11px]">
          <span class="font-medium text-slate-500">Mức hoàn thiện</span>
          <span class="tabular-nums text-slate-400">
            {{ readiness.configured }}/{{ readiness.total }} module
          </span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
          <div
            class="h-full rounded-full bg-brand transition-all"
            :style="{ width: `${readiness.percent}%` }"
          />
        </div>
      </div>

      <dl class="grid grid-cols-3 gap-2 text-[11px]">
        <div class="rounded-xl bg-slate-50 px-2.5 py-2 ring-1 ring-slate-100">
          <dt class="text-slate-400">
            Tiêu chí PB
          </dt>
          <dd class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800">
            {{ workspace.criteria_count }}
          </dd>
        </div>
        <div class="rounded-xl bg-slate-50 px-2.5 py-2 ring-1 ring-slate-100">
          <dt class="text-slate-400">
            Module
          </dt>
          <dd class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800">
            {{ workspace.modules_configured }}/{{ workspace.modules_live }}
          </dd>
        </div>
        <div class="rounded-xl bg-slate-50 px-2.5 py-2 ring-1 ring-slate-100">
          <dt class="text-slate-400">
            Checklist
          </dt>
          <dd class="mt-0.5 font-display text-base font-semibold tabular-nums text-slate-800">
            {{ checklistDone }}/{{ actionableChecklist.length }}
          </dd>
        </div>
      </dl>

      <div>
        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
          Checklist onboard
        </h3>
        <ul class="mt-2 space-y-1.5">
          <li
            v-for="step in checklist"
            :key="step.key"
            class="flex items-start gap-2 rounded-lg px-2 py-1.5 text-sm"
            :class="step.planned ? 'text-slate-400' : 'text-slate-700'"
          >
            <AppIcon
              :name="step.done ? 'done' : (step.planned ? 'calendar' : 'system-config')"
              :size="14"
              class="mt-0.5 shrink-0"
              :class="step.done ? 'text-emerald-600' : 'text-slate-400'"
            />
            <div class="min-w-0">
              <p :class="step.done ? 'font-medium' : ''">
                {{ step.label }}
              </p>
              <p class="text-[11px] text-slate-400">
                {{ step.done_hint }}
              </p>
            </div>
          </li>
        </ul>
      </div>

      <div v-if="canManage">
        <label class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
          Ghi chú nội bộ
        </label>
        <textarea
          v-model="notesForm.notes"
          rows="3"
          class="input mt-1.5 w-full text-sm"
          :disabled="!workspace.can_update || notesForm.processing"
          placeholder="Ghi chú cấu hình, lưu ý onboard…"
        />
        <p
          v-if="notesForm.errors.notes"
          class="mt-1 text-xs text-rose-600"
        >
          {{ notesForm.errors.notes }}
        </p>
        <div class="mt-2 flex flex-wrap gap-2">
          <button
            v-if="workspace.can_update"
            type="button"
            class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
            :disabled="notesForm.processing"
            @click="saveNotes"
          >
            <AppIcon
              name="documents"
              :size="15"
            />
            Lưu ghi chú
          </button>
          <button
            v-if="workspace.can_ensure"
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
            @click="ensureWorkspace"
          >
            <AppIcon
              name="plus"
              :size="15"
            />
            Kích hoạt
          </button>
          <button
            v-if="workspace.can_update && workspace.status !== 'archived'"
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs text-rose-700"
            :disabled="saving"
            @click="archiveWorkspace"
          >
            Lưu trữ
          </button>
          <button
            v-if="workspace.can_update && workspace.status === 'archived'"
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
            :disabled="saving"
            @click="restoreWorkspace"
          >
            Khôi phục
          </button>
        </div>
        <p
          v-if="!workspace.can_update && workspace.can_ensure"
          class="mt-2 text-[11px] text-slate-400"
        >
          Kích hoạt workspace trước để lưu ghi chú hoặc lưu trữ.
        </p>
      </div>
    </div>

    <template
      v-if="workspace"
      #footer
    >
      <div class="flex flex-wrap gap-2">
        <Link
          :href="workspace.href"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="system-config"
            :size="15"
          />
          Mở workspace
        </Link>
        <Link
          v-if="workspace.evaluation_href"
          :href="workspace.evaluation_href"
          class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="award"
            :size="15"
          />
          Tiêu chí
        </Link>
      </div>
    </template>
  </Drawer>
</template>
