<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import CoachingMaterialEmbed from '@/modules/coaching/components/CoachingMaterialEmbed.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    sessionId: { type: Number, required: true },
    materials: { type: Array, default: () => [] },
    materialTypes: { type: Array, default: () => [] },
    isEditing: { type: Boolean, default: false },
});

const TYPE_ICON = {
    youtube: 'link',
    loom: 'link',
    canva: 'documents',
    google_docs: 'documents',
    gdrive: 'documents',
    pdf: 'documents',
    pptx: 'documents',
    file: 'download',
};

function iconForType(type) {
    return TYPE_ICON[type] ?? 'link';
}

const selectedId = ref(null);
const showAddForm = ref(false);

watch(
    () => props.materials,
    (list) => {
        const ids = list.map((m) => m.id);
        if (!ids.length) {
            selectedId.value = null;
            return;
        }
        if (selectedId.value == null || !ids.includes(selectedId.value)) {
            selectedId.value = ids[0];
        }
    },
    { immediate: true, deep: true },
);

const selectedMaterial = computed(
    () => props.materials.find((m) => m.id === selectedId.value) ?? null,
);

const materialForm = useForm({ type: 'youtube', title: '', url: '', file: null });

function openAddForm() {
    materialForm.clearErrors();
    showAddForm.value = true;
}

function closeAddForm() {
    showAddForm.value = false;
    materialForm.reset();
    materialForm.clearErrors();
}

function onFileChange(event) {
    materialForm.file = event.target.files?.[0] ?? null;
}

function submitMaterial() {
    materialForm.post(`/coaching/sessions/${props.sessionId}/materials`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            materialForm.reset();
            showAddForm.value = false;
        },
    });
}

function selectMaterial(id) {
    selectedId.value = id;
}

function externalHref(m) {
    if (m?.url) return m.url;
    if (m?.file_url) return m.file_url;
    return null;
}
</script>

