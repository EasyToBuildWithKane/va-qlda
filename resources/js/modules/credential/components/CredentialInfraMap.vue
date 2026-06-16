<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/shared/composables/useToast';

const props = defineProps({
    credentialId: { type: Number, required: true },
    credentialName: { type: String, default: '' },
    relations: { type: Array, default: () => [] },
    canUpdate: { type: Boolean, default: false },
    linkableCredentials: { type: Array, default: () => [] },
    relationTypes: { type: Array, default: () => [] },
});

const toast = useToast();
const form = ref({
    target_id: '',
    relation_type: 'related',
});

async function addRelation() {
    if (!form.value.target_id) {
        toast.error('Chọn tài khoản liên kết.');
        return;
    }
    try {
        await axios.post(route('api.credentials.relations.store', { credential: props.credentialId }), {
            target_id: Number(form.value.target_id),
            relation_type: form.value.relation_type,
        });
        toast.success('Đã thêm liên kết.');
        router.reload({ preserveScroll: true });
    } catch {
        toast.error('Không thêm được liên kết.');
    }
}

async function removeRelation(relationId) {
    try {
        await axios.delete(route('api.credentials.relations.destroy', {
            credential: props.credentialId,
            relation: relationId,
        }));
        toast.success('Đã xóa liên kết.');
        router.reload({ preserveScroll: true });
    } catch {
        toast.error('Không xóa được liên kết.');
    }
}
</script>

<template>
  <div class="space-y-4">
    <div class="rounded-xl border border-slate-200/80 bg-gradient-to-b from-slate-50/80 to-white p-6">
      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
        Sơ đồ liên kết
      </p>
      <div class="mt-4 flex flex-col items-start gap-2">
        <div class="flex items-center gap-2 rounded-lg bg-brand/10 px-3 py-2 text-sm font-medium text-brand">
          <AppIcon
            name="vault"
            :size="16"
          />
          {{ credentialName }}
        </div>
        <template
          v-for="rel in relations"
          :key="rel.id"
        >
          <AppIcon
            name="chevron-down"
            :size="16"
            class="ml-4 text-slate-400"
          />
          <div class="ml-4 flex w-full max-w-md items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
            <div>
              <span class="text-xs text-slate-500">{{ rel.relation_type?.label }}</span>
              <span class="ml-2 font-medium">{{ rel.target?.name }}</span>
              <span class="text-xs text-slate-400">({{ rel.target?.system_category?.label }})</span>
            </div>
            <button
              v-if="canUpdate"
              type="button"
              class="text-xs text-rose-600 hover:underline"
              @click="removeRelation(rel.id)"
            >
              Xóa
            </button>
          </div>
        </template>
        <p
          v-if="!relations.length"
          class="ml-4 text-sm text-slate-500"
        >
          Chưa liên kết hạ tầng.
        </p>
      </div>
    </div>

    <div
      v-if="canUpdate && linkableCredentials.length"
      class="card p-5"
    >
      <p class="text-sm font-medium">
        Thêm liên kết
      </p>
      <div class="mt-3 grid gap-3 sm:grid-cols-2">
        <select
          v-model="form.target_id"
          class="input h-10 w-full text-sm"
        >
          <option value="">
            Chọn tài khoản / tài sản
          </option>
          <option
            v-for="c in linkableCredentials"
            :key="c.id"
            :value="c.id"
          >
            {{ c.name }} ({{ c.system_category?.label || c.system_category }})
          </option>
        </select>
        <select
          v-model="form.relation_type"
          class="input h-10 w-full text-sm"
        >
          <option
            v-for="t in relationTypes"
            :key="t.value"
            :value="t.value"
          >
            {{ t.label }}
          </option>
        </select>
      </div>
      <button
        type="button"
        class="btn-primary mt-3 h-9 px-4 text-xs"
        @click="addRelation"
      >
        Lưu liên kết
      </button>
    </div>
  </div>
</template>
