<script setup>
/* eslint-disable vue/no-v-html -- Laravel pagination link labels */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

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

const showNav = computed(() => (props.meta?.links?.length ?? 0) > 3);

const linkBtnClass = (active) => (active
    ? 'bg-brand text-white shadow-sm ring-1 ring-brand/20'
    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-800');
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
            v-if="showNav"
            class="inline-flex flex-wrap items-center gap-0.5 rounded-btn border border-slate-200 bg-white p-0.5 shadow-sm"
            aria-label="Phân trang"
          >
            <template
              v-for="(link, i) in meta.links"
              :key="i"
            >
              <button
                v-if="client && link.page != null"
                type="button"
                class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-xs font-medium transition"
                :class="linkBtnClass(link.active)"
                :aria-current="link.active ? 'page' : undefined"
                @click="onPageLink(link)"
              >
                <span v-html="link.label" />
              </button>
              <Link
                v-else-if="!client && link.url"
                :href="link.url"
                preserve-scroll
                class="inline-flex h-8 min-w-[2rem] items-center justify-center rounded-md px-2 text-xs font-medium transition"
                :class="linkBtnClass(link.active)"
                :aria-current="link.active ? 'page' : undefined"
              >
                <span v-html="link.label" />
              </Link>
              <span
                v-else
                class="inline-flex h-8 min-w-[2rem] items-center justify-center px-2 text-xs text-slate-300"
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
    class="flex flex-col gap-1.5 border-t border-slate-200/70 bg-slate-50/40 px-4 py-2 sm:flex-row sm:items-center sm:justify-between"
  >
    <p class="text-[11px] leading-tight text-slate-500">
      <span class="text-slate-500">Hiển thị </span>
      <span class="font-semibold tabular-nums text-slate-700">{{ rangeLabel }}</span>
      <span class="text-slate-400"> bản ghi</span>
    </p>

    <div
      class="flex flex-wrap items-center gap-1.5 sm:justify-end"
      :class="showNav ? 'sm:gap-2' : ''"
    >
      <label
        class="inline-flex h-7 items-center gap-1.5 rounded-md border border-slate-200/90 bg-white pl-2 pr-1.5 text-slate-600 shadow-sm transition hover:border-slate-300"
      >
        <span class="text-[11px] font-medium text-slate-500">Số dòng</span>
        <span class="relative inline-flex items-center">
          <select
            :value="perPage"
            class="h-5 min-w-[2.25rem] cursor-pointer appearance-none rounded border-0 bg-slate-50 py-0 pl-1.5 pr-5 text-xs font-semibold tabular-nums text-slate-800 ring-1 ring-slate-200/80 transition hover:bg-white focus:bg-white focus:outline-none focus:ring-1 focus:ring-brand/30"
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
          <AppIcon
            name="chevron-down"
            :size="12"
            class="pointer-events-none absolute right-1 top-1/2 -translate-y-1/2 text-slate-400"
          />
        </span>
        <span class="text-[11px] text-slate-400">/ trang</span>
      </label>

      <nav
        v-if="showNav"
        class="inline-flex max-w-full flex-wrap items-center justify-end gap-px rounded-md border border-slate-200/90 bg-white p-px shadow-sm"
        aria-label="Phân trang"
      >
        <template
          v-for="(link, i) in meta.links"
          :key="i"
        >
          <button
            v-if="client && link.page != null"
            type="button"
            class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-xs font-medium transition"
            :class="linkBtnClass(link.active)"
            :aria-current="link.active ? 'page' : undefined"
            @click="onPageLink(link)"
          >
            <span v-html="link.label" />
          </button>
          <Link
            v-else-if="!client && link.url"
            :href="link.url"
            preserve-scroll
            class="inline-flex h-7 min-w-[1.75rem] items-center justify-center rounded px-1.5 text-xs font-medium transition"
            :class="linkBtnClass(link.active)"
            :aria-current="link.active ? 'page' : undefined"
          >
            <span v-html="link.label" />
          </Link>
          <span
            v-else
            class="inline-flex h-7 min-w-[1.75rem] items-center justify-center px-1.5 text-xs text-slate-300"
            v-html="link.label"
          />
        </template>
      </nav>
    </div>
  </div>
</template>
