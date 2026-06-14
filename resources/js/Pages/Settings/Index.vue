<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import FieldsTab from './partials/FieldsTab.vue';
import PermissionsTab from './partials/PermissionsTab.vue';
import EmailTemplateTab from './partials/EmailTemplateTab.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
    emailTemplates: { type: Array, default: () => [] },
    emailPreviewBrand: { type: String, default: 'VAschools QLDA' },
    emailTestRecipient: { type: String, default: '' },
    permissions: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

const active = ref('general');
const groupMeta = (key) => props.groups.find((g) => g.key === key) ?? { label: '', description: '' };
</script>

<template>
  <Head title="Cấu hình hệ thống" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Cấu hình hệ thống"
        subtitle="Quản trị nhận diện, đăng nhập, email, thông báo và phân quyền"
        icon="system-config"
      />
    </template>

    <div class="w-full max-w-none">
      <div class="flex flex-col gap-6 md:flex-row">
        <!-- Tab rail -->
        <nav class="flex shrink-0 gap-2 overflow-x-auto pb-1 md:w-64 md:flex-col md:overflow-visible md:pb-0">
          <button
            v-for="g in groups"
            :key="g.key"
            type="button"
            class="group flex shrink-0 items-center gap-3 rounded-card border px-3.5 py-3 text-left transition-colors md:w-full"
            :class="active === g.key
              ? 'border-brand/30 bg-brand/[0.06]'
              : 'border-transparent hover:bg-slate-50'"
            @click="active = g.key"
          >
            <span
              class="grid h-9 w-9 shrink-0 place-items-center rounded-lg transition-colors"
              :class="active === g.key ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200'"
            >
              <AppIcon
                :name="g.icon"
                :size="17"
              />
            </span>
            <span class="min-w-0">
              <span
                class="block text-[13.5px] font-semibold leading-tight"
                :class="active === g.key ? 'text-brand' : 'text-slate-700'"
              >{{ g.label }}</span>
              <span class="mt-0.5 hidden truncate text-[11.5px] text-slate-400 md:block">{{ g.description }}</span>
            </span>
          </button>
        </nav>

        <!-- Active panel -->
        <section class="card min-w-0 flex-1 p-5 md:p-6">
          <FieldsTab
            v-show="active === 'general'"
            group="general"
            :title="groupMeta('general').label"
            :description="groupMeta('general').description"
            :fields="settings.general ?? []"
            :can-manage="can.manage"
          />
          <FieldsTab
            v-show="active === 'auth'"
            group="auth"
            :title="groupMeta('auth').label"
            :description="groupMeta('auth').description"
            :fields="settings.auth ?? []"
            :can-manage="can.manage"
          />
          <FieldsTab
            v-show="active === 'telegram'"
            group="telegram"
            :title="groupMeta('telegram').label"
            :description="groupMeta('telegram').description"
            :fields="settings.telegram ?? []"
            :can-manage="can.manage"
          />
          <EmailTemplateTab
            v-show="active === 'email'"
            :title="groupMeta('email').label"
            :description="groupMeta('email').description"
            :email-fields="settings.email ?? []"
            :email-templates="emailTemplates"
            :email-preview-brand="emailPreviewBrand"
            :email-test-recipient="emailTestRecipient ?? ''"
            :can-manage="can.manage"
          />
          <PermissionsTab
            v-show="active === 'permissions'"
            :permissions="permissions"
            :can-manage="can.manage"
          />
        </section>
      </div>
    </div>
  </AppLayout>
</template>
