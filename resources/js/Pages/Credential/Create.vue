<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';

const props = defineProps({
    options: { type: Object, required: true },
    defaults: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: '',
    credential_type: 'internal_system',
    system_category: 'cms',
    login_url: '',
    username: '',
    login_password: '',
    email: '',
    phone: '',
    provider_name: '',
    description: '',
    notes: '',
    project_id: '',
    department_id: '',
    owner_id: props.defaults.owner_id || '',
    environment: props.defaults.environment || 'production',
    status: props.defaults.status || 'active',
    mfa_enabled: false,
    is_shared: false,
    is_critical: false,
    expires_at: '',
});

const categoriesForType = computed(() => {
    const map = {
        internal_system: ['cms', 'landing_page', 'crm', 'erp', 'hrm', 'lms', 'knowledge_base', 'ai_platform'],
        infrastructure: ['vps', 'server', 'hosting', 'cdn', 'dns', 'domain', 'database', 'mail_server', 'ssl'],
        provider: ['cloud_provider', 'hosting_provider', 'sms_provider', 'email_provider', 'payment_gateway', 'ai_services', 'third_party_api', 'api_key'],
        working_account: ['admin_account', 'user_account', 'other'],
    };
    const allowed = new Set(map[form.credential_type] || []);
    return (props.options.system_category || []).filter((o) => allowed.has(o.value));
});

function submit() {
    form.transform((data) => ({
        ...data,
        project_id: data.project_id || null,
        department_id: data.department_id || null,
        owner_id: data.owner_id || null,
        expires_at: data.expires_at || null,
    })).post(route('credentials.store'));
}
</script>

<template>
  <Head title="Thêm tài khoản" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Thêm tài khoản"
        subtitle="Nhập thông tin truy cập — mật khẩu được mã hóa khi lưu"
        icon="vault"
        icon-color="emerald"
        back-href="/credentials"
      />
    </template>

    <form
      class="card mx-auto max-w-3xl space-y-4 p-6"
      @submit.prevent="submit"
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="text-xs text-slate-600">Tên hiển thị *</label>
          <input
            v-model="form.name"
            class="input mt-1 h-10 w-full text-sm"
            required
          >
        </div>
        <div>
          <label class="text-xs text-slate-600">Loại *</label>
          <select
            v-model="form.credential_type"
            class="input mt-1 h-10 w-full text-sm"
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
          <label class="text-xs text-slate-600">Hệ thống / danh mục *</label>
          <select
            v-model="form.system_category"
            class="input mt-1 h-10 w-full text-sm"
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
          <label class="text-xs text-slate-600">Username</label>
          <input
            v-model="form.username"
            class="input mt-1 h-10 w-full text-sm"
          >
        </div>
        <div>
          <label class="text-xs text-slate-600">Mật khẩu</label>
          <input
            v-model="form.login_password"
            type="password"
            class="input mt-1 h-10 w-full text-sm"
          >
        </div>
        <div class="sm:col-span-2">
          <label class="text-xs text-slate-600">URL đăng nhập</label>
          <input
            v-model="form.login_url"
            class="input mt-1 h-10 w-full text-sm"
          >
        </div>
        <div>
          <label class="text-xs text-slate-600">Người phụ trách</label>
          <select
            v-model="form.owner_id"
            class="input mt-1 h-10 w-full text-sm"
          >
            <option value="">
              —
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
          <label class="text-xs text-slate-600">Môi trường</label>
          <select
            v-model="form.environment"
            class="input mt-1 h-10 w-full text-sm"
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
      </div>
      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <a
          href="/credentials"
          class="btn-ghost h-10 px-4 text-sm"
        >Huỷ</a>
        <button
          type="submit"
          class="btn-primary h-10 px-4 text-sm"
          :disabled="form.processing"
        >
          Tạo tài khoản
        </button>
      </div>
    </form>
  </AppLayout>
</template>
