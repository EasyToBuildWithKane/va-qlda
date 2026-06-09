<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

const props = defineProps({
    report: { type: Object, required: true },
});

// Mirrors config/daily_report.php for live preview only (server is authoritative).
const baseWeights = {
    task_completion: 0.30,
    skill_score: 0.20,
    attitude_score: 0.15,
    expertise_score: 0.20,
};
const baseWeightSum = Object.values(baseWeights).reduce((s, w) => s + w, 0);
const KAIZEN_BONUS_MAX = 2;

const baseDimensions = [
    ['task_completion', 'Hoàn thành công việc', 'Mức độ hoàn thành mục tiêu và khối lượng công việc đặt ra trong ngày.'],
    ['skill_score', 'Kỹ năng', 'Kỹ năng chuyên môn và cách xử lý công việc thể hiện trong báo cáo.'],
    ['attitude_score', 'Thái độ', 'Tinh thần trách nhiệm, chủ động, hợp tác và đúng hạn.'],
    ['expertise_score', 'Chuyên môn', 'Chiều sâu chuyên môn và chất lượng giải pháp đưa ra.'],
];

const kaizenDimension = [
    'kaizen_score',
    'Cải tiến (Kaizen)',
    'Tinh thần cầu tiến: đề xuất cải tiến quy trình, cách làm tốt hơn. Điểm slider quy đổi thành điểm cộng thêm (tối đa +2).',
];

const form = useForm({
    task_completion: 10,
    skill_score: 10,
    attitude_score: 10,
    kaizen_score: 10,
    expertise_score: 10,
    notes: '',
});

const rejectForm = useForm({ notes: '' });

const kaizenBonus = computed(
    () => (Number(form.kaizen_score || 0) / 10) * KAIZEN_BONUS_MAX,
);

const total = computed(() => {
    const base = Object.entries(baseWeights).reduce(
        (sum, [key, w]) => sum + Number(form[key] || 0) * (w / baseWeightSum),
        0,
    );
    return base + kaizenBonus.value;
});

const grade = computed(() => {
    const t = total.value;
    if (t >= 9) return 'S';
    if (t >= 8) return 'A';
    if (t >= 6.5) return 'B';
    if (t >= 5) return 'C';
    return 'D';
});

const pct = (key) => Math.round((baseWeights[key] / baseWeightSum) * 100);

const submitScore = () =>
    form.post(`/daily-reports/${props.report.id}/score`, { preserveScroll: true });

const submitReject = () =>
    rejectForm.post(`/daily-reports/${props.report.id}/reject`, { preserveScroll: true });
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="font-display text-sm font-semibold text-slate-800">
        Chấm điểm đánh giá
      </h3>
      <FieldTooltip text="Bốn tiêu chí chính (0–10) tính điểm có trọng số. Kaizen quy đổi thành điểm cộng thêm tối đa +2. Tổng quy đổi xếp loại S/A/B/C/D." />
    </div>

    <div class="grid grid-cols-1 gap-3">
      <div
        v-for="[key, label, hint] in baseDimensions"
        :key="key"
      >
        <div class="mb-1 flex items-center justify-between">
          <label class="flex items-center gap-1.5 text-sm text-slate-600">
            {{ label }}
            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">{{ pct(key) }}%</span>
            <FieldTooltip :text="hint" />
          </label>
          <span class="text-sm font-semibold text-slate-800">{{ Number(form[key]).toFixed(1) }}</span>
        </div>
        <input
          v-model.number="form[key]"
          type="range"
          min="0"
          max="10"
          step="0.5"
          class="w-full accent-brand"
          :title="`${label}: ${Number(form[key]).toFixed(1)} / 10`"
        >
        <p
          v-if="form.errors[key]"
          class="text-xs text-danger"
        >
          {{ form.errors[key] }}
        </p>
      </div>

      <div>
        <div class="mb-1 flex items-center justify-between">
          <label class="flex items-center gap-1.5 text-sm text-slate-600">
            {{ kaizenDimension[1] }}
            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700">+{{ KAIZEN_BONUS_MAX }} điểm</span>
            <FieldTooltip :text="kaizenDimension[2]" />
          </label>
          <span class="text-sm font-semibold text-slate-800">
            {{ Number(form.kaizen_score).toFixed(1) }}
            <span class="text-xs font-medium text-emerald-600">(+{{ kaizenBonus.toFixed(2) }})</span>
          </span>
        </div>
        <input
          v-model.number="form.kaizen_score"
          type="range"
          min="0"
          max="10"
          step="0.5"
          class="w-full accent-brand"
          :title="`${kaizenDimension[1]}: ${Number(form.kaizen_score).toFixed(1)} / 10 → +${kaizenBonus.toFixed(2)} điểm`"
        >
        <p
          v-if="form.errors.kaizen_score"
          class="text-xs text-danger"
        >
          {{ form.errors.kaizen_score }}
        </p>
      </div>
    </div>

    <div class="flex items-center justify-between rounded-card bg-slate-50 px-4 py-3">
      <span class="text-sm text-slate-500">Điểm tổng (tự tính)</span>
      <div class="flex items-center gap-3">
        <span class="font-display text-2xl font-bold text-brand">{{ total.toFixed(2) }}</span>
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">
          {{ grade }}
        </span>
      </div>
    </div>

    <div>
      <label class="label flex items-center gap-1.5">
        Nhận xét của người duyệt
        <span class="text-xs font-normal text-slate-400">(không bắt buộc)</span>
        <FieldTooltip text="Ghi nhận điểm tốt và góp ý để thành viên cải thiện. Nhận xét sẽ hiển thị cho người báo cáo." />
      </label>
      <textarea
        v-model="form.notes"
        rows="2"
        class="input resize-y"
        placeholder="VD: Báo cáo rõ ràng, cần bổ sung số liệu cho phần kết quả…"
      />
    </div>

    <button
      class="btn-primary w-full"
      :disabled="form.processing"
      @click="submitScore"
    >
      Chấm điểm & duyệt báo cáo
    </button>

    <details class="text-sm">
      <summary class="cursor-pointer text-slate-500 hover:text-slate-700">
        Trả lại cho người báo cáo
      </summary>
      <div class="mt-2 space-y-2">
        <label class="label flex items-center gap-1.5">
          Lý do trả lại <span class="text-danger">*</span>
          <FieldTooltip text="Nêu rõ nội dung cần bổ sung / chỉnh sửa để người báo cáo biết cách hoàn thiện." />
        </label>
        <textarea
          v-model="rejectForm.notes"
          rows="2"
          class="input resize-y"
          placeholder="VD: Thiếu phần kế hoạch ngày mai, vui lòng bổ sung…"
        />
        <p
          v-if="rejectForm.errors.notes"
          class="text-xs text-danger"
        >
          {{ rejectForm.errors.notes }}
        </p>
        <button
          class="btn-ghost w-full text-danger"
          :disabled="rejectForm.processing"
          @click="submitReject"
        >
          Trả lại & yêu cầu chỉnh sửa
        </button>
      </div>
    </details>
  </div>
</template>
