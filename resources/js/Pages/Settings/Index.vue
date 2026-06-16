<script setup>
import { ref, provide } from 'vue';
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
const dirtyGroups = ref(new Set());

function setGroupDirty(groupKey, isDirty) {
    const next = new Set(dirtyGroups.value);
    if (isDirty) next.add(groupKey);
    else next.delete(groupKey);
    dirtyGroups.value = next;
}

provide('setGroupDirty', setGroupDirty);

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

    <div class="flex w-full max-w-none flex-col gap-5">
      <nav
        class="grid grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-slate-50/90 p-1 sm:grid-cols-3 lg:grid-cols-5"
        aria-label="Nhóm cấu hình"
      >
        <button
          v-for="g in groups"
          :key="g.key"
          type="button"
          class="relative flex min-w-0 items-center justify-center gap-1.5 rounded-lg px-2 py-2.5 text-center transition-colors sm:gap-2 sm:px-2.5"
          :class="active === g.key
            ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200/80'
            : 'text-slate-600 hover:bg-white/60 hover:text-slate-800'"
          :title="g.description"
          @click="active = g.key"
        >
          <AppIcon
            :name="g.icon"
            :size="15"
            class="shrink-0"
            :class="active === g.key ? 'text-brand' : 'text-slate-400'"
          />
          <span
            class="min-w-0 truncate text-[11px] font-semibold leading-tight sm:text-xs lg:text-[13px]"
            :class="active === g.key ? 'text-brand' : ''"
          >{{ g.label }}</span>
          <span
            v-if="dirtyGroups.has(g.key)"
            class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-amber-400"
            title="Có thay đổi chưa lưu"
          />
        </button>
      </nav>

      <section class="card min-w-0 p-5 md:p-6">
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
          :email-fields="settings.email ?? []"
          :email-templates="emailTemplates"
          :email-preview-brand="emailPreviewBrand"
          :email-test-recipient="emailTestRecipient ?? ''"
          :can-manage="can.manage"
        />
        <FieldsTab
          v-show="active === 'clm'"
          group="clm"
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
