<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

const props = defineProps({
    show: Boolean,
    options: { type: Object, required: true },
    proposalDefaults: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const activeSection = ref('general');
const copyRecipientFromProposer = ref(true);
const showPreview = ref(false);

const SECTIONS = [
    { key: 'general', label: 'Thông tin chung', icon: 'info' },
    { key: 'content', label: 'Nội dung đề xuất', icon: 'edit' },
    { key: 'budget', label: 'Chi phí & Ngân sách', icon: 'money' },
    { key: 'account', label: 'Thông tin tài khoản', icon: 'account' },
    { key: 'preview', label: 'Xem trước Phiếu', icon: 'pdf' },
];

const form = reactive({
    proposal_type: 'ai_account',
    subject_about: '',
    send_to: '',
    proposer_name: '',
    proposer_position: '',
    proposer_department: '',
    tool_name: '',
    vendor_name: '',
    vendor_website: '',
    group_function: 'DEV',
    license_type: 'Pro',
    cost_amount: '',
    cost_unit: 'monthly',
    quantity: '1',
    seats: '',
    proposal_content: '',
    description: '',
    reason_for_proposal: '',
    expected_benefit: '',
    objectives: '',
    staff_count: '1',
    users_list_raw: '',
    department_using: '',
    recipient_name: '',
    recipient_position: '',
    recipient_email: '',
    recipient_phone: '',
    purchase_type: 'new',
    registration_email: '',
    planned_use_date: '',
    start_date: '',
    end_date: '',
});

const costPreviewAmount = computed(() => {
    const n = parseInt(String(form.cost_amount).replace(/\D/g, ''), 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
});

const monthlyCost = computed(() => {
    const n = costPreviewAmount.value;
    if (!n) return 0;
    if (form.cost_unit === 'yearly') return Math.round(n / 12);
    if (form.cost_unit === 'quarterly') return Math.round(n / 3);
    if (form.cost_unit === 'one_time') return 0;
    return n;
});

const today = computed(() => new Date().toLocaleDateString('vi-VN', {
    weekday: 'long', day: 'numeric', month: 'numeric', year: 'numeric',
}));

const objectiveLines = computed(() =>
    form.objectives.split('\n').map(l => l.trim()).filter(Boolean)
);

const usersListArray = computed(() =>
    form.users_list_raw.split('\n').map(l => l.trim()).filter(Boolean)
);

function defaultForm() {
    const d = props.proposalDefaults ?? {};
    return {
        proposal_type: 'ai_account',
        subject_about: '',
        send_to: d.send_to ?? 'Ban Giám đốc',
        proposer_name: d.proposer_name ?? '',
        proposer_position: d.proposer_position ?? '',
        proposer_department: d.proposer_department ?? '',
        tool_name: '',
        vendor_name: '',
        vendor_website: '',
        group_function: 'DEV',
        license_type: 'Pro',
        cost_amount: '',
        cost_unit: 'monthly',
        quantity: '1',
        seats: '',
        proposal_content: '',
        description: '',
        reason_for_proposal: '',
        expected_benefit: '',
        objectives: d.objectives ?? '',
        staff_count: '1',
        users_list_raw: '',
        department_using: d.proposer_department ?? '',
        recipient_name: d.proposer_name ?? '',
        recipient_position: d.proposer_position ?? '',
        recipient_email: d.recipient_email ?? '',
        recipient_phone: d.recipient_phone ?? '',
        purchase_type: 'new',
        registration_email: '',
        planned_use_date: '',
        start_date: '',
        end_date: '',
    };
}

watch(() => props.show, (open) => {
    if (!open) return;
    dirty.value = false;
    activeSection.value = 'general';
    showPreview.value = false;
    copyRecipientFromProposer.value = true;
    Object.assign(form, defaultForm());
});

watch(() => form.tool_name, (name) => {
    if (!form.subject_about && name?.trim()) {
        form.subject_about = `Đăng ký sử dụng ${name.trim()}`;
    }
});

watch(copyRecipientFromProposer, (on) => {
    if (!on) return;
    syncRecipient();
});

watch(() => [form.proposer_name, form.proposer_position], () => {
    if (copyRecipientFromProposer.value) syncRecipient();
});

function syncRecipient() {
    form.recipient_name = form.proposer_name;
    form.recipient_position = form.proposer_position;
    form.recipient_email = props.proposalDefaults?.recipient_email ?? form.recipient_email;
    form.recipient_phone = props.proposalDefaults?.recipient_phone ?? form.recipient_phone;
}

function onInput() { dirty.value = true; }

function handleSubmit() {
    emit('submit', {
        proposal_type: form.proposal_type || undefined,
        subject_about: form.subject_about.trim(),
        send_to: form.send_to.trim() || undefined,
        tool_name: form.tool_name.trim(),
        vendor_name: form.vendor_name.trim() || undefined,
        vendor_website: form.vendor_website.trim() || undefined,
        group_function: form.group_function,
        license_type: form.license_type.trim(),
        cost_amount: parseInt(form.cost_amount, 10),
        cost_unit: form.cost_unit,
        quantity: form.quantity ? parseInt(form.quantity, 10) : 1,
        seats: form.seats ? parseInt(form.seats, 10) : null,
        proposer_name: form.proposer_name.trim(),
        proposer_position: form.proposer_position.trim() || undefined,
        proposer_department: form.proposer_department.trim() || undefined,
        proposal_content: form.proposal_content.trim(),
        description: form.description.trim() || undefined,
        reason_for_proposal: form.reason_for_proposal.trim() || undefined,
        expected_benefit: form.expected_benefit.trim() || undefined,
        objectives: form.objectives.trim() || undefined,
        staff_count: form.staff_count ? parseInt(form.staff_count, 10) : 1,
        users_list: usersListArray.value.length ? usersListArray.value : undefined,
        department_using: form.department_using.trim() || undefined,
        recipient_name: form.recipient_name.trim() || undefined,
        recipient_position: form.recipient_position.trim() || undefined,
        recipient_email: form.recipient_email.trim() || undefined,
        recipient_phone: form.recipient_phone.trim() || undefined,
        purchase_type: form.purchase_type,
        registration_email: form.registration_email.trim() || undefined,
        planned_use_date: form.planned_use_date || undefined,
        start_date: form.start_date || undefined,
        end_date: form.end_date || undefined,
    });
}

function goSection(key) {
    activeSection.value = key;
    if (key === 'preview') showPreview.value = true;
}
</script>

<template>
  <Modal
    :show="show"
    title="Thêm Phiếu Đề Xuất"
    max-width="max-w-6xl"
    :dirty="dirty"
    @close="emit('close')"
  >
    <div class="flex min-h-[600px] gap-0 -mx-6 -mb-6">
      <!-- ── Sidebar nav ── -->
      <nav class="w-44 shrink-0 border-r border-slate-100 bg-slate-50 px-2 py-3">
        <button
          v-for="s in SECTIONS"
          :key="s.key"
          type="button"
          class="mb-0.5 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition"
          :class="activeSection === s.key
            ? 'bg-brand text-white font-medium'
            : 'text-slate-600 hover:bg-slate-200'"
          @click="goSection(s.key)"
        >
          <AppIcon
            :name="s.icon"
            :size="14"
            class="shrink-0"
          />
          {{ s.label }}
        </button>
      </nav>

      <!-- ── Form area ── -->
      <div class="flex min-w-0 flex-1 flex-col">
        <form
          class="flex flex-1 flex-col overflow-hidden"
          @submit.prevent="handleSubmit"
        >
          <div class="flex-1 overflow-y-auto px-6 py-5">
            <!-- ── SECTION: Thông tin chung ── -->
            <div v-show="activeSection === 'general'">
              <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Thông tin chung
              </p>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <!-- Loại đề xuất -->
                <div class="sm:col-span-2">
                  <label class="label">Loại đề xuất <span class="text-danger">*</span></label>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="t in options.proposal_type"
                      :key="t.value"
                      type="button"
                      class="rounded-full border px-3 py-1 text-xs font-medium transition"
                      :class="form.proposal_type === t.value
                        ? 'border-brand bg-brand text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-brand/40'"
                      @click="form.proposal_type = t.value; onInput()"
                    >
                      {{ t.label }}
                    </button>
                  </div>
                </div>
                <!-- Trích yếu -->
                <div class="sm:col-span-2">
                  <label class="label flex items-center gap-1">
                    Về việc (trích yếu) <span class="text-danger">*</span>
                    <FieldTooltip text="Dòng «Về việc» trên phiếu PDX." />
                  </label>
                  <input
                    v-model="form.subject_about"
                    type="text"
                    required
                    class="input w-full"
                    placeholder="Đăng ký sử dụng Cursor Pro"
                    @input="onInput"
                  >
                </div>
                <!-- Kính gửi -->
                <div class="sm:col-span-2">
                  <label class="label">Kính gửi</label>
                  <input
                    v-model="form.send_to"
                    type="text"
                    class="input w-full"
                    placeholder="Phòng Công nghệ & Phòng Kế Toán"
                    @input="onInput"
                  >
                </div>
                <!-- Họ tên -->
                <div>
                  <label class="label">Họ &amp; tên người đề xuất <span class="text-danger">*</span></label>
                  <input
                    v-model="form.proposer_name"
                    type="text"
                    required
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <!-- Chức vụ -->
                <div>
                  <label class="label">Chức vụ</label>
                  <input
                    v-model="form.proposer_position"
                    type="text"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <!-- Phòng ban -->
                <div class="sm:col-span-2">
                  <label class="label">Đơn vị / Phòng ban</label>
                  <input
                    v-model="form.proposer_department"
                    type="text"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
              </div>
            </div>

            <!-- ── SECTION: Nội dung đề xuất ── -->
            <div v-show="activeSection === 'content'">
              <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Nội dung đề xuất
              </p>
              <div class="space-y-4">
                <div>
                  <label class="label">Tên đề xuất / Sản phẩm <span class="text-danger">*</span></label>
                  <input
                    v-model="form.tool_name"
                    type="text"
                    required
                    class="input w-full"
                    placeholder="VD: Cursor AI Pro, Adobe CC, Figma Org…"
                    @input="onInput"
                  >
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <label class="label">Nhà cung cấp</label>
                    <input
                      v-model="form.vendor_name"
                      type="text"
                      class="input w-full"
                      @input="onInput"
                    >
                  </div>
                  <div>
                    <label class="label">Website</label>
                    <input
                      v-model="form.vendor_website"
                      type="url"
                      class="input w-full"
                      placeholder="https://..."
                      @input="onInput"
                    >
                  </div>
                </div>
                <div>
                  <label class="label">Nội dung đề xuất <span class="text-danger">*</span>
                    <span class="ml-1 text-xs font-normal text-slate-400">(tối thiểu 20 ký tự)</span>
                  </label>
                  <textarea
                    v-model="form.proposal_content"
                    rows="4"
                    required
                    minlength="20"
                    class="input w-full"
                    placeholder="Mô tả tóm tắt đề xuất…"
                    @input="onInput"
                  />
                </div>
                <div>
                  <label class="label">Mô tả chi tiết</label>
                  <textarea
                    v-model="form.description"
                    rows="3"
                    class="input w-full"
                    placeholder="Mô tả chi tiết thêm về sản phẩm / dịch vụ…"
                    @input="onInput"
                  />
                </div>
                <div>
                  <label class="label">Lý do đề xuất</label>
                  <textarea
                    v-model="form.reason_for_proposal"
                    rows="3"
                    class="input w-full"
                    placeholder="Tại sao cần sản phẩm/dịch vụ này?"
                    @input="onInput"
                  />
                </div>
                <div>
                  <label class="label">Mục tiêu <span class="text-xs font-normal text-slate-400">(mỗi dòng một ý)</span></label>
                  <textarea
                    v-model="form.objectives"
                    rows="4"
                    class="input w-full font-mono text-sm"
                    placeholder="Tăng tốc quá trình phân tích&#10;Hỗ trợ xây dựng Wireframe…"
                    @input="onInput"
                  />
                </div>
                <div>
                  <label class="label">Hiệu quả mong đợi</label>
                  <textarea
                    v-model="form.expected_benefit"
                    rows="3"
                    class="input w-full"
                    placeholder="Kỳ vọng tiết kiệm bao nhiêu % thời gian, nâng cao chất lượng như thế nào…"
                    @input="onInput"
                  />
                </div>
              </div>
            </div>

            <!-- ── SECTION: Chi phí & Ngân sách ── -->
            <div v-show="activeSection === 'budget'">
              <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Chi phí &amp; Ngân sách
              </p>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label class="label">Nhóm chức năng <span class="text-danger">*</span></label>
                  <select
                    v-model="form.group_function"
                    required
                    class="input w-full"
                    @change="onInput"
                  >
                    <option
                      v-for="o in options.group_function"
                      :key="o.value"
                      :value="o.value"
                    >
                      {{ o.label }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="label">Loại license / Gói <span class="text-danger">*</span></label>
                  <input
                    v-model="form.license_type"
                    type="text"
                    required
                    list="proposal-license-types"
                    class="input w-full"
                    @input="onInput"
                  >
                  <datalist id="proposal-license-types">
                    <option
                      v-for="t in options.license_types"
                      :key="t"
                      :value="t"
                    />
                  </datalist>
                </div>
                <div>
                  <label class="label">Chi phí (VNĐ) <span class="text-danger">*</span></label>
                  <input
                    v-model="form.cost_amount"
                    type="number"
                    min="1"
                    required
                    class="input w-full"
                    @input="onInput"
                  >
                  <p
                    v-if="costPreviewAmount"
                    class="mt-1 text-xs text-slate-500"
                  >
                    <VndAmount
                      :amount="costPreviewAmount"
                      inline
                    /> / {{ form.cost_unit === 'yearly' ? 'năm' : form.cost_unit === 'quarterly' ? 'quý' : form.cost_unit === 'one_time' ? 'một lần' : 'tháng' }}
                    <span
                      v-if="monthlyCost && form.cost_unit !== 'monthly' && form.cost_unit !== 'one_time'"
                      class="ml-2 text-slate-400"
                    >
                      ≈ <VndAmount
                        :amount="monthlyCost"
                        inline
                      /> / tháng
                    </span>
                  </p>
                </div>
                <div>
                  <label class="label">Chu kỳ thanh toán <span class="text-danger">*</span></label>
                  <select
                    v-model="form.cost_unit"
                    required
                    class="input w-full"
                    @change="onInput"
                  >
                    <option
                      v-for="o in options.cost_unit"
                      :key="o.value"
                      :value="o.value"
                    >
                      {{ o.label }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="label">Số lượng (bảng ngân sách)</label>
                  <input
                    v-model="form.quantity"
                    type="number"
                    min="1"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Tình trạng <span class="text-danger">*</span></label>
                  <select
                    v-model="form.purchase_type"
                    required
                    class="input w-full"
                    @change="onInput"
                  >
                    <option
                      v-for="o in options.purchase_type"
                      :key="o.value"
                      :value="o.value"
                    >
                      {{ o.label }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="label">Ngày đưa vào sử dụng (dự kiến)</label>
                  <input
                    v-model="form.planned_use_date"
                    type="date"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Email đăng ký tài khoản</label>
                  <input
                    v-model="form.registration_email"
                    type="email"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
              </div>
            </div>

            <!-- ── SECTION: Thông tin tài khoản ── -->
            <div v-show="activeSection === 'account'">
              <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Thông tin tài khoản &amp; Người dùng
              </p>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <label class="label">Số lượng tài khoản / Seats</label>
                  <input
                    v-model="form.seats"
                    type="number"
                    min="1"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Số nhân sự sử dụng</label>
                  <input
                    v-model="form.staff_count"
                    type="number"
                    min="1"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Bộ phận sử dụng</label>
                  <input
                    v-model="form.department_using"
                    type="text"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div class="sm:col-span-2">
                  <label class="label">Danh sách người sử dụng <span class="text-xs font-normal text-slate-400">(mỗi dòng một tên)</span></label>
                  <textarea
                    v-model="form.users_list_raw"
                    rows="4"
                    class="input w-full font-mono text-sm"
                    placeholder="Nguyễn Văn A&#10;Trần Thị B&#10;…"
                    @input="onInput"
                  />
                </div>
                <div>
                  <label class="label">Ngày bắt đầu</label>
                  <input
                    v-model="form.start_date"
                    type="date"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Ngày kết thúc</label>
                  <input
                    v-model="form.end_date"
                    type="date"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div class="sm:col-span-2">
                  <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                    Nhân sự tiếp nhận
                  </p>
                  <label class="mb-3 flex items-center gap-2 text-sm text-slate-600">
                    <input
                      v-model="copyRecipientFromProposer"
                      type="checkbox"
                      class="rounded border-slate-300"
                    >
                    Trùng thông tin người đề xuất
                  </label>
                </div>
                <div>
                  <label class="label">Họ &amp; tên tiếp nhận</label>
                  <input
                    v-model="form.recipient_name"
                    type="text"
                    class="input w-full"
                    :disabled="copyRecipientFromProposer"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Chức vụ</label>
                  <input
                    v-model="form.recipient_position"
                    type="text"
                    class="input w-full"
                    :disabled="copyRecipientFromProposer"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Email</label>
                  <input
                    v-model="form.recipient_email"
                    type="email"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <label class="label">Điện thoại</label>
                  <input
                    v-model="form.recipient_phone"
                    type="text"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
              </div>
            </div>

            <!-- ── SECTION: Preview ── -->
            <div v-show="activeSection === 'preview'">
              <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Xem trước Phiếu Đề Xuất
              </p>

              <!-- PDX Preview -->
              <div
                class="rounded-xl border border-slate-200 bg-white p-6 text-[13px] leading-relaxed"
                style="font-family:'Times New Roman',serif; color:#111;"
              >
                <!-- Header -->
                <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
                  <tr>
                    <td style="width:42%;text-align:center;font-weight:bold;font-size:13px;vertical-align:top;padding:2px;">
                      HỆ THỐNG TRƯỜNG VIỆT MỸ<br>
                      <span style="font-weight:normal;font-size:11px;">—<br>PHÒNG CÔNG NGHỆ</span>
                    </td>
                    <td style="width:58%;text-align:center;vertical-align:top;padding:2px;">
                      <div style="font-weight:bold;font-size:13px;">
                        CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
                      </div>
                      <div style="text-decoration:underline;">
                        Độc lập – Tự do – Hạnh phúc
                      </div>
                      <div style="font-style:italic;font-size:11px;">
                        {{ today }}
                      </div>
                    </td>
                  </tr>
                </table>

                <!-- Title -->
                <div style="text-align:center;margin:12px 0 6px;">
                  <div style="font-weight:bold;font-size:15px;letter-spacing:1px;">
                    PHIẾU ĐỀ XUẤT
                  </div>
                  <div style="font-style:italic;font-size:12px;">
                    (Về việc: {{ form.subject_about || '…' }})
                  </div>
                </div>

                <!-- Kính gửi -->
                <div style="margin-left:32px;margin-bottom:6px;">
                  <span style="font-style:italic;font-weight:bold;">Kính gửi:</span>
                  &nbsp;&nbsp;&nbsp;Ban Giám đốc<br>
                  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ form.send_to || 'Phòng Công nghệ &amp; Phòng Kế Toán' }}
                </div>

                <!-- 1. Đại diện -->
                <div style="margin-bottom:4px;">
                  <p style="font-weight:bold;margin:6px 0 2px;">
                    1. Đại diện:
                  </p>
                  <div style="margin-left:18px;">
                    <div><strong>Họ &amp; Tên:</strong> {{ form.proposer_name || '—' }}</div>
                    <div><strong>Chức vụ:</strong> {{ form.proposer_position || '—' }}</div>
                    <div><strong>Đơn vị / Phòng ban:</strong> {{ form.proposer_department || '—' }}</div>
                  </div>
                </div>

                <!-- 2. Nội dung -->
                <div style="margin-bottom:4px;">
                  <p style="font-weight:bold;margin:6px 0 2px;">
                    2. Nội dung đề xuất:
                  </p>
                  <div style="margin-left:18px;white-space:pre-wrap;">
                    {{ form.proposal_content || '…' }}
                  </div>
                </div>

                <!-- 3. Mục tiêu -->
                <div
                  v-if="objectiveLines.length"
                  style="margin-bottom:4px;"
                >
                  <p style="font-weight:bold;margin:6px 0 2px;">
                    3. Mục tiêu:
                  </p>
                  <ul style="margin-left:32px;padding:0;">
                    <li
                      v-for="(line, idx) in objectiveLines"
                      :key="idx"
                    >
                      {{ line }}
                    </li>
                  </ul>
                </div>

                <!-- 4. Thông tin chi tiết -->
                <div style="margin-bottom:4px;">
                  <p style="font-weight:bold;margin:6px 0 2px;">
                    4. Thông tin chi tiết:
                  </p>
                  <p style="margin-left:18px;font-weight:bold;">
                    4.1 Ngân sách dự kiến:
                  </p>
                  <table style="width:100%;border-collapse:collapse;margin:4px 0;font-size:12px;">
                    <thead>
                      <tr style="background:#f5f5f5;">
                        <th style="border:1px solid #333;padding:4px 6px;text-align:center;">
                          STT
                        </th>
                        <th style="border:1px solid #333;padding:4px 6px;">
                          Sản phẩm / Công cụ
                        </th>
                        <th style="border:1px solid #333;padding:4px 6px;text-align:center;">
                          SL
                        </th>
                        <th style="border:1px solid #333;padding:4px 6px;text-align:center;">
                          Thành tiền (VNĐ/Tháng)
                        </th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td style="border:1px solid #333;padding:4px 6px;text-align:center;">
                          1
                        </td>
                        <td style="border:1px solid #333;padding:4px 6px;">
                          {{ form.tool_name || '—' }} - {{ form.license_type || '—' }}
                        </td>
                        <td style="border:1px solid #333;padding:4px 6px;text-align:center;">
                          {{ form.quantity || '01' }}
                        </td>
                        <td style="border:1px solid #333;padding:4px 6px;text-align:center;">
                          <VndAmount
                            v-if="monthlyCost"
                            :amount="monthlyCost"
                            inline
                          />
                          <span v-else>~</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                  <p style="margin-left:18px;">
                    <strong>4.2 Số lượng nhân sự sử dụng:</strong> {{ form.staff_count || '01' }} nhân sự
                  </p>
                  <p style="margin-left:18px;font-weight:bold;">
                    4.3 Nhân sự tiếp nhận:
                  </p>
                  <div style="margin-left:36px;">
                    <div><strong>Họ &amp; Tên:</strong> {{ form.recipient_name || '—' }}</div>
                    <div><strong>Chức vụ:</strong> {{ form.recipient_position || '—' }}</div>
                    <div><strong>Email:</strong> {{ form.recipient_email || '—' }}</div>
                    <div><strong>SĐT:</strong> {{ form.recipient_phone || '—' }}</div>
                  </div>
                  <p style="margin-left:18px;">
                    <strong>4.4 Tình trạng:</strong>
                    ☑ {{ form.purchase_type === 'new' ? 'Mua mới' : '' }}
                    <span v-if="form.purchase_type !== 'new'"> ☑ Gia hạn</span>
                  </p>
                  <p
                    v-if="form.registration_email"
                    style="margin-left:18px;"
                  >
                    <strong>4.5 Thông tin bổ sung:</strong> Email đăng ký: {{ form.registration_email }}
                  </p>
                </div>

                <!-- 5. Thời gian -->
                <div>
                  <p style="font-weight:bold;margin:6px 0 2px;">
                    5. Thời gian đưa vào sử dụng (dự kiến):
                    <span style="color:#cc0000;">{{ form.planned_use_date || '…' }}</span>
                  </p>
                </div>

                <p style="margin-top:10px;font-style:italic;">
                  Kính trình Ban Lãnh Đạo xem xét và phê duyệt.
                </p>

                <!-- Signature row -->
                <table style="width:100%;border-collapse:collapse;margin-top:16px;font-size:12px;">
                  <tr>
                    <td style="border:1px dotted #666;padding:6px 8px;text-align:center;width:33%;">
                      <strong>Người đề xuất</strong>
                      <div style="height:50px;" />
                      <strong>{{ form.proposer_name || '—' }}</strong>
                    </td>
                    <td style="border:1px dotted #666;padding:6px;width:33%;" />
                    <td style="border:1px dotted #666;padding:6px 8px;text-align:center;width:34%;">
                      <strong>Phòng Công nghệ</strong><br><strong>Trưởng phòng</strong>
                      <div style="height:50px;" />
                      <strong>Bùi Quang Toàn</strong>
                    </td>
                  </tr>
                </table>
              </div>

              <div class="mt-3 flex gap-2 text-sm">
                <p class="text-slate-400">
                  Bản xem trước cập nhật theo thời gian thực. PDF chính xác sẽ được tạo sau khi gửi phiếu.
                </p>
              </div>
            </div>
          </div>

          <!-- ── Footer action bar ── -->
          <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/60 px-6 py-3">
            <div class="flex gap-2">
              <button
                v-for="s in SECTIONS.filter(s => s.key !== 'preview')"
                :key="s.key"
                type="button"
                class="h-2 w-2 rounded-full transition"
                :class="activeSection === s.key ? 'bg-brand' : 'bg-slate-300'"
                :title="s.label"
                @click="activeSection = s.key"
              />
            </div>
            <div class="flex gap-2">
              <button
                type="button"
                class="btn-ghost border border-slate-200 text-sm"
                @click="activeSection === SECTIONS[0].key ? emit('close') : activeSection = SECTIONS[Math.max(0, SECTIONS.findIndex(s => s.key === activeSection) - 1)].key"
              >
                {{ activeSection === SECTIONS[0].key ? 'Huỷ' : 'Quay lại' }}
              </button>
              <button
                v-if="activeSection !== 'preview' && SECTIONS.findIndex(s => s.key === activeSection) < SECTIONS.length - 1"
                type="button"
                class="btn-secondary text-sm"
                @click="activeSection = SECTIONS[Math.min(SECTIONS.length - 1, SECTIONS.findIndex(s => s.key === activeSection) + 1)].key"
              >
                Tiếp theo
              </button>
              <button
                type="submit"
                class="btn-primary text-sm"
              >
                <AppIcon
                  name="send"
                  :size="14"
                  class="mr-1"
                />
                Gửi phiếu đề xuất
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </Modal>
</template>
