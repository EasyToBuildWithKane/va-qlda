<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import CredentialFieldLabel from '@/modules/credential/components/CredentialFieldLabel.vue';
import { CATEGORIES_BY_TYPE } from '@/modules/credential/config/categoriesByType.js';

const props = defineProps({
    credential: { type: Object, required: true },
    options: { type: Object, required: true },
});

const INPUT_CLASS = 'input h-10 w-full text-sm';

function dateInputValue(iso) {
    if (!iso) return '';
    return String(iso).slice(0, 10);
}

const c = props.credential;

const form = useForm({
    name: c.name || '',
    credential_type: c.credential_type?.value || 'internal_system',
    system_category: c.system_category?.value || 'cms',
    login_url: c.login_url || '',
    username: c.username || '',
    login_password: '',
    email: c.email || '',
    phone: c.phone || '',
    provider_name: c.provider_name || '',
    description: c.description || '',
    notes: c.notes || '',
    project_id: c.project_id || '',
    department_id: c.department_id || '',
    owner_id: c.owner_id || '',
    environment: c.environment?.value || 'production',
    status: c.status?.value || 'active',
    mfa_enabled: Boolean(c.mfa_enabled),
    is_shared: Boolean(c.is_shared),
    is_critical: Boolean(c.is_critical),
    expires_at: dateInputValue(c.expires_at),
});

const categoriesForType = computed(() => {
    const allowed = new Set(CATEGORIES_BY_TYPE[form.credential_type] || []);
    return (props.options.system_category || []).filter((o) => allowed.has(o.value));
});

watch(() => form.credential_type, () => {
    const first = categoriesForType.value[0];
    if (first && !categoriesForType.value.some((o) => o.value === form.system_category)) {
        form.system_category = first.value;
    }
});

function submit() {
    form.transform((data) => ({
        ...data,
        project_id: data.project_id || null,
        department_id: data.department_id || null,
        owner_id: data.owner_id || null,
        expires_at: data.expires_at || null,
    })).put(route('credentials.update', props.credential.id));
}
</script>

