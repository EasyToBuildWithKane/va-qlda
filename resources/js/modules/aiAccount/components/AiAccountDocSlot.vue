<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import FilterDatePicker from '@/shared/ui/FilterDatePicker.vue';

const props = defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
    dateLabel: { type: String, required: true },
    dateValue: { type: String, default: '' },
    datePlaceholder: { type: String, default: 'Chọn ngày' },
    existingDoc: { type: Object, default: null },
    pendingFile: { type: File, default: null },
    confirmLabel: { type: String, default: '' },
    confirmed: { type: Boolean, default: false },
    /** ISO yyyy-MM-dd — ngày tối thiểu (vd. sau ngày gửi đề xuất) */
    minDate: { type: String, default: null },
});

const emit = defineEmits(['update:dateValue', 'file-change', 'confirm', 'clear-file']);

const inputRef = ref(null);

const fileName = computed(() => {
    if (props.pendingFile?.name) return props.pendingFile.name;
    return props.existingDoc?.original_name || null;
});

const hasFile = computed(() => !!fileName.value);

function onPick(e) {
    const file = e.target.files?.[0] || null;
    emit('file-change', file);
    if (inputRef.value) inputRef.value.value = '';
}

function openPicker() {
    inputRef.value?.click();
}

function formatSize(bytes) {
    if (!bytes || !Number.isFinite(bytes)) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
  <div class="rounded-xl border border-slate-200/90 bg-white p-3 shadow-sm sm:p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="text-sm font-semibold text-slate-800">
          {{ title }}
        </h3>
        <p
          v-if="hint"
          class="mt-0.5 text-[11px] leading-relaxed text-slate-500"
        >
          {{ hint }}
        </p>
      </div>
      <button
        v-if="confirmLabel"
        type="button"
        class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-btn border px-2.5 text-[11px] font-semibold transition"
        :class="confirmed
          ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
          : 'border-brand/30 bg-brand/5 text-brand hover:bg-brand/10'"
        @click="emit('confirm')"
      >
        <AppIcon
          :name="confirmed ? 'check' : 'calendar'"
          :size="13"
        />
        {{ confirmed ? 'Đã ghi nhận hôm nay' : confirmLabel }}
      </button>
    </div>

    <label class="mb-3 block">
      <span class="mb-1 block text-xs font-medium text-slate-600">{{ dateLabel }}</span>
      <FilterDatePicker
        :model-value="dateValue"
        :placeholder="datePlaceholder"
        :min-date="minDate"
        @update:model-value="emit('update:dateValue', $event)"
      />
    </label>

    <div>
      <span class="mb-1.5 block text-xs font-medium text-slate-600">
        File đính kèm
        <span class="font-normal text-slate-400">(1 file · PDF / ảnh / Word / Excel)</span>
      </span>

      <div
        v-if="hasFile"
        class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2.5"
      >
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white ring-1 ring-slate-200">
          <AppIcon
            name="documents"
            :size="18"
            class="text-brand"
          />
        </div>
        <div class="min-w-0 flex-1">
          <a
            v-if="existingDoc?.url && !pendingFile"
            :href="existingDoc.url"
            target="_blank"
            rel="noopener"
            class="block truncate text-sm font-medium text-brand underline-offset-2 hover:underline"
          >{{ fileName }}</a>
          <p
            v-else
            class="truncate text-sm font-medium text-slate-800"
          >
            {{ fileName }}
          </p>
          <p class="text-[11px] text-slate-500">
            <template v-if="pendingFile">
              Mới chọn · {{ formatSize(pendingFile.size) }}
            </template>
            <template v-else-if="existingDoc?.size">
              Đã lưu · {{ formatSize(existingDoc.size) }}
            </template>
            <template v-else>
              Đã lưu trên hệ thống
            </template>
          </p>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <button
            type="button"
            class="btn-ghost h-8 px-2 text-xs"
            @click="openPicker"
          >
            Đổi file
          </button>
          <button
            v-if="pendingFile"
            type="button"
            class="btn-ghost h-8 px-2 text-xs text-rose-600"
            @click="emit('clear-file')"
          >
            Bỏ
          </button>
        </div>
      </div>

      <button
        v-else
        type="button"
        class="group flex w-full flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-slate-300 bg-gradient-to-b from-slate-50/90 to-white px-4 py-6 text-center transition hover:border-brand/45 hover:from-brand/[0.04] hover:to-white"
        @click="openPicker"
      >
        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-slate-200 transition group-hover:ring-brand/30">
          <AppIcon
            name="upload"
            :size="18"
            class="text-slate-400 group-hover:text-brand"
          />
        </span>
        <span class="text-xs font-semibold text-slate-700">Kéo thả hoặc bấm để chọn file</span>
        <span class="text-[11px] text-slate-400">Tối đa 10 MB · gắn 1–1 với ngày ở trên</span>
      </button>

      <input
        ref="inputRef"
        type="file"
        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
        class="sr-only"
        @change="onPick"
      >
    </div>
  </div>
</template>
