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

const PROPOSAL_STATUS_META = {
    new: {
        tone: 'violet',
        portalPill: 'border-violet-400/45 bg-violet-500/15 text-violet-100 ring-1 ring-violet-400/30',
        portalRow: 'border-l-violet-400/70',
    },
    triaged: {
        tone: 'sky',
        portalPill: 'border-sky-400/45 bg-sky-500/15 text-sky-100 ring-1 ring-sky-400/30',
        portalRow: 'border-l-sky-400/70',
    },
    in_progress: {
        tone: 'amber',
        portalPill: 'border-amber-400/45 bg-amber-500/15 text-amber-50 ring-1 ring-amber-400/35',
        portalRow: 'border-l-amber-400/70',
    },
    done: {
        tone: 'emerald',
        portalPill: 'border-emerald-400/45 bg-emerald-500/15 text-emerald-50 ring-1 ring-emerald-400/35',
        portalRow: 'border-l-emerald-400/70',
    },
    rejected: {
        tone: 'rose',
        portalPill: 'border-rose-400/55 bg-rose-500/20 text-rose-50 ring-2 ring-rose-400/40 shadow-[0_0_20px_-8px_rgba(244,63,94,0.55)]',
        portalRow: 'border-l-rose-400 bg-rose-500/[0.06]',
    },
};

/** @param {Record<string, unknown>|null|undefined} row */
export function proposalStatusMeta(row) {
    const value = String(row?.status?.value ?? row?.status ?? '').trim();
    const label = proposalStatusLabel(row);
    const preset = PROPOSAL_STATUS_META[value] ?? {
        tone: 'slate',
        portalPill: 'border-white/20 bg-white/10 text-white/80 ring-1 ring-white/15',
        portalRow: 'border-l-white/20',
    };

    return {
        value,
        label,
        ...preset,
    };
}

/** @param {Record<string, unknown>|null|undefined} row */
export function acknowledgementStatus(row) {
    const status = row?.status?.value ?? row?.status;
    if (status == null || status === '' || status === 'new') {
        return {
            acknowledged: false,
            tone: 'amber',
            label: 'Chưa tiếp nhận',
            detail: 'Đang chờ Phòng Công nghệ tiếp nhận',
        };
    }
    if (status === 'triaged') {
        return {
            acknowledged: true,
            tone: 'sky',
            label: 'Đã tiếp nhận',
            detail: 'Phòng Công nghệ đã ghi nhận và phân loại đề xuất',
        };
    }
    if (status === 'in_progress') {
        return {
            acknowledged: true,
            tone: 'amber',
            label: 'Đang xử lý',
            detail: 'Phòng Công nghệ đang triển khai hoặc đánh giá giải pháp',
        };
    }
    if (status === 'done') {
        return {
            acknowledged: true,
            tone: 'emerald',
            label: 'Hoàn thành',
            detail: 'Đề xuất đã được xử lý xong',
        };
    }
    if (status === 'rejected') {
        return {
            acknowledged: true,
            tone: 'rose',
            label: 'Đã từ chối',
            detail: 'Xem lý do từ chối trong chi tiết đề xuất',
        };
    }
    return {
        acknowledged: true,
        tone: 'emerald',
        label: 'Đã ghi nhận',
        detail: 'Phòng Công nghệ đã cập nhật tiến độ',
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