<template>
  <div
    class="flex min-h-[calc(100dvh-15rem)] flex-1 flex-col gap-4"
    v-bind="$attrs"
  >
    <div
      v-if="isEditing"
      class="flex flex-wrap items-center justify-between gap-2"
    >
      <p class="text-xs text-slate-500">
        Thêm link hoặc tệp đính kèm cho buổi học.
      </p>
      <button
        v-if="!showAddForm"
        type="button"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-sm"
        @click="openAddForm"
      >
        <AppIcon
          name="add"
          :size="15"
        />
        Thêm tài liệu
      </button>
    </div>

    <form
      v-if="isEditing && showAddForm"
      class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5"
      @submit.prevent="submitMaterial"
    >
      <div class="mb-4 flex items-start justify-between gap-3">
        <div>
          <p class="text-sm font-semibold text-slate-800">
            Thêm tài liệu mới
          </p>
          <p class="mt-0.5 text-xs text-slate-500">
            YouTube, Canva, Loom, Google Docs/Drive hoặc tệp PDF, PPTX.
          </p>
        </div>
        <button
          type="button"
          class="btn-ghost inline-flex h-8 shrink-0 items-center gap-1 px-2 text-xs"
          @click="closeAddForm"
        >
          <AppIcon
            name="close"
            :size="14"
          />
          Đóng
        </button>
      </div>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="label">Loại</label>
          <select
            v-model="materialForm.type"
            class="input h-10 w-full text-sm"
          >
            <option
              v-for="mt in materialTypes"
              :key="mt.value"
              :value="mt.value"
            >
              {{ mt.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="label">Tiêu đề</label>
          <input
            v-model="materialForm.title"
            class="input h-10 w-full"
            placeholder="Tiêu đề hiển thị"
            required
          >
        </div>
      </div>
      <div class="mt-3">
        <label class="label">URL (tuỳ chọn)</label>
        <input
          v-model="materialForm.url"
          class="input h-10 w-full"
          placeholder="https://…"
        >
      </div>
      <div class="mt-3">
        <label class="label">Hoặc tải tệp lên</label>
        <input
          type="file"
          class="input w-full text-sm"
          @change="onFileChange"
        >
      </div>
      <p
        v-if="materialForm.errors.url"
        class="mt-2 text-xs text-danger"
      >
        {{ materialForm.errors.url }}
      </p>
      <div class="mt-4 flex justify-end gap-2">
        <button
          type="button"
          class="btn-ghost h-9 px-3 text-sm"
          @click="closeAddForm"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary h-9 gap-1.5 px-4 text-sm"
          :disabled="materialForm.processing"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Lưu tài liệu
        </button>
      </div>
    </form>

    <div
      v-if="!materials.length"
      class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-16 text-center"
    >
      <AppIcon
        name="link"
        :size="36"
        class="text-slate-300"
      />
      <p class="mt-3 text-sm font-medium text-slate-600">
        Chưa có tài liệu
      </p>
      <p class="mt-1 max-w-sm text-xs text-slate-500">
        <template v-if="isEditing">
          Bấm «Thêm tài liệu» để gắn video, slide hoặc file cho buổi học.
        </template>
        <template v-else>
          Coach sẽ đính kèm tài liệu tại đây.
        </template>
      </p>
    </div>

    <div
      v-else
      class="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row lg:items-stretch"
    >
      <aside
        class="shrink-0 lg:w-72 xl:w-80"
        aria-label="Danh sách tài liệu"
      >
        <p class="mb-2 px-0.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
          Danh sách · {{ materials.length }}
        </p>
        <ul
          class="max-h-[min(40vh,16rem)] space-y-1.5 overflow-y-auto rounded-xl border border-slate-100 bg-slate-50/50 p-1.5 lg:max-h-none lg:flex-1"
          role="list"
        >
          <li
            v-for="m in materials"
            :key="m.id"
          >
            <button
              type="button"
              class="flex w-full items-start gap-2.5 rounded-lg border px-3 py-2.5 text-left transition-colors"
              :class="selectedId === m.id
                ? 'border-brand/30 bg-white shadow-sm ring-1 ring-brand/15'
                : 'border-transparent bg-transparent hover:bg-white hover:shadow-sm'"
              @click="selectMaterial(m.id)"
            >
              <span
                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ring-1"
                :class="selectedId === m.id
                  ? 'bg-brand/10 text-brand ring-brand/20'
                  : 'bg-white text-slate-500 ring-slate-200'"
              >
                <AppIcon
                  :name="iconForType(m.type)"
                  :size="15"
                />
              </span>
              <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-semibold text-slate-800">
                  {{ m.title }}
                </span>
                <span class="mt-0.5 block text-[11px] font-medium text-slate-500">
                  {{ m.type_label }}
                </span>
              </span>
            </button>
          </li>
        </ul>
      </aside>

      <section
        v-if="selectedMaterial"
        class="flex min-h-[min(50vh,28rem)] min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm lg:min-h-[calc(100dvh-18rem)]"
        aria-label="Xem trước tài liệu"
      >
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-4 py-3">
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-slate-900">
              {{ selectedMaterial.title }}
            </p>
            <p class="text-[11px] font-medium text-slate-500">
              {{ selectedMaterial.type_label }}
            </p>
          </div>
          <a
            v-if="externalHref(selectedMaterial)"
            :href="externalHref(selectedMaterial)"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-btn border border-slate-200 px-3 text-xs font-semibold text-brand hover:bg-brand-50/50"
          >
            <AppIcon
              name="external-link"
              :size="14"
            />
            {{ selectedMaterial.file_url && !selectedMaterial.url ? 'Tải file' : 'Mở liên kết' }}
          </a>
        </div>
        <div class="flex min-h-0 flex-1 flex-col p-4">
          <CoachingMaterialEmbed
            v-if="selectedMaterial.embedAllowed && selectedMaterial.embedSrc"
            class="min-h-0 flex-1"
            :url="selectedMaterial.url"
            :embed-src="selectedMaterial.embedSrc"
            :title="selectedMaterial.title"
            tall
            hide-external-link
          />
          <div
            v-else
            class="flex flex-1 flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-6 py-12 text-center"
          >
            <AppIcon
              :name="iconForType(selectedMaterial.type)"
              :size="32"
              class="text-slate-300"
            />
            <p class="mt-3 text-sm font-medium text-slate-600">
              Không xem trước được trong trang
            </p>
            <p class="mt-1 max-w-xs text-xs text-slate-500">
              Mở liên kết hoặc tải file để xem nội dung.
            </p>
            <a
              v-if="externalHref(selectedMaterial)"
              :href="externalHref(selectedMaterial)"
              target="_blank"
              rel="noopener noreferrer"
              class="btn-primary mt-4 inline-flex h-9 items-center gap-1.5 px-4 text-sm"
            >
              <AppIcon
                name="external-link"
                :size="15"
              />
              {{ selectedMaterial.file_url && !selectedMaterial.url ? 'Tải file' : 'Mở trong tab mới' }}
            </a>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
