<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import MoneyInput from '@/modules/contract/components/MoneyInput.vue';
import VendorFieldLabel from '@/modules/contract/components/VendorFieldLabel.vue';
import { formatMoney, formatDate } from '../composables/useContractFormat.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    contract: { type: Object, default: null },
});

const emit = defineEmits(['close', 'saved']);

const linkDraft = ref('');
const pendingLinks = ref([]);
const autoName = ref(true);

const form = useForm({
    name: '',
    effective_date: '',
    new_expiry: '',
    new_cost: null,
    note: '',
    links: [],
});

/** Tên mặc định backend sẽ tự đặt khi để trống. Khớp ContractRenewalController. */
const autoNamePreview = computed(() => (props.contract ? `${props.contract.name} — Phụ lục` : ''));

watch(() => props.show, (open) => {
    if (!open || !props.contract) return;
    linkDraft.value = '';
    pendingLinks.value = [];
    autoName.value = true;
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

// Khi bật "tự đặt tên", xoá tên tuỳ chỉnh để backend tự sinh.
watch(autoName, (auto) => {
    if (auto) form.name = '';
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
    if (autoName.value) form.name = '';
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
    max-width="max-w-4xl"
    :dirty="form.isDirty || pendingLinks.length > 0"
    @close="emit('close')"
  >
    <div
      v-if="contract"
      class="space-y-5"
    >
      <!-- Current contract summary -->
      <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-sm">
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
        class="space-y-5"
        @submit.prevent="submit"
      >
        <!-- Name + auto toggle -->
        <div>
          <div class="mb-1 flex items-center justify-between gap-2">
            <VendorFieldLabel
              for-id="renew-name"
              label="Tên phụ lục"
              tooltip="Tên hợp đồng phụ lục sẽ tạo. Bật 'Tự đặt tên' để hệ thống tự sinh theo hợp đồng gốc."
            />
            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-slate-500">
              <input
                v-model="autoName"
                type="checkbox"
                class="h-3.5 w-3.5 rounded border-slate-300 text-brand focus:ring-brand"
              >
              Tự đặt tên
            </label>
          </div>
          <input
            id="renew-name"
            :value="autoName ? autoNamePreview : form.name"
            type="text"
            class="input h-10 w-full text-sm"
            :class="autoName ? 'bg-slate-50 text-slate-500' : ''"
            :disabled="autoName"
            :placeholder="autoNamePreview"
            @input="form.name = $event.target.value"
          >
          <p
            v-if="autoName"
            class="mt-1 text-[11px] text-slate-400"
          >
            Hệ thống sẽ tự đặt tên như trên. Tắt để nhập tên tuỳ chỉnh.
          </p>
        </div>

        <!-- 2-col grid: dates + cost/note -->
        <div class="grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2">
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="renew-effective"
              label="Ngày hiệu lực mới"
              tooltip="Ngày phụ lục bắt đầu có hiệu lực — mặc định là ngày hết hạn của HĐ hiện tại."
            />
            <input
              id="renew-effective"
              v-model="form.effective_date"
              type="date"
              class="input h-10 w-full text-sm"
            >
          </div>
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="renew-expiry"
              label="Ngày hết hạn mới"
              required
              tooltip="Ngày hết hạn của phụ lục gia hạn."
            />
            <input
              id="renew-expiry"
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

          <div class="min-w-0">
            <VendorFieldLabel
              for-id="renew-cost"
              label="Chi phí năm mới"
              tooltip="Chi phí năm cho kỳ gia hạn — định dạng VNĐ ngay khi nhập."
            />
            <MoneyInput
              id="renew-cost"
              v-model="form.new_cost"
              placeholder="Nhập chi phí năm mới"
            />
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
          <div class="min-w-0">
            <VendorFieldLabel
              for-id="renew-note"
              label="Ghi chú"
              tooltip="Lý do gia hạn, điều khoản bổ sung…"
            />
            <textarea
              id="renew-note"
              v-model="form.note"
              rows="3"
              class="input w-full text-sm"
              placeholder="VD: gia hạn theo báo giá mới, điều khoản bổ sung…"
            />
          </div>
        </div>

        <!-- Links / Hồ sơ đính kèm -->
        <div>
          <VendorFieldLabel
            for-id="renew-link"
            label="Link hồ sơ phụ lục"
            tooltip="Dán link Google Drive / SharePoint rồi bấm Thêm. Có thể thêm nhiều link."
          />
          <div class="flex gap-2">
            <input
              id="renew-link"
              v-model="linkDraft"
              type="url"
              class="input h-10 flex-1 text-sm"
              placeholder="https://drive.google.com/…"
              @keydown.enter.prevent="addLink"
            >
            <button
              type="button"
              class="btn-primary h-10 shrink-0 gap-1.5 px-4 text-xs"
              :disabled="!linkDraft.trim()"
              @click="addLink"
            >
              <AppIcon
                name="add"
                :size="14"
              /> Thêm
            </button>
          </div>
          <ul
            v-if="pendingLinks.length"
            class="mt-2 space-y-1.5"
          >
            <li
              v-for="(link, idx) in pendingLinks"
              :key="idx"
              class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs"
            >
              <AppIcon
                name="link"
                :size="13"
                class="shrink-0 text-sky-500"
              />
              <span class="min-w-0 flex-1 truncate text-slate-600">{{ link }}</span>
              <button
                type="button"
                class="shrink-0 rounded p-0.5 text-slate-400 hover:bg-rose-50 hover:text-rose-500"
                title="Bỏ link"
                @click="removeLink(idx)"
              >
                <AppIcon
                  name="delete"
                  :size="13"
                />
              </button>
            </li>
          </ul>
          <p
            v-else
            class="mt-1 text-[11px] text-slate-400"
          >
            Chưa có hồ sơ đính kèm. Có thể thêm nhiều link.
          </p>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
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
