<script setup>
import { computed, provide } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import FieldsTab from './partials/FieldsTab.vue';
import PermissionsTab from './partials/PermissionsTab.vue';
import EmailTemplateTab from './partials/EmailTemplateTab.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    activeGroup: { type: String, default: 'general' },
    settings: { type: Object, default: () => ({}) },
    emailTemplates: { type: Array, default: () => [] },
    emailPreviewBrand: { type: String, default: 'VAschools QLDA' },
    emailTestRecipient: { type: String, default: '' },
    permissions: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
});

// The active group now comes from the URL (/settings/{group}); each tab is a
// sidebar sub-menu item, so navigating swaps the Inertia prop.
const active = computed(() => props.activeGroup);

// Children still inject this to flag unsaved changes; kept as a harmless no-op
// now that the in-page tab strip (which showed the dirty dots) lives in the
// sidebar instead.
provide('setGroupDirty', () => {});

const groupMeta = (key) => props.groups.find((g) => g.key === key) ?? { label: '', description: '' };
const activeMeta = computed(() => groupMeta(active.value));
</script>

<template>
  <Head :title="`${activeMeta.label} — Cấu hình hệ thống`" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="activeMeta.label || 'Cấu hình hệ thống'"
        :subtitle="activeMeta.description || 'Quản trị nhận diện, đăng nhập, email, thông báo và phân quyền'"
        :icon="activeMeta.icon || 'system-config'"
      />
    </template>

    <div class="flex w-full max-w-none flex-col gap-5">
      <section class="card min-w-0 p-5 md:p-6">
        <FieldsTab
          v-show="active === 'general'"
          group="general"
          :save-hotkeys-enabled="active === 'general'"
          :title="groupMeta('general').label"
          :description="groupMeta('general').description"
          :fields="settings.general ?? []"
          :can-manage="can.manage"
        />
        <FieldsTab
          v-show="active === 'auth'"
          group="auth"
          :save-hotkeys-enabled="active === 'auth'"
          :title="groupMeta('auth').label"
          :description="groupMeta('auth').description"
          :fields="settings.auth ?? []"
          :can-manage="can.manage"
        />
        <FieldsTab
          v-show="active === 'telegram'"
          group="telegram"
          :save-hotkeys-enabled="active === 'telegram'"
          :title="groupMeta('telegram').label"
          :description="groupMeta('telegram').description"
          :fields="settings.telegram ?? []"
          :can-manage="can.manage"
        />
        <EmailTemplateTab
          v-show="active === 'email'"
          :save-hotkeys-enabled="active === 'email'"
          :title="groupMeta('email').label"
          :email-fields="settings.email ?? []"
          :email-templates="emailTemplates"
          :email-preview-brand="emailPreviewBrand"
          :email-test-recipient="emailTestRecipient ?? ''"
          :can-manage="can.manage"
        />
        <FieldsTab
          v-show="active === 'clm'"
          group="clm"
          :save-hotkeys-enabled="active === 'clm'"
          :title="groupMeta('clm').label"
          :description="groupMeta('clm').description"
          :fields="settings.clm ?? []"
          :can-manage="can.manage"
        />
        <PermissionsTab
          v-show="active === 'permissions'"
          :permissions="permissions"
          :can-manage="can.manage"
        />
      </section>
    </div>
  </AppLayout>
</template>
