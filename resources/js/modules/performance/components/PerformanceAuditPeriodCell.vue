<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

const props = defineProps({
    row: { type: Object, required: true },
    filterLabel: { type: String, default: '' },
});

const rootRef = ref(null);
const menuOpen = ref(false);
const collapsedBucketKeys = ref(new Set());

const periodTitle = computed(() =>
    displayOrEmpty(props.row.periodLabel || props.filterLabel, EMPTY_LABELS.period),
);

const buckets = computed(() => (Array.isArray(props.row.periodBuckets) ? props.row.periodBuckets : []));

const hasBuckets = computed(() => buckets.value.length > 0);

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
    if (!menuOpen.value) {
        collapsedBucketKeys.value = new Set();
    }
}

function closeMenu() {
    menuOpen.value = false;
}

function onDocMouseDown(e) {
    if (!menuOpen.value) return;
    if (rootRef.value?.contains(e.target)) return;
    closeMenu();
}

onMounted(() => document.addEventListener('mousedown', onDocMouseDown));
onBeforeUnmount(() => document.removeEventListener('mousedown', onDocMouseDown));

function isBucketOpen(key) {
    return !collapsedBucketKeys.value.has(key);
}

function toggleBucket(key) {
    const next = new Set(collapsedBucketKeys.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    collapsedBucketKeys.value = next;
}

function bucketStatusText(bucket) {
    const committed = bucket.committed ?? 0;
    if (committed <= 0) return 'Chưa có cam kết trong kỳ con';
    return `Hoàn thành ${bucket.done}/${committed} · ${bucket.commitmentRate ?? 0}%`;
}

function bucketGradeLabel(bucket) {
    const committed = bucket.committed ?? 0;
    if (committed <= 0) return EMPTY_LABELS.gradeNoCommitment;
    return displayOrEmpty(bucket.grade, EMPTY_LABELS.grade);
}
</script>

<template>
  <div
    ref="rootRef"
    class="relative min-w-[9rem]"
  >
    <button
      type="button"
      class="inline-flex max-w-full items-center gap-1 rounded-md border border-slate-200/80 bg-white px-2 py-1 text-left text-xs text-slate-700 shadow-sm hover:border-brand/30 hover:bg-slate-50"
      :aria-expanded="menuOpen"
      aria-haspopup="true"
      @click.stop="toggleMenu"
    >
      <span class="min-w-0 truncate font-medium">{{ periodTitle }}</span>
      <AppIcon
        name="chevron-down"
        :size="14"
        class="shrink-0 text-slate-400 transition-transform"
        :class="menuOpen ? 'rotate-180' : ''"
      />
    </button>

    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      leave-active-class="transition duration-100 ease-in"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="menuOpen"
        class="absolute left-0 top-full z-30 mt-1 w-72 max-w-[min(18rem,calc(100vw-2rem))] origin-top-left overflow-hidden rounded-xl border border-slate-200 bg-white shadow-elevation-2"
        role="menu"
        @click.stop
      >
        <div class="border-b border-slate-100 px-3 py-2">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-brand/80">
            Kỳ audit
          </p>
          <p class="truncate text-sm font-medium text-slate-800">
            {{ periodTitle }}
          </p>
        </div>

        <div
          v-if="hasBuckets"
          class="max-h-64 overflow-y-auto p-1"
        >
          <div
            v-for="bucket in buckets"
            :key="bucket.key"
            class="rounded-lg border border-transparent"
          >
            <button
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left hover:bg-slate-50"
              :aria-expanded="isBucketOpen(bucket.key)"
              @click.stop="toggleBucket(bucket.key)"
            >
              <AppIcon
                name="chevron-down"
                :size="14"
                class="shrink-0 text-slate-400 transition-transform"
                :class="isBucketOpen(bucket.key) ? '' : '-rotate-90'"
              />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-xs font-semibold text-slate-700">{{ bucket.label }}</span>
                <span
                  v-if="bucket.range"
                  class="block truncate text-[10px] text-slate-400"
                >{{ bucket.range }}</span>
              </span>
            </button>
            <div
              v-if="isBucketOpen(bucket.key)"
              class="border-t border-slate-100 px-3 pb-2 pt-1.5 text-[11px] leading-snug text-slate-600"
            >
              <p>{{ bucketStatusText(bucket) }}</p>
              <p class="mt-0.5 text-slate-500">
                Xếp loại: {{ bucketGradeLabel(bucket) }}
              </p>
            </div>
          </div>
        </div>
        <p
          v-else
          class="px-3 py-4 text-center text-xs text-slate-400"
        >
          Chưa có dữ liệu theo kỳ con.
        </p>
      </div>
    </Transition>
  </div>
</template>
