<script setup>
/* eslint-disable vue/no-v-html -- server-rendered proposal preview HTML from authenticated API */
import { computed, reactive, ref, watch } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import VndAmount from '@/modules/aiAccount/components/VndAmount.vue';
import ProposalFormLabel from '@/modules/aiAccount/components/ProposalFormLabel.vue';
import { PROPOSAL_FORM_HINTS as H } from '@/modules/aiAccount/config/proposalFormHints';
import ProposerEmployeePick from '@/modules/aiAccount/components/ProposerEmployeePick.vue';
import { useProposalPdfPreview } from '@/modules/aiAccount/composables/useProposalPdfPreview';
import MoneyInput from '@/shared/ui/MoneyInput.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

const props = defineProps({
    show: Boolean,
    options: { type: Object, required: true },
    proposalDefaults: { type: Object, default: () => ({}) },
    formLookups: {
        type: Object,
        default: () => ({
            employees: [],
            departments: [],
            tools: [],
            vendors: [],
            send_to: [],
            account_templates: [],
        }),
    },
});

const emit = defineEmits(['close', 'submit']);

const dirty = ref(false);
const activeSection = ref('general');
const copyRecipientFromProposer = ref(true);
const selectedProposerId = ref(null);

const departmentNames = computed(() =>
    (props.formLookups.departments ?? []).map((d) => d.name).filter(Boolean),
);

const employeeNameSuggestions = computed(() =>
    (props.formLookups.employees ?? []).map((e) => e.name).filter(Boolean),
);

const SEND_TO_DEFAULT = 'Ban Giám đốc\nPhòng Công nghệ & Phòng Kế Toán';

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

const usersListArray = computed(() =>
    form.users_list_raw.split('\n').map(l => l.trim()).filter(Boolean)
);

function defaultForm() {
    const d = props.proposalDefaults ?? {};
    return {
        proposal_type: 'ai_account',
        subject_about: '',
        send_to: d.send_to ?? SEND_TO_DEFAULT,
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
    resetPreview();
    copyRecipientFromProposer.value = true;
    selectedProposerId.value = props.proposalDefaults?.proposer_employee_id ?? null;
    Object.assign(form, defaultForm());
});

watch(() => form.tool_name, (name) => {
    if (!form.subject_about && name?.trim()) {
        form.subject_about = `Đăng ký sử dụng ${name.trim()}`;
    }
});

function onProposerPicked(emp) {
    if (!emp) return;
    form.proposer_name = emp.name ?? '';
    form.proposer_position = emp.role_title ?? '';
    if (emp.department) form.proposer_department = emp.department;
    if (copyRecipientFromProposer.value) {
        syncRecipient();
        if (emp.email) form.recipient_email = emp.email;
        if (emp.phone) form.recipient_phone = emp.phone;
    }
    onInput();
}

function applyToolTemplate() {
    const name = form.tool_name?.trim();
    if (!name) return;
    const tpl = props.formLookups.account_templates?.find(
        (t) => t.tool_name?.toLowerCase() === name.toLowerCase(),
    );
    if (!tpl) return;
    form.license_type = tpl.license_type ?? form.license_type;
    form.group_function = tpl.group_function ?? form.group_function;
    if (!form.cost_amount && tpl.cost_amount) form.cost_amount = String(tpl.cost_amount);
    if (tpl.cost_unit) form.cost_unit = tpl.cost_unit;
    if (!form.registration_email && tpl.registration_email) {
        form.registration_email = tpl.registration_email;
    }
    onInput();
}

function onToolNameBlur() {
    applyToolTemplate();
}

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

function buildSubmitPayload() {
    return {
        proposal_type: form.proposal_type || undefined,
        subject_about: form.subject_about.trim(),
        send_to: form.send_to.trim() || undefined,
        tool_name: form.tool_name.trim(),
        vendor_name: form.vendor_name.trim() || undefined,
        vendor_website: form.vendor_website.trim() || undefined,
        group_function: form.group_function,
        license_type: form.license_type.trim(),
        cost_amount: parseInt(String(form.cost_amount).replace(/\D/g, ''), 10) || 0,
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
    };
}

