<script setup>
import {
    computed, reactive, ref, watch,
} from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { visitKbBlogFeed } from '@/composables/useKbBlogFeed';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import KbBlogSidebar from '@/modules/knowledge-base/components/KbBlogSidebar.vue';
import KbBlogAside from '@/modules/knowledge-base/components/KbBlogAside.vue';
import KbBlogPostCard from '@/modules/knowledge-base/components/KbBlogPostCard.vue';

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
    visitKbBlogFeed(routeParams(resetPage));
}

function onPageChange(page) {
    visitKbBlogFeed({
        q: filterForm.q || undefined,
        category_id: props.filters.category_id || undefined,
        tag: props.filters.tag || undefined,
        per_page: perPage.value,
        page,
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

function clearCategoryFilter() {
    filterForm.category_id = '';
    visitKbBlogFeed({
        tag: props.filters.tag || undefined,
        q: filterForm.q || undefined,
        per_page: perPage.value,
        page: 1,
    });
}

function clearTagFilter() {
    filterForm.tag = '';
    visitKbBlogFeed({
        category_id: props.filters.category_id || undefined,
        q: filterForm.q || undefined,
        per_page: perPage.value,
        page: 1,
    });
}

function onSidebarCategory(categoryId) {
    filterForm.category_id = String(categoryId);
    filterForm.tag = '';
    visitKbBlogFeed({
        category_id: categoryId,
        q: filterForm.q || undefined,
        per_page: perPage.value,
        page: 1,
    });
}

function onSidebarClearFilters() {
    filterForm.category_id = '';
    filterForm.tag = '';
    filterForm.q = '';
    visitKbBlogFeed({ per_page: perPage.value, page: 1 });
}

watch(
    () => props.filters,
    (f) => {
        filterForm.category_id = f.category_id ?? '';
        filterForm.tag = f.tag ?? '';
        if (f.q !== undefined && f.q !== filterForm.q) {
            filterForm.q = f.q ?? '';
        }
    },
    { deep: true },
);

const activeCategoryName = computed(() => {
    const id = props.filters.category_id;
    if (!id) return '';
    const cat = (props.sidebar.categories ?? []).find((c) => String(c.id) === String(id));
    return cat?.name ?? '';
});

const currentPage = computed(() => Number(props.articles.meta?.current_page) || 1);

const useMagazineFeed = computed(() =>
    currentPage.value === 1
    && !props.filters.q
    && (props.articles.data?.length ?? 0) > 0);

const featuredArticle = computed(() =>
    (useMagazineFeed.value ? props.articles.data[0] : null));

const feedArticles = computed(() =>
    (useMagazineFeed.value ? props.articles.data.slice(1) : props.articles.data ?? []));
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

    <div class="kb-blog-page mx-auto w-full max-w-[90rem]">
      <div class="kb-blog-layout grid grid-cols-1 gap-4 sm:gap-5 xl:grid-cols-[16rem_minmax(0,1fr)_14rem] xl:gap-6 xl:items-start">
        <aside class="order-2 min-w-0 xl:order-1">
          <KbBlogSidebar
            v-model:search-query="filterForm.q"
            :sidebar="sidebar"
            :filters="filters"
            @filter-category="onSidebarCategory"
            @filter-tag="onFilterTag"
            @clear-filters="onSidebarClearFilters"
          />
        </aside>

        <main class="order-1 min-w-0 xl:order-2">
          <div
            v-if="filters.category_id || filters.tag"
            class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-slate-200/70 bg-white/80 px-3 py-2.5 sm:mb-5 sm:px-4"
            role="status"
          >
            <span class="text-xs font-medium uppercase tracking-wide text-slate-400">
              Đang lọc
            </span>
            <button
              v-if="filters.category_id && activeCategoryName"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-full border border-brand/15 bg-brand/[0.06] py-1 pl-2.5 pr-1.5 text-xs font-medium text-brand transition hover:bg-brand/10"
              @click="clearCategoryFilter"
            >
              {{ activeCategoryName }}
              <AppIcon
                name="close"
                :size="12"
                class="opacity-70"
              />
            </button>
            <button
              v-if="filters.tag"
              type="button"
              class="inline-flex items-center gap-1.5 rounded-full border border-brand/15 bg-brand/[0.06] py-1 pl-2.5 pr-1.5 text-xs font-medium text-brand transition hover:bg-brand/10"
              @click="clearTagFilter"
            >
              #{{ filters.tag }}
              <AppIcon
                name="close"
                :size="12"
                class="opacity-70"
              />
            </button>
          </div>

          <div
            v-if="!articles.data?.length"
            class="rounded-card border border-dashed border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-12 text-center"
          >
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-brand/[0.08] text-brand/60">
              <AppIcon
                name="documents"
                :size="22"
              />
            </div>
            <p class="font-display text-sm font-medium text-slate-700">
              Chưa có bài viết phù hợp
            </p>
            <p class="mt-1 text-sm text-slate-500">
              Thử đổi chuyên mục hoặc xóa bộ lọc.
            </p>
          </div>

          <div
            v-else
            class="space-y-4 sm:space-y-5 lg:space-y-6"
          >
            <KbBlogPostCard
              v-if="featuredArticle"
              :article="featuredArticle"
              variant="featured"
            />

            <div
              v-if="feedArticles.length"
              class="grid gap-4 sm:gap-5"
              :class="useMagazineFeed ? 'md:grid-cols-2' : 'mx-auto max-w-3xl w-full'"
            >
              <KbBlogPostCard
                v-for="a in feedArticles"
                :key="a.id"
                :article="a"
                :variant="useMagazineFeed ? 'compact' : 'standard'"
              />
            </div>
          </div>

          <DatagridPaginationFooter
            v-if="articles.meta"
            class="mt-6 sm:mt-8"
            variant="bar"
            client
            :meta="articles.meta"
            :per-page="perPage"
            :per-page-options="PER_PAGE_OPTIONS"
            @update:per-page="onPerPageChange"
            @page-change="onPageChange"
          />
        </main>

        <aside class="order-3 hidden min-w-0 xl:block">
          <KbBlogAside
            :sidebar="sidebar"
            :filters="filters"
            @filter-tag="onFilterTag"
          />
        </aside>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.kb-blog-page {
  background: radial-gradient(ellipse 120% 80% at 50% -20%, rgba(154, 0, 54, 0.04), transparent 55%);
}

@media (max-width: 1279px) {
  .kb-blog-layout {
    /* Sidebar full width under feed — consistent gutters */
    padding-inline: 0;
  }
}
</style>
