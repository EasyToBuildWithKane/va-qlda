<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { formatMoney, formatDate } from '../composables/useContractFormat.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    contract: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const linkDraft = ref('');
const pendingLinks = ref([]);

const form = useForm({
    name: '',
    effective_date: '',
    new_expiry: '',
    new_cost: null,
    note: '',
    links: [],
});

watch(() => props.show, (open) => {
    if (!open || !props.contract) return;
    linkDraft.value = '';
    pendingLinks.value = [];
    form.clearErrors();
    form.defaults({
        name: '',
        effective_date: props.contract.expiry_date ?? '',
        new_expiry: suggestNextExpiry(props.contract),
        new_cost: props.contract.annual_cost ?? null,
        note: '',
        links: [],
    });
    form.reset();
});

function suggestNextExpiry(c) {
    const base = c.expiry_date ? new Date(`${c.expiry_date}T00:00:00`) : new Date();
    const months = c.renewal_term_months || 12;
    const d = new Date(base);
    d.setMonth(d.getMonth() + months);
    return d.toISOString().slice(0, 10);
}

const costDelta = computed(() => {
    const oldCost = Number(props.contract?.annual_cost || 0);
    const newCost = Number(form.new_cost || 0);
    if (!oldCost || !newCost) return null;
    const pct = ((newCost - oldCost) / oldCost) * 100;
    return { diff: newCost - oldCost, pct: Math.round(pct * 10) / 10 };
});

function addLink() {
    const url = linkDraft.value.trim();
    if (!url) return;
    if (!pendingLinks.value.includes(url)) {
        pendingLinks.value.push(url);
        form.links = [...pendingLinks.value];
    }
    linkDraft.value = '';
}

function removeLink(idx) {
    pendingLinks.value.splice(idx, 1);
    form.links = [...pendingLinks.value];
}

function submit() {
    form.links = [...pendingLinks.value];
    form.post(`/contracts/${props.contract.id}/renewals`, {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); emit('close'); },
    });
}
</script>

<template>
  <Modal
    :show="show"
    title="Gia hạn — Tạo phụ lục mới"
    max-width="max-w-2xl"
    :dirty="form.isDirty || pendingLinks.length > 0"
    @close="emit('close')"
  >
    <div
      v-if="contract"
      class="space-y-4"
    >
      <!-- Current contract summary -->
      <div class="rounded-lg bg-slate-50 p-3 text-sm">
        <p class="font-semibold text-slate-800">
          {{ contract.name }}
        </p>
        <p class="text-xs text-slate-400">
          {{ contract.code }}
        </p>
        <dl class="mt-2 grid grid-cols-2 gap-2">
          <div>
            <dt class="text-xs text-slate-400">
              Hết hạn hiện tại
            </dt>
            <dd class="font-medium text-slate-700">
              {{ contract.expiry_date ? formatDate(contract.expiry_date) : 'Chưa có' }}
            </dd>
          </div>
          <div>
            <dt class="text-xs text-slate-400">
              Chi phí năm hiện tại
            </dt>
            <dd class="font-medium text-slate-700">
              {{ formatMoney(contract.annual_cost, contract.currency) }}
            </dd>
          </div>
        </dl>
      </div>

      <form
        class="space-y-4"
        @submit.prevent="submit"
      >
        <!-- Name -->
        <div>
          <label class="label">Tên phụ lục (để trống = tự đặt)</label>
          <input
            v-model="form.name"
            type="text"
            class="input h-10 w-full text-sm"
            :placeholder="`${contract.name} — Phụ lục`"
          >
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="label">Ngày hiệu lực mới</label>
            <input
              v-model="form.effective_date"
              type="date"
              class="input h-10 w-full text-sm"
            >
          </div>
          <div>
            <label class="label">Ngày hết hạn mới *</label>
            <input
              v-model="form.new_expiry"
              type="date"
              class="input h-10 w-full text-sm"
            >
            <p
              v-if="form.errors.new_expiry"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.new_expiry }}
            </p>
          </div>
        </div>

        <!-- Cost -->
        <div>
          <label class="label">Chi phí năm mới (VNĐ)</label>
          <input
            v-model="form.new_cost"
            type="number"
            min="0"
            class="input h-10 w-full text-sm"
          >
          <p
            v-if="costDelta"
            class="mt-1 text-xs"
            :class="costDelta.diff > 0 ? 'text-rose-600' : 'text-emerald-600'"
          >
            {{ costDelta.diff > 0 ? '▲' : '▼' }}
            {{ formatMoney(Math.abs(costDelta.diff), contract.currency) }}
            ({{ costDelta.pct > 0 ? '+' : '' }}{{ costDelta.pct }}%) so với hiện tại
          </p>
        </div>

        <!-- Note -->
        <div>
          <label class="label">Ghi chú</label>
          <textarea
            v-model="form.note"
            rows="2"
            class="input w-full text-sm"
            placeholder="VD: gia hạn theo báo giá mới, điều khoản bổ sung…"
          />
        </div>

        <!-- Links / Hồ sơ đính kèm -->
        <div>
          <label class="label">Link hồ sơ phụ lục (Google Drive, SharePoint…)</label>
          <div class="flex gap-2">
            <input
              v-model="linkDraft"
              type="url"
              class="input h-9 flex-1 text-sm"
              placeholder="https://drive.google.com/…"
              @keydown.enter.prevent="addLink"
            >
            <button
              type="button"
              class="btn-ghost h-9 shrink-0 px-3 text-xs"
              @click="addLink"
            >
              <AppIcon
                name="add"
                :size="13"
              /> Thêm
            </button>
          </div>
          <ul
            v-if="pendingLinks.length"
            class="mt-2 space-y-1"
          >
            <li
              v-for="(link, idx) in pendingLinks"
              :key="idx"
              class="flex items-center gap-2 rounded bg-slate-50 px-2.5 py-1.5 text-xs"
            >
              <AppIcon
                name="link"
                :size="12"
                class="shrink-0 text-sky-500"
              />
              <span class="min-w-0 flex-1 truncate text-slate-600">{{ link }}</span>
              <button
                type="button"
                class="shrink-0 text-slate-400 hover:text-rose-500"
                @click="removeLink(idx)"
              >
                <AppIcon
                  name="delete"
                  :size="12"
                />
              </button>
            </li>
          </ul>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button
            type="button"
            class="btn-ghost"
            @click="emit('close')"
          >
            Huỷ
          </button>
          <button
            type="submit"
            class="btn-primary"
            :disabled="form.processing"
          >
            {{ form.processing ? 'Đang tạo…' : 'Tạo phụ lục gia hạn' }}
          </button>
        </div>
      </form>
    </div>
  </Modal>
</template>
