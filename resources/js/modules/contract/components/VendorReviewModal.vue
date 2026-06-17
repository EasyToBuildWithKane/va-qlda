<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    vendor: { type: Object, default: null },
    criteria: { type: Array, default: () => [] }, // [{ key, label, hint }]
    recommendationOptions: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const form = useForm({
    reviewed_at: '',
    service_quality: null,
    sla: null,
    speed: null,
    price_satisfaction: null,
    stability: null,
    attitude: null,
    recommendation: null,
    note: '',
});

watch(() => props.show, (open) => {
    if (!open) return;
    form.clearErrors();
    form.defaults({
        reviewed_at: new Date().toISOString().slice(0, 10),
        service_quality: null,
        sla: null,
        speed: null,
        price_satisfaction: null,
        stability: null,
        attitude: null,
        recommendation: null,
        note: '',
    });
    form.reset();
});

const total = computed(() => {
    const vals = props.criteria
        .map((c) => form[c.key])
        .filter((v) => v !== null && v !== '' && !Number.isNaN(Number(v)))
        .map(Number);
    if (!vals.length) return null;
    return Math.round((vals.reduce((s, v) => s + v, 0) / vals.length) * 100) / 100;
});

const totalTone = computed(() => {
    if (total.value === null) return 'text-slate-400';
    if (total.value < 7) return 'text-rose-600';
    if (total.value < 8.5) return 'text-amber-600';
    return 'text-emerald-600';
});

function submit() {
    if (!props.vendor) return;
    form.post(`/contracts/vendors/${props.vendor.id}/reviews`, {
        preserveScroll: true,
        onSuccess: () => { emit('saved'); emit('close'); },
    });
}
</script>

<template>
  <Modal
    :show="show"
    :title="vendor ? `Đánh giá: ${vendor.name}` : 'Đánh giá nhà cung cấp'"
    max-width="max-w-3xl"
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <div class="flex items-center justify-between rounded-card bg-slate-50 px-4 py-3">
        <div>
          <p class="text-[11px] uppercase tracking-wide text-slate-500">
            Điểm tổng (trung bình 6 tiêu chí)
          </p>
          <p
            class="font-display text-3xl font-bold tabular-nums"
            :class="totalTone"
          >
            {{ total ?? '—' }}<span class="text-base font-normal text-slate-400"> /10</span>
          </p>
        </div>
        <p class="max-w-[14rem] text-[11px] text-slate-500">
          Thang điểm 0–10. NCC có điểm dưới 7 sẽ được gắn cờ cảnh báo trên dashboard.
        </p>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div
          v-for="c in criteria"
          :key="c.key"
        >
          <label class="label">{{ c.label }}</label>
          <input
            v-model="form[c.key]"
            type="number"
            min="0"
            max="10"
            step="0.5"
            class="input"
            placeholder="0–10"
          >
          <p class="mt-1 text-[11px] text-slate-400">
            {{ c.hint }}
          </p>
          <p
            v-if="form.errors[c.key]"
            class="mt-1 text-xs text-rose-600"
          >
            {{ form.errors[c.key] }}
          </p>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="label">Ngày đánh giá</label>
          <input
            v-model="form.reviewed_at"
            type="date"
            class="input"
          >
        </div>
        <div>
          <label class="label">Đề xuất</label>
          <select
            v-model="form.recommendation"
            class="input"
          >
            <option :value="null">
              — Chọn đề xuất —
            </option>
            <option
              v-for="o in recommendationOptions"
              :key="o.value"
              :value="o.value"
            >
              {{ o.label }}
            </option>
          </select>
        </div>
      </div>

      <div>
        <label class="label">Ghi chú</label>
        <textarea
          v-model="form.note"
          rows="2"
          class="input"
          placeholder="Nhận xét, lý do, khuyến nghị cụ thể…"
        />
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost"
          @click="emit('close')"
        >
          Đóng
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Đang lưu…' : 'Lưu đánh giá' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
