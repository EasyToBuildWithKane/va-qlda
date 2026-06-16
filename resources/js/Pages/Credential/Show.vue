<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CredentialPasswordViewer from '@/modules/credential/components/CredentialPasswordViewer.vue';
import CredentialAccessGrantsPanel from '@/modules/credential/components/CredentialAccessGrantsPanel.vue';
import CredentialInfraMap from '@/modules/credential/components/CredentialInfraMap.vue';
import CredentialAuditTimeline from '@/modules/credential/components/CredentialAuditTimeline.vue';
import Badge from '@/shared/ui/Badge.vue';
import { date, datetime } from '@/composables/useFormat';

const props = defineProps({
    credential: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    auditLogs: { type: Object, default: () => ({}) },
    linkableCredentials: { type: Array, default: () => [] },
    pendingAccessRequests: { type: Array, default: () => [] },
});

const tab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'info' },
    { key: 'security', label: 'Bảo mật', icon: 'vault' },
    { key: 'access', label: 'Phân quyền', icon: 'people' },
    { key: 'infra', label: 'Hạ tầng', icon: 'globe' },
    { key: 'audit', label: 'Nhật ký', icon: 'clock' },
];

const editForm = useForm({
    name: props.credential.name,
    login_password: '',
});

function submitPassword() {
    editForm.put(route('credentials.update', props.credential.id), {
        preserveScroll: true,
        onSuccess: () => {
            editForm.login_password = '';
        },
    });
}
</script>

<template>
  <Head :title="credential.name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="credential.name"
        :subtitle="credential.system_category?.label"
        icon="vault"
        back-href="/credentials"
      />
    </template>

    <nav class="mb-4 flex flex-wrap gap-1 border-b border-slate-200 pb-1">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition"
        :class="tab === t.key ? 'bg-brand/10 text-brand' : 'text-slate-600 hover:bg-slate-100'"
        @click="tab = t.key"
      >
        <AppIcon
          :name="t.icon"
          :size="14"
        />
        {{ t.label }}
      </button>
    </nav>

    <div
      v-if="tab === 'overview'"
      class="grid gap-4 lg:grid-cols-2"
    >
      <div class="card p-5">
        <dl class="grid gap-3 text-sm">
          <div>
            <dt class="text-xs text-slate-500">
              Username
            </dt><dd class="font-medium">
              {{ credential.username || '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              Email
            </dt><dd>{{ credential.email || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              URL đăng nhập
            </dt><dd class="break-all">
              {{ credential.login_url || '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              Nhà cung cấp
            </dt><dd>{{ credential.provider_name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              Dự án
            </dt><dd>{{ credential.project?.name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              Phòng ban
            </dt><dd>{{ credential.department?.name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              Mô tả
            </dt><dd class="whitespace-pre-wrap">
              {{ credential.description || '—' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-500">
              Ghi chú
            </dt><dd class="whitespace-pre-wrap">
              {{ credential.notes || '—' }}
            </dd>
          </div>
        </dl>
      </div>
      <div class="card p-5">
        <div class="flex flex-wrap gap-2">
          <Badge
            v-for="b in credential.badges"
            :key="b"
            tone="brand"
          >
            {{ b }}
          </Badge>
          <Badge :tone="credential.environment?.value === 'production' ? 'rose' : 'sky'">
            {{ credential.environment?.label }}
          </Badge>
          <Badge
            v-if="credential.is_shared"
            tone="sky"
          >
            Shared
          </Badge>
          <Badge
            v-if="credential.is_critical"
            tone="amber"
          >
            Critical
          </Badge>
        </div>
        <p class="mt-4 text-xs text-slate-500">
          Tạo {{ datetime(credential.created_at) }}
        </p>
        <p class="text-xs text-slate-500">
          Hết hạn {{ credential.expires_at ? date(credential.expires_at) : '—' }}
        </p>
      </div>
    </div>

    <div
      v-else-if="tab === 'security'"
      class="space-y-4"
    >
      <CredentialPasswordViewer
        :credential-id="credential.id"
        :can-view-password="credential.can?.view_password"
        :has-password="credential.has_password"
        :password-changed-at="credential.password_changed_at ? datetime(credential.password_changed_at) : null"
        :password-expires-at="credential.password_expires_at ? date(credential.password_expires_at) : null"
        :mfa-enabled="credential.mfa_enabled"
      />
      <div
        v-if="credential.can?.update"
        class="card p-5"
      >
        <p class="text-sm font-medium">
          Đổi mật khẩu
        </p>
        <form
          class="mt-3 flex flex-wrap gap-2"
          @submit.prevent="submitPassword"
        >
          <input
            v-model="editForm.login_password"
            type="password"
            class="input h-10 min-w-[14rem] flex-1 text-sm"
            placeholder="Mật khẩu mới"
          >
          <button
            type="submit"
            class="btn-primary h-10 px-4 text-sm"
            :disabled="editForm.processing"
          >
            Lưu
          </button>
        </form>
      </div>
      <div
        v-if="credential.password_histories?.length"
        class="card p-5"
      >
        <p class="text-sm font-medium">
          Lịch sử mật khẩu
        </p>
        <ul class="mt-2 space-y-2 text-xs text-slate-600">
          <li
            v-for="h in credential.password_histories"
            :key="h.id"
          >
            {{ datetime(h.changed_at) }} · {{ h.changed_by?.display_name || '—' }}
          </li>
        </ul>
      </div>
    </div>

    <div v-else-if="tab === 'access'">
      <CredentialAccessGrantsPanel
        :credential-id="credential.id"
        :grants="credential.access_grants || []"
        :can-manage-access="credential.can?.manage_access"
        :can-request-access="credential.can?.view && !credential.can?.manage_access"
        :permission-options="options.permissions"
        :owner-options="options.owners"
        :pending-access-requests="pendingAccessRequests"
      />
    </div>

    <div v-else-if="tab === 'infra'">
      <CredentialInfraMap
        :credential-id="credential.id"
        :credential-name="credential.name"
        :relations="credential.outgoing_relations || []"
        :can-update="credential.can?.update"
        :linkable-credentials="linkableCredentials"
        :relation-types="options.relation_types || []"
      />
    </div>

    <div
      v-else-if="tab === 'audit'"
      class="card p-5"
    >
      <CredentialAuditTimeline :logs="auditLogs.data || auditLogs || []" />
    </div>
  </AppLayout>
</template>
