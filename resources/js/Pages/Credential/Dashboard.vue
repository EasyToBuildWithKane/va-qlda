<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import CredentialSummaryBar from '@/modules/credential/components/CredentialSummaryBar.vue';
import CredentialAuditTimeline from '@/modules/credential/components/CredentialAuditTimeline.vue';

defineProps({
    summary: { type: Object, required: true },
    recentAudit: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});
</script>

<template>
  <Head title="Tổng quan bảo mật" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Tổng quan bảo mật"
        subtitle="Vault tài khoản, hạ tầng và nhà cung cấp"
        icon="vault"
      >
        <Link
          :href="route('credentials.index')"
          class="btn-ghost h-9 px-3 text-xs"
        >
          Danh sách
        </Link>
        <Link
          v-if="can.create"
          :href="route('credentials.create')"
          class="btn-primary h-9 px-3 text-xs"
        >
          Thêm
        </Link>
      </PageHeader>
    </template>

    <CredentialSummaryBar :summary="summary" />

    <div class="card p-5">
      <h2 class="text-sm font-semibold text-slate-800">
        Hoạt động gần đây
      </h2>
      <div class="mt-4">
        <CredentialAuditTimeline :logs="recentAudit.data || recentAudit || []" />
      </div>
    </div>
  </AppLayout>
</template>
