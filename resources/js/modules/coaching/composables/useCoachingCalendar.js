import { reactive, ref } from 'vue';
import axios from 'axios';
import { useToast } from '@/shared/composables/useToast';

/**
 * Status visual metadata — maps the 4 backend statuses onto the enterprise
 * scheduler palette (spec: Scheduled→Blue, In Progress→Purple, Completed→Green,
 * Cancelled→Red). Labels stay Vietnamese (project convention).
 */
export const SESSION_STATUS_META = {
    pending: { label: 'Chưa học', color: '#2563EB', tint: '#EFF6FF', text: '#1D4ED8' },
    in_progress: { label: 'Đang học', color: '#7C3AED', tint: '#F5F3FF', text: '#6D28D9' },
    completed: { label: 'Hoàn thành', color: '#10B981', tint: '#ECFDF5', text: '#047857' },
    cancelled: { label: 'Hủy', color: '#EF4444', tint: '#FEF2F2', text: '#B91C1C' },
};

export function statusMeta(value) {
    return (
        SESSION_STATUS_META[value] ?? {
            label: value || '—',
            color: '#64748B',
            tint: '#F1F5F9',
            text: '#475569',
        }
    );
}

/**
 * Calendar data layer: lazy range-bounded feed, client-side filtering and the
 * write operations (reschedule / resize / quick-create / status edits).
 *
 * @param {object}   opts
 * @param {string}   opts.feedUrl       GET JSON feed (range-bounded)
 * @param {string}   opts.storeUrl      POST quick-create
 * @param {Function} opts.updateUrlFor  (id) => PATCH url for a session
 */
export function useCoachingCalendar({ feedUrl, storeUrl, updateUrlFor }) {
    const toast = useToast();

    const loading = ref(false);
    const error = ref(false);
    const isEmpty = ref(false);

    const filters = reactive({
        coaches: new Set(), // empty = all
        statuses: new Set(), // empty = all
        query: '',
    });

    let lastRaw = [];

    function matches(ev) {
        const p = ev.extendedProps || {};
        if (filters.statuses.size && !filters.statuses.has(p.status)) return false;
        if (filters.coaches.size && !filters.coaches.has(p.coachName)) return false;
        if (filters.query) {
            const q = filters.query.trim().toLowerCase();
            const hay = [ev.title, p.studentName, p.coachName, p.courseName, p.courseCode, p.topic]
                .filter(Boolean)
                .join(' ')
                .toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    }

    function decorate(ev) {
        const m = statusMeta(ev.extendedProps?.status);
        return {
            ...ev,
            classNames: [`cc-evt`, `cc-evt--${ev.extendedProps?.status || 'unknown'}`],
            backgroundColor: m.tint,
            borderColor: m.color,
            borderLeftColor: m.color,
            textColor: m.text,
        };
    }

    function applyVisible() {
        const visible = lastRaw.filter(matches);
        isEmpty.value = lastRaw.length > 0 && visible.length === 0;
        return visible.map(decorate);
    }

    /** FullCalendar event source function. */
    async function source(info, success, failure) {
        loading.value = true;
        error.value = false;
        try {
            const { data } = await axios.get(feedUrl, {
                params: { start: info.startStr, end: info.endStr },
            });
            lastRaw = Array.isArray(data) ? data : [];
            success(applyVisible());
        } catch (e) {
            error.value = true;
            failure?.(e);
        } finally {
            loading.value = false;
        }
    }

    function toggleSetValue(set, value) {
        if (set.has(value)) set.delete(value);
        else set.add(value);
    }

    async function reschedule(id, payload) {
        try {
            const { data } = await axios.patch(updateUrlFor(id), payload);
            return data.event;
        } catch (e) {
            toast.error(e?.response?.data?.message || 'Không thể cập nhật lịch buổi học.');
            throw e;
        }
    }

    async function updateSession(id, payload) {
        try {
            const { data } = await axios.patch(updateUrlFor(id), payload);
            toast.success('Đã cập nhật buổi học.');
            return data.event;
        } catch (e) {
            toast.error(e?.response?.data?.message || 'Không thể cập nhật buổi học.');
            throw e;
        }
    }

    async function createSession(payload) {
        try {
            const { data } = await axios.post(storeUrl, payload);
            toast.success('Đã thêm buổi học.');
            return data.event;
        } catch (e) {
            const errors = e?.response?.data?.errors;
            const first = errors ? Object.values(errors)[0]?.[0] : null;
            toast.error(first || e?.response?.data?.message || 'Không thể tạo buổi học.');
            throw e;
        }
    }

    return {
        loading,
        error,
        isEmpty,
        filters,
        source,
        toggleSetValue,
        reschedule,
        updateSession,
        createSession,
    };
}
