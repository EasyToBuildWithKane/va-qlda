<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridPaginationFooter from '@/shared/ui/DatagridPaginationFooter.vue';
import CredentialFieldLabel from '@/modules/credential/components/CredentialFieldLabel.vue';
import CredentialPasswordViewer from '@/modules/credential/components/CredentialPasswordViewer.vue';
import CredentialAccessGrantsPanel from '@/modules/credential/components/CredentialAccessGrantsPanel.vue';
import CredentialAuditTimeline from '@/modules/credential/components/CredentialAuditTimeline.vue';
import CredentialExpiryCountdown from '@/modules/credential/components/CredentialExpiryCountdown.vue';
import { isCredentialExpiringWithinDays } from '@/modules/credential/utils/credentialExpiry';
import { useSecondTicker } from '@/shared/composables/useSecondTicker';
import { useToast } from '@/shared/composables/useToast';
import { date, datetime } from '@/composables/useFormat';

const props = defineProps({
    credential: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    auditLogs: { type: Object, default: () => ({ data: [], meta: null }) },
    auditPerPage: { type: Number, default: 10 },
    pendingAccessRequests: { type: Array, default: () => [] },
});

const toast = useToast();
const { now: expiryNow } = useSecondTicker();
const inertiaPage = usePage();
const tab = ref('overview');

function syncTabFromAuditQuery() {
    if (typeof window === 'undefined') return;
    const url = new URL(inertiaPage.url, window.location.origin);
    if (url.searchParams.has('audit_page') || url.searchParams.has('audit_per_page')) {
        tab.value = 'audit';
    }
}

onMounted(syncTabFromAuditQuery);
watch(() => inertiaPage.url, syncTabFromAuditQuery);

const tabs = computed(() => {
    const items = [
        { key: 'overview', label: 'Tổng quan', icon: 'info' },
        { key: 'security', label: 'Bảo mật', icon: 'vault' },
        { key: 'access', label: 'Phân quyền', icon: 'people' },
        { key: 'audit', label: 'Nhật ký', icon: 'clock' },
    ];
    if (!props.credential.can?.view_access_tab) {
        return items.filter((t) => t.key !== 'access');
    }
    return items;
});

const editForm = useForm({
    name: props.credential.name,
    login_password: '',
    login_password_confirmation: '',
});

function displayValue(value, empty = 'Chưa cập nhật') {
    if (value === null || value === undefined || value === '') return empty;
    return value;
}

function submitPassword() {
    if (!editForm.login_password) {
        toast.error('Nhập mật khẩu mới.');
        return;
    }
    if (editForm.login_password !== editForm.login_password_confirmation) {
        toast.error('Mật khẩu xác nhận không khớp.');
        return;
    }
    editForm.put(route('credentials.update', props.credential.id), {
        preserveScroll: true,
        onSuccess: () => {
            editForm.login_password = '';
            editForm.login_password_confirmation = '';
        },
    });
}

const auditPerPageLocal = ref(props.auditPerPage);

const auditLogList = computed(() => props.auditLogs?.data ?? []);

function reloadAuditLogs(page) {
    tab.value = 'audit';
    router.get(route('credentials.show', props.credential.id), {
        audit_page: page ?? props.auditLogs?.meta?.current_page ?? 1,
        audit_per_page: auditPerPageLocal.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['auditLogs', 'auditPerPage'],
    });
}

const badgeLabelsText = computed(() => {
    const list = (props.credential.badges || []).filter(Boolean);
    return list.length ? list.join(' · ') : '';
});

const profileExpiringSoon = computed(() => isCredentialExpiringWithinDays(props.credential.expires_at, 7));
</script>

