<script setup>
import { reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Badge from '@/shared/ui/Badge.vue';
import DatagridToolbarSearch from '@/shared/ui/DatagridToolbarSearch.vue';

const props = defineProps({
    courses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

function load() {
    router.get('/coaching/courses', {
        q: filterForm.q || undefined,
        status: filterForm.status || undefined,
    }, { preserveState: true, replace: true });
}

let qTimer = null;
watch(() => filterForm.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(load, 350);
});
</script>

<template>
  <Head title="Khóa học Coaching" />
  <AppLayout>
    <PageHeader
      title="Khóa học"
      subtitle="Quản lý coaching và mentoring"
    >
      <Link
        href="/coaching"
        class="btn-ghost h-9 px-3 text-sm"
      >
        Dashboard
      </Link>
      <Link
        v-if="can.create"
        href="/coaching/courses/create"
        class="btn-primary h-9 px-3 text-sm"
      >
        Thêm khóa
      </Link>
    </PageHeader>

    <div class="card mb-4 p-3">
      <DatagridToolbarSearch
        v-model="filterForm.q"
        input-id="coaching-course-search"
      />
      <select
        v-model="filterForm.status"
        class="input mt-2 h-9 text-xs"
        @change="load"
      >
        <option value="">
          Mọi trạng thái
        </option>
        <option
          v-for="s in options.statuses"
          :key="s.value"
          :value="s.value"
        >
          {{ s.label }}
        </option>
      </select>
    </div>

    <div class="space-y-2">
      <Link
        v-for="c in courses.data"
        :key="c.id"
        :href="`/coaching/courses/${c.id}`"
        class="card flex flex-wrap items-center justify-between gap-2 p-4 hover:border-brand/30"
      >
        <div>
          <span class="font-mono text-xs text-slate-400">{{ c.code }}</span>
          <h2 class="font-display font-semibold text-slate-800">
            {{ c.name }}
          </h2>
          <p
            v-if="c.student_display"
            class="text-xs text-slate-500"
          >
            Học viên: {{ c.student_display }}
            <template v-if="c.coach_display">
              · Coach: {{ c.coach_display }}
            </template>
          </p>
        </div>
        <Badge
          v-if="c.status"
          :label="c.status.label"
          color="slate"
        />
      </Link>
    </div>
  </AppLayout>
</template>
