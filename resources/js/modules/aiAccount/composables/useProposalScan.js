import { computed, ref } from 'vue';
import { httpPatch, httpPost } from '@/shared/services/http';
import { useToast } from '@/shared/composables/useToast';

/** Cấu hình các trường OCR hiển thị trên bước review. */
export const SCAN_FIELD_CONFIG = [
    { key: 'proposal_code', label: 'Số / mã phiếu gốc', type: 'text' },
    { key: 'proposal_date', label: 'Ngày lập phiếu', type: 'text' },
    { key: 'proposer_name', label: 'Người đề xuất', type: 'text', required: true },
    { key: 'proposer_position', label: 'Chức vụ', type: 'text' },
    { key: 'proposer_department', label: 'Phòng ban / bộ phận', type: 'text' },
    { key: 'send_to', label: 'Kính gửi', type: 'text' },
    { key: 'subject_about', label: 'Trích yếu (Về việc)', type: 'text', required: true },
    { key: 'proposal_content', label: 'Nội dung đề xuất', type: 'textarea', required: true },
    { key: 'justification', label: 'Lý do / mục đích', type: 'textarea' },
    { key: 'cost_amount', label: 'Chi phí (VND)', type: 'number', required: true },
    { key: 'quantity', label: 'Số lượng', type: 'number' },
    { key: 'notes', label: 'Ghi chú', type: 'textarea' },
];

export function confidenceTone(confidence) {
    if (confidence >= 0.9) return 'emerald';
    if (confidence >= 0.7) return 'amber';
    return 'rose';
}

export function useProposalScan() {
    const toast = useToast();
    const uploading = ref(false);
    const saving = ref(false);
    const scan = ref(null);

    const fields = computed(() => scan.value?.fields ?? {});
    const signatures = computed(() => scan.value?.signatures ?? []);

    async function uploadFile(file) {
        uploading.value = true;
        try {
            const formData = new FormData();
            formData.append('file', file);
            const res = await httpPost(route('api.ai-accounts.proposal-scans.store'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            scan.value = res.data?.scan ?? null;
            toast.success(res.message ?? 'Đã trích xuất dữ liệu từ phiếu.');
            return scan.value;
        } catch (e) {
            const failedScan = e.response?.data?.data?.scan;
            if (failedScan) scan.value = failedScan;
            toast.error(e.response?.data?.message ?? 'Không xử lý được tài liệu. Vui lòng thử lại.');
            return null;
        } finally {
            uploading.value = false;
        }
    }

    async function saveAndConfirm(editedValues) {
        if (!scan.value?.id) return null;
        saving.value = true;
        try {
            const fieldsPayload = {};
            for (const [key, value] of Object.entries(editedValues)) {
                if (String(value ?? '').trim() !== '') {
                    fieldsPayload[key] = { value: String(value).trim() };
                }
            }
            await httpPatch(
                route('api.ai-accounts.proposal-scans.update', { scan: scan.value.id }),
                { fields: fieldsPayload },
            );

            const res = await httpPost(
                route('api.ai-accounts.proposal-scans.confirm', { scan: scan.value.id }),
                {
                    subject_about: editedValues.subject_about,
                    proposer_name: editedValues.proposer_name,
                    proposer_position: editedValues.proposer_position || null,
                    proposer_department: editedValues.proposer_department || null,
                    send_to: editedValues.send_to || null,
                    proposal_content: editedValues.proposal_content,
                    justification: editedValues.justification || null,
                    cost_amount: Number(editedValues.cost_amount) || null,
                    quantity: Number(editedValues.quantity) || null,
                    notes: editedValues.notes || null,
                },
            );
            scan.value = res.data?.scan ?? scan.value;
            toast.success(res.message ?? 'Đã lưu Phiếu Đề Xuất từ bản quét.');
            return res.data ?? null;
        } catch (e) {
            const firstError = Object.values(e.response?.data?.errors ?? {})[0]?.[0];
            toast.error(firstError ?? e.response?.data?.message ?? 'Không lưu được phiếu. Vui lòng kiểm tra dữ liệu.');
            return null;
        } finally {
            saving.value = false;
        }
    }

    function reset() {
        scan.value = null;
        uploading.value = false;
        saving.value = false;
    }

    return {
        uploading,
        saving,
        scan,
        fields,
        signatures,
        uploadFile,
        saveAndConfirm,
        reset,
    };
}
