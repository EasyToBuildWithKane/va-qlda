<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    contractId: { type: Number, required: true },
    attachments: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] }, // [{value,label,color}]
    canManage: { type: Boolean, default: false },
});

const dialog = useDialog();
const toast = useToast();

const mode = ref('file'); // file | link
const form = useForm({
    category: 'contract',
    title: '',
    notes: '',
    external_url: '',
    files: [],
});

function onFiles(e) {
    form.files = Array.from(e.target.files || []);
}

function submit() {
    if (mode.value === 'file' && !form.files.length) {
        toast.error('Hãy chọn ít nhất một file.');
        return;
    }
    if (mode.value === 'link' && !form.external_url) {
        toast.error('Hãy nhập link hồ sơ.');
        return;
    }
    form.post(`/contracts/${props.contractId}/attachments`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('title', 'notes', 'external_url', 'files');
            router.reload({ only: ['contract'] });
        },
    });
}

async function remove(a) {
    const ok = await dialog.confirm({
        title: 'Xoá hồ sơ?',
        message: `"${a.original_name}" sẽ bị xoá.`,
        confirmText: 'Xoá',
        tone: 'danger',
    });
    if (!ok) return;
    router.delete(`/contracts/${props.contractId}/attachments/${a.id}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['contract'] }),
    });
}

// Nhóm theo loại; trong mỗi loại, hiện version cao nhất là chính.
const grouped = computed(() => {
    const map = new Map();
    for (const a of props.attachments) {
        const key = a.category?.value ?? 'contract';
        if (!map.has(key)) map.set(key, { category: a.category, items: [] });
        map.get(key).items.push(a);
    }
    return [...map.values()];
});
</script>

<template>
  <div class="space-y-4">
    <!-- Upload form -->
    <div
      v-if="canManage"
      class="rounded-lg border border-slate-200 p-3"
    >
      <div class="mb-3 flex items-center gap-1 rounded-md bg-slate-100 p-1 text-sm w-fit">
        <button
          type="button"
          class="rounded px-3 py-1 font-medium"
          :class="mode === 'file' ? 'bg-white text-brand shadow-sm' : 'text-slate-500'"
          @click="mode = 'file'"
        >
          Tải file
        </button>
        <button
          type="button"
          class="rounded px-3 py-1 font-medium"
          :class="mode === 'link' ? 'bg-white text-brand shadow-sm' : 'text-slate-500'"
          @click="mode = 'link'"
        >
          Link ngoài
        </button>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="label">Loại hồ sơ</label>
          <select
            v-model="form.category"
            class="input"
          >
            <option
              v-for="c in categories"
              :key="c.value"
              :value="c.value"
            >
              {{ c.label }}
            </option>
          </select>
        </div>
        <div v-if="mode === 'file'">
          <label class="label">Chọn file (PDF, DOCX, XLSX…)</label>
          <input
            type="file"
            multiple
            class="input"
            @change="onFiles"
          >
        </div>
        <div v-else>
          <label class="label">Link (Google Drive, SharePoint…)</label>
          <input
            v-model="form.external_url"
            class="input"
            placeholder="https://…"
          >
        </div>
        <div class="sm:col-span-2">
          <label class="label">Ghi chú</label>
          <input
            v-model="form.notes"
            class="input"
          >
        </div>
      </div>
      <div class="mt-3 flex justify-end">
        <button
          type="button"
          class="btn-primary"
          :disabled="form.processing"
          @click="submit"
        >
          <AppIcon
            name="upload"
            :size="15"
          />
          {{ form.processing ? 'Đang lưu…' : 'Thêm hồ sơ' }}
        </button>
      </div>
    </div>

    <!-- Grouped list -->
    <div
      v-if="grouped.length"
      class="space-y-3"
    >
      <section
        v-for="g in grouped"
        :key="g.category?.value"
      >
        <h4 class="mb-1.5 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-400">
          <Badge
            :label="g.category?.label ?? 'Hồ sơ'"
            :color="g.category?.color ?? 'slate'"
          />
          <span>{{ g.items.length }} mục</span>
        </h4>
        <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200">
          <li
            v-for="a in g.items"
            :key="a.id"
            class="flex items-center gap-3 px-3 py-2"
          >
            <AppIcon
              :name="a.is_external ? 'link' : 'documents'"
              :size="16"
              class="shrink-0 text-slate-400"
            />
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm text-slate-700">
                {{ a.original_name }}
              </p>
              <p
                v-if="a.notes"
                class="truncate text-xs text-slate-400"
              >
                {{ a.notes }}
              </p>
            </div>
            <span
              v-if="a.version > 1"
              class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500"
            >v{{ a.version }}</span>
            <a
              v-if="a.url"
              :href="a.url"
              target="_blank"
              class="shrink-0 text-xs text-brand hover:underline"
            >Mở</a>
            <button
              v-if="canManage"
              type="button"
              class="grid h-7 w-7 shrink-0 place-items-center rounded text-slate-400 hover:bg-rose-50 hover:text-rose-600"
              @click="remove(a)"
            >
              <AppIcon
                name="delete"
                :size="14"
              />
            </button>
          </li>
        </ul>
      </section>
    </div>
    <p
      v-else
      class="rounded-lg border border-dashed border-slate-200 py-8 text-center text-sm text-slate-400"
    >
      Chưa có hồ sơ nào.
    </p>
  </div>
</template>
