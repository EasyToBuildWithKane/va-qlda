<script setup>
/* eslint-disable vue/no-v-html -- Laravel pagination link labels */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    meta: { type: Object, default: null },
    perPage: { type: Number, required: true },
    perPageOptions: { type: Array, default: () => [5, 10, 15, 20] },
    colspan: { type: Number, default: 1 },
    /** `table-row` = inside `<tfoot><tr>`; `bar` = standalone footer below table */
    variant: { type: String, default: 'table-row' },
    /** Client-side pagination: nav buttons emit page-change instead of Inertia Link */
    client: { type: Boolean, default: false },
});

const emit = defineEmits(['update:perPage', 'page-change']);

function onPageLink(link) {
    if (link.page != null) {
        emit('page-change', link.page);
    }
}

const rangeLabel = computed(() => {
    const m = props.meta;
    if (!m?.total) return 'Không có bản ghi';
    const from = m.from ?? 0;
    const to = m.to ?? 0;
    return `${from}–${to} trong ${m.total}`;
});

const isBar = computed(() => props.variant === 'bar');
</script>

<template>
  <tr
    v-if="!isBar"
    class="bg-slate-50/80"
  >
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
              <button
                v-if="client && link.page != null"
                type="button"
                class="inline-flex min-w-[2rem] items-center justify-center rounded-btn px-2 py-1 text-xs font-medium transition"
                :class="link.active
                  ? 'bg-brand text-white'
                  : 'text-slate-600 hover:bg-slate-100'"
                :aria-current="link.active ? 'page' : undefined"
                @click="onPageLink(link)"
              >
                <span v-html="link.label" />
              </button>
              <Link
                v-else-if="!client && link.url"
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

  <div
    v-else
    class="flex flex-col gap-3 border-t border-slate-100 bg-gradient-to-b from-slate-50/80 to-white px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
  >
    <p class="text-sm text-slate-500">
      <span class="font-medium text-slate-700">{{ rangeLabel }}</span>
      <span class="text-slate-400"> bản ghi</span>
    </p>

    <div class="flex flex-wrap items-center justify-end gap-3">
      <label class="inline-flex items-center gap-2 rounded-btn border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 shadow-sm">
        <span class="text-xs font-medium text-slate-500">Hiển thị</span>
        <select
          :value="perPage"
          class="h-7 min-w-[3.25rem] cursor-pointer border-0 bg-transparent py-0 pl-0 pr-6 text-sm font-semibold text-slate-800 focus:ring-0"
          aria-label="Số dòng mỗi trang"
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
        <span class="text-xs text-slate-500">/ trang</span>
      </label>

      <nav
        v-if="meta?.links?.length > 3"
        class="inline-flex items-center gap-0.5 rounded-btn border border-slate-200 bg-white p-0.5 shadow-sm"
        aria-label="Phân trang"
      >
        <template
          v-for="(link, i) in meta.links"
          :key="i"
        >
          <button
            v-if="client && link.page != null"
            type="button"
            class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2.5 text-sm font-medium transition"
            :class="link.active
              ? 'bg-brand text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-100'"
            :aria-current="link.active ? 'page' : undefined"
            @click="onPageLink(link)"
          >
            <span v-html="link.label" />
          </button>
          <Link
            v-else-if="!client && link.url"
            :href="link.url"
            preserve-scroll
            class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2.5 text-sm font-medium transition"
            :class="link.active
              ? 'bg-brand text-white shadow-sm'
              : 'text-slate-600 hover:bg-slate-100'"
            :aria-current="link.active ? 'page' : undefined"
          >
            <span v-html="link.label" />
          </Link>
          <span
            v-else
            class="inline-flex h-8 min-w-[2rem] items-center justify-center px-2.5 text-sm text-slate-300"
            v-html="link.label"
          />
        </template>
      </nav>
    </div>
  </div>
</template>