const {
    html: previewHtml,
    loading: previewLoading,
    error: previewError,
    reset: resetPreview,
} = useProposalPdfPreview(form, activeSection, buildSubmitPayload);

function handleSubmit() {
    if (!costPreviewAmount.value) {
        activeSection.value = 'budget';
        return;
    }
    emit('submit', buildSubmitPayload());
}

function goSection(key) {
    activeSection.value = key;
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
      <nav class="w-48 shrink-0 border-r border-slate-100 bg-slate-50 px-2 py-3">
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
              <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Thông tin chung
              </p>
              <p class="mb-4 text-sm text-slate-500">
                Thông tin hiển thị trên đầu phiếu PDX (trích yếu, kính gửi, người đề xuất).
              </p>
              <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Loại đề xuất"
                    required
                    :tooltip="H.proposal_type"
                  />
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="t in options.proposal_type"
                      :key="t.value"
                      type="button"
                      class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                      :class="form.proposal_type === t.value
                        ? 'border-brand bg-brand text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-brand/40'"
                      @click="form.proposal_type = t.value; onInput()"
                    >
                      {{ t.label }}
                    </button>
                  </div>
                </div>

                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Về việc (trích yếu)"
                    required
                    :tooltip="H.subject_about"
                  />
                  <input
                    v-model="form.subject_about"
                    type="text"
                    required
                    class="input w-full"
                    placeholder="VD: Đăng ký sử dụng Cursor Pro cho team phát triển"
                    autocomplete="off"
                    @input="onInput"
                  >
                </div>

                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Kính gửi"
                    :tooltip="H.send_to"
                    hint="Mỗi dòng một đơn vị (dòng 1: Ban Giám đốc)"
                  />
                  <textarea
                    v-model="form.send_to"
                    rows="3"
                    class="input w-full font-mono text-sm leading-relaxed"
                    placeholder="Ban Giám đốc&#10;Phòng Công nghệ & Phòng Kế Toán"
                    @input="onInput"
                  />
                </div>

                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Tìm người đề xuất (danh sách nhân sự)"
                    :tooltip="H.proposer_pick"
                    hint="Gõ trực tiếp vào ô — tên, email hoặc mã NV"
                  />
                  <ProposerEmployeePick
                    v-model="selectedProposerId"
                    :employees="formLookups.employees"
                    :initial-label="form.proposer_name"
                    @pick="onProposerPicked"
                  />
                </div>

                <div>
                  <ProposalFormLabel
                    label="Họ & tên người đề xuất"
                    required
                    :tooltip="H.proposer_name"
                  />
                  <input
                    v-model="form.proposer_name"
                    type="text"
                    required
                    class="input w-full"
                    placeholder="VD: Nguyễn Văn An"
                    autocomplete="name"
                    @input="onInput"
                  >
                </div>
                <div>
                  <ProposalFormLabel
                    label="Chức vụ"
                    :tooltip="H.proposer_position"
                  />
                  <input
                    v-model="form.proposer_position"
                    type="text"
                    class="input w-full"
                    placeholder="VD: Chuyên viên CNTT"
                    autocomplete="organization-title"
                    @input="onInput"
                  >
                </div>
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Đơn vị / Phòng ban"
                    :tooltip="H.proposer_department"
                  />
                  <input
                    v-model="form.proposer_department"
                    type="text"
                    list="proposal-departments"
                    class="input w-full"
                    placeholder="VD: Phòng Công nghệ"
                    autocomplete="organization"
                    @input="onInput"
                  >
                  <datalist id="proposal-departments">
                    <option
                      v-for="name in departmentNames"
                      :key="name"
                      :value="name"
                    />
                  </datalist>
                </div>
              </div>
            </div>

            <!-- ── SECTION: Nội dung đề xuất ── -->
            <div v-show="activeSection === 'content'">
              <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Nội dung đề xuất
              </p>
              <p class="mb-4 text-sm text-slate-500">
                Mô tả sản phẩm, lý do và mục tiêu (mục 2–3 trên phiếu in).
              </p>
              <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Tên đề xuất / Sản phẩm"
                    required
                    :tooltip="H.tool_name"
                  />
                  <input
                    v-model="form.tool_name"
                    type="text"
                    required
                    list="proposal-tools"
                    class="input w-full"
                    placeholder="VD: Cursor AI Pro, Claude Team, Figma Organization"
                    autocomplete="off"
                    @input="onInput"
                    @blur="onToolNameBlur"
                  >
                  <datalist id="proposal-tools">
                    <option
                      v-for="t in formLookups.tools"
                      :key="t"
                      :value="t"
                    />
                  </datalist>
                  <p
                    v-if="formLookups.account_templates?.some(t => t.tool_name?.toLowerCase() === form.tool_name?.trim().toLowerCase())"
                    class="mt-1 text-xs text-brand"
                  >
                    Đã có trong danh mục AI — tab Chi phí sẽ gợi ý gói &amp; giá khi rời ô nhập.
                  </p>
                </div>
                <div>
                  <ProposalFormLabel
                    label="Nhà cung cấp"
                    :tooltip="H.vendor_name"
                  />
                  <input
                    v-model="form.vendor_name"
                    type="text"
                    list="proposal-vendors"
                    class="input w-full"
                    placeholder="VD: Anthropic, OpenAI, Microsoft"
                    autocomplete="off"
                    @input="onInput"
                  >
                  <datalist id="proposal-vendors">
                    <option
                      v-for="v in formLookups.vendors"
                      :key="v"
                      :value="v"
                    />
                  </datalist>
                </div>
                <div>
                  <ProposalFormLabel
                    label="Website"
                    :tooltip="H.vendor_website"
                  />
                  <input
                    v-model="form.vendor_website"
                    type="url"
                    class="input w-full"
                    placeholder="https://cursor.com/pricing"
                    autocomplete="url"
                    @input="onInput"
                  >
                </div>
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Nội dung đề xuất"
                    required
                    :tooltip="H.proposal_content"
                    hint="Tối thiểu 20 ký tự"
                  />
                  <textarea
                    v-model="form.proposal_content"
                    rows="4"
                    required
                    minlength="20"
                    class="input w-full"
                    placeholder="Tóm tắt: team nào dùng, trong bao lâu, tính năng chính cần có…"
                    @input="onInput"
                  />
                </div>
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Mô tả chi tiết"
                    :tooltip="H.description"
                  />
                  <textarea
                    v-model="form.description"
                    rows="3"
                    class="input w-full"
                    placeholder="Phiên bản, tích hợp IDE, chính sách dữ liệu…"
                    @input="onInput"
                  />
                </div>
                <div>
                  <ProposalFormLabel
                    label="Lý do đề xuất"
                    :tooltip="H.reason_for_proposal"
                  />
                  <textarea
                    v-model="form.reason_for_proposal"
                    rows="4"
                    class="input w-full"
                    placeholder="Vì sao cần ngay / gia hạn? Rủi ro nếu không có?"
                    @input="onInput"
                  />
                </div>
                <div>
                  <ProposalFormLabel
                    label="Hiệu quả mong đợi"
                    :tooltip="H.expected_benefit"
                  />
                  <textarea
                    v-model="form.expected_benefit"
                    rows="4"
                    class="input w-full"
                    placeholder="VD: Giảm ~30% thời gian code review, tăng chất lượng tài liệu BA…"
                    @input="onInput"
                  />
                </div>
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Mục tiêu"
                    :tooltip="H.objectives"
                    hint="Mỗi dòng một ý"
                  />
                  <textarea
                    v-model="form.objectives"
                    rows="4"
                    class="input w-full font-mono text-sm"
                    placeholder="Tăng tốc quá trình phân tích&#10;Hỗ trợ xây dựng Wireframe&#10;Giảm thời gian thiết kế giao diện"
                    @input="onInput"
                  />
                </div>
              </div>
            </div>

            <!-- ── SECTION: Chi phí & Ngân sách ── -->
            <div v-show="activeSection === 'budget'">
              <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Chi phí &amp; Ngân sách
              </p>
              <p class="mb-4 text-sm text-slate-500">
                Số liệu cho bảng ngân sách mục 4.1 và thời gian đưa vào sử dụng (mục 5).
              </p>
              <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                  <ProposalFormLabel
                    label="Nhóm chức năng"
                    required
                    :tooltip="H.group_function"
                  />
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
                  <ProposalFormLabel
                    label="Loại license / Gói"
                    required
                    :tooltip="H.license_type"
                  />
                  <input
                    v-model="form.license_type"
                    type="text"
                    required
                    list="proposal-license-types"
                    class="input w-full"
                    placeholder="VD: Pro, Team, Business"
                    autocomplete="off"
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
                  <ProposalFormLabel
                    label="Chi phí (VNĐ)"
                    required
                    :tooltip="H.cost_amount"
                  />
                  <MoneyInput
                    v-model="form.cost_amount"
                    placeholder="VD: 1.000.000"
                    @update:model-value="onInput"
                  />
                  <p
                    v-if="costPreviewAmount"
                    class="mt-1.5 rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs text-slate-600"
                  >
                    <VndAmount
                      :amount="costPreviewAmount"
                      inline
                    />
                    <span class="text-slate-500">
                      / {{ form.cost_unit === 'yearly' ? 'năm' : form.cost_unit === 'quarterly' ? 'quý' : form.cost_unit === 'one_time' ? 'một lần' : 'tháng' }}
                    </span>
                    <span
                      v-if="monthlyCost && form.cost_unit !== 'monthly' && form.cost_unit !== 'one_time'"
                      class="ml-2 text-slate-400"
                    >
                      ≈ <VndAmount
                        :amount="monthlyCost"
                        inline
                      /> / tháng (trên phiếu)
                    </span>
                  </p>
                </div>
                <div>
                  <ProposalFormLabel
                    label="Chu kỳ thanh toán"
                    required
                    :tooltip="H.cost_unit"
                  />
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
                  <ProposalFormLabel
                    label="Số lượng (bảng ngân sách)"
                    :tooltip="H.quantity"
                  />
                  <input
                    v-model="form.quantity"
                    type="number"
                    min="1"
                    class="input w-full"
                    placeholder="1"
                    @input="onInput"
                  >
                </div>
                <div>
                  <ProposalFormLabel
                    label="Tình trạng"
                    required
                    :tooltip="H.purchase_type"
                  />
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
                  <ProposalFormLabel
                    label="Ngày đưa vào sử dụng (dự kiến)"
                    :tooltip="H.planned_use_date"
                  />
                  <input
                    v-model="form.planned_use_date"
                    type="date"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <ProposalFormLabel
                    label="Email đăng ký tài khoản"
                    :tooltip="H.registration_email"
                  />
                  <input
                    v-model="form.registration_email"
                    type="email"
                    class="input w-full"
                    placeholder="ten.ban@hcm.vaschools.edu.vn"
                    autocomplete="email"
                    @input="onInput"
                  >
                </div>
              </div>
            </div>

            <!-- ── SECTION: Thông tin tài khoản ── -->
            <div v-show="activeSection === 'account'">
              <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
                Thông tin tài khoản &amp; Người dùng
              </p>
              <p class="mb-4 text-sm text-slate-500">
                Phạm vi sử dụng và người tiếp nhận license (mục 4.2–4.3 trên phiếu).
              </p>
              <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                  <ProposalFormLabel
                    label="Số lượng tài khoản / Seats"
                    :tooltip="H.seats"
                  />
                  <input
                    v-model="form.seats"
                    type="number"
                    min="1"
                    class="input w-full"
                    placeholder="VD: 5"
                    @input="onInput"
                  >
                </div>
                <div>
                  <ProposalFormLabel
                    label="Số nhân sự sử dụng"
                    :tooltip="H.staff_count"
                  />
                  <input
                    v-model="form.staff_count"
                    type="number"
                    min="1"
                    class="input w-full"
                    placeholder="VD: 5"
                    @input="onInput"
                  >
                </div>
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Bộ phận sử dụng"
                    :tooltip="H.department_using"
                  />
                  <input
                    v-model="form.department_using"
                    type="text"
                    list="proposal-departments-using"
                    class="input w-full"
                    placeholder="VD: Phòng Công nghệ — Team phát triển"
                    autocomplete="off"
                    @input="onInput"
                  >
                  <datalist id="proposal-departments-using">
                    <option
                      v-for="name in departmentNames"
                      :key="`use-${name}`"
                      :value="name"
                    />
                  </datalist>
                </div>
                <div class="sm:col-span-2">
                  <ProposalFormLabel
                    label="Danh sách người sử dụng"
                    :tooltip="H.users_list"
                    hint="Mỗi dòng một tên"
                  />
                  <textarea
                    v-model="form.users_list_raw"
                    rows="4"
                    list="proposal-user-names"
                    class="input w-full font-mono text-sm"
                    placeholder="Nguyễn Văn A&#10;Trần Thị B&#10;Lê Văn C"
                    @input="onInput"
                  />
                  <datalist id="proposal-user-names">
                    <option
                      v-for="name in employeeNameSuggestions"
                      :key="`user-${name}`"
                      :value="name"
                    />
                  </datalist>
                </div>
                <div>
                  <ProposalFormLabel
                    label="Ngày bắt đầu"
                    :tooltip="H.start_date"
                  />
                  <input
                    v-model="form.start_date"
                    type="date"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>
                <div>
                  <ProposalFormLabel
                    label="Ngày kết thúc"
                    :tooltip="H.end_date"
                  />
                  <input
                    v-model="form.end_date"
                    type="date"
                    class="input w-full"
                    @input="onInput"
                  >
                </div>

                <div class="sm:col-span-2 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                  <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    Nhân sự tiếp nhận
                  </p>
                  <label class="mb-4 flex items-center gap-2 text-sm text-slate-600">
                    <input
                      v-model="copyRecipientFromProposer"
                      type="checkbox"
                      class="rounded border-slate-300"
                    >
                    <span>Trùng thông tin người đề xuất</span>
                    <FieldTooltip :text="H.copy_recipient" />
                  </label>
                  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                      <ProposalFormLabel
                        label="Họ & tên tiếp nhận"
                        :tooltip="H.recipient_name"
                      />
                      <input
                        v-model="form.recipient_name"
                        type="text"
                        class="input w-full"
                        placeholder="Người phối hợp nhận license"
                        :disabled="copyRecipientFromProposer"
                        @input="onInput"
                      >
                    </div>
                    <div>
                      <ProposalFormLabel
                        label="Chức vụ"
                        :tooltip="H.proposer_position"
                      />
                      <input
                        v-model="form.recipient_position"
                        type="text"
                        class="input w-full"
                        placeholder="VD: Chuyên viên"
                        :disabled="copyRecipientFromProposer"
                        @input="onInput"
                      >
                    </div>
                    <div>
                      <ProposalFormLabel
                        label="Email"
                        :tooltip="H.recipient_email"
                      />
                      <input
                        v-model="form.recipient_email"
                        type="email"
                        class="input w-full"
                        placeholder="email@hcm.vaschools.edu.vn"
                        autocomplete="email"
                        @input="onInput"
                      >
                    </div>
                    <div>
                      <ProposalFormLabel
                        label="Điện thoại"
                        :tooltip="H.recipient_phone"
                      />
                      <input
                        v-model="form.recipient_phone"
                        type="tel"
                        class="input w-full"
                        placeholder="VD: 0901234567"
                        autocomplete="tel"
                        @input="onInput"
                      >
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- ── SECTION: Preview (cùng Blade với PDF) ── -->
            <div v-show="activeSection === 'preview'">
              <div class="mb-3 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                  Xem trước Phiếu Đề Xuất
                </p>
                <span
                  v-if="previewLoading"
                  class="text-xs text-slate-500"
                >Đang tải…</span>
              </div>

              <p
                v-if="previewError"
                class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
              >
                {{ previewError }}
              </p>

              <div
                v-if="previewHtml"
                class="proposal-pdf-preview"
                v-html="previewHtml"
              />
              <p
                v-else-if="!previewLoading"
                class="text-sm text-slate-500"
              >
                Điền thông tin các tab trước — bản xem trước sẽ hiển thị khi bạn mở tab này.
              </p>

              <p class="mt-3 text-xs text-slate-400">
                Bản xem trước dùng cùng mẫu in với file PDF sau khi gửi phiếu (có thể lệch vài pixel do trình duyệt).
              </p>
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