<template>
  <Head :title="credential.name" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="credential.name"
        :subtitle="credential.system_category?.label"
        icon="vault"
      >
        <Link
          :href="route('credentials.index')"
          class="btn-ghost h-9 px-3 text-xs"
        >
          Danh sách
        </Link>
        <Link
          v-if="credential.can?.update"
          :href="route('credentials.edit', credential.id)"
          class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs"
        >
          <AppIcon
            name="edit"
            :size="15"
          />
          Chỉnh sửa
        </Link>
      </PageHeader>
    </template>

    <div
      v-if="profileExpiringSoon"
      class="mb-5 flex flex-wrap items-start gap-3 rounded-card border border-amber-200/90 bg-amber-50/90 px-4 py-3 shadow-sm"
      role="status"
    >
      <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800 ring-1 ring-amber-200/80">
        <AppIcon
          name="clock"
          :size="18"
          aria-hidden="true"
        />
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-amber-950">
          Hồ sơ sắp hết hạn (trong 7 ngày)
        </p>
        <p class="mt-0.5 text-xs text-amber-900/90">
          Ngày hết hạn:
          <span class="font-medium tabular-nums">{{ credential.expires_at ? date(credential.expires_at) : '' }}</span>
        </p>
        <CredentialExpiryCountdown
          :expires-at="credential.expires_at"
          :now="expiryNow"
          class="!mt-1.5 text-sm"
        />
      </div>
    </div>

    <nav
      class="mb-5 flex flex-wrap gap-1 border-b border-slate-200 pb-1"
      aria-label="Tab chi tiết tài khoản"
    >
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition"
        :class="tab === t.key ? 'bg-brand/10 text-brand' : 'text-slate-600 hover:bg-slate-100'"
        :aria-current="tab === t.key ? 'page' : undefined"
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
      <section class="card p-5">
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Thông tin đăng nhập
        </h3>
        <dl class="divide-y divide-slate-100">
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Username"
                tooltip="Tên đăng nhập trên hệ thống hoặc dịch vụ bên ngoài."
              />
            </dt>
            <dd class="text-sm font-medium text-slate-800">
              {{ displayValue(credential.username) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Email"
                tooltip="Email liên kết với tài khoản (khôi phục, thông báo)."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.email) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="URL đăng nhập"
                tooltip="Đường dẫn trang đăng nhập — có thể mở trực tiếp khi cần."
              />
            </dt>
            <dd class="break-all text-sm text-slate-800">
              <a
                v-if="credential.login_url"
                :href="credential.login_url"
                class="text-brand hover:underline"
                target="_blank"
                rel="noopener noreferrer"
              >{{ credential.login_url }}</a>
              <span v-else>{{ displayValue(credential.login_url) }}</span>
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Số điện thoại"
                tooltip="Số liên hệ hoặc OTP nếu có."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.phone) }}
            </dd>
          </div>
        </dl>
      </section>

      <section class="card p-5">
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Phạm vi & phụ trách
        </h3>
        <dl class="divide-y divide-slate-100">
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Dự án"
                tooltip="Dự án sử dụng tài khoản này trong phạm vi Workspace."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.project?.name) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Phòng ban"
                tooltip="Đơn vị nội bộ quản lý hồ sơ."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.department?.name) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Người phụ trách"
                tooltip="Người chịu trách nhiệm vận hành và cấp quyền (cùng người tạo nếu không đổi)."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.owner?.display_name) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Loại / Danh mục"
                tooltip="Phân loại hệ thống để lọc và báo cáo."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.credential_type?.label) }}
              <span
                v-if="credential.system_category?.label"
                class="text-slate-500"
              > · {{ credential.system_category.label }}</span>
            </dd>
          </div>
        </dl>
      </section>

      <section class="card p-5 lg:col-span-2">
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Mô tả & ghi chú
        </h3>
        <dl class="grid gap-4 sm:grid-cols-2">
          <div>
            <CredentialFieldLabel
              compact
              label="Mô tả"
              tooltip="Tóm tắt mục đích sử dụng tài khoản."
              wide
            />
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800">
              {{ displayValue(credential.description) }}
            </p>
          </div>
          <div>
            <CredentialFieldLabel
              compact
              label="Ghi chú nội bộ"
              tooltip="Ghi chú vận hành — không hiển thị ra ngoài module."
              wide
            />
            <p class="mt-1 whitespace-pre-wrap text-sm text-slate-800">
              {{ displayValue(credential.notes) }}
            </p>
          </div>
        </dl>
      </section>

      <section class="card p-5 lg:col-span-2">
        <h3 class="mb-4 border-b border-slate-100 pb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Trạng thái & nhãn
        </h3>
        <dl class="divide-y divide-slate-100">
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Trạng thái"
                tooltip="Trạng thái vận hành trên hệ thống (đang dùng, khóa, …)."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.status?.label) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Môi trường"
                tooltip="Production, staging hoặc môi trường dev tương ứng."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(credential.environment?.label) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Nhãn tùy chỉnh"
                tooltip="Các nhãn gắn thêm trên hồ sơ — lọc và nhận diện nhanh."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(badgeLabelsText) }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Dùng chung"
                tooltip="Tài khoản dùng chung cho nhiều thành viên trong phạm vi dự án."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ credential.is_shared ? 'Có' : 'Không' }}
            </dd>
          </div>
          <div class="grid gap-1 py-3 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Quan trọng (Critical)"
                tooltip="Đánh dấu tài sản nhạy cảm — ưu tiên bảo mật và theo dõi."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ credential.is_critical ? 'Có' : 'Không' }}
            </dd>
          </div>
        </dl>
        <dl class="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2">
          <div class="grid gap-1 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Ngày tạo hồ sơ"
              />
            </dt>
            <dd class="text-sm text-slate-800">
              {{ displayValue(datetime(credential.created_at)) }}
            </dd>
          </div>
          <div class="grid gap-1 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-center">
            <dt>
              <CredentialFieldLabel
                compact
                label="Hết hạn hồ sơ"
                tooltip="Ngày hết hạn tài khoản hoặc gói dịch vụ (nếu có)."
              />
            </dt>
            <dd class="text-sm text-slate-800">
              <span :class="{ 'font-medium text-amber-900': profileExpiringSoon }">
                {{ credential.expires_at ? date(credential.expires_at) : 'Không hết hạn' }}
              </span>
              <CredentialExpiryCountdown
                v-if="profileExpiringSoon"
                :expires-at="credential.expires_at"
                :now="expiryNow"
              />
            </dd>
          </div>
        </dl>
      </section>
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
        <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
          Cập nhật mật khẩu
        </h3>
        <form
          class="grid gap-4 lg:grid-cols-2"
          @submit.prevent="submitPassword"
        >
          <div>
            <CredentialFieldLabel
              for-id="new-password"
              label="Mật khẩu mới"
              required
              tooltip="Tối thiểu 8 ký tự khuyến nghị. Bản cũ được lưu vào lịch sử mã hóa."
              wide
            />
            <input
              id="new-password"
              v-model="editForm.login_password"
              type="password"
              class="input h-10 w-full text-sm"
              placeholder="Nhập mật khẩu mới"
              autocomplete="new-password"
            >
          </div>
          <div>
            <CredentialFieldLabel
              for-id="confirm-password"
              label="Nhập lại mật khẩu"
              required
              tooltip="Phải trùng khớp với mật khẩu mới."
              wide
            />
            <input
              id="confirm-password"
              v-model="editForm.login_password_confirmation"
              type="password"
              class="input h-10 w-full text-sm"
              placeholder="Nhập lại mật khẩu mới"
              autocomplete="new-password"
            >
          </div>
          <div class="flex items-end lg:col-span-2">
            <button
              type="submit"
              class="btn-primary h-10 px-4 text-sm"
              :disabled="editForm.processing"
            >
              Lưu mật khẩu
            </button>
          </div>
        </form>
      </div>
      <div
        v-if="credential.password_histories?.length"
        class="card p-5"
      >
        <CredentialFieldLabel
          label="Lịch sử mật khẩu"
          tooltip="Các lần đổi trước — chỉ metadata, không hiển thị plaintext."
          wide
        />
        <ul class="mt-3 space-y-2 text-sm text-slate-600">
          <li
            v-for="h in credential.password_histories"
            :key="h.id"
            class="flex flex-wrap gap-1 rounded-lg border border-slate-100 px-3 py-2"
          >
            <span>{{ datetime(h.changed_at) }}</span>
            <span class="text-slate-400">·</span>
            <span>{{ displayValue(h.changed_by?.display_name) }}</span>
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

    <div
      v-else-if="tab === 'audit'"
      class="card overflow-hidden"
    >
      <div class="border-b border-slate-100 px-5 py-4">
        <CredentialFieldLabel
          label="Nhật ký thao tác"
          tooltip="Ghi nhận xem/sao chép mật khẩu, chỉnh sửa, cấp và thu hồi quyền."
          wide
          compact
        />
      </div>
      <div class="px-5 py-4">
        <CredentialAuditTimeline :logs="auditLogList" />
      </div>
      <DatagridPaginationFooter
        v-if="auditLogs.meta?.total"
        v-model:per-page="auditPerPageLocal"
        variant="bar"
        client
        :meta="auditLogs.meta"
        :per-page-options="[5, 10, 15, 20]"
        @update:per-page="reloadAuditLogs(1)"
        @page-change="reloadAuditLogs"
      />
    </div>
  </AppLayout>
</template>
