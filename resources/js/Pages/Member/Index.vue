<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import MemberDirectoryCard from '@/modules/profile/components/MemberDirectoryCard.vue';

const props = defineProps({
    members: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.q || '');
const status = ref(props.filters.status || 'all');

let debounce = null;
watch(search, (val) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => reload({ q: val || undefined }), 300);
});

function setStatus(val) {
    status.value = val;
    reload({ status: val === 'all' ? undefined : val });
}

function reload(params) {
    router.get('/members', {
        q: search.value || undefined,
        status: status.value === 'all' ? undefined : status.value,
        ...params,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

const statusTabs = computed(() => [
    { value: 'all', label: 'Tất cả', count: props.summary.total },
    { value: 'active', label: 'Đang hoạt động', count: props.summary.active },
    { value: 'inactive', label: 'Ngừng', count: props.summary.inactive },
]);

const pageLinks = computed(() => props.members.meta?.links || []);

function pageLabel(label) {
    return String(label)
        .replace(/&laquo;|«/g, '‹')
        .replace(/&raquo;|»/g, '›')
        .replace(/<[^>]*>/g, '')
        .trim();
}
</script>

<template>
  <Head title="Hồ sơ thành viên" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Hồ sơ thành viên"
        subtitle="Danh bạ năng lực & hồ sơ nhân sự"
        icon="member-profiles"
        :badge="summary.total"
      />
    </template>

    <div class="mx-auto max-w-6xl space-y-4">
      <!-- Toolbar -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-xs">
          <AppIcon
            name="search"
            :size="16"
            class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="search"
            type="search"
            placeholder="Tìm theo tên, mã, chức danh..."
            class="input w-full pl-9"
          >
        </div>

        <div class="flex items-center gap-1 rounded-lg bg-slate-100 p-1">
          <button
            v-for="t in statusTabs"
            :key="t.value"
            type="button"
            class="rounded-md px-3 py-1.5 text-[12.5px] font-medium transition-colors"
            :class="status === t.value ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
            @click="setStatus(t.value)"
          >
            {{ t.label }}
            <span class="ml-1 text-slate-400">{{ t.count }}</span>
          </button>
        </div>
      </div>

      <!-- Grid -->
      <EmptyState
        v-if="!members.data.length"
        icon="members"
        title="Không tìm thấy thành viên"
        description="Thử thay đổi từ khoá hoặc bộ lọc."
      />
      <div
        v-else
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
      >
        <MemberDirectoryCard
          v-for="m in members.data"
          :key="m.id"
          :member="m"
        />
      </div>

      <!-- Pagination -->
      <nav
        v-if="pageLinks.length > 3"
        class="flex flex-wrap items-center justify-center gap-1 pt-2"
      >
        <component
          :is="link.url ? Link : 'span'"
          v-for="(link, i) in pageLinks"
          :key="i"
          :href="link.url || undefined"
          preserve-scroll
          class="grid h-8 min-w-[32px] place-items-center rounded-lg px-2 text-[13px] font-medium transition-colors"
          :class="[
            link.active ? 'bg-brand text-white' : 'text-slate-600 hover:bg-slate-100',
            !link.url ? 'cursor-default text-slate-300 hover:bg-transparent' : '',
          ]"
        >
          {{ pageLabel(link.label) }}
        </component>
      </nav>
    </div>
  </AppLayout>
</template>
