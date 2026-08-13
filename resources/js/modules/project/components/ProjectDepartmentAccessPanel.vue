<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import SearchSelect from '@/shared/ui/SearchSelect.vue';
import { PROJECT_COLOR_SWATCH } from '@/modules/project/utils/projectColors';

const props = defineProps({
    departmentOptions: { type: Array, default: () => [] },
    ownerId: { type: [Number, String, null], default: null },
    relatedIds: { type: Array, default: () => [] },
    ownerError: { type: String, default: null },
    relatedError: { type: String, default: null },
});

const emit = defineEmits(['update:ownerId', 'update:relatedIds', 'touch-owner', 'touch-related']);

const ownerIdNum = computed(() => {
    if (props.ownerId === null || props.ownerId === '') return null;
    const n = Number(props.ownerId);
    return Number.isFinite(n) && n > 0 ? n : null;
});

const relatedIdSet = computed(() => new Set((props.relatedIds ?? []).map((id) => Number(id)).filter((id) => id > 0)));

const partnerOptions = computed(() =>
    props.departmentOptions.filter((d) => Number(d.id) !== ownerIdNum.value),
);

const selectedPartners = computed(() =>
    partnerOptions.value.filter((d) => relatedIdSet.value.has(Number(d.id))),
);

const availablePartners = computed(() =>
    partnerOptions.value.filter((d) => !relatedIdSet.value.has(Number(d.id))),
);

function setOwner(id) {
    emit('update:ownerId', id);
    emit('touch-owner');
    const nextOwner = id != null && id !== '' ? Number(id) : null;
    if (nextOwner && relatedIdSet.value.has(nextOwner)) {
        emit('update:relatedIds', (props.relatedIds ?? []).filter((x) => Number(x) !== nextOwner));
        emit('touch-related');
    }
}

function addRelated(id) {
    const n = Number(id);
    if (!n || n === ownerIdNum.value || relatedIdSet.value.has(n)) return;
    emit('update:relatedIds', [...(props.relatedIds ?? []), n]);
    emit('touch-related');
}

function removeRelated(id) {
    const n = Number(id);
    if (!n) return;
    emit('update:relatedIds', (props.relatedIds ?? []).filter((x) => Number(x) !== n));
    emit('touch-related');
}

function swatch(color) {
    return PROJECT_COLOR_SWATCH[color] || PROJECT_COLOR_SWATCH.slate;
}
</script>

<template>
  <div class="grid grid-cols-1 gap-5">
    <div>
      <label class="label">Phòng ban phụ trách</label>
      <SearchSelect
        :model-value="ownerId"
        :options="departmentOptions"
        placeholder="Tìm & chọn phòng ban…"
        search-placeholder="Tìm theo tên hoặc mã…"
        :search-keys="['name', 'code']"
        @update:model-value="setOwner"
      />
      <p
        v-if="ownerError"
        class="mt-1.5 flex items-center gap-1 text-xs text-danger"
      >
        <AppIcon
          name="close"
          :size="12"
        />
        {{ ownerError }}
      </p>
    </div>

    <div>
      <label class="label">Phòng ban liên đới</label>
      <SearchSelect
        :model-value="null"
        :options="availablePartners"
        placeholder="Tìm & chọn phòng ban…"
        search-placeholder="Tìm theo tên hoặc mã…"
        :search-keys="['name', 'code']"
        :disabled="!availablePartners.length"
        @update:model-value="addRelated"
      />
      <ul
        v-if="selectedPartners.length"
        class="mt-2.5 flex flex-wrap gap-2"
        aria-label="Phòng ban liên đới đã chọn"
      >
        <li
          v-for="d in selectedPartners"
          :key="d.id"
        >
          <span class="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-white py-1 pl-2.5 pr-1 text-sm text-slate-700 shadow-[0_1px_0_rgb(15_23_42_/_0.03)]">
            <span
              class="h-2 w-2 shrink-0 rounded-full"
              :class="swatch(d.color)"
            />
            <span class="truncate font-medium">{{ d.name }}</span>
            <button
              type="button"
              class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-slate-400 transition hover:bg-slate-100 hover:text-rose-600"
              :aria-label="`Bỏ ${d.name}`"
              @click="removeRelated(d.id)"
            >
              <AppIcon
                name="close"
                :size="12"
              />
            </button>
          </span>
        </li>
      </ul>
      <p
        v-if="relatedError"
        class="mt-1.5 flex items-center gap-1 text-xs text-danger"
      >
        <AppIcon
          name="close"
          :size="12"
        />
        {{ relatedError }}
      </p>
    </div>
  </div>
</template>
