<script setup>
/* eslint-disable vue/no-v-html -- Laravel pagination link labels */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    meta: { type: Object, default: null },
    perPage: { type: Number, required: true },
    perPageOptions: { type: Array, default: () => [5, 10, 15, 20] },
    colspan: { type: Number, default: 1 },
});

const emit = defineEmits(['update:perPage']);

const rangeLabel = computed(() => {
    const m = props.meta;
    if (!m?.total) return '0 bản ghi';
    const from = m.from ?? 0;
    const to = m.to ?? 0;
    return `${from}–${to} / ${m.total}`;
});
</script>

<template>
  <tr class="bg-slate-50/80">
    <td
      :colspan="colspan"
      class="px-4 py-3"
    >
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-slate-500">
          Hiển thị <span class="font-semibold text-slate-700">{{ rangeLabel }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-2">
          <label class="flex items-center gap-1.5 text-xs text-slate-600">
            <span class="shrink-0">Số dòng</span>
            <select
              :value="perPage"
              class="input h-8 min-w-[4.5rem] py-0 text-xs"
              @change="emit('update:perPage', Number($event.target.value))"
            >
              <option
                v-for="n in perPageOptions"
                :key="n"
                :value="n"
              >
                {{ n }}
              </option>
            </select>
          </label>

          <nav
            v-if="meta?.links?.length > 3"
            class="flex flex-wrap gap-1"
            aria-label="Phân trang"
          >
            <template
              v-for="(link, i) in meta.links"
              :key="i"
            >
              <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                class="inline-flex min-w-[2rem] items-center justify-center rounded-btn px-2 py-1 text-xs font-medium transition"
                :class="link.active
                  ? 'bg-brand text-white'
                  : 'text-slate-600 hover:bg-slate-100'"
              >
                <span v-html="link.label" />
              </Link>
              <span
                v-else
                class="inline-flex min-w-[2rem] items-center justify-center px-2 py-1 text-xs text-slate-300"
                v-html="link.label"
              />
            </template>
          </nav>
        </div>
      </div>
    </td>
  </tr>
</template>
