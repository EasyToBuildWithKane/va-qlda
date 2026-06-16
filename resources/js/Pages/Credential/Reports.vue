<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import CredentialSummaryBar from '@/modules/credential/components/CredentialSummaryBar.vue';
import CredentialHelpBanner from '@/modules/credential/components/CredentialHelpBanner.vue';

defineProps({
    summary: { type: Object, required: true },
    bySystem: { type: Array, default: () => [] },
    byDepartment: { type: Array, default: () => [] },
    security: { type: Object, default: () => ({}) },
    topAccess: { type: Array, default: () => [] },
});
</script>

<template>
  <Head title="Báo cáo bảo mật" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Báo cáo bảo mật"
        subtitle="Phân tích rủi ro mật khẩu và mức độ truy cập"
        icon="performance"
      />
    </template>

    <CredentialHelpBanner
      title="Báo cáo bảo mật (Admin)"
      intro="Phân tích rủi ro tập trung: MFA, mật khẩu quá hạn, phân bổ theo hệ thống và phòng ban."
      :steps="[
        'Ưu tiên xử lý «Chưa bật MFA» và «Mật khẩu quá hạn».',
        'Theo dõi ai truy cập secret nhiều nhất — phát hiện dùng chung bất thường.',
        'Dùng số liệu theo phòng ban để giao phụ trách rõ ràng.',
      ]"
      note="Chỉ vai trò Admin xem trang này. Dữ liệu aggregate toàn vault, không phụ thuộc phân trang danh sách."
    />

    <CredentialSummaryBar :summary="summary" />

    <div class="grid gap-5 lg:grid-cols-2">
      <div class="card p-5">
        <h2 class="text-sm font-semibold">
          Cảnh báo bảo mật
        </h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li>Chưa bật MFA: <strong>{{ security.no_mfa ?? 0 }}</strong></li>
          <li>Mật khẩu quá hạn: <strong>{{ security.password_overdue ?? 0 }}</strong></li>
          <li>Tài khoản bị khóa: <strong>{{ security.locked ?? 0 }}</strong></li>
        </ul>
      </div>
      <div class="card p-5">
        <h2 class="text-sm font-semibold">
          Truy cập mật khẩu nhiều nhất
        </h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li
            v-for="(row, i) in topAccess"
            :key="i"
          >
            {{ row.account }} — {{ row.total }} lần
          </li>
          <li
            v-if="!topAccess.length"
            class="text-slate-500"
          >
            Chưa có dữ liệu.
          </li>
        </ul>
      </div>
      <div class="card p-5 lg:col-span-2">
        <h2 class="text-sm font-semibold">
          Theo hệ thống
        </h2>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
          <li
            v-for="(row, i) in bySystem"
            :key="i"
            class="rounded-lg border border-slate-100 px-3 py-2 text-sm"
          >
            {{ row.category }} · {{ row.total }}
          </li>
        </ul>
      </div>
      <div class="card p-5 lg:col-span-2">
        <h2 class="text-sm font-semibold">
          Theo phòng ban
        </h2>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
          <li
            v-for="(row, i) in byDepartment"
            :key="i"
            class="text-sm"
          >
            {{ row.department }} — {{ row.total }}
          </li>
        </ul>
      </div>
    </div>
  </AppLayout>
</template>
