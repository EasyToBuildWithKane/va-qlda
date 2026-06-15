<script setup>
import {
    computed, reactive, ref, watch,
} from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import KbBlogSidebar from '@/Components/KnowledgeBase/KbBlogSidebar.vue';
import KbBlogAside from '@/Components/KnowledgeBase/KbBlogAside.vue';
import KbBlogPostCard from '@/Components/KnowledgeBase/KbBlogPostCard.vue';

const PER_PAGE_OPTIONS = [10, 15, 20];

const props = defineProps({
    articles: { type: Object, required: true },
    sidebar: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    q: props.filters.q ?? '',
    category_id: props.filters.category_id ?? '',
    tag: props.filters.tag ?? '',
});

const perPage = ref(Number(props.filters.per_page) || props.articles.meta?.per_page || 10);

function routeParams(resetPage = false) {
    const params = {
        q: filterForm.q || undefined,
        category_id: filterForm.category_id || undefined,
        tag: filterForm.tag || undefined,
        per_page: perPage.value,
    };
    if (resetPage) params.page = 1;
    return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v != null));
}

function load(resetPage = false) {
    router.get(route('knowledge-base.blog'), routeParams(resetPage), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(() => load(true), 350);
});

function onPerPageChange(n) {
    perPage.value = n;
    load(true);
}

function onFilterTag(slug) {
    filterForm.tag = filterForm.tag === slug ? '' : slug;
    load(true);
}

const activeCategoryName = computed(() => {
    const id = props.filters.category_id;
    if (!id) return '';
    const cat = (props.sidebar.categories ?? []).find((c) => String(c.id) === String(id));
    return cat?.name ?? '';
});
</script>

<template>
  <Head title="Blog tri thức" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Blog tri thức"
        subtitle="Bài viết dạng tạp chí — kinh nghiệm và hướng dẫn nội bộ"
        icon="documents"
        icon-color="brand"
        :badge="articles.meta?.total ?? null"
      >
        <Link
          v-if="can.create"
          href="/knowledge-base/articles/create"
          class="btn-primary inline-flex h-9 shrink-0 items-center gap-1.5 px-3 text-sm"
        >
          <AppIcon
            name="add"
            :size="15"
          />
          Viết bài
        </Link>
      </PageHeader>
    </template>

    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
      <aside class="order-2 w-full shrink-0 xl:order-1 xl:w-60">
        <KbBlogSidebar
          v-model:search-query="filterForm.q"
          :sidebar="sidebar"
          :filters="filters"
        />
      </aside>

      <main class="order-1 min-w-0 flex-1 xl:order-2">
        <div
          v-if="filters.category_id || filters.tag"
          class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500"
        >
          <span>Đang lọc:</span>
          <span
            v-if="filters.category_id && activeCategoryName"
            class="rounded-full bg-brand/5 px-2.5 py-0.5 text-xs font-medium text-brand"
          >
            {{ activeCategoryName }}
          </span>
          <span
            v-if="filters.tag"
            class="rounded-full bg-brand/5 px-2.5 py-0.5 text-xs font-medium text-brand"
          >
            #{{ filters.tag }}
          </span>
        </div>

        <div
          v-if="!articles.data?.length"
          class="rounded-card border border-dashed border-slate-200 bg-slate-50/50 p-10 text-center text-sm text-slate-500"
        >
          Chưa có bài viết phù hợp.
        </div>

        <div
          v-else
          class="space-y-8"
        >
          <KbBlogPostCard
            v-for="a in articles.data"
            :key="a.id"
            :article="a"
          />
        </div>

        <DatagridPaginationFooter
          v-if="articles.meta"
          class="mt-6"
          variant="bar"
          :meta="articles.meta"
          :per-page="perPage"
          :per-page-options="PER_PAGE_OPTIONS"
          @update:per-page="onPerPageChange"
        />
      </main>

      <aside class="order-3 hidden w-56 shrink-0 xl:block">
        <KbBlogAside
          :sidebar="sidebar"
          :filters="filters"
          @filter-tag="onFilterTag"
        />
      </aside>
    </div>
  </AppLayout>
</template>