<template>
  <Head :title="`Chỉnh sửa · ${credential.name}`" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Chỉnh sửa tài khoản"
        :subtitle="credential.name"
        icon="vault"
      >
        <Link
          :href="route('credentials.show', credential.id)"
          class="btn-ghost h-9 px-3 text-xs"
        >
          Xem hồ sơ
        </Link>
      </PageHeader>
    </template>

    <form
      class="grid w-full grid-cols-1 gap-5 lg:grid-cols-2"
      @submit.prevent="submit"
    >
      <section class="card flex h-full flex-col space-y-4 p-5 sm:p-6">
        <h3 class="text-xs font-semibold uppercase tracking-[0.12em] text-brand/80">
          1 · Phân loại
        </h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <CredentialFieldLabel
              for-id="cred-type"
              label="Loại tài khoản"
              required
              tooltip="Nhóm lớn: hệ thống nội bộ, hạ tầng, nhà cung cấp hoặc tài khoản làm việc."
            />
            <select
              id="cred-type"
              v-model="form.credential_type"
              :class="INPUT_CLASS"
            >
              <option
                v-for="o in options.credential_type"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-category"
              label="Hệ thống / danh mục"
              required
              tooltip="Danh mục chi tiết (CMS, Domain, Database…). Danh sách thay đổi theo loại tài khoản."
              wide
            />
            <select
              id="cred-category"
              v-model="form.system_category"
              :class="INPUT_CLASS"
            >
              <option
                v-for="o in categoriesForType"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-env"
              label="Môi trường"
              required
              tooltip="Production: hệ thống đang phục vụ người dùng. Staging/Dev: thử nghiệm."
            />
            <select
              id="cred-env"
              v-model="form.environment"
              :class="INPUT_CLASS"
            >
              <option
                v-for="o in options.environment"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-provider"
              label="Nhà cung cấp"
              tooltip="Tên dịch vụ: AWS, Cloudflare, Google Workspace, hosting… (tuỳ chọn)."
            />
            <input
              id="cred-provider"
              v-model="form.provider_name"
              :class="INPUT_CLASS"
              placeholder="VD: AWS, Viettel IDC, Cloudflare"
            >
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-status"
              label="Trạng thái"
              required
              tooltip="Đang hoạt động, ngưng dùng, hết hạn hoặc bị khóa — ảnh hưởng lọc KPI danh sách."
            />
            <select
              id="cred-status"
              v-model="form.status"
              :class="INPUT_CLASS"
            >
              <option
                v-for="o in options.status"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
        </div>
      </section>

      <section class="card flex h-full flex-col space-y-4 p-5 sm:p-6">
        <h3 class="text-xs font-semibold uppercase tracking-[0.12em] text-brand/80">
          2 · Thông tin đăng nhập
        </h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <CredentialFieldLabel
              for-id="cred-name"
              label="Tên hiển thị"
              required
              tooltip="Tên ngắn gọn để tìm trong danh sách — VD: «CMS Trường ABC Production»."
              wide
            />
            <input
              id="cred-name"
              v-model="form.name"
              :class="INPUT_CLASS"
              placeholder="VD: CMS website chính — Production"
              required
            >
            <p
              v-if="form.errors.name"
              class="mt-1 text-xs text-danger"
            >
              {{ form.errors.name }}
            </p>
          </div>
          <div class="sm:col-span-2">
            <CredentialFieldLabel
              for-id="cred-url"
              label="URL đăng nhập"
              tooltip="Link trang admin hoặc portal. Dùng https khi có thể."
            />
            <input
              id="cred-url"
              v-model="form.login_url"
              :class="INPUT_CLASS"
              placeholder="https://admin.example.com/login"
              type="url"
              autocomplete="off"
            >
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-user"
              label="Username / ID đăng nhập"
              tooltip="Tên đăng nhập, email đăng nhập hoặc mã tài khoản trên hệ thống đích."
            />
            <input
              id="cred-user"
              v-model="form.username"
              :class="INPUT_CLASS"
              placeholder="admin hoặc email đăng nhập"
              autocomplete="off"
            >
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-pass"
              label="Mật khẩu"
              tooltip="Lưu mã hóa (AES). Chỉ người được cấp quyền mới xem/sao chép — mọi thao tác ghi audit."
              wide
            />
            <input
              id="cred-pass"
              v-model="form.login_password"
              type="password"
              :class="INPUT_CLASS"
              placeholder="Để trống nếu không đổi mật khẩu"
              autocomplete="new-password"
            >
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-email"
              label="Email liên kết"
              tooltip="Email recovery hoặc email đăng ký tài khoản (nếu khác username)."
            />
            <input
              id="cred-email"
              v-model="form.email"
              type="email"
              :class="INPUT_CLASS"
              placeholder="contact@congty.vn"
            >
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-phone"
              label="Số điện thoại"
              tooltip="Số dùng xác minh 2FA hoặc liên hệ khẩn (tuỳ chọn)."
            />
            <input
              id="cred-phone"
              v-model="form.phone"
              :class="INPUT_CLASS"
              placeholder="09xx xxx xxx"
            >
          </div>
        </div>
      </section>

      <section class="card flex h-full flex-col space-y-4 p-5 sm:p-6">
        <h3 class="text-xs font-semibold uppercase tracking-[0.12em] text-brand/80">
          3 · Phụ trách & phạm vi
        </h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <CredentialFieldLabel
              for-id="cred-owner"
              label="Người phụ trách"
              tooltip="Chủ sở hữu hồ sơ — có quyền cấp/thu hồi truy cập và duyệt yêu cầu."
            />
            <select
              id="cred-owner"
              v-model="form.owner_id"
              :class="INPUT_CLASS"
            >
              <option value="">
                Chưa gán — hiển thị trong KPI «Chưa gán phụ trách»
              </option>
              <option
                v-for="o in options.owners"
                :key="o.id"
                :value="o.id"
              >
                {{ o.display_name }}
              </option>
            </select>
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-dept"
              label="Phòng ban"
              tooltip="Phòng ban sở hữu hoặc sử dụng chính tài khoản này."
            />
            <select
              id="cred-dept"
              v-model="form.department_id"
              :class="INPUT_CLASS"
            >
              <option value="">
                Không gắn phòng ban
              </option>
              <option
                v-for="d in options.departments"
                :key="d.id"
                :value="d.id"
              >
                {{ d.name }}
              </option>
            </select>
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-project"
              label="Dự án"
              tooltip="Gắn với dự án VA-Workspace nếu tài khoản phục vụ một dự án cụ thể."
            />
            <select
              id="cred-project"
              v-model="form.project_id"
              :class="INPUT_CLASS"
            >
              <option value="">
                Không gắn dự án
              </option>
              <option
                v-for="p in options.projects"
                :key="p.id"
                :value="p.id"
              >
                {{ p.name }}
              </option>
            </select>
          </div>
          <div>
            <CredentialFieldLabel
              for-id="cred-expires"
              label="Ngày hết hạn"
              tooltip="Hết hạn gói dịch vụ, domain, SSL hoặc license — dùng cảnh báo trên dải thống kê danh sách."
            />
            <input
              id="cred-expires"
              v-model="form.expires_at"
              type="date"
              :class="INPUT_CLASS"
            >
          </div>
        </div>
        <div class="flex flex-wrap gap-4 pt-1">
          <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input
              v-model="form.is_shared"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
            >
            Tài khoản dùng chung (Shared)
          </label>
          <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input
              v-model="form.is_critical"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
            >
            Critical — ưu tiên bảo mật
          </label>
          <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-700">
            <input
              v-model="form.mfa_enabled"
              type="checkbox"
              class="rounded border-slate-300 text-brand focus:ring-brand/30"
            >
            Đã bật MFA / 2FA
          </label>
        </div>
      </section>

      <section class="card flex h-full flex-col space-y-4 p-5 sm:p-6">
        <h3 class="text-xs font-semibold uppercase tracking-[0.12em] text-brand/80">
          4 · Ghi chú
        </h3>
        <div>
          <CredentialFieldLabel
            for-id="cred-desc"
            label="Mô tả"
            tooltip="Tóm tắt vai trò của tài khoản trong vận hành."
          />
          <textarea
            id="cred-desc"
            v-model="form.description"
            class="input min-h-[4.5rem] w-full text-sm"
            placeholder="VD: Tài khoản admin WordPress — chỉ Tech Lead deploy plugin."
          />
        </div>
        <div>
          <CredentialFieldLabel
            for-id="cred-notes"
            label="Ghi chú nội bộ"
            tooltip="Thông tin bổ sung không hiển thị ra ngoài — quy trình reset, ticket liên quan…"
          />
          <textarea
            id="cred-notes"
            v-model="form.notes"
            class="input min-h-[4rem] w-full text-sm"
            placeholder="VD: Reset mật khẩu qua ticket IT — không gửi qua chat cá nhân."
          />
        </div>
      </section>

      <div class="flex flex-wrap justify-end gap-2 pb-6 lg:col-span-2">
        <Link
          :href="route('credentials.show', credential.id)"
          class="btn-ghost h-10 px-4 text-sm"
        >
          Huỷ
        </Link>
        <button
          type="submit"
          class="btn-primary h-10 px-4 text-sm"
          :disabled="form.processing"
        >
          Lưu thay đổi
        </button>
      </div>
    </form>
  </AppLayout>
</template>
