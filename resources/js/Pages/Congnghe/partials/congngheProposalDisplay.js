import { date, datetime } from '@/composables/useFormat';

export const PROPOSAL_EMPTY = {
    referenceCode: 'Chưa có mã phiếu',
    submitterName: 'Chưa ghi nhận họ tên người gửi',
    submitterEmail: 'Chưa ghi nhận email liên hệ',
    department: 'Chưa ghi nhận phòng ban',
    title: 'Không có tiêu đề',
    content: 'Không có nội dung mô tả',
    status: 'Chưa có trạng thái',
    sentAt: 'Chưa ghi nhận thời gian gửi',
    attachments: 'Không có file đính kèm',
};

/** @param {string|null|undefined} code */
export function referenceCodeLabel(code) {
    const value = String(code ?? '').trim();
    return value !== '' ? `Mã phiếu ${value}` : PROPOSAL_EMPTY.referenceCode;
}

/** @param {string|null|undefined} iso */
export function parseProposalInstant(iso) {
    if (iso == null || iso === '') {
        return null;
    }
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
}

/** @param {Record<string, unknown>|null|undefined} row */
export function emailPcnStatus(row) {
    const instant = parseProposalInstant(row?.email_sent_at);
    if (instant) {
        const formatted = datetime(row.email_sent_at);
        return {
            sent: true,
            tone: 'emerald',
            label: 'Đã gửi email',
            detail: formatted !== '—' ? formatted : 'Email đã chuyển tới Phòng Công nghệ',
        };
    }
    return {
        sent: false,
        tone: 'amber',
        label: 'Chưa gửi email',
        detail: 'Hệ thống chưa xác nhận email tới Phòng CN',
    };
}

/** @param {string|null|undefined} name */
export function submitterNameText(name) {
    const value = String(name ?? '').trim();
    return value !== '' ? value : PROPOSAL_EMPTY.submitterName;
}

/** @param {string|null|undefined} email */
export function submitterEmailText(email) {
    const value = String(email ?? '').trim();
    return value !== '' ? value : PROPOSAL_EMPTY.submitterEmail;
}

/** @param {string|null|undefined} department */
export function departmentText(department) {
    const value = String(department ?? '').trim();
    return value !== '' ? value : PROPOSAL_EMPTY.department;
}

/** @param {string|null|undefined} iso */
export function submittedAtText(iso) {
    if (!iso) {
        return PROPOSAL_EMPTY.sentAt;
    }
    const formatted = datetime(iso);
    return formatted !== '—' ? formatted : PROPOSAL_EMPTY.sentAt;
}

/** @param {number|null|undefined} count */
export function attachmentCountText(count) {
    const n = Number(count ?? 0);
    if (n <= 0) {
        return PROPOSAL_EMPTY.attachments;
    }
    return `${n} file đính kèm`;
}

/** @param {Record<string, unknown>|null|undefined} row */
export function proposalStatusLabel(row) {
    const label = row?.status?.label;
    if (label != null && String(label).trim() !== '') {
        return String(label).trim();
    }
    const raw = row?.status?.value ?? row?.status;
    if (raw != null && String(raw).trim() !== '') {
        return String(raw).trim();
    }
    return PROPOSAL_EMPTY.status;
}

/** @param {Record<string, unknown>|null|undefined} row */
export function acknowledgementStatus(row) {
    const status = row?.status?.value ?? row?.status;
    const ok = status != null && status !== '' && status !== 'new';
    return {
        acknowledged: ok,
        tone: ok ? 'emerald' : 'amber',
        label: ok ? 'Đã ghi nhận' : 'Chưa ghi nhận',
        detail: ok
            ? 'Phòng Công nghệ đã cập nhật tiến độ'
            : 'Đang chờ Phòng Công nghệ tiếp nhận',
    };
}

/** @param {string|null|undefined} iso — hiển thị ngày gửi trên bảng */
export function submittedDateText(iso) {
    if (!iso) {
        return PROPOSAL_EMPTY.sentAt;
    }
    const formatted = date(iso);
    return formatted !== '—' ? formatted : PROPOSAL_EMPTY.sentAt;
}
